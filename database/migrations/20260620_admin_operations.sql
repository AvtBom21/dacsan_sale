USE dac_san_nha_dan;

INSERT INTO settings (setting_key, setting_value, note) VALUES
  ('bank_name', '', 'Tên ngân hàng nhận chuyển khoản'),
  ('bank_account_number', '', 'Số tài khoản nhận chuyển khoản'),
  ('bank_account_holder', '', 'Chủ tài khoản nhận chuyển khoản'),
  ('bank_transfer_content', 'THANH TOAN {order_id}', 'Nội dung chuyển khoản; hỗ trợ {order_id}'),
  ('bank_qr_image_path', '', 'Ảnh QR chuyển khoản trong products_image')
ON DUPLICATE KEY UPDATE note = VALUES(note);
