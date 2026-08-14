<?php

namespace Movie\Core\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Backpack\CRUD\app\Http\Controllers\CrudController as BackpackCrudController;

/**
 * backpack/crud thật (không như bản fork hacoidev/crud cũ) không có sẵn
 * AuthorizesRequests trên CrudController — mọi controller trong package này
 * gọi $this->authorize(...) kiểu Laravel Policy nên cần lớp trung gian này.
 */
abstract class BaseCrudController extends BackpackCrudController
{
    use AuthorizesRequests;
}
