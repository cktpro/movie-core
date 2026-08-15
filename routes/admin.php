<?php

use Illuminate\Support\Facades\Route;
use CKSource\CKFinderBridge\Controller\CKFinderController;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\Base.
// Routes you generate using Backpack\Generators will be placed here.

Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace'  => 'Movie\Core\Controllers\Admin',
], function () {
    if (config('backpack.base.setup_dashboard_routes')) {
        Route::get('dashboard', 'AdminController@dashboard')->name('backpack.dashboard');
        Route::get('/', 'AdminController@redirect')->name('backpack');
    }

    Route::crud('catalog', 'CatalogCrudController');
    Route::crud('category', 'CategoryCrudController');
    Route::crud('region', 'RegionCrudController');
    Route::crud('movie', 'MovieCrudController');
    Route::crud('actor', 'ActorCrudController');
    Route::crud('director', 'DirectorCrudController');
    Route::crud('studio', 'StudioCrudController');
    Route::crud('tag', 'TagCrudController');
    Route::crud('menu', 'MenuCrudController');
    // Phải khai báo trước Route::crud('episode', ...) để không bị nuốt bởi các route
    // có tham số của nó. Thay cho enableExportButtons() của Backpack (chỉ chạy khi có
    // package trả phí backpack/pro từ v5).
    Route::get('episode/export-csv', 'EpisodeCrudController@exportCsv')->name('episode.exportCsv');
    Route::crud('episode', 'EpisodeCrudController');
    Route::crud('theme', 'ThemeManagementController');
    Route::crud('plugin', 'PluginController');
    Route::crud('sitemap', 'SiteMapController');
    Route::get('quick-action/delete-cache', 'QuickActionController@delete_cache');
});

Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        [
            \Movie\Core\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class
        ],
        (array) config('backpack.base.middleware_key', 'admin')
    ),
], function () {
    Route::prefix('/ckfinder')->group(function () {
        Route::any('/connector', [CKFinderController::class, 'requestAction'])->name('ckfinder_connector');
        Route::any('/browser', [CKFinderController::class, 'browserAction'])->name('ckfinder_browser');
    });
});
