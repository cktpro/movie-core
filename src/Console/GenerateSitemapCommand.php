<?php

namespace Movie\Core\Console;

use Illuminate\Console\Command;
use Movie\Core\Services\SitemapGenerator;

/**
 * Sinh lại `public/sitemap.xml`.
 *
 * Trước đây sitemap chỉ được tạo khi có người bấm nút trong admin, nên nó cũ
 * dần trong khi crawler thêm phim mỗi ngày — phim mới không được Google phát
 * hiện qua sitemap. Lệnh này chạy theo lịch trong MovieServiceProvider để
 * sitemap tự cập nhật.
 */
class GenerateSitemapCommand extends Command
{
    protected $signature = 'movie:sitemap:generate';

    protected $description = 'Sinh lại public/sitemap.xml (một file duy nhất, gồm trang chủ, catalog, thể loại, quốc gia và phim)';

    public function handle(SitemapGenerator $generator)
    {
        $this->info('Đang sinh sitemap...');

        $stats = $generator->generate();

        $this->table(
            ['Nhóm', 'Số URL'],
            [
                ['Trang (trang chủ + catalog)', $stats['pages']],
                ['Thể loại', $stats['categories']],
                ['Quốc gia', $stats['regions']],
                ['Phim', $stats['movies']],
                ['Tổng', $stats['total']],
            ]
        );

        if ($stats['legacy_removed'] > 0) {
            $this->line("Đã xoá {$stats['legacy_removed']} file sitemap kiểu cũ trong public/sitemap.");
        }

        if ($stats['over_limit']) {
            // Google đọc 50.000 URL đầu rồi bỏ qua phần còn lại, và không báo
            // lỗi gì — nên phải tự nói ra ở đây.
            $this->warn(sprintf(
                'Sitemap có %d URL, vượt giới hạn %d URL/file của chuẩn sitemap. Cần chia file trở lại.',
                $stats['total'],
                SitemapGenerator::MAX_URLS
            ));
        }

        $this->info('Đã ghi ' . $stats['path']);

        return 0;
    }
}
