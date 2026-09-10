<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Thêm mẫu SEO mặc định cho trang danh sách (2026-09-10).
 *
 * Bối cảnh: mọi taxonomy khác (thể loại, quốc gia, studio, diễn viên, đạo diễn,
 * tag) đều có mẫu {name} trong `settings` để Model rơi về khi ô SEO của bản ghi
 * bỏ trống. Riêng Catalog thì không — nó đọc thẳng 3 cột seo_* của bản ghi và
 * hết. Mà CatalogsTableSeeder lại ghi sẵn chuỗi giữ chỗ 'Title Phim bộ',
 * 'Des Phim bộ', 'Key Phim bộ'... nên đúng ba chuỗi đó bị phơi ra <title>,
 * og:title, twitter:title và JSON-LD của /danh-sach/phim-bo, /danh-sach/phim-le,
 * /danh-sach/phim-moi. Ba trang này lại nằm trong sitemap.xml.
 *
 * Migration làm hai việc:
 *  1. Tạo 3 setting site_catalog_title/des/key (nhóm metas, tab "Danh Sách").
 *  2. Xoá chuỗi giữ chỗ trong `catalogs` để fallback có chỗ phát huy.
 *
 * Bước 2 CHỈ xoá khi giá trị khớp CHÍNH XÁC chuỗi của seeder. Site nào đã tự
 * viết nội dung SEO thật cho catalog thì giữ nguyên, không được đụng vào.
 */
class AddCatalogSeoSettings extends Migration
{
    /**
     * @var array<string, array{name: string, value: string}>
     */
    protected $settings = [
        'site_catalog_title' => [
            'name'  => 'Tiêu đề danh sách mặc định',
            'value' => '{name} mới nhất - tổng hợp {name} hay full HD vietsub',
        ],
        'site_catalog_des' => [
            'name'  => 'Description danh sách mặc định',
            'value' => 'Tổng hợp {name} mới nhất, cập nhật liên tục, vietsub và thuyết minh full HD. Xem {name} hay chọn lọc.',
        ],
        'site_catalog_key' => [
            'name'  => 'Keywords danh sách mặc định',
            'value' => '{name}, {name} mới, {name} hay, {name} vietsub, xem {name}',
        ],
    ];

    public function up()
    {
        foreach ($this->settings as $key => $config) {
            if (DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }

            // Chèn bằng query builder chứ không qua Model: Setting::$fillable chỉ
            // có ['value'], mọi cột khác bị bỏ lặng lẽ rồi chết ở "Field 'key'
            // doesn't have a default value".
            DB::table('settings')->insert([
                'key'         => $key,
                'name'        => $config['name'],
                'description' => $key,
                'value'       => $config['value'],
                'field'       => json_encode([
                    'name' => 'value',
                    'type' => 'text',
                    'hint' => 'Thông tin: {name}',
                    'tab'  => 'Danh Sách',
                ], JSON_UNESCAPED_UNICODE),
                'active'      => 0,
                'group'       => 'metas',
            ]);
        }

        foreach (['seo_title' => 'Title', 'seo_des' => 'Des', 'seo_key' => 'Key'] as $cot => $tienTo) {
            DB::table('catalogs')
                ->whereRaw('`' . $cot . '` = CONCAT(?, name)', [$tienTo . ' '])
                ->update([$cot => '']);
        }
    }

    public function down()
    {
        DB::table('settings')->whereIn('key', array_keys($this->settings))->delete();

        // Không dựng lại chuỗi giữ chỗ trong `catalogs`: chúng vốn là rác, và
        // sau khi up() chạy thì không phân biệt được ô nào do migration xoá với
        // ô nào người dùng tự để trống.
    }
}
