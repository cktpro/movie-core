{{--
    Field "code" — bản thay thế cho field cùng tên của backpack/pro.

    Bộ option của các theme (movie-hhtq, movie-ripple) và vài bản ghi settings khai báo
    'type' => 'code' cho những ô nhập nhiều dòng: cấu hình block trang chủ, CSS/JS chèn
    thêm, HTML footer. Backpack bản free KHÔNG có view field này (nó nằm trong addon
    backpack/pro có phí), nên thiếu file này là trang /admin/theme/{id}/edit chết 500 với
    "Cannot find the field view: code".

    Ở đây chỉ cần một textarea không bẻ dòng, chữ monospace — đủ để sửa code tử tế mà
    không kéo theo thư viện soạn thảo nào. Đăng ký namespace 'movie::base.fields' nằm ở
    MovieServiceProvider::mergeBackpackConfigs().
--}}
@php
    // Đặt mặc định rồi để khai báo của field ghi đè, nhờ vậy 'attributes' => ['rows' => 5]
    // trong option theme vẫn có tác dụng.
    $field['attributes'] = array_merge([
        'rows' => 10,
        'spellcheck' => 'false',
        'autocomplete' => 'off',
        'autocorrect' => 'off',
        'autocapitalize' => 'off',
        'wrap' => 'off',
        'style' => 'font-family: Menlo, Monaco, Consolas, \'Liberation Mono\', \'Courier New\', monospace; font-size: 13px; line-height: 1.5; tab-size: 4;',
    ], $field['attributes'] ?? []);
@endphp

@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    @include('crud::fields.inc.translatable_icon')

    <textarea
        name="{{ $field['name'] }}"
        @include('crud::fields.inc.attributes')
        >{{ old_empty_or_null($field['name'], '') ?? $field['value'] ?? $field['default'] ?? '' }}</textarea>

    {{-- HINT --}}
    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')

{{-- Phím Tab chèn dấu tab thay vì nhảy sang ô kế tiếp --}}
@push('crud_fields_scripts')
    @bassetBlock('movie/fields/code.js')
    <script>
        (function () {
            document.querySelectorAll('textarea[name="{{ $field['name'] }}"]').forEach(function (el) {
                if (el.dataset.codeTabBound) return;
                el.dataset.codeTabBound = '1';
                el.addEventListener('keydown', function (e) {
                    if (e.key !== 'Tab' || e.shiftKey) return;
                    e.preventDefault();
                    var s = this.selectionStart, en = this.selectionEnd;
                    this.value = this.value.substring(0, s) + '    ' + this.value.substring(en);
                    this.selectionStart = this.selectionEnd = s + 4;
                });
            });
        })();
    </script>
    @endBassetBlock
@endpush
