{{--
    Nút xuất CSV cho trang danh sách tập phim bị báo lỗi.

    Thay cho enableExportButtons() của Backpack (tính năng trả phí từ v5). Đăng ký ở
    EpisodeCrudController::setupListOperation() qua
    CRUD::addButtonFromView('top', 'episode_export', 'episode_export', 'end').
--}}
@if ($crud->hasAccess('list'))
    <a href="{{ url($crud->route . '/export-csv') }}" class="btn btn-sm btn-secondary">
        <i class="la la-download"></i> Xuất CSV
    </a>
@endif
