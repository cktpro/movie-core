<?php

namespace Movie\Core\Controllers\Admin;

use Movie\Core\Requests\MovieRequest;
use Movie\Core\Controllers\Admin\BaseCrudController as CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Movie\Core\Models\Actor;
use Movie\Core\Models\Director;
use Movie\Core\Models\Movie;
use Movie\Core\Models\Region;
use Movie\Core\Models\Studio;
use Movie\Core\Models\Category;
use Movie\Core\Models\Tag;

/**
 * Class MovieCrudController
 * @package Movie\Core\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class MovieCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation {
        store as backpackStore;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation {
        update as backpackUpdate;
    }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation {
        destroy as traitDestroy;
    }
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
        CRUD::setModel(\Movie\Core\Models\Movie::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/movie');
        CRUD::setEntityNameStrings('movie', 'movies');
        CRUD::setCreateView('movie::movies.create',);
        CRUD::setUpdateView('movie::movies.edit',);
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        $this->authorize('browse', Movie::class);

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number','tab'=>'Thông tin phim']);
         */

        // Bộ lọc thủ công — thay cho API addFilter() của Backpack (từ v5 addFilter() chỉ
        // chạy khi có package trả phí backpack/pro; bản fork hacoidev/crud dùng trước khi
        // nâng Laravel 12 là Backpack 4.1, hồi đó filter còn miễn phí).
        //
        // Cách hoạt động: form GET thường (view movie::crud.buttons.movie_filters) nạp lại
        // trang kèm query string. Không cần JS đồng bộ URL vì DataTables của Backpack v7 tự
        // ghép query string hiện tại vào URL ajax — xem crud/components/datatable/
        // datatable_logic.blade.php: `config.urlStart + '/search' + searchParams`. Nhờ vậy
        // bộ lọc vẫn đúng khi phân trang / sắp xếp / tìm kiếm.
        $this->applyMovieFilters();

        CRUD::addButtonFromView('top', 'movie_filters', 'movie_filters', 'beginning');

        CRUD::addButtonFromModelFunction('line', 'open_view', 'openView', 'beginning');

        CRUD::addColumn([
            'name' => 'name',
            'origin_name' => 'origin_name',
            'publish_year' => 'publish_year',
            'status' => 'status',
            'movie_type' => 'type',
            'episode_current' => 'episode_current',
            'label' => 'Thông tin',
            'type' => 'view',
            'view' => 'movie::movies.columns.column_movie_info',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->where('name', 'like', '%' . $searchTerm . '%')->orWhere('origin_name', 'like', '%' . $searchTerm . '%');
                // $query->whereRaw("MATCH(name, origin_name) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);
            }
        ]);

        CRUD::addColumn([
            'name' => 'thumb_url', 'label' => 'Ảnh thumb', 'type' => 'image',
            'height' => '100px',
            'width'  => '68px',
        ]);
        CRUD::addColumn(['name' => 'categories', 'label' => 'Thể loại', 'type' => 'relationship',]);
        CRUD::addColumn(['name' => 'regions', 'label' => 'Khu vực', 'type' => 'relationship',]);
        CRUD::addColumn(['name' => 'updated_at', 'label' => 'Cập nhật lúc', 'type' => 'datetime', 'format' => 'DD/MM/YYYY HH:mm:ss']);
        // CRUD::addColumn(['name' => 'user_name', 'label' => 'Cập nhật bởi', 'type' => 'text',]);
        CRUD::addColumn(['name' => 'view_total', 'label' => 'Lượt xem', 'type' => 'number',]);
    }

    /**
     * Các lựa chọn của bộ lọc thủ công. Dùng chung cho việc dựng form (view) và
     * kiểm tra giá trị hợp lệ (controller) để hai bên không lệch nhau.
     */
    public static function movieFilterOptions()
    {
        return [
            'status' => [
                'label'   => 'Tình trạng',
                'options' => [
                    'trailer'   => 'Sắp chiếu',
                    'ongoing'   => 'Đang chiếu',
                    'completed' => 'Hoàn thành',
                ],
            ],
            'type' => [
                'label'   => 'Định dạng',
                'options' => [
                    'single' => 'Phim lẻ',
                    'series' => 'Phim bộ',
                ],
            ],
            'category_id' => [
                'label'   => 'Thể loại',
                'options' => Category::orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            'region_id' => [
                'label'   => 'Quốc gia',
                'options' => Region::orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            'other' => [
                'label'   => 'Thông tin',
                'options' => [
                    'thumb_url-'          => 'Thiếu ảnh thumb',
                    'poster_url-'         => 'Thiếu ảnh poster',
                    'trailer_url-'        => 'Thiếu trailer',
                    'language-vietsub'    => 'Vietsub',
                    'language-thuyết minh' => 'Thuyết minh',
                    'language-lồng tiếng'  => 'Lồng tiếng',
                ],
            ],
        ];
    }

    /**
     * Đọc query string và gắn điều kiện vào query của trang danh sách.
     *
     * Chạy trong setupListOperation() nên áp dụng cho cả lần nạp trang lẫn request
     * ajax /search của DataTables (request ajax mang theo cùng query string).
     */
    protected function applyMovieFilters()
    {
        $request = request();

        if ($val = $request->query('status')) {
            $this->crud->addClause('where', 'status', $val);
        }

        if ($val = $request->query('type')) {
            $this->crud->addClause('where', 'type', $val);
        }

        if ($val = $request->query('category_id')) {
            $this->crud->addClause('whereHas', 'categories', function ($query) use ($val) {
                $query->where('id', $val);
            });
        }

        if ($val = $request->query('region_id')) {
            $this->crud->addClause('whereHas', 'regions', function ($query) use ($val) {
                $query->where('id', $val);
            });
        }

        if ($val = $request->query('other')) {
            [$field, $sub] = array_pad(explode('-', $val, 2), 2, '');

            if ($field === 'language') {
                $this->crud->addClause('where', 'language', 'like', '%' . $sub . '%');
            } elseif (in_array($field, ['thumb_url', 'poster_url', 'trailer_url'], true)) {
                // Bọc trong closure để nhóm các orWhere lại. Bản cũ nối orWhere thẳng vào
                // query gốc nên khi kết hợp với bộ lọc khác, các điều kiện kia bị OR ra
                // ngoài và trả về sai kết quả.
                //
                // $field cũng được kiểm tra qua danh sách trắng: bản cũ lấy thẳng tên cột
                // từ query string đưa vào where(), tức người dùng chỉ định được cột tuỳ ý.
                $this->crud->addClause('where', function ($query) use ($field) {
                    $query->where($field, '')
                        ->orWhereNull($field)
                        ->orWhere($field, 'like', '%img.ophim%')
                        ->orWhere($field, 'like', '%img.hiephanhthienha%');
                });
            }
        }

        if ($request->query('is_recommended')) {
            $this->crud->addClause('where', 'is_recommended', true);
        }

        if ($request->query('is_shown_in_theater')) {
            $this->crud->addClause('where', 'is_shown_in_theater', true);
        }
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        $this->authorize('create', Movie::class);

        CRUD::setValidation(MovieRequest::class);

        /**
         * Fields can be defined using the fluent syntax or array syntax:
         * - CRUD::field('price')->type('number');
         * - CRUD::addField(['name' => 'price', 'type' => 'number']));
         */

        CRUD::addField(['name' => 'name', 'label' => 'Tên phim', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-6'
        ], 'attributes' => ['placeholder' => 'Tên'], 'tab' => 'Thông tin phim']);
        CRUD::addField(['name' => 'origin_name', 'label' => 'Tên gốc', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-6'
        ], 'tab' => 'Thông tin phim']);
        CRUD::addField(['name' => 'slug', 'label' => 'Đường dẫn tĩnh', 'type' => 'text', 'tab' => 'Thông tin phim']);
        CRUD::addField([
            'name' => 'thumb_url', 'label' => 'Ảnh Thumb', 'type' => 'ckfinder', 'preview' => ['width' => 'auto', 'height' => '340px'], 'tab' => 'Thông tin phim'
        ]);
        CRUD::addField(['name' => 'poster_url', 'label' => 'Ảnh Poster', 'type' => 'ckfinder', 'preview' => ['width' => 'auto', 'height' => '340px'], 'tab' => 'Thông tin phim']);

        CRUD::addField(['name' => 'content', 'label' => 'Nội dung', 'type' => 'summernote', 'tab' => 'Thông tin phim']);
        CRUD::addField(['name' => 'notify', 'label' => 'Thông báo / ghi chú', 'type' => 'text', 'attributes' => ['placeholder' => 'Tuần này hoãn chiếu'], 'tab' => 'Thông tin phim']);

        CRUD::addField(['name' => 'showtimes', 'label' => 'Lịch chiếu phim', 'type' => 'text', 'attributes' => ['placeholder' => '21h tối hàng ngày'], 'tab' => 'Thông tin phim']);
        CRUD::addField(['name' => 'trailer_url', 'label' => 'Trailer Youtube URL', 'type' => 'text', 'tab' => 'Thông tin phim']);

        CRUD::addField(['name' => 'episode_time', 'label' => 'Thời lượng tập phim', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-4'
        ], 'attributes' => ['placeholder' => '45 phút'], 'tab' => 'Thông tin phim']);
        CRUD::addField(['name' => 'episode_current', 'label' => 'Tập phim hiện tại', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-4'
        ], 'attributes' => ['placeholder' => '5'], 'tab' => 'Thông tin phim']);

        CRUD::addField(['name' => 'episode_total', 'label' => 'Tổng số tập phim', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-4'
        ], 'attributes' => ['placeholder' => '12'], 'tab' => 'Thông tin phim']);

        CRUD::addField(['name' => 'language', 'label' => 'Ngôn ngữ', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-4'
        ], 'attributes' => ['placeholder' => 'Tiếng Việt'], 'tab' => 'Thông tin phim']);

        CRUD::addField(['name' => 'quality', 'label' => 'Chất lượng', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-4'
        ], 'tab' => 'Thông tin phim']);

        CRUD::addField(['name' => 'publish_year', 'label' => 'Năm xuất bản', 'type' => 'text', 'wrapperAttributes' => [
            'class' => 'form-group col-md-4'
        ], 'tab' => 'Thông tin phim']);

        CRUD::addField(['name' => 'type', 'label' => 'Định dạng', 'type' => 'radio', 'options' => ['single' => 'Phim lẻ', 'series' => 'Phim bộ'], 'tab' => 'Phân loại']);
        CRUD::addField(['name' => 'status', 'label' => 'Tình trạng', 'type' => 'radio', 'options' => ['trailer' => 'Sắp chiếu', 'ongoing' => 'Đang chiếu', 'completed' => 'Hoàn thành'], 'tab' => 'Phân loại']);
        CRUD::addField(['name' => 'categories', 'label' => 'Thể loại', 'type' => 'checklist', 'tab' => 'Phân loại']);
        CRUD::addField(['name' => 'regions', 'label' => 'Khu vực', 'type' => 'checklist', 'tab' => 'Phân loại']);
        CRUD::addField(['name' => 'directors', 'label' => 'Đạo diễn', 'type' => 'select2_relationship_tags', 'tab' => 'Phân loại']);
        CRUD::addField(['name' => 'actors', 'label' => 'Diễn viên',  'type' => 'select2_relationship_tags', 'tab' => 'Phân loại']);
        CRUD::addField(['name' => 'tags', 'label' => 'Tags',  'type' => 'select2_relationship_tags', 'tab' => 'Phân loại']);
        CRUD::addField(['name' => 'studios', 'label' => 'Studios',  'type' => 'select2_relationship_tags', 'tab' => 'Phân loại']);

        CRUD::addField([
            'name' => 'episodes',
            'type' => 'view',
            'view' => 'movie::movies.inc.episode',
            'tab' => 'Danh sách tập phim'
        ],);

        CRUD::addField(['name' => 'update_handler', 'label' => 'Trình cập nhật', 'type' => 'select_from_array', 'options' => collect(config('movie.updaters', []))->pluck('name', 'handler')->toArray(), 'tab' => 'Cập nhật']);
        CRUD::addField(['name' => 'update_identity', 'label' => 'ID cập nhật', 'type' => 'text', 'tab' => 'Cập nhật']);

        CRUD::addField(['name' => 'is_shown_in_theater', 'label' => 'Phim chiếu rạp', 'type' => 'boolean', 'tab' => 'Khác']);
        CRUD::addField(['name' => 'is_copyright', 'label' => 'Có bản quyền phim', 'type' => 'boolean', 'tab' => 'Khác']);
        CRUD::addField(['name' => 'is_sensitive_content', 'label' => 'Cảnh báo nội dung người lớn', 'type' => 'boolean', 'tab' => 'Khác']);
        CRUD::addField(['name' => 'is_recommended', 'label' => 'Đề cử', 'type' => 'boolean', 'tab' => 'Khác']);
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
        CRUD::addField(['name' => 'timestamps', 'label' => 'Cập nhật thời gian', 'type' => 'checkbox', 'tab' => 'Cập nhật']);
    }

    public function store(Request $request)
    {
        $this->getTaxonamies($request);

        return $this->backpackStore();
    }

    public function update(Request $request)
    {
        $this->getTaxonamies($request);

        return $this->backpackUpdate();
    }

    protected function getTaxonamies(Request $request)
    {
        $actors = request('actors', []);
        $directors = request('directors', []);
        $tags = request('tags', []);
        $studios = request('studios', []);

        $actor_ids = [];
        foreach ($actors as $actor) {
            $actor_ids[] = Actor::firstOrCreate([
                'name_md5' => md5($actor)
            ], [
                'name' => $actor
            ])->id;
        }

        $director_ids = [];
        foreach ($directors as $director) {
            $director_ids[] = Director::firstOrCreate([
                'name_md5' => md5($director)
            ], [
                'name' => $director
            ])->id;
        }

        $tag_ids = [];
        foreach ($tags as $tag) {
            $tag_ids[] = Tag::firstOrCreate([
                'name_md5' => md5($tag)
            ], [
                'name' => $tag
            ])->id;
        }

        $studio_ids = [];
        foreach ($studios as $studio) {
            $studio_ids[] = Studio::firstOrCreate([
                'name_md5' => md5($studio)
            ], [
                'name' => $studio
            ])->id;
        }

        $request['actors'] = $actor_ids;
        $request['directors'] = $director_ids;
        $request['tags'] = $tag_ids;
        $request['studios'] = $studio_ids;
    }

    // protected function setupDeleteOperation()
    // {
    //     $this->authorize('delete', $this->crud->getEntryWithLocale($this->crud->getCurrentEntryId()));
    // }

    public function deleteImage($movie)
    {
        // Delete images
        if ($movie->thumb_url && !filter_var($movie->thumb_url, FILTER_VALIDATE_URL) && file_exists(public_path($movie->thumb_url))) {
            unlink(public_path($movie->thumb_url));
        }
        if ($movie->poster_url && !filter_var($movie->poster_url, FILTER_VALIDATE_URL) && file_exists(public_path($movie->poster_url))) {
            unlink(public_path($movie->poster_url));
        }
        return true;
    }

    public function destroy($id)
    {
        $this->crud->hasAccessOrFail('delete');
        $movie = Movie::find($id);

        $this->deleteImage($movie);

        // get entry ID from Request (makes sure its the last ID for nested resources)
        $id = $this->crud->getCurrentEntryId() ?? $id;

        $res = $this->crud->delete($id);
        if ($res) {
        }
        return $res;
    }

    public function bulkDelete()
    {
        $this->crud->hasAccessOrFail('bulkDelete');
        $entries = request()->input('entries', []);
        $deletedEntries = [];

        foreach ($entries as $key => $id) {
            if ($entry = $this->crud->model->find($id)) {
                $this->deleteImage($entry);
                $deletedEntries[] = $entry->delete();
            }
        }

        return $deletedEntries;
    }
}
