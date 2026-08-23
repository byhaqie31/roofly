<?php
// backend/app/Http/Controllers/Api/Admin/PermissionController.php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPermissions;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'permissions' => AdminPermissions::catalogue(),
            'preset'      => AdminPermissions::operationsPreset(),
        ]);
    }
}
