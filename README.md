# roxone/movie-core

Phần lõi của Movie CMS: model nghiệp vụ, toàn bộ admin (Backpack CRUD), route admin, migration, policy, SEO và hệ thống theme.

Bản fork của Ophim CMS, đã đổi tên (`Ophim\*` → `Movie\*`) và nâng lên Laravel 12 / Backpack v7. Upstream cũ (`hacoidev/*`) không còn tồn tại.

## Yêu cầu

- PHP **8.2** trở lên, kèm các extension: `fileinfo`, `gd` (có hỗ trợ WebP nếu dùng crawler), `curl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `bcmath`, `intl`, `exif`
- Laravel **12**
- MySQL 5.7+ / MariaDB 10.4+
- `php.ini`: `max_input_vars=100000` — form option của admin rất lớn, thiếu là dữ liệu bị cắt lúc lưu mà **không báo lỗi**

## Cài đặt

```bash
composer require roxone/movie-core -W
```

1. Khai báo kết nối database trong `.env`
2. `php artisan movie:install` — chạy migration và publish asset/config
3. Cho `app/Models/User` kế thừa user của core:

```php
use Movie\Core\Models\User as MovieUser;

class User extends MovieUser {
    use HasApiTokens, HasFactory, Notifiable;
    // ...
}
```

4. Tạo tài khoản quản trị: `php artisan movie:user`
5. Xoá route `/` mặc định trong `routes/web.php` — route trang công khai do **theme** nạp, không phải từ dự án:

```php
Route::get('/', function () {
    return view('welcome');
});
```

6. `php artisan storage:link`
7. `php artisan optimize:clear`

Sau đó cài một theme (vd [roxone/movie-hhtq](https://github.com/cktpro/movie-hhtq)) và kích hoạt trong admin — **chưa kích hoạt theme thì toàn bộ trang công khai trả 404**, vì tập route front-end được nạp từ theme đang active trong bảng `themes`.

## Cập nhật

```bash
composer update roxone/movie-core -W
php artisan movie:install
php artisan optimize:clear
```

Xoá OPcache trên server nếu có bật.

## Lệnh

| Lệnh | Việc |
|---|---|
| `movie:install` | migrate + publish asset/config (chạy lại được sau mỗi lần update) |
| `movie:user` | tạo tài khoản admin |
| `movie:menu:generate` | sinh lại menu thể loại / quốc gia |
| `movie:episode:change_domain_play` | đổi domain link phát của tập phim |

## Cron

Reset bộ đếm lượt xem theo ngày/tuần/tháng và chạy crawler theo lịch đều dựa vào scheduler của Laravel:

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Môi trường production

- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain.com`
- `config/app.php`: `'timezone' => 'Asia/Ho_Chi_Minh'`, `'locale' => 'vi'`
- `php artisan storage:link` phải chạy được. Thiếu symlink là **admin mất sạch CSS**: Backpack v7 dùng `backpack/basset` để nội bộ hoá asset, nó ghi file vào `storage/app/public/basset/` rồi sinh URL `/storage/basset/...`
- Trên các panel như aaPanel, nhớ bỏ `proc_open`, `putenv`, `symlink` khỏi `disable_functions` — thiếu thì composer và `storage:link` không chạy được

## Tuỳ biến giao diện admin

Blade của admin nằm trong package; muốn sửa thì publish rồi chỉnh bản đã publish trong `resources/views/vendor/` của dự án — bản này được ưu tiên hơn view trong package.

Field/column tuỳ biến được tra qua `config('backpack.crud.view_namespaces.*')`; core đã đăng ký sẵn các namespace `movie::base.fields`, `movie::base.columns`, `movie::crud.buttons`, `movie::crud.filters`.

## Add-on

- Crawler: [roxone/movie-nguonc-crawler](https://github.com/cktpro/movie-crawl-tool)
- Theme: [roxone/movie-hhtq](https://github.com/cktpro/movie-hhtq)
