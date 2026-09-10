{{-- Trang sửa gộp toàn bộ setting của một nhóm. Dựng theo khuôn crud::edit nhưng
     action trỏ tới setting/group/{group} thay vì setting/{id}, vì form này ghi
     nhiều bản ghi một lúc chứ không phải một entry như CRUD thường. --}}
@extends(backpack_view('blank'))

@php
    $breadcrumbs = [
        trans('backpack::crud.admin') => backpack_url('dashboard'),
        'Cài đặt'                     => backpack_url('setting'),
        $title                        => false,
    ];
@endphp

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none">
        <h1 class="mb-0">{{ $title }}</h1>
        <p class="ms-2 ml-2 mb-0">{{ count($crud->fields()) }} mục cài đặt</p>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-9 bold-labels">

            @include('crud::inc.grouped_errors')

            <form method="post" action="{{ backpack_url('setting/group/' . $group) }}">
                {!! csrf_field() !!}
                {!! method_field('PUT') !!}

                {{-- crud::form_content lo phần render field + nạp JS/CSS của từng loại
                     field (ckfinder, code, switch...). Nó cũng tự bật tab khi field có
                     khoá 'tab' — nhóm metas dùng đúng cơ chế đó. --}}
                @include('crud::form_content', ['fields' => $crud->fields(), 'action' => 'edit'])

                <div class="form-group my-3">
                    <button type="submit" class="btn btn-success text-white">
                        <span class="la la-save" role="presentation" aria-hidden="true"></span> &nbsp;Lưu
                    </button>
                    <a href="{{ backpack_url('dashboard') }}" class="btn btn-secondary text-decoration-none">
                        <span class="la la-ban" role="presentation" aria-hidden="true"></span> &nbsp;{{ trans('backpack::crud.cancel') }}
                    </a>
                </div>
            </form>

        </div>
    </div>
@endsection
