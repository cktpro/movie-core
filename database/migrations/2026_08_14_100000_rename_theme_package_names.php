<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Đổi tên package Composer (2026-08-14, gộp 2 lần đổi tên trong cùng ngày):
 * hacoidev/ophim-ripple (giá trị GỐC đang có thật trên DB production, chưa
 * từng đổi) -> roxone/movie-ripple (tên cuối cùng). Bảng `themes` lưu
 * `package_name` của theme để build đường dẫn `vendor/{package_name}/routes/web.php`
 * (MovieServiceProvider::loadThemeRoutes()) — nếu không đổi theo, route công khai
 * của theme "ripple" sẽ không nạp được (thư mục vendor thực tế đã chuyển từ
 * vendor/hacoidev/ophim-ripple sang vendor/roxone/movie-ripple).
 */
class RenameThemePackageNames extends Migration
{
    protected $renames = [
        'hacoidev/ophim-ripple' => 'roxone/movie-ripple',
    ];

    public function up()
    {
        foreach ($this->renames as $old => $new) {
            DB::table('themes')->where('package_name', $old)->update(['package_name' => $new]);
        }
    }

    public function down()
    {
        foreach ($this->renames as $old => $new) {
            DB::table('themes')->where('package_name', $new)->update(['package_name' => $old]);
        }
    }
}
