<?php

namespace Movie\Core\Controllers\Admin;

use Movie\Core\Controllers\Admin\BaseCrudController as CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Movie\Core\Services\SitemapGenerator;
use Prologue\Alerts\Facades\Alert;

class SiteMapController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setRoute(config('backpack.base.route_prefix') . '/sitemap');
        CRUD::setEntityNameStrings('site map', 'site map');
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupCreateOperation()
    {
        // CRUD này không gắn với model nào (setup() chỉ setRoute + setEntityNameStrings),
        // nên phải khai báo 'entity' => false. Backpack v7 gọi makeSureFieldHasEntity()
        // trong addField(): nếu 'entity' chưa được set, nó tự đoán quan hệ bằng cách gọi
        // $model->isRelation(...) trên getModel() — mà getModel() ở đây trả về chuỗi tên
        // model mặc định, gây "Call to a member function isRelation() on string" (500).
        // Backpack 4.1 (bản fork hacoidev/crud dùng trước khi nâng Laravel 12) không có
        // bước đoán này nên không cần khai báo.
        CRUD::addField([
            'name'   => 'sitemap',
            'type'   => 'custom_html',
            'entity' => false,
            'value'  => 'Sitemap sẽ được lưu tại đường dẫn: <i>' . url('/sitemap.xml') . '</i>',
        ]);
        $this->crud->addSaveAction([
            'name' => 'save_and_new',
            'redirect' => function ($crud, $request, $itemId) {
                return $crud->route;
            },
            'button_text' => 'Tạo sitemap',
        ]);

        $this->crud->setOperationSetting('showSaveActionChange', false);
    }

    /**
     * Nút "Tạo sitemap" trong admin.
     *
     * Toàn bộ logic nằm ở SitemapGenerator để lệnh chạy theo lịch
     * (`movie:sitemap:generate`) cho ra đúng cùng một file.
     */
    public function store(Request $request, SitemapGenerator $generator)
    {
        $stats = $generator->generate();

        Alert::success(sprintf(
            'Đã tạo sitemap tại /sitemap.xml — %d URL (%d phim, %d thể loại, %d quốc gia, %d trang).',
            $stats['total'],
            $stats['movies'],
            $stats['categories'],
            $stats['regions'],
            $stats['pages']
        ))->flash();

        if ($stats['over_limit']) {
            Alert::warning(sprintf(
                'Sitemap có %d URL, vượt giới hạn %d URL/file của chuẩn sitemap — Google sẽ bỏ qua phần dư.',
                $stats['total'],
                SitemapGenerator::MAX_URLS
            ))->flash();
        }

        return back();
    }
}
