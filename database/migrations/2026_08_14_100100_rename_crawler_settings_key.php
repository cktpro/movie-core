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
        DB::table('settings')->where('key', $this->oldKey)->update(['key' => $this->newKey]);
    }

    public function down()
    {
        DB::table('settings')->where('key', $this->newKey)->update(['key' => $this->oldKey]);
    }
}
