<?php

namespace Movie\Core\Controllers\Admin;

use Movie\Core\Requests\MenuRequest;
use Movie\Core\Controllers\Admin\BaseCrudController as CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Movie\Core\Models\Menu;

/**
 * Class MenuCrudController
 * @package Movie\CoreHttp\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class MenuCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ReorderOperation;

    use \Movie\Core\Traits\Operations\BulkDeleteOperation {
        bulkDelete as traitBulkDelete;
    }

    public function setup()
    {
        $this->crud->setModel(Menu::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/menu');
        $this->crud->setEntityNameStrings('menu item', 'menu items');
        $this->crud->enableReorder('name', 2);
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        $this->authorize('browse', Menu::class);

        $this->crud->addColumn([
            'name' => 'name',
            'label' => 'Label',
        ]);
        $this->crud->addColumn([
            'label' => 'Parent',
            'type' => 'select',
            'name' => 'parent_id',
            'entity' => 'parent',
            'attribute' => 'name',
            'model' => Menu::class,
        ]);
        $this->crud->addColumn([
            'name' => 'link',
            'label' => 'Link',
            'type' => 'url',
        ]);
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        $this->authorize('create', Menu::class);

        $this->crud->addField([
            'name' => 'name',
            'label' => 'Label',
        ]);
        $this->crud->addField([
            'label' => 'Parent',
            'type' => 'select',
            'name' => 'parent_id',
            'entity' => 'parent',
            'attribute' => 'name',
            Menu::class
        ]);

        $this->crud->addField([
            // backpack/crud v7 không cho field 'name' là mảng nữa (CrudField::__construct
            // abort 500 thẳng) -> field ghép "type" + "link" giờ khai 1 tên chính (name)
            // + 1 tên phụ (link_name), xem resources/views/core/base/fields/page_or_link.blade.php
            'name' => 'type',
            'link_name' => 'link',
            'label' => 'Type',
            'type' => 'page_or_link',
        ]);
    }

        /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->authorize('update', $this->crud->getEntryWithLocale($this->crud->getCurrentEntryId()));


        $this->setupCreateOperation();
    }

    protected function setupDeleteOperation()
    {
        $this->authorize('delete', $this->crud->getEntryWithLocale($this->crud->getCurrentEntryId()));
    }

    public function bulkDelete()
    {
        $this->crud->hasAccessOrFail('bulkDelete');
        $entries = request()->input('entries', []);
        $deletedEntries = [];
        foreach ($entries as $key => $id) {
            if ($entry = $this->crud->model->find($id)) {
                $deletedEntries[] = $entry->delete();
            }
        }

        return $deletedEntries;
    }
}
