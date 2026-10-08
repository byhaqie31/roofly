<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'phone'            => $user->phone,
            'personal'         => $user->personal_info,
            'emergencyContact' => $user->emergency_contact,
        ]);
    }

    public function update(UpdateTenantProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->toModelAttributes());

        return $this->show($request);
    }
}
