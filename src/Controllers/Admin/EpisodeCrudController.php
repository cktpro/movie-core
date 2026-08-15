<?php

namespace Movie\Core\Controllers\Admin;

use Movie\Core\Controllers\Admin\BaseCrudController as CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Movie\Core\Models\Episode;

/**
 * Class EpisodeCrudController
 * @package Movie\Core\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class EpisodeCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation {
        store as backpackStore;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation {
        update as backpackUpdate;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    use \Movie\Core\Traits\Operations\BulkDeleteOperation {
        bulkDelete as traitBulkDelete;
    }

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\Movie\Core\Models\Episode::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/episode');
        CRUD::setEntityNameStrings('Episode', 'episodes');
        $this->crud->addButtonFromModelFunction('line', 'open_episode', 'openEpisode', 'beginning');
        $this->crud->denyAccess('create');
        $this->crud->denyAccess('delete');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        $this->authorize('browse', Episode::class);

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number','tab'=>'Thông tin phim']);
         */
        // Nút xuất CSV thủ công, thay cho enableExportButtons() của Backpack (từ v5 chỉ
        // chạy khi có package trả phí backpack/pro; bản fork hacoidev/crud dùng trước khi
        // nâng Laravel 12 là Backpack 4.1, hồi đó tính năng này còn miễn phí).
        CRUD::addButtonFromView('top', 'episode_export', 'episode_export', 'end');

        $this->crud->addClause('where', 'has_report', true);

        CRUD::addColumn([
            'name' => 'movie', 'label' => 'Phim', 'type' => 'relationship',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('movie', function ($movie) use ($searchTerm) {
                    $movie->where('name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('origin_name', 'like', '%' . $searchTerm . '%');
                });
            }
        ]);
        CRUD::addColumn(['name' => 'name', 'label' => 'Tập', 'type' => 'text']);
        CRUD::addColumn(['name' => 'type', 'label' => 'Type', 'type' => 'text']);
        CRUD::addColumn(['name' => 'link', 'label' => 'Link', 'type' => 'textarea']);
    }

    /**
     * Xuất danh sách tập phim bị báo lỗi ra CSV.
     *
     * Thay cho enableExportButtons() của Backpack (tính năng trả phí từ v5). Dùng
     * streamed response + chunk để không nạp hết bảng episodes vào bộ nhớ.
     */
    public function exportCsv()
    {
        $this->authorize('browse', Episode::class);

        $ten = 'tap-phim-bao-loi-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 để Excel không hiển thị tiếng Việt thành ký tự lạ.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['ID', 'Phim', 'Tập', 'Type', 'Link', 'Nội dung báo lỗi', 'Cập nhật lúc']);

            Episode::with('movie')
                ->where('has_report', true)
                ->orderBy('id')
                ->chunk(500, function ($episodes) use ($out) {
                    foreach ($episodes as $episode) {
                        fputcsv($out, [
                            $episode->id,
                            optional($episode->movie)->name,
                            $episode->name,
                            $episode->type,
                            $episode->link,
                            $episode->report_message,
                            optional($episode->updated_at)->format('d/m/Y H:i:s'),
                        ]);
                    }
                });

            fclose($out);
        }, $ten, [
            'Content-Type' => 'text/csv; charset=UTF-8',
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
        abort(404);
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


        CRUD::addField(['name' => 'type', 'label' => 'Type', 'type' => 'select_from_array', 'options' => config('movie.episodes.types')]);
        CRUD::addField(['name' => 'link', 'label' => 'Nguồn phát', 'type' => 'url']);
        CRUD::addField(['name' => 'has_report', 'label' => 'Đánh dấu đang lỗi', 'type' => 'checkbox']);
        CRUD::addField(['name' => 'report_message', 'label' => 'Report message', 'type' => 'textarea']);
    }

    public function bulkDelete()
    {
        $this->crud->hasAccessOrFail('bulkDelete');
        $entries = request()->input('entries', []);
        $deletedEntries = [];

        foreach ($entries as $key => $id) {
            if ($entry = $this->crud->model->find($id)) {
                $deletedEntries[] = $entry->update(['has_report' => 0, 'report_message' => '']);
            }
        }

        return $deletedEntries;
    }
}
