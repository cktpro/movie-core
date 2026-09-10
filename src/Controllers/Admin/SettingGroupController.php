<?php

namespace Movie\Core\Controllers\Admin;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Settings\app\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Movie\Core\Controllers\Admin\BaseCrudController as CrudController;
use Prologue\Alerts\Facades\Alert;

/**
 * Trang sửa nhiều setting cùng lúc theo nhóm: generals / metas / jwplayer / others.
 *
 * Vì sao cần controller này: bản fork `hacoidev/settings` cũ có sẵn trang gộp theo
 * nhóm, còn `backpack/settings` chính chủ (thay vào từ 16/08/2026) chỉ đăng ký 5 route
 * — list, {id}/edit, {id} (PUT), {id}/details, search — không có `group/{group}/edit`.
 * Sidebar thì vẫn trỏ tới 4 URL dạng đó nên cả 4 mục "Cài đặt" trả 404.
 *
 * Trang list còn sống cũng không cứu được: SettingCrudController::setupListOperation()
 * lọc cứng `where active = 1`, trong khi SettingsTableSeeder cố ý ghi `'active' => 0`
 * cho từng mục — nên /admin/setting mở ra rỗng không. Trước khi có file này thì không
 * còn lối nào sửa settings từ admin.
 */
class SettingGroupController extends CrudController
{
    /**
     * Tên hiển thị của từng nhóm, khớp với nhãn trong sidebar.
     */
    protected const TEN_NHOM = [
        'generals' => 'Cấu hình chung',
        'metas'    => 'SEO',
        'jwplayer' => 'Jwplayer',
        'others'   => 'Khác',
    ];

    public function setup()
    {
        // Bắt buộc phải setModel dù trang này ghi nhiều bản ghi chứ không phải một
        // entry: CrudPanel là singleton, nhiều view/field của Backpack gọi
        // CRUD::getModel() (ví dụ makeSureFieldHasEntity, hasUploadFields) và sẽ
        // nổ 500 nếu chưa có model. Bỏ dòng này thì trang chỉ chạy khi request
        // trước đó tình cờ đã set model — tức hỏng trên trình duyệt thật.
        CRUD::setModel(Setting::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/setting');
        CRUD::setEntityNameStrings('cài đặt', 'cài đặt');
    }

    public function edit($group)
    {
        $settings = $this->layNhom($group);

        CRUD::setOperation('update');

        foreach ($settings as $setting) {
            CRUD::addField($this->dungField($setting));
        }

        return view('movie::base.setting_group', [
            'crud'  => $this->crud,
            'group' => $group,
            'title' => $this->tenNhom($group),
        ]);
    }

    public function update(Request $request, $group)
    {
        $settings = $this->layNhom($group);

        foreach ($settings as $setting) {
            $field = $this->giaiMaField($setting);
            $type  = $field['type'] ?? 'text';

            // switch/checkbox không gửi gì lên khi ở trạng thái tắt, nên phải suy ra 0
            // thay vì bỏ qua — nếu bỏ qua thì không bao giờ tắt được.
            if (in_array($type, ['switch', 'checkbox'], true)) {
                $giaTri = $request->input($setting->key) ? 1 : 0;
            } elseif (! $request->has($setting->key)) {
                // Field không render thành input (ví dụ type 'view') thì để nguyên giá trị cũ.
                continue;
            } else {
                $giaTri = $request->input($setting->key);
            }

            // Setting::$fillable chỉ có ['value'], mọi cột khác bị bỏ lặng lẽ khi
            // fill()/update() — gán thẳng thuộc tính rồi save() là cách an toàn.
            $setting->value = $giaTri;
            $setting->save();
        }

        // Settings được đọc lúc boot (bootSeoDefaults) và theme cache theo site_cache_ttl,
        // không xoá cache thì trang công khai vẫn trả nội dung cũ.
        Artisan::call('cache:clear');

        Alert::success('Đã lưu ' . $this->tenNhom($group))->flash();

        return redirect(backpack_url('setting/group/' . $group . '/edit'));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Setting>
     */
    protected function layNhom(string $group)
    {
        $settings = Setting::where('group', $group)->orderBy('id')->get();

        if ($settings->isEmpty()) {
            abort(404, 'Không có nhóm cài đặt "' . $group . '"');
        }

        return $settings;
    }

    protected function dungField(Setting $setting): array
    {
        $field = $this->giaiMaField($setting);

        // JSON trong DB luôn để 'name' => 'value' vì bản cũ sửa mỗi lần một dòng.
        // Ở trang gộp thì N input sẽ trùng tên nhau, nên đổi tên theo key của setting.
        $field['name']  = $setting->key;
        $field['label'] = $setting->name ?: $setting->key;
        $field['value'] = $setting->value;

        // CRUD này không gắn model nào; Backpack v7 gọi makeSureFieldHasEntity() trong
        // addField() và sẽ đoán quan hệ trên getModel() (trả về chuỗi) -> lỗi 500.
        // Xem ghi chú tương tự ở SiteMapController.
        $field['entity'] = false;

        return $field;
    }

    protected function giaiMaField(Setting $setting): array
    {
        $field = json_decode((string) $setting->field, true);

        return is_array($field) && $field !== [] ? $field : ['type' => 'text'];
    }

    protected function tenNhom(string $group): string
    {
        return static::TEN_NHOM[$group] ?? ucfirst($group);
    }
}
