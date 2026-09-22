# Memora Theme - Tài liệu Hướng dẫn & Quy chuẩn Code

Tài liệu hướng dẫn cấu trúc dự án child theme **Memora (Hello Elementor Child)**, quy chuẩn viết code và cách sử dụng các tính năng tùy biến.

---

## 1. Quy chuẩn viết code (Coding Conventions)

Để dễ dàng quản lý, bảo trì và phân biệt giữa các khối tính năng độc lập, tất cả các file PHP/JS trong dự án bắt buộc tuân theo quy chuẩn comment và ngắt dòng sau:

### Cấu trúc chuẩn:

```php
//====================================
// START - TÊN CHỨC NĂNG 1 
//====================================
Code chức năng 1
//====================================
// END - TÊN CHỨC NĂNG 1
//====================================





//====================================
// START - TÊN CHỨC NĂNG 2
//====================================
Code chức năng 2
//====================================
// END - TÊN CHỨC NĂNG 2
//====================================
```

> **Lưu ý:**
> - Luôn có khối mở đầu `START - [TÊN CHỨC NĂNG]` và khối đóng `END - [TÊN CHỨC NĂNG]`.
> - **Cách ra đúng 5 dòng trống** giữa khối kết thúc của chức năng trước và khối bắt đầu của chức năng tiếp theo.

---

## 2. Cấu trúc thư mục & Files chính

| Tên File | Chức năng |
| :--- | :--- |
| `functions.php` | File khởi tạo của Child Theme, nạp stylesheet cha/con, `custom-field-group.php` và `menu-header.php`. |
| `custom-field-group.php` | Định nghĩa các Field Group (ACF), trang Options, CSS hiệu ứng và Shortcode hiển thị. |
| `menu-header.php` | Định nghĩa Shortcode `[menu__header]` xuất menu đa tầng (drill-down) dạng thẻ kính mờ (Frosted Glass). |
| `style.css` | Khai báo thông tin Child Theme Hello Elementor và các CSS ghi đè. |
| `readme.md` | Tài liệu hướng dẫn sử dụng và quy chuẩn kỹ thuật của dự án. |

---

## 3. Tính năng dải chạy Marquee (Text & Image)

### 3.1. Quản lý trong Admin
* **Đường dẫn quản trị:** `/wp-admin/admin.php?page=chinh_sua_chung` (Menu: **Chỉnh sửa chung**).
* **Các thiết lập có sẵn:**
  * **Tab Danh sách phần tử:**
    * Thêm không giới hạn phần tử dạng Repeater, kéo thả đổi thứ tự.
    * Chọn định dạng: **Văn bản (Text)** hoặc **Hình ảnh (Image)**.
    * Tùy chọn gắn liên kết URL và mở tab mới cho từng phần tử.
  * **Tab Cài đặt hiển thị:**
    * Tốc độ chạy (giây).
    * Khoảng cách giữa các phần tử (px).
    * Hướng chạy: Phải sang Trái (mặc định) hoặc Trái sang Phải.
    * Tự động dừng khi di chuột qua (Hover pause).
    * Tùy chỉnh màu nền và màu chữ.

### 3.2. Cách hiển thị ra giao diện

#### Cách 1: Sử dụng Shortcode (Elementor / Gutenberg / Widgets)
```text
[marquee]
```

**Các tham số tùy chọn ghi đè (Attributes):**
* `[marquee speed="15"]`: Thay đổi tốc độ chạy thành 15 giây.
* `[marquee gap="60"]`: Khoảng cách giữa các phần tử là 60px.
* `[marquee direction="right"]`: Đổi hướng chạy từ Trái sang Phải.
* `[marquee class="custom-marquee-class"]`: Bổ sung thêm CSS class tùy biến.

#### Cách 2: Gọi trong Template PHP
```php
<?php
if ( function_exists( 'memora_render_marquee' ) ) {
    echo memora_render_marquee();
}
?>
```

---

## 4. Tính năng Menu Header Drilldown (`[menu__header]`)

Component menu điều hướng đa tầng (Drill-down Navigation) thiết kế phong cách thẻ kính mờ (Frosted Glass) cao cấp, typography Serif thanh lịch.

### 4.1. Đặc tính thiết kế chuẩn
* **Đơn vị `em` đồng bộ:** Toàn bộ kích thước thẻ (`width: 16.5em`), padding, margin, mũi tên `<` và viền đều sử dụng đơn vị `em` dựa trên `font-size` gốc. Nhờ đó, việc điều chỉnh responsive chỉ cần thay đổi cỡ chữ `font-size` (VD: desktop `22px`, tablet `20px`, mobile `18px`).
* **Màu viền:** `#cddce8`.
* **Nút mở Menu (3 gạch ngang - Hamburger):** Mặc định hiển thị tại vị trí đặt shortcode trên header, màu nâu `#733e1c`. Khi nhấp vào, thẻ menu kính mờ sẽ xuất hiện ngay phía dưới.
* **Nền kính mờ:** `rgba(255, 255, 255, 0.85)` kết hợp `backdrop-filter: blur(16px)`.
* **Nút đóng `✕`:** Màu nâu đậm `#733e1c`.
* **2 Thanh kẻ ngang phân cách:** Màu hồng phấn `#dfb0bf` (Thanh 1 nằm dưới nút `✕`, Thanh 2 nằm dưới nút Back `< [Tên Menu Cha]`).
* **Căn chỉnh chữ:** Mũi tên `<` căn trái, toàn bộ tiêu đề mục menu căn phải.
* **Dữ liệu hoàn toàn động:** Lấy trực tiếp từ hệ thống menu WordPress có `id=5` (`Appearance > Menus`), phản ánh ngay lập tức mọi thay đổi phân cấp cha - con mà quản trị viên cấu hình.

### 4.2. Cách sử dụng Shortcode

#### Cú pháp cơ bản (Tự động tải Menu ID = 5 và hiển thị nút 3 gạch ngang):
```text
[menu__header]
```

#### Các thuộc tính tùy biến (Attributes):
* `id="5"`: Chỉ định ID của Menu WordPress cần hiển thị (Mặc định: `5`).
* `font_size="22px"`: Tùy chỉnh font-size gốc (VD: `20px`, `24px`...), toàn bộ kích cỡ thẻ và khoảng cách sẽ tự động co giãn theo tỉ lệ thuận.
* `toggle="false"`: Ẩn nút 3 gạch ngang mặc định (dùng khi bạn muốn kích hoạt menu qua một nút bấm tùy biến khác trên header).
* `inline="true"`: Hiển thị thẻ menu trực tiếp vào vị trí chèn shortcode (dạng tĩnh, không mở qua popup).
* `open="true"`: Mở sẵn modal khi tải trang.
* `class="custom-menu-header"`: Bổ sung CSS class tùy biến.

#### Kích hoạt đóng/mở từ nút bấm Elementor bất kỳ:
Có thể gán bất kỳ thẻ nào trên trang để mở menu popup bằng các cách sau:
1. Gán class: `memora-menu-toggle` vào Button hoặc Icon trong Elementor.
2. Gán link liên kết: `#menu__header`.
3. Gọi qua JavaScript: `window.MemoraMenu.open();` hoặc `window.MemoraMenu.close();` hoặc `window.MemoraMenu.toggle();`.

---

## 5. Hệ thống Đặt lịch chụp ảnh (Memora Booking System)

Hệ thống đặt lịch tự động hoàn chỉnh, bám sát thiết kế nhận diện thương hiệu Memora Film (Tone màu xanh pastel `#cbe3f3`, nâu đất `#733e1c`, nút trái tim hồng phấn `#dfb0bf`, code ngẫu nhiên 4 số).

### 5.1. Cấu hình ACF trong Admin
- **Trang cấu hình:** `/wp-admin/admin.php?page=cau-hinh-thoi-gian-chup-anh` (Menu: **Cấu hình Lịch Chụp**).
- **Các trường cấu hình:**
  1. `thoi_gian_bat_dau_phuc_vu_hanh_chinh`: Giờ bắt đầu phục vụ (Time Picker, VD: `10:00`).
  2. `thoi_gian_ket_thuc_phuc_vu_hanh_chinh`: Giờ kết thúc phục vụ (Time Picker, VD: `22:00`).
  3. `dan_cach_gio_chup_anh`: Khoảng cách giữa các ca chụp tính theo phút (Number, VD: `15`).
  4. `cac_goi_chup_anh`: Danh sách gói dịch vụ (Repeater):
     - `ten_goi_chup`: Tên gói (VD: `5p`, `10p`, `15p`).
     - `gia_goi_chup`: Giá tiền (Number, VD: `200000`).

### 5.2. Danh sách Shortcodes sử dụng trong trang

| Shortcode | Mô tả chức năng | Tùy biến Attributes |
| :--- | :--- | :--- |
| `[choose_date]` | 3 trường chọn Ngày, Tháng, Năm. Tự động tính số ngày trong tháng và đồng bộ với khung giờ. | Không |
| `[choose_time]` | Hiển thị các nút chọn khung giờ tự động tính theo giờ bắt đầu/kết thúc/giãn cách. Tự động vô hiệu hóa giờ đã có người đặt hoặc giờ đã qua. | Không |
| `[choose_photography_package]` | Hiển thị các gói chụp ảnh lấy từ ACF Repeater `cac_goi_chup_anh`. | Không |
| `[confirm_booking]` | Thẻ xác nhận lịch chụp (Khớp Ảnh 1), hiển thị 3 viên pill: Giờ, Ngày, Gói chụp & nút **THANH TOÁN**. | `checkout_url="/thanh-toan/"` |
| `[checkout_booking]` | Form nhập thông tin khách hàng (Tên, SĐT, IG), bảng giá cọc 50% & nút **Thanh Toán hình Trái Tim** (Khớp Ảnh 2). | `thankyou_url="/thanh-toan-thanh-cong/"` |
| `[thankyou_booking]` | Trang thông báo thanh toán thành công (Khớp Ảnh 3), hiển thị mã Code 4 số ngẫu nhiên, thông tin lịch và Note dặn dò. | Không (tự đọc `?code=xxxx`) |
| `[lookup_booking]` / `[tra_cuu_lich]` | Trang tra cứu đơn lịch chụp (Khớp Ảnh 4) theo Số điện thoại và Code chụp qua AJAX. | Không |
| `[booking_room_template]` | Nhúng template Elementor (mặc định id="286") vào chi tiết `phong-chup-anh`, tự động truyền ID và Tên phòng vào hệ thống. | `id="286"` `booking_page_url="/dat-lich/"` |
| `[booking_room_url]` | Xuất chuỗi URL đặt lịch có gắn thông tin phòng (dán trực tiếp vào ô Link của Elementor Button). | `page_url="/dat-lich/"` `room_id=""` |
| `[booking_room_button]` | Xuất nút bấm "ĐẶT LỊCH CHỤP" chuẩn phong cách Memora, click mở modal popup chứa template 286 hoặc dẫn link đặt lịch. | `id="286"` `action="popup"` hoặc `action="link"` `text="ĐẶT LỊCH CHỤP"` |

### 5.3. Quản lý Đơn trong WP Admin
- **Đường dẫn quản trị:** `/wp-admin/edit.php?post_type=memora_booking` (Menu: **Lịch Chụp Ảnh**).
- **Tính năng:**
  - Danh sách trực quan: Mã Code (4 số), Tên KH + SĐT + Instagram, Lịch chụp, Gói chụp, Giá cọc 50%, Huy hiệu Trạng thái (`Chờ xác nhận`, `Đã cọc 50%`, `Hoàn tất`, `Đã hủy`).
  - Meta Box chi tiết: Xem toàn bộ thông tin đơn, cập nhật trạng thái đơn hàng, ghi chú nội bộ của admin.
  - Tự động chống trùng lịch (Double-booking Prevention): Khi có khách đặt một khung giờ trong ngày, khung giờ đó sẽ lập tức được khóa lại."# theme-by-memora" 
