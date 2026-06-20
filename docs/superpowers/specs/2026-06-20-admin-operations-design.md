# Thiết kế hoàn thiện hệ thống quản trị

## Mục tiêu

Biến khu vực admin hiện tại từ các bảng chỉ đọc thành hệ thống vận hành đầy đủ trên nền PHP thuần và MySQL/MariaDB hiện có. Hệ thống phải hỗ trợ xử lý đơn hàng, in hóa đơn, quản lý sản phẩm, kho, kế hoạch mua hàng, cài đặt và phân quyền mà không làm mất lịch sử nghiệp vụ.

## Nguyên tắc

- Giữ kiến trúc PHP server-rendered hiện tại; JavaScript chỉ đảm nhiệm thao tác tương tác và gọi API.
- Dùng trang chi tiết riêng cho đơn hàng, sản phẩm, PO và lot kho. Modal chỉ dùng cho thao tác ngắn.
- Không xóa sản phẩm, UOM hoặc ảnh đã từng được sử dụng; dùng trạng thái hoạt động để ngừng sử dụng.
- Dữ liệu snapshot trong `order_items` và `plan_items` không thay đổi khi giá, tên hoặc UOM sản phẩm được sửa sau này.
- Mọi thay đổi tồn kho phải chạy trong transaction và tạo bản ghi `inventory_movements`.
- Kiểm tra quyền ở cả trang PHP và API. Việc ẩn nút trên giao diện không thay thế kiểm tra quyền phía server.
- Giao diện tiếng Việt, responsive, không thêm framework frontend hoặc dependency runtime mới.

## Kiến trúc điều hướng

Các trang danh sách hiện tại tiếp tục được giữ:

- Dashboard
- Đơn hàng
- Sản phẩm
- Kho
- Purchase Plan
- Cài đặt

Các trang chi tiết mới dùng query route trong `admin/index.php`:

- `?page=order-detail&id=<order_id>`
- `?page=product-detail&id=<product_id>`
- `?page=product-form` và `?page=product-form&id=<product_id>`
- `?page=purchase-plan-detail&id=<plan_id>`
- `?page=inventory-lot&id=<lot_id>`
- `?page=admin-users`

Danh sách có tìm kiếm, bộ lọc, giới hạn kết quả và phân trang. Mỗi dòng có nút xem chi tiết; các thao tác thay đổi dữ liệu được hiển thị theo quyền.

## Đơn hàng và hóa đơn

### Danh sách

- Hiển thị mã đơn, ngày tạo, khách hàng, điện thoại, ngày nhận, trạng thái, tổng tiền và thao tác.
- Cho phép lọc theo trạng thái, khoảng ngày và từ khóa.
- Đơn `confirmed` chưa thuộc PO có checkbox để chọn tạo PO.
- Có thao tác nhanh chuyển sang trạng thái hợp lệ kế tiếp, nhưng hành động hủy luôn cần xác nhận.

### Chi tiết

Trang chi tiết chia thành hai vùng:

1. Vùng vận hành: trạng thái hiện tại, các trạng thái được phép chuyển, PO liên quan, lot xuất kho và lịch sử biến động.
2. Vùng hóa đơn: bố cục sạch, có thể chụp màn hình hoặc in.

Hóa đơn hiển thị:

- Tên cửa hàng, điện thoại và thông tin liên hệ.
- Mã đơn, ngày tạo, trạng thái.
- Tên, điện thoại, địa chỉ khách hàng và ngày nhận.
- Phương thức giao hàng.
- Danh sách sản phẩm, UOM, số lượng, đơn giá và thành tiền.
- Tạm tính, phí giao hàng và tổng cộng.
- Ghi chú đơn hàng.
- Thông tin ngân hàng, số tài khoản, chủ tài khoản, nội dung chuyển khoản và ảnh QR.

Nút `In hóa đơn` gọi `window.print()`. CSS `@media print` chỉ giữ phần hóa đơn, ẩn sidebar, topbar và nút thao tác.

### Trạng thái

Giữ quy tắc hiện tại:

- `new → confirmed | cancelled`
- `confirmed → ordered | cancelled`
- `ordered → received | cancelled`
- `received → ready | cancelled`
- `ready → done | cancelled`

Luồng PO tự chuyển đơn từ `confirmed` sang `ordered`, và khi PO nhận đủ thì sang `received`. Khi chuyển sang `done`, hệ thống xuất kho FEFO trong transaction.

## Sản phẩm

### Danh sách

- Tìm theo mã, tên hoặc danh mục.
- Lọc đang bán/đang ẩn.
- Nút thêm sản phẩm.
- Nút xem, sửa và bật/tắt.
- Không có API hoặc nút xóa.

### Thêm và sửa

Form gồm:

- Mã sản phẩm, tên, slug, danh mục và nguồn hàng.
- Mô tả ngắn, mô tả đầy đủ, thành phần.
- Base UOM, hạn sử dụng và trạng thái hoạt động.
- Danh sách UOM: mã, nhãn, tỷ lệ quy đổi, giá bán, giá vốn, base/default, được bán, được mua, hoạt động và thứ tự.
- Danh sách ảnh: ảnh chính/chi tiết, alt, thứ tự và trạng thái.

Quy tắc:

- Mã sản phẩm chỉ được nhập lúc tạo và không được đổi sau khi tạo.
- Mỗi sản phẩm phải có đúng một base UOM và tối đa một default UOM đang hoạt động.
- Base UOM có `conversion_to_base = 1`.
- UOM đã được tham chiếu không bị xóa; chỉ chuyển `is_active = 0`.
- Ảnh cũ không bị xóa vật lý tự động; có thể tắt hoặc thay ảnh chính.

### Upload ảnh

- Hỗ trợ JPEG, PNG và WebP.
- Kiểm tra MIME thực tế, kích thước tối đa và tên mở rộng.
- Đặt tên file do server sinh trong `products_image/`, không dùng trực tiếp tên file người dùng.
- Chặn path traversal và file thực thi.
- Nếu ghi database thất bại, xóa file mới vừa upload.
- QR thanh toán trong Settings dùng cùng cơ chế upload an toàn.

## Kho

### Xem dữ liệu

- Tổng tồn theo sản phẩm.
- Danh sách lot, số lượng on-hand, reserved, available, ngày nhập, hạn dùng và nhà cung cấp.
- Chi tiết lot và toàn bộ movement liên quan.
- Bộ lọc sản phẩm, nguồn hàng, loại movement và khoảng ngày.

### Nhập kho thủ công

Admin/owner chọn sản phẩm, UOM mua, nguồn hàng, số lượng, giá vốn, nhà cung cấp, ngày nhận, hạn dùng và ghi chú. Hệ thống:

- Quy đổi sang base UOM.
- Tạo lot mới.
- Tạo movement `IN/MANUAL`.
- Thực hiện toàn bộ trong một transaction.

### Điều chỉnh tăng/giảm

Điều chỉnh luôn áp dụng lên một lot cụ thể:

- Nhập số lượng base tăng hoặc giảm.
- Bắt buộc ghi lý do.
- Không cho giảm xuống dưới lượng reserved hoặc dưới 0.
- Cập nhật `qty_base_on_hand`.
- Tạo movement `ADJUST/MANUAL` với `qty_base` có dấu để lưu đúng chiều thay đổi.

## Purchase Plan

### Tạo PO

- Chọn nhiều đơn `confirmed` chưa được gom PO.
- Xem trước nhóm sản phẩm/UOM/nguồn và số lượng.
- Nhập ghi chú rồi tạo PO.
- Sau khi tạo, các order item được đóng dấu `planned_plan_id`.

### Chi tiết PO

- Thông tin PO, trạng thái, nguồn hàng và khoảng ngày.
- Các đơn liên quan.
- Các dòng cần đặt, đã đặt, đã nhận và còn lại.
- Lịch sử phiếu nhận và lot tạo ra.
- Nút sao chép nội dung đặt hàng.
- Nút nhận hàng một phần/toàn bộ.
- Nút hủy nếu chưa nhận hàng.

### Nhận hàng

Form nhận hàng cho từng dòng gồm số lượng, giá nhập, ngày nhận, hạn dùng, nhà cung cấp và ghi chú. Service hiện tại tiếp tục chịu trách nhiệm:

- Không nhận vượt số lượng còn lại.
- Tạo receipt, lot và movement.
- Cập nhật trạng thái `partial_received` hoặc `received`.
- Chuyển đơn liên quan sang `received` khi PO nhận đủ.

## Cài đặt

### Cửa hàng và thanh toán

Các key quản lý:

- `store_name`
- `store_phone`
- `zalo_link`
- `free_ship_threshold`
- `default_shipping_zone_id`
- `bank_name`
- `bank_account_number`
- `bank_account_holder`
- `bank_transfer_content`
- `bank_qr_image_path`

Form hiển thị nhãn nghiệp vụ thay vì cho sửa key tùy ý. Thông tin thanh toán được đưa vào hóa đơn.

### Vùng giao hàng

- Thêm vùng mới.
- Sửa tên và phí.
- Bật/tắt.
- Chọn đúng một vùng mặc định đang hoạt động.
- Không xóa vùng đã được đơn hàng tham chiếu.

## Tài khoản và phân quyền

### Vai trò

- `owner`: toàn quyền, bao gồm quản lý tài khoản quản trị.
- `admin`: xử lý đơn hàng, sản phẩm, kho, PO và cài đặt.
- `staff`: xem dashboard, đơn hàng, PO; xem/in hóa đơn; chuyển trạng thái đơn hợp lệ và thao tác PO được cho phép. Không sửa sản phẩm, kho, cài đặt hoặc tài khoản.

### Quản lý tài khoản

Owner có thể:

- Tạo tài khoản.
- Đổi họ tên, vai trò và trạng thái.
- Đặt lại mật khẩu.

Không cho vô hiệu hóa owner đang đăng nhập nếu đó là owner hoạt động cuối cùng.

## API và service

Các API mới hoặc mở rộng:

- Product detail/create/update/active/upload image.
- Manual stock receipt, lot detail và lot adjustment.
- Shipping zone create/update/default/active.
- Payment settings update và QR upload.
- Admin user list/create/update/reset password.

API dùng JSON cho dữ liệu thường và multipart cho upload. Tất cả request thay đổi dữ liệu yêu cầu đăng nhập, CSRF và quyền tương ứng.

Logic nghiệp vụ đặt trong service/repository, không đặt trực tiếp trong view hoặc JavaScript.

## Xử lý lỗi

- Validation trả HTTP 422 với thông báo tiếng Việt cụ thể.
- Không tìm thấy trả 404.
- Không có quyền trả 403.
- Lỗi transaction rollback toàn bộ.
- UI giữ nguyên dữ liệu form khi có thể và hiển thị lỗi gần vùng thao tác.
- Nút submit bị khóa trong lúc gửi để tránh thao tác lặp.

## Kiểm thử và vệ sinh

- Test service/repository bằng database thử nghiệm tách biệt hoặc transaction rollback.
- Test API đăng nhập, CSRF, phân quyền và validation.
- Test UI bằng XAMPP tại `http://localhost/Dacsan/admin`.
- Test desktop và mobile bằng Playwright vì Browser plugin không có trong phiên.
- Kiểm tra luồng in hóa đơn bằng print media emulation.
- Dữ liệu test dùng prefix rõ ràng và được xóa sau kiểm tra.
- Script Playwright, screenshot, trace và file upload mẫu đặt ngoài repository rồi xóa.
- Không thêm dependency frontend/runtime mới vào dự án.

## Tiêu chí hoàn thành

- Mọi bảng admin có đường dẫn chi tiết và action phù hợp.
- Hóa đơn hiển thị đủ thông tin thanh toán, in được và chụp gửi khách rõ ràng.
- Sản phẩm thêm/sửa/bật/tắt được, không có đường xóa.
- Upload ảnh hoạt động an toàn.
- Kho nhập thủ công và điều chỉnh tăng/giảm có movement đầy đủ.
- PO tạo, xem, nhận hàng và hủy đúng quy tắc.
- Settings quản lý cửa hàng, ngân hàng, QR và vùng giao hàng.
- Quyền owner/admin/staff được kiểm tra phía server.
- PHP syntax, test tự động, API smoke test và UI flow đều đạt.
- Không còn dữ liệu hoặc artifact test trong dự án.
