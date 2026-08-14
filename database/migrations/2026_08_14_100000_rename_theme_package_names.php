<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Đổi tên package Composer (2026-08-14): hacoidev/ophim-core -> roxone/ophim-core,
 * hacoidev/ophim-ripple -> roxone/ophim-ripple. Bảng `themes` lưu `package_name`
 * của theme để build đường dẫn `vendor/{package_name}/routes/web.php`
 * (OphimServiceProvider::loadThemeRoutes()) — nếu không đổi theo, route công khai
 * của theme "ripple" sẽ không nạp được sau khi đổi tên package (thư mục vendor
 * thực tế đã chuyển từ vendor/hacoidev/ophim-ripple sang vendor/roxone/ophim-ripple).
 */
class RenameThemePackageNames extends Migration
{
    protected $renames = [
        'hacoidev/ophim-ripple' => 'roxone/ophim-ripple',
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
