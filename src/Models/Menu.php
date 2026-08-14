<?php

namespace Ophim\Core\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Ophim\Core\Traits\HasFactory;

class Menu extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'menus';
    protected $fillable = ['name', 'type', 'link', 'parent_id', 'lft', 'rgt'];

    public function parent()
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * Get all menu items, in a hierarchical collection.
     * Only supports 2 levels of indentation.
     */
    public static function getTree()
    {
        $menu = Cache::remember('ophim_menus_all', setting('site_cache_ttl', 5 * 60), function () {
            return self::all();
        })->sortBy('lft');

        if ($menu->count()) {
            foreach ($menu as $k => $menu_item) {
                $menu_item->children = collect([]);

                foreach ($menu as $i => $menu_subitem) {
                    if ($menu_subitem->parent_id == $menu_item->id) {
                        $menu_item->children->push($menu_subitem);

                        // remove the subitem for the first level
                        $menu = $menu->reject(function ($item) use ($menu_subitem) {
                            return $item->id == $menu_subitem->id;
                        });
                    }
                }
            }
        }

        return $menu;
    }

    public function url()
    {
        switch ($this->type) {
            case 'external_link':
                return $this->link;
                break;

            case 'internal_link':
                return is_null($this->link) ? '#' : url($this->link);
                break;

            default: //page_link
                if ($this->page) {
                    return url($this->page->slug);
                }
                break;
        }
    }
}
