# Storefront Contact Information Design

## Mục tiêu

Thêm khối “Thông tin liên hệ” vào section 2 “Câu chuyện” để khách có thể gọi điện hoặc mở Zalo đặt hàng mà không làm loãng CTA sản phẩm ở section 1.

## Thiết kế

- Vị trí: bên dưới đoạn giới thiệu trong `.story-copy`.
- Nội dung: nhãn “Thông tin liên hệ”, số điện thoại và nút “Nhắn Zalo”.
- Dữ liệu: lấy từ API `settings`, sử dụng `store_phone` và `zalo_link`; không hardcode.
- Desktop: khối liên hệ dạng hàng ngang, nền kính nhẹ, cùng hệ màu hiện tại.
- Mobile: nội dung và hai nút xếp gọn, không làm section vượt chiều cao màn hình.
- Nếu thiếu số điện thoại hoặc Zalo, ẩn riêng hành động tương ứng; nếu thiếu cả hai, ẩn toàn bộ khối.

## Kiểm tra

- Khối liên hệ hiển thị đúng dữ liệu cấu hình.
- Nút điện thoại dùng liên kết `tel:`.
- Nút Zalo mở liên kết cấu hình trong tab mới.
- Không tràn ngang hoặc va chạm tại 1440×900 và 390×844.

