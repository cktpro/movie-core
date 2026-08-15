{{--
    Bộ lọc thủ công cho trang danh sách phim.

    Thay cho addFilter() của Backpack (chỉ chạy khi có backpack/pro từ v5). Đây là form
    GET thường: submit sẽ nạp lại trang kèm query string, và DataTables của Backpack v7
    tự ghép query string đó vào URL ajax /search nên bộ lọc giữ nguyên khi phân trang.

    Đăng ký ở MovieCrudController::setupListOperation() qua
    CRUD::addButtonFromView('top', 'movie_filters', 'movie_filters', 'beginning').
--}}
@php
    $filters   = \Movie\Core\Controllers\Admin\MovieCrudController::movieFilterOptions();
    $daLoc     = collect(['status', 'type', 'category_id', 'region_id', 'other', 'is_recommended', 'is_shown_in_theater'])
                    ->contains(fn ($k) => request()->query($k));
@endphp

<div class="w-100 mb-2">
    <form method="GET" action="{{ url($crud->route) }}" class="d-flex flex-wrap align-items-center"
          style="gap:.5rem;">

        @foreach ($filters as $ten => $cauHinh)
            <select name="{{ $ten }}" class="form-control form-control-sm" style="width:auto;min-width:150px;"
                    onchange="this.form.submit()">
                <option value="">{{ $cauHinh['label'] }}: tất cả</option>
                @foreach ($cauHinh['options'] as $giaTri => $nhan)
                    <option value="{{ $giaTri }}" @if ((string) request()->query($ten) === (string) $giaTri) selected @endif>
                        {{ $nhan }}
                    </option>
                @endforeach
            </select>
        @endforeach

        <label class="mb-0 d-flex align-items-center" style="gap:.25rem;">
            <input type="checkbox" name="is_recommended" value="1"
                   @if (request()->query('is_recommended')) checked @endif
                   onchange="this.form.submit()">
            <span>Đề cử</span>
        </label>

        <label class="mb-0 d-flex align-items-center" style="gap:.25rem;">
            <input type="checkbox" name="is_shown_in_theater" value="1"
                   @if (request()->query('is_shown_in_theater')) checked @endif
                   onchange="this.form.submit()">
            <span>Chiếu rạp</span>
        </label>

        <button type="submit" class="btn btn-sm btn-primary">
            <i class="la la-filter"></i> Lọc
        </button>

        @if ($daLoc)
            <a href="{{ url($crud->route) }}" class="btn btn-sm btn-secondary">
                <i class="la la-eraser"></i> Bỏ lọc
            </a>
        @endif
    </form>
</div>
