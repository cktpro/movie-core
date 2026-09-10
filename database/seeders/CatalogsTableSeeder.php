<?php

namespace Movie\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Movie\Core\Models\Catalog;

class CatalogsTableSeeder extends Seeder
{
    /**
     * Run the database seeds_
     *
     * @return void
     */
    public function run()
    {
        // Ba ô SEO để trống có chủ đích: Catalog::generateSeoTags() sẽ rơi về mẫu
        // site_catalog_title/des/key trong settings (có {name}), giống Category.
        // Trước đây chỗ này ghi sẵn 'Title Phim bộ'/'Des Phim bộ'... và vì model
        // không có fallback nên chuỗi giữ chỗ đó bị đẩy thẳng ra <title> của
        // /danh-sach/phim-bo, /danh-sach/phim-le, /danh-sach/phim-moi trên site thật.
        $catalogs = [
            [
                'name'          => 'Phim mới',
                'slug'          => 'phim-moi',
                'paginate'      => 20,
                'value'         => '|is_copyright|0|updated_at|desc',
                'seo_title'     => '',
                'seo_des'       => '',
                'seo_key'       => '',
            ],
            [
                'name'          => 'Phim bộ',
                'slug'          => 'phim-bo',
                'paginate'      => 20,
                'value'         => '|type|series|updated_at|desc',
                'seo_title'     => '',
                'seo_des'       => '',
                'seo_key'       => '',
            ],
            [
                'name'          => 'Phim lẻ',
                'slug'          => 'phim-le',
                'paginate'      => 20,
                'value'         => '|type|single|updated_at|desc',
                'seo_title'     => '',
                'seo_des'       => '',
                'seo_key'       => '',
            ]
        ];

        foreach ($catalogs as $index => $catalog) {
            $result = Catalog::firstOrCreate(collect($catalog)->only('slug')->toArray(), collect($catalog)->except('slug')->toArray());

            if (!$result) {
                $this->command->info("Insert failed at record $index");

                return;
            }
        }

    }
}
