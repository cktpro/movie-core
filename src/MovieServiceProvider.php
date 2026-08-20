<?php

namespace Movie\Core;

use Illuminate\Console\Scheduling\Schedule;
use Movie\Core\Policies\PermissionPolicy;
use Movie\Core\Policies\RolePolicy;
use Movie\Core\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Movie\Core\Console\CreateUser;
use Movie\Core\Console\InstallCommand;
use Movie\Core\Console\GenerateMenuCommand;
use Movie\Core\Console\ChangeDomainEpisodeCommand;
use Movie\Core\Console\GenerateSitemapCommand;
use Movie\Core\Middleware\CKFinderAuth;
use Movie\Core\Models\Actor;
use Movie\Core\Models\Catalog;
use Movie\Core\Models\Category;
use Movie\Core\Models\Director;
use Movie\Core\Models\Episode;
use Movie\Core\Models\Menu;
use Movie\Core\Models\Movie;
use Movie\Core\Models\Region;
use Movie\Core\Models\Studio;
use Movie\Core\Models\Tag;
use Movie\Core\Models\Theme;
use Movie\Core\Policies\ActorPolicy;
use Movie\Core\Policies\CatalogPolicy;
use Movie\Core\Policies\CategoryPolicy;
use Movie\Core\Policies\CrawlSchedulePolicy;
use Movie\Core\Policies\DirectorPolicy;
use Movie\Core\Policies\EpisodePolicy;
use Movie\Core\Policies\MenuPolicy;
use Movie\Core\Policies\MoviePolicy;
use Movie\Core\Policies\RegionPolicy;
use Movie\Core\Policies\StudioPolicy;
use Movie\Core\Policies\TagPolicy;

class MovieServiceProvider extends ServiceProvider
{
    /**
     * Get the policies defined on the provider.
     *
     * @return array
     */
    public function policies()
    {
        return [
            Actor::class => ActorPolicy::class,
            Catalog::class => CatalogPolicy::class,
            Category::class => CategoryPolicy::class,
            Region::class => RegionPolicy::class,
            Director::class => DirectorPolicy::class,
            Tag::class => TagPolicy::class,
            Studio::class => StudioPolicy::class,
            Movie::class => MoviePolicy::class,
            Episode::class => EpisodePolicy::class,
            Menu::class => MenuPolicy::class,
            CrawlSchedule::class => CrawlSchedulePolicy::class
        ];
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'movie');

        $this->mergeBackpackConfigs();

        $this->mergeCkfinderConfigs();

        $this->mergePolicies();
    }

    public function boot()
    {
        $this->registerPolicies();

        try {
            foreach (glob(__DIR__ . '/Helpers/*.php') as $filename) {
                require_once $filename;
            }
        } catch (\Exception $e) {
            //throw $e;
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/admin.php');

        $this->app->booted(function () {
            $this->registerAdminThemeViewOverrides();
            $this->loadThemeRoutes();
            $this->loadScheduler();
        });

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadViewsFrom(__DIR__ . '/../resources/views/core/', 'movie');

        $this->loadViewsFrom(__DIR__ . '/../resources/views/themes', 'themes');

        $this->publishFiles();

        $this->commands([
            InstallCommand::class,
            CreateUser::class,
            GenerateMenuCommand::class,
            ChangeDomainEpisodeCommand::class,
            GenerateSitemapCommand::class,
        ]);

        $this->bootSeoDefaults();
    }

    protected function publishFiles()
    {
        // Đích publish phải là resources/views/vendor/<namespace>/ thì Laravel mới coi là
        // bản ghi đè. Namespace của package là 'movie' (loadViewsFrom bên dưới), nên đích
        // đúng là views/vendor/movie/ — trước đây vẫn để 'hacoidev' theo tên cũ, khiến mọi
        // file đã publish nằm ở chỗ không ai đọc tới và không ghi đè được gì.
        // Đây cũng là thư mục registerAdminThemeViewOverrides() cắm vào namespace theme.
        $backpack_menu_contents_view = [
            __DIR__ . '/../resources/views/core/base/' => resource_path('views/vendor/movie/base/'),
            __DIR__ . '/../resources/views/core/crud/' => resource_path('views/vendor/movie/crud/'),
        ];

        $players = [
            __DIR__ . '/../resources/assets/js/hls.min.js' => public_path('js/hls.min.js'),
            __DIR__ . '/../resources/assets/js/jwplayer-8.9.3.js' => public_path('js/jwplayer-8.9.3.js'),
            __DIR__ . '/../resources/assets/js/jwplayer.hlsjs.min.js' => public_path('js/jwplayer.hlsjs.min.js'),
        ];

        $this->publishes($backpack_menu_contents_view, 'cms_menu_content');
        $this->publishes($players, 'players');

        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('movie.php')
        ], 'config');
    }

    protected function mergeBackpackConfigs()
    {
        config(['backpack.base.styles' => array_merge(config('backpack.base.styles', []), [
            'packages/select2/dist/css/select2.css',
            'packages/select2-bootstrap-theme/dist/select2-bootstrap.min.css'
        ])]);

        config(['backpack.base.scripts' => array_merge(config('backpack.base.scripts', []), [
            'packages/select2/dist/js/select2.full.min.js'
        ])]);

        config(['backpack.base.middleware_class' => array_merge(config('backpack.base.middleware_class', []), [
            \Backpack\CRUD\app\Http\Middleware\UseBackpackAuthGuardInsteadOfDefaultAuthGuard::class,
        ])]);

        config(['cachebusting_string' => \Composer\InstalledVersions::getPrettyVersion('backpack/crud') ?? 'unknown']);

        config(['backpack.base.project_logo' => '<b>Movie</b>CMS']);
        config(['backpack.base.developer_name' => 'hacoidev']);
        config(['backpack.base.developer_link' => 'mailto:hacoi.dev@gmail.com']);
        config(['backpack.base.show_powered_by' => false]);

        // backpack/crud v7 tra cứu view field/column/button/filter tuỳ biến qua
        // config('backpack.crud.view_namespaces.*'), không tự động biết namespace
        // 'movie::' (đăng ký ở loadViewsFrom bên dưới, trỏ resources/views/core/).
        config(['backpack.crud.view_namespaces.fields' => array_merge(
            config('backpack.crud.view_namespaces.fields', []),
            ['movie::base.fields']
        )]);
        config(['backpack.crud.view_namespaces.columns' => array_merge(
            config('backpack.crud.view_namespaces.columns', []),
            ['movie::base.columns', 'movie::movies.columns']
        )]);
        config(['backpack.crud.view_namespaces.buttons' => array_merge(
            config('backpack.crud.view_namespaces.buttons', []),
            ['movie::crud.buttons']
        )]);
        config(['backpack.crud.view_namespaces.filters' => array_merge(
            config('backpack.crud.view_namespaces.filters', []),
            ['movie::crud.filters']
        )]);
    }

    protected function mergeCkfinderConfigs()
    {
        config(['ckfinder.authentication' => CKFinderAuth::class]);
        config(['ckfinder.backends.default' => config('movie.ckfinder.backends')]);
    }

    protected function mergePolicies()
    {
        config(['backpack.permissionmanager.policies.permission' => PermissionPolicy::class]);
        config(['backpack.permissionmanager.policies.role' => RolePolicy::class]);
        config(['backpack.permissionmanager.policies.user' => UserPolicy::class]);
    }

    protected function bootSeoDefaults()
    {
        config([
            'seotools.meta.defaults.title' => setting('site_homepage_title'),
            'seotools.meta.defaults.description' => setting('site_meta_description'),
            'seotools.meta.defaults.keywords' => [setting('site_meta_keywords')],
            'seotools.meta.defaults.canonical' => url("/")
        ]);

        config([
            'seotools.opengraph.defaults.title' => setting('site_homepage_title'),
            'seotools.opengraph.defaults.description' => setting('site_meta_description'),
            'seotools.opengraph.defaults.type' => 'website',
            'seotools.opengraph.defaults.url' => url("/"),
            'seotools.opengraph.defaults.site_name' => setting('site_meta_siteName'),
            'seotools.opengraph.defaults.images' => [setting('site_meta_image')],
        ]);

        config([
            'seotools.twitter.defaults.card' => 'website',
            'seotools.twitter.defaults.title' => setting('site_homepage_title'),
            'seotools.twitter.defaults.description' => setting('site_meta_description'),
            'seotools.twitter.defaults.url' => url("/"),
            'seotools.twitter.defaults.site' => setting('site_meta_siteName'),
            'seotools.twitter.defaults.image' => setting('site_meta_image'),
        ]);

        config([
            'seotools.json-ld.defaults.title' => setting('site_homepage_title'),
            'seotools.json-ld.defaults.type' => 'WebPage',
            'seotools.json-ld.defaults.description' => setting('site_meta_description'),
            'seotools.json-ld.defaults.images' => setting('site_meta_image'),
        ]);
    }

    /**
     * Cho phép các view giao diện admin của movie-core ghi đè view cùng tên của theme
     * Backpack (backpack/theme-coreuiv2): dashboard, inc/sidebar_content (menu trái),
     * inc/main_header, inc/topbar_right_content (menu "Delete All Cache").
     *
     * Vì sao cần: helper backpack_view($view) của Backpack v7 tra theo thứ tự
     *   1. config('backpack.ui.view_namespace')  -> backpack.theme-coreuiv2::$view
     *   2. backpack_theme_config('view_namespace_fallback')
     *   3. backpack.ui::$view
     * nên nó KHÔNG bao giờ nhìn tới namespace movie::. Bản fork hacoidev/crud dùng
     * trước khi nâng Laravel 12 (Backpack 4.1) không có tầng theme này — backpack_view()
     * hồi đó trỏ thẳng vào view của package, nên dashboard/sidebar tuỳ biến chạy bình
     * thường. Sau khi chuyển sang backpack/crud v7 + theme, toàn bộ chúng bị theme
     * thay thế: dashboard thành trang trống mặc định và menu trái rỗng hoàn toàn.
     *
     * Cách xử lý: chèn thư mục view của package lên ĐẦU danh sách đường dẫn của
     * namespace theme, nên view nào package có thì package thắng, view nào không có
     * thì rơi về theme như cũ. Không ảnh hưởng field/column/button/filter vì những
     * thứ đó tra qua config('backpack.crud.view_namespaces.*') chứ không qua namespace
     * theme (xem Backpack\CRUD\ViewNamespaces::getFromConfigFor()).
     */
    protected function registerAdminThemeViewOverrides()
    {
        $namespace = rtrim((string) config('backpack.ui.view_namespace'), ':');

        if ($namespace === '') {
            return;
        }

        // Đường dẫn nào prepend sau thì đứng trước, nên package đi trước rồi mới tới
        // thư mục cho người dùng tự ghi đè — kết quả: người dùng > package > theme.
        View::prependNamespace($namespace, __DIR__ . '/../resources/views/core/base');
        View::prependNamespace($namespace, resource_path('views/vendor/movie/base'));
    }

    protected function loadThemeRoutes()
    {
        try {
            $activatedTheme = Theme::getActivatedTheme();
            if ($activatedTheme && file_exists($routeFile = base_path('vendor/' . $activatedTheme->package_name . '/routes/web.php'))) {
                $this->loadRoutesFrom($routeFile);
            }
        } catch (\Exception $e) {
            // Log
        }
    }

    protected function loadScheduler()
    {
        $schedule = $this->app->make(Schedule::class);

        $schedule->call(function () {
            DB::table('movies')->update(['view_day' => 0]);
        })->daily();
        $schedule->call(function () {
            DB::table('movies')->update(['view_week' => 0]);
        })->weekly();
        $schedule->call(function () {
            DB::table('movies')->update(['view_month' => 0]);
        })->monthly();

        // Sitemap là file tĩnh trong public/, trước đây chỉ sinh khi có người
        // bấm nút trong admin nên nó cũ dần trong lúc crawler thêm phim mỗi
        // ngày. withoutOverlapping() phòng trường hợp site nhiều phim khiến
        // lần chạy trước còn chưa xong.
        $schedule->command('movie:sitemap:generate')
            ->dailyAt('03:00')
            ->withoutOverlapping();
    }
}