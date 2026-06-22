<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DateTimeImmutable;
use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Core\Csrf;
use DacSanNhaDan\Repositories\CustomerRepository;
use DacSanNhaDan\Repositories\OrderRepository;
use DacSanNhaDan\Support\IdGenerator;
use DacSanNhaDan\Support\Phone;
use PDO;
use Throwable;

final class CheckoutService
{
    public function __construct(
        private PDO $pdo,
        private CartQuoteService $cartQuote,
        private CustomerRepository $customers,
        private OrderRepository $orders
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function checkout(array $payload): array
    {
        $checkoutToken = trim((string) ($payload['checkout_token'] ?? ''));
        Csrf::requireCheckoutToken($checkoutToken);

        $customerName = $this->validateCustomerName($payload['customer_name'] ?? '');
        $customerPhone = $this->validatePhone($payload['customer_phone'] ?? '');
        $shippingMethod = $this->validateShippingMethod($payload['shipping_method'] ?? 'delivery');
        $customerAddress = $this->validateAddress($payload['customer_address'] ?? '', $shippingMethod);
        $receiveDate = $this->validateReceiveDate($payload['receive_date'] ?? null);
        $paymentMethod = $this->validatePaymentMethod($payload['payment_method'] ?? 'COD');
        $note = $this->validateNote($payload['note'] ?? '');

        $quote = $this->cartQuote->quote([
            'items' => $payload['items'] ?? [],
            'shipping_method' => $shippingMethod,
            'shipping_zone_id' => $payload['shipping_zone_id'] ?? '',
        ]);

        if ($quote['can_checkout'] !== true) {
            throw new AppException('Giỏ hàng chưa hợp lệ. Vui lòng kiểm tra tồn kho và thông tin giao hàng.', 422);
        }

        $this->pdo->beginTransaction();

        try {
            $authenticatedCustomerId = (int) ($payload['_authenticated_customer_id'] ?? 0);
            if ($authenticatedCustomerId > 0 && $this->customers->findById($authenticatedCustomerId) !== null) {
                $customerId = $authenticatedCustomerId;
            } else {
                $customerId = $this->customers->upsertCustomer([
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
                    'customer_address' => $customerAddress,
                ]);
            }

            $orderId = $this->uniqueOrderId();
            $this->orders->createOrder([
                'order_id' => $orderId,
                'customer_id' => $customerId ?: null,
                'status' => 'new',
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_address' => $customerAddress,
                'receive_date' => $receiveDate,
                'note' => $this->paymentNote($paymentMethod, $note),
                'shipping_method' => $shippingMethod,
                'shipping_zone_id' => $shippingMethod === 'delivery'
                    ? ($quote['shipping_zone']['zone_id'] ?? null)
                    : null,
                'shipping_fee_vnd' => (int) $quote['shipping_fee_vnd'],
                'subtotal_vnd' => (int) $quote['subtotal_vnd'],
                'total_vnd' => (int) $quote['total_vnd'],
                'source_summary' => (string) $quote['source_summary'],
            ]);
            $this->orders->createOrderItems($orderId, $quote['items']);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        Csrf::regenerateCheckoutToken();

        return [
            'order_id' => $orderId,
            'message' => 'Đơn hàng đã được ghi nhận thành công.',
            'customer' => [
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_address' => $customerAddress,
            ],
            'items' => $quote['items'],
            'totals' => [
                'subtotal_vnd' => (int) $quote['subtotal_vnd'],
                'shipping_fee_vnd' => (int) $quote['shipping_fee_vnd'],
                'total_vnd' => (int) $quote['total_vnd'],
            ],
            'source_summary' => (string) $quote['source_summary'],
        ];
    }

    private function validateCustomerName(mixed $value): string
    {
        $name = trim((string) $value);
        $length = mb_strlen($name, 'UTF-8');

        if ($length < 2 || $length > 160 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            throw new AppException('Vui lòng nhập họ tên khách hàng hợp lệ.', 422);
        }

        return $name;
    }

    private function validatePhone(mixed $value): string
    {
        $phone = Phone::normalize($value);

        if (!Phone::isValidVietnamPhone($phone)) {
            throw new AppException('Số điện thoại chưa hợp lệ.', 422);
        }

        return $phone;
    }

    private function validateShippingMethod(mixed $value): string
    {
        $method = trim((string) $value);

        if (!in_array($method, ['delivery', 'pickup'], true)) {
            throw new AppException('Phương thức nhận hàng không hợp lệ.', 422);
        }

        return $method;
    }

    private function validateAddress(mixed $value, string $shippingMethod): string
    {
        $address = trim((string) $value);

        if ($shippingMethod === 'delivery') {
            $length = mb_strlen($address, 'UTF-8');
            if ($length < 5 || $length > 255 || preg_match('/[\x00-\x1F\x7F]/', $address) === 1) {
                throw new AppException('Vui lòng nhập địa chỉ giao hàng rõ ràng.', 422);
            }
        }

        return $address;
    }

    private function validateReceiveDate(mixed $value): ?string
    {
        $date = trim((string) ($value ?? ''));
        if ($date === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new AppException('Ngày nhận hàng không hợp lệ.', 422);
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new AppException('Ngày nhận hàng không hợp lệ.', 422);
        }

        return $date;
    }

    private function validatePaymentMethod(mixed $value): string
    {
        $method = strtoupper(trim((string) $value));

        if (!in_array($method, ['COD', 'BANK'], true)) {
            throw new AppException('Hình thức thanh toán không hợp lệ.', 422);
        }

        return $method;
    }

    private function validateNote(mixed $value): string
    {
        $note = trim((string) $value);

        if (mb_strlen($note, 'UTF-8') > 1000) {
            throw new AppException('Ghi chú đơn hàng quá dài.', 422);
        }

        return $note;
    }

    private function paymentNote(string $paymentMethod, string $note): string
    {
        $paymentLabel = $paymentMethod === 'BANK'
            ? 'Chuyển khoản ngân hàng'
            : 'Thanh toán khi nhận hàng';

        return trim('Thanh toán: ' . $paymentLabel . ($note !== '' ? "\n" . $note : ''));
    }

    private function uniqueOrderId(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $orderId = IdGenerator::orderId();

            if ($this->orders->findOrderById($orderId) === null) {
                return $orderId;
            }
        }

        throw new AppException('Không tạo được mã đơn hàng duy nhất. Vui lòng thử lại.', 500);
    }
}
