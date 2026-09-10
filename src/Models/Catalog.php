<?php

namespace Movie\Core\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\Settings\app\Models\Setting;
use Movie\Core\Contracts\TaxonomyInterface;
use Illuminate\Database\Eloquent\Model;
use Movie\Core\Contracts\SeoInterface;
use Movie\Core\Traits\HasFactory;
use Movie\Core\Traits\HasTitle;
use Movie\Core\Traits\HasDescription;
use Movie\Core\Traits\HasKeywords;
use Movie\Core\Traits\Sluggable;
use Illuminate\Support\Str;
use Artesaos\SEOTools\Facades\JsonLdMulti;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\TwitterCard;

class Catalog extends Model implements TaxonomyInterface, SeoInterface
{
    use CrudTrait;
    use Sluggable;
    use HasFactory;
    use HasTitle;
    use HasDescription;
    use HasKeywords;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'catalogs';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public static function primaryCacheKey(): string
    {
        $site_routes = setting('site_routes_types', '/danh-sach/{type}');
        if (strpos($site_routes, '{type}')) return 'slug';
        if (strpos($site_routes, '{id}')) return 'id';
        return 'slug';
    }

    public function getUrl()
    {
        $params = [];
        $site_routes = setting('site_routes_types', '/danh-sach/{type}');
        if (strpos($site_routes, '{type}')) $params['type'] = $this->slug;
        if (strpos($site_routes, '{id}')) $params['id'] = $this->id;
        return route('types.movies.index', $params);
    }

    protected function titlePattern(): string
    {
        return Setting::get('site_catalog_title', '');
    }

    protected function descriptionPattern(): string
    {
        return Setting::get('site_catalog_des', '');
    }

    protected function keywordsPattern(): string
    {
        return Setting::get('site_catalog_key', '');
    }

    public function generateSeoTags()
    {
        // Bỏ trống ô SEO của bản ghi thì rơi về mẫu {name} trong settings, giống
        // hệt Category/Region/Tag. Trước 10/09/2026 Catalog đọc thẳng 3 cột này và
        // không có fallback, nên chuỗi mẫu 'Title Phim bộ' mà CatalogsTableSeeder
        // ghi sẵn bị phơi nguyên si ra <title>, og:title, twitter:title lẫn JSON-LD.
        $seo_title = $this->seo_title ?: $this->getTitle();
        $seo_des = Str::limit($this->seo_des ?: $this->getDescription(), 150, '...');
        $seo_key = $this->seo_key ?: $this->getKeywords();
        $getUrl = $this->getUrl();
        $site_meta_siteName = setting('site_meta_siteName');

        SEOMeta::setTitle($seo_title, false)
            ->setDescription($seo_des)
            ->addKeyword([$seo_key])
            ->setCanonical($getUrl)
            ->setPrev(request()->root())
            ->setPrev(request()->root());

        OpenGraph::setSiteName($site_meta_siteName)
            ->setType('website')
            ->setTitle($seo_title, false)
            ->addProperty('locale', 'vi-VN')
            ->addProperty('url', $getUrl)
            ->setDescription($seo_des);

        TwitterCard::setSite($site_meta_siteName)
            ->setTitle($seo_title, false)
            ->setType('summary')
            ->setDescription($seo_des)
            ->setUrl($getUrl);

        JsonLdMulti::newJsonLd()
            ->setSite($site_meta_siteName)
            ->setTitle($seo_title, false)
            ->setType('WebPage')
            ->setDescription($seo_des)
            ->setUrl($getUrl);

        $breadcrumb = [];
        array_push($breadcrumb, [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Home',
            'item' => url('/')
        ]);
        array_push($breadcrumb, [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => $this->name,
            'item' => $getUrl
        ]);
        array_push($breadcrumb, [
            '@type' => 'ListItem',
            'position' => 3,
            'name' => "Trang " . (request()->get('page') ?: 1),
        ]);
        JsonLdMulti::newJsonLd()
            ->setType('BreadcrumbList')
            ->addValue('name', '')
            ->addValue('description', '')
            ->addValue('itemListElement', $breadcrumb);
    }

    public function openView($crud = false)
    {
        return '<a class="btn btn-sm btn-link" target="_blank" href="'.$this->getUrl().'" data-toggle="tooltip" title="View link"><i class="la la-link"></i> View</a>';
    }


    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
