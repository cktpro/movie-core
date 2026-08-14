{{-- PAGE OR LINK field --}}
{{-- Used in Backpack\MenuCRUD --}}

<?php
    // 'name' của field phải là string (backpack/crud v7 abort 500 nếu là mảng), nên
    // tên field "link" phụ được truyền riêng qua khoá 'link_name' (xem MenuCrudController).
    $field['allows_null'] = $field['allows_null'] ?? false;
    $nameType = $field['name'];
    $nameLink = $field['link_name'] ?? 'link';
    $field['options']['internal_link'] = $field['options']['internal_link'] ?? trans('backpack::crud.internal_link');
    $field['options']['external_link'] = $field['options']['external_link'] ?? trans('backpack::crud.external_link');
?>

@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    @include('crud::fields.inc.translatable_icon')

    <div class="row" data-init-function="bpFieldInitPageOrLinkElement">
        {{-- hidden placeholders for content --}}
        <input type="hidden" value="{{ $entry->{$nameLink} ?? '' }}" name="{{ $nameLink }}" />

        <div class="col-sm-3">
                {{-- type select --}}
            <select
                data-identifier="page_or_link_select"
                name="{!! $nameType !!}"
                @include('crud::fields.inc.attributes')
                >

                @if ($field['allows_null'])
                    <option value="">-</option>
                @endif

                @foreach ($field['options'] as $key => $value)
                    <option value="{{ $key }}"
                        @if (isset($entry) && $key === $entry->{$nameType})
                            selected
                        @endif
                    >{{ $value }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-9">
            {{-- internal link input --}}
            <div class="page_or_link_value internal_link {{ isset($entry) && $entry->{$nameType} === 'internal_link' ? '' : '' }}">
                <input
                    type="text"
                    class="form-control"
                    placeholder="{{ trans('backpack::crud.internal_link_placeholder', ['url', url(config('backpack.base.route_prefix').'/page')]) }}"
                    for="{{ $nameLink }}"
                    required

                    @if (isset($entry) && $entry->{$nameType} !== 'internal_link')
                        disabled="disabled"
                    @endif

                    @if (isset($entry) && $entry->{$nameType} === 'internal_link' && $entry->{$nameLink})
                        value="{{ $entry->{$nameLink} }}"
                    @endif
                    >
            </div>

            {{-- external link input --}}
            <div class="page_or_link_value external_link {{ isset($entry) && $entry->{$nameType} === 'external_link' ? '' : 'd-none' }}">
                <input
                    type="url"
                    class="form-control"
                    placeholder="{{ trans('backpack::crud.page_link_placeholder') }}"
                    for="{{ $nameLink }}"
                    required

                    @if (isset($entry) && $entry->{$nameType} !== 'external_link')
                        disabled="disabled"
                    @endif

                    @if (isset($entry) && $entry->{$nameType} === 'external_link' && $entry->{$nameLink})
                        value="{{ $entry->{$nameLink} }}"
                    @endif
                    >
            </div>
        </div>
    </div>

    {{-- HINT --}}
    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif

@include('crud::fields.inc.wrapper_end')


{{-- ########################################## --}}
{{-- Extra CSS and JS for this particular field --}}
{{-- If a field type is shown multiple times on a form, the CSS and JS will only be loaded once --}}
@if ($crud->fieldTypeNotLoaded($field))
    @php
        $crud->markFieldTypeAsLoaded($field);
    @endphp

    {{-- FIELD JS - will be loaded in the after_scripts section --}}
    @push('crud_fields_scripts')
    <script>
        function bpFieldInitPageOrLinkElement(element) {
            element = element[0]; // jQuery > Vanilla
            const select = element.querySelector('select[data-identifier=page_or_link_select]');
            const values = element.querySelectorAll('.page_or_link_value');
            // updates hidden fields
            const updateHidden = () => {
                let selectedInput = select.value && element.querySelector(`.${select.value}`).firstElementChild;
                element.querySelectorAll(`input[type="hidden"]`).forEach(hidden => {
                    hidden.value = selectedInput && hidden.getAttribute('name') === selectedInput.getAttribute('for') ? selectedInput.value : '';
                });
            }
            // save input changes to hidden placeholders
            values.forEach(value => value.firstElementChild.addEventListener('input', updateHidden));
            // main select change
            select.addEventListener('change', () => {
                values.forEach(value => {
                    let isSelected = value.classList.contains(select.value);
                    // toggle visibility and disabled
                    value.classList.toggle('d-none', !isSelected);
                    value.firstElementChild.toggleAttribute('disabled', !isSelected);
                });
                // updates hidden fields
                updateHidden();
            });
        }
    </script>
    @endpush

@endif
{{-- End of Extra CSS and JS --}}
{{-- ########################################## --}}
