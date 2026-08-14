<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Đổi tên key cấu hình crawler (2026-08-14) trong bảng `settings`:
 * hacoidev/ophim-crawler.options -> hacoidev/movie-crawler.options.
 *
 * Package roxone/ophim-nguonc-crawler (giờ là roxone/movie-nguonc-crawler)
 * không có migration riêng (chỉ dùng Setting::firstOrCreate() lúc runtime,
 * xem Option::getEntry()), nên đặt migration này trong ophim-core (movie-core)
 * — nơi duy nhất có loadMigrationsFrom() được đăng ký — để không mất cấu hình
 * crawler đã lưu sẵn trên DB production (domain, danh sách loại trừ, cấu hình
 * R2, lịch cron...) khi code đổi sang đọc/ghi key mới.
 */
class RenameCrawlerSettingsKey extends Migration
{
    protected $oldKey = 'hacoidev/ophim-crawler.options';
    protected $newKey = 'hacoidev/movie-crawler.options';

    public function up()
    {
        if (!DB::table('settings')->where('key', $this->oldKey)->exists()) {
            return;
        }

        // Option::getEntry() đã đọc/ghi key mới ngay từ đầu, nên nếu crawler
        // từng chạy trước khi migration này thực thi (vd. package:discover),
        // nó có thể đã tự tạo sẵn 1 dòng rỗng ở key mới. Dòng đó là rác,
        // ưu tiên giữ dữ liệu thật đang nằm ở key cũ.
        DB::table('settings')->where('key', $this->newKey)->delete();

        DB::table('settings')->where('key', $this->oldKey)->update(['key' => $this->newKey]);
    }

    public function down()
    {
        DB::table('settings')->where('key', $this->newKey)->update(['key' => $this->oldKey]);
    }
}
