<?php

namespace Movie\Core\Services;

use Illuminate\Support\Facades\URL as LARURL;
use Backpack\Settings\app\Models\Setting;
use Movie\Core\Models\Catalog;
use Movie\Core\Models\Category;
use Movie\Core\Models\Movie;
use Movie\Core\Models\Region;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Sinh sitemap cho site công khai.
 *
 * Từ 2026-08-20 chỉ sinh DUY NHẤT một file `public/sitemap.xml` chứa toàn bộ
 * URL, thay cho kiểu cũ (một sitemap index trỏ tới page/categories/regions/
 * movies-sitemap{n}). Site có vài nghìn URL nên không cần chia file, mà gộp
 * lại thì Search Console chỉ phải theo dõi một nguồn.
 *
 * Dùng chung bởi hai lối vào — nút "Tạo sitemap" trong admin
 * (SiteMapController) và lệnh `movie:sitemap:generate` chạy theo lịch — nên
 * kết quả của hai đường luôn giống hệt nhau.
 */
class SitemapGenerator
{
    /**
     * Giới hạn của chuẩn sitemap: 50.000 URL cho mỗi file.
     *
     * Vượt ngưỡng thì Google bỏ qua phần dư chứ không báo lỗi, nên chỗ gọi
     * phải tự cảnh báo (xem $stats['over_limit']).
     */
    public const MAX_URLS = 50000;

    /**
     * Các file của kiểu sinh cũ, xoá đi sau khi đã ghi file gộp.
     *
     * Chỉ liệt kê đúng tên do bản cũ tạo ra — không quét bừa cả thư mục, để
     * không xoá nhầm file ai đó đặt tay vào đây.
     */
    protected const LEGACY_FILES = [
        'sitemap/page-sitemap.xml',
        'sitemap/categories-sitemap.xml',
        'sitemap/regions-sitemap.xml',
    ];

    /**
     * Sinh sitemap.
     *
     * @return array{pages:int,categories:int,regions:int,movies:int,total:int,over_limit:bool,path:string,legacy_removed:int}
     */
    public function generate(): array
    {
        $this->renderStyles();

        $sitemap = Sitemap::create();
        $stats = ['pages' => 0, 'categories' => 0, 'regions' => 0, 'movies' => 0];

        $sitemap->add(
            Url::create('/')
                ->setLastModificationDate(now())
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_HOURLY)
                ->setPriority(1)
        );
        $stats['pages']++;

        Catalog::chunkById(100, function ($catalogs) use ($sitemap, &$stats) {
            foreach ($catalogs as $catalog) {
                $sitemap->add(
                    Url::create($catalog->getUrl())
                        ->setLastModificationDate($catalog->updated_at ?: now())
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                        ->setPriority(0.9)
                );
                $stats['pages']++;
            }
        });

        Category::chunkById(100, function ($categories) use ($sitemap, &$stats) {
            foreach ($categories as $category) {
                $sitemap->add(
                    Url::create($category->getUrl())
                        ->setLastModificationDate($category->updated_at ?: now())
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                        ->setPriority(0.8)
                );
                $stats['categories']++;
            }
        });

        Region::chunkById(100, function ($regions) use ($sitemap, &$stats) {
            foreach ($regions as $region) {
                $sitemap->add(
                    Url::create($region->getUrl())
                        ->setLastModificationDate($region->updated_at ?: now())
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                        ->setPriority(0.8)
                );
                $stats['regions']++;
            }
        });

        // chunkById để không nạp cả bảng movies vào bộ nhớ một lúc; bản thân
        // đối tượng Sitemap vẫn giữ toàn bộ URL, nhưng mỗi URL nhẹ hơn model.
        Movie::chunkById(200, function ($movies) use ($sitemap, &$stats) {
            foreach ($movies as $movie) {
                $sitemap->add(
                    Url::create($movie->getUrl())
                        ->setLastModificationDate($movie->updated_at ?: now())
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                        ->setPriority(0.7)
                );
                $stats['movies']++;
            }
        });

        $path = public_path('sitemap.xml');
        $sitemap->writeToFile($path);
        $this->addStyles('sitemap.xml');

        $stats['total'] = $stats['pages'] + $stats['categories'] + $stats['regions'] + $stats['movies'];
        $stats['over_limit'] = $stats['total'] > self::MAX_URLS;
        $stats['path'] = $path;
        $stats['legacy_removed'] = $this->removeLegacyFiles();

        return $stats;
    }

    /**
     * Ghi file XSL để trình duyệt hiển thị sitemap dễ đọc.
     */
    public function renderStyles(): void
    {
        $xml = view('movie::sitemap/styles', [
            'title'  => Setting::get('site_homepage_title'),
            'domain' => LARURL::to('/'),
        ])->render();

        file_put_contents(public_path('main-sitemap.xsl'), $xml);
    }

    /**
     * Chèn khai báo stylesheet vào file XML vừa ghi.
     */
    public function addStyles(string $fileName): void
    {
        $path = public_path($fileName);

        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);
        $content = str_replace(
            '?' . '>',
            '?' . '>' . '<' . '?' . 'xml-stylesheet type="text/xsl" href="' . LARURL::to('/') . '/main-sitemap.xsl"?' . '>',
            $content
        );
        file_put_contents($path, $content);
    }

    /**
     * Dọn file của kiểu sinh cũ (sitemap index + các file con).
     *
     * Cần thiết vì đây là file tĩnh: không xoá thì `public/sitemap/*.xml` cũ
     * vẫn nằm đó và Google tiếp tục đọc dữ liệu đóng băng từ lần sinh cuối.
     *
     * @return int số file đã xoá
     */
    protected function removeLegacyFiles(): int
    {
        $removed = 0;

        foreach (self::LEGACY_FILES as $file) {
            $path = public_path($file);
            if (is_file($path) && @unlink($path)) {
                $removed++;
            }
        }

        // movies-sitemap1.xml, movies-sitemap2.xml... số lượng tuỳ số phim lúc
        // sinh lần trước nên phải quét theo mẫu.
        foreach (glob(public_path('sitemap/movies-sitemap*.xml')) ?: [] as $path) {
            if (@unlink($path)) {
                $removed++;
            }
        }

        // Thư mục rỗng thì bỏ luôn; còn file lạ bên trong thì giữ nguyên.
        $dir = public_path('sitemap');
        if (is_dir($dir) && !(glob($dir . '/*') ?: [])) {
            @rmdir($dir);
        }

        return $removed;
    }
}
