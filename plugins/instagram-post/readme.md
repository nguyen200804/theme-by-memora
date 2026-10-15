# Plugin Instagram Post

Plugin WordPress kết nối **Instagram Graph API / Basic Display API**, cung cấp trang quản trị **Setting Instagram** và shortcode `[instagram_post]` để xuất feed bài viết theo số lượng tùy chỉnh kèm liên kết tài khoản Instagram.

---

## 1. Giới thiệu & Tính năng nổi bật

* **Kết nối Instagram Graph API chính chủ:** Tự động lấy các bài viết mới nhất (hình ảnh, video/reels, album, caption, permalink).
* **Trang quản trị Setting Instagram chuyên nghiệp:**
  * Quản lý Access Token an toàn (hỗ trợ ẩn/hiện, nút kiểm tra kết nối API tức thì).
  * Nút **Xóa Cache** và nút **Gia hạn Token 60 ngày** chỉ với 1 click.
  * Tùy chỉnh **Số bài viết xuất ra** (mặc định 6 bài).
  * Tùy chỉnh **Link Profile Instagram** (gắn vào nút theo dõi).
  * Tùy biến số cột hiển thị (2, 3, 4, 5, 6 cột), khoảng cách (gap), tỉ lệ ảnh (1:1, 4:5, 3:4, 2:3 hoặc 9:16), bo góc.
* **Shortcode linh hoạt `[instagram_post]`:** Có thể nhúng vào Elementor, Gutenberg, Widget hoặc file PHP template.
* **Cơ chế Transient Caching thông minh:** Lưu bộ nhớ đệm từ 1 đến 24 giờ, giúp website tải siêu nhanh (0ms độ trễ API) và không bao giờ bị nghẽn giới hạn lượt gọi của Meta.
* **Tự động làm mới Token (Cron Job):** Định kỳ hàng tuần tự động gửi yêu cầu gia hạn Long-Lived Token để token không bao giờ bị hết hạn.
* **Thiết kế sang trọng phong cách Memora:** Hiệu ứng hover phóng to ảnh, overlay mờ mịn với logo Instagram và trích đoạn caption; nút CTA Theo dõi Instagram dạng Pill bo tròn cao cấp; responsive 100% trên mọi thiết bị.

---

## 2. Cài đặt & Kích hoạt

### Cách 1: Cài đặt qua file ZIP (Khuyên dùng)
1. Nén thư mục `instagram-post` thành file `instagram-post.zip` (hoặc dùng file zip có sẵn).
2. Vào **WP Admin > Plugins > Add New (Thêm mới) > Upload Plugin (Tải plugin lên)**.
3. Chọn file `instagram-post.zip` và bấm **Install Now (Cài đặt)**, sau đó bấm **Activate (Kích hoạt)**.

### Cách 2: Copy trực tiếp vào thư mục Plugins
1. Copy thư mục `instagram-post` vào đường dẫn `/wp-content/plugins/`.
2. Vào **WP Admin > Plugins (Gói mở rộng)** và bấm **Kích hoạt** plugin **Instagram Post**.

---

## 3. Hướng dẫn lấy Instagram Access Token từ Meta Developers

1. Truy cập [developers.facebook.com](https://developers.facebook.com) và đăng nhập bằng tài khoản Facebook của bạn.
2. Bấm **My Apps (Ứng dụng của tôi) > Create App (Tạo ứng dụng)**:
   * Chọn trường hợp sử dụng: **Khác (Other)** > Tiếp tục.
   * Chọn loại ứng dụng: **Người tiêu dùng (Consumer)** hoặc **Doanh nghiệp**.
3. Tại trang tổng quan App, tìm sản phẩm **Instagram Basic Display** và bấm **Set Up (Thiết lập)**.
4. Cuộn xuống mục **User Token Generator (Trình tạo mã truy cập người dùng)**:
   * Bấm **Add or Remove Instagram Testers** và thêm tài khoản Instagram của bạn.
   * Mở ứng dụng hoặc web Instagram của tài khoản đó > **Cài đặt > Quyền của trang web và ứng dụng > Lời mời thử nghiệm** và chấp nhận lời mời.
5. Quay lại trang Meta Developers, bấm **Generate Token**, đăng nhập tài khoản Instagram và tích chọn quyền cấp token.
6. Sao chép chuỗi mã Token dài nhận được, dán vào ô **Instagram Access Token** tại trang **WP Admin > Setting Instagram**, sau đó bấm **Lưu tất cả Cài đặt**.

---

## 4. Cách sử dụng Shortcode `[instagram_post]`

### 4.1. Cú pháp cơ bản:
Tự động lấy toàn bộ cấu hình (số bài viết, link Instagram, số cột, khoảng cách) đã set tại Setting Instagram:
```text
[instagram_post]
```

### 4.2. Danh sách thuộc tính tùy biến (Attributes):

| Thuộc tính | Kiểu dữ liệu | Mặc định | Ý nghĩa & Ví dụ |
| :--- | :--- | :--- | :--- |
| `limit` / `count` | Số nguyên | Cấu hình Setting (VD: `6`) | Giới hạn số lượng bài viết xuất ra. VD: `[instagram_post limit="8"]` |
| `columns` / `col` | Số nguyên (1 - 6) | Cấu hình Setting (VD: `4`) | Số cột hiển thị trên Desktop. VD: `[instagram_post columns="6"]` |
| `gap` | Số nguyên (px) | `16` | Khoảng cách giữa các ảnh. VD: `[instagram_post gap="12"]` |
| `ratio` | `square` / `portrait` / `3/4` / `2/3` / `story` | `square` (1/1) | Tỉ lệ ảnh: `square` (1:1 vuông), `portrait` (4:5 dọc), `3/4` (3:4 dọc), `2/3` (2:3 dọc) hoặc `story` / `9/16` (9:16 dọc dài) |
| `radius` | Số nguyên (px) | `10` | Bo góc ảnh bài viết. VD: `[instagram_post radius="16"]` |
| `link` | URL | Cấu hình Setting | Ghi đè link Instagram profile. VD: `[instagram_post link="https://instagram.com/memora_film"]` |
| `show_button` | `true` / `false` | `true` | Bật/tắt nút "Theo dõi trên Instagram" ở chân danh sách ảnh |
| `button_text` | Chuỗi ký tự | Cấu hình Setting | Đổi chữ trên nút. VD: `button_text="Follow us on Instagram"` |
| `show_caption`| `true` / `false` | `true` | Bật/tắt hiển thị trích đoạn caption khi rê chuột qua bài viết |
| `class` | Chuỗi ký tự | `""` | Bổ sung class CSS tùy biến vào thẻ container |

### 4.3. Ví dụ áp dụng thực tế:
```text
<!-- Xuất 6 bài viết dạng 6 cột tràn viền không có nút theo dõi -->
[instagram_post limit="6" columns="6" gap="10" show_button="false"]

<!-- Xuất 8 bài viết dạng 4 cột khổ dọc thời trang 4:5 kèm nút theo dõi -->
[instagram_post limit="8" columns="4" ratio="portrait" button_text="Xem thêm trên Instagram @memora"]

<!-- Xuất 6 bài viết dạng 3 cột tỉ lệ 9:16 (Story/Reels) -->
[instagram_post limit="6" columns="3" ratio="9/16"]

<!-- Gọi trong PHP template của theme -->
<?php echo do_shortcode('[instagram_post limit="6"]'); ?>
```

---

## 5. Cấu trúc thư mục Plugin

```text
instagram-post/
├── instagram-post.php              # File khởi tạo chính của Plugin
├── readme.md                       # Tài liệu hướng dẫn sử dụng và cài đặt
├── includes/
│   ├── helpers.php                 # Hàm lấy cấu hình mặc định và xử lý dữ liệu
│   ├── api.php                     # Kết nối Instagram Graph API, Caching và Refresh Token
│   ├── settings.php                # Giao diện và xử lý trang quản trị Setting Instagram
│   └── shortcode.php               # Đăng ký và xử lý shortcode [instagram_post]
└── assets/
    ├── css/
    │   ├── instagram-post.css      # CSS giao diện frontend, grid, hover overlay, responsive
    │   └── instagram-post-admin.css# CSS trang quản trị Setting Instagram
    └── js/
        └── instagram-post-admin.js # JavaScript AJAX xử lý nút Test Token, Clear Cache
```
