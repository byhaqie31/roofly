<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\InviteTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\User;
use App\Services\TenantInvites;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * List all tenants visible to this owner: invited directly by them, or
     * carrying (or having carried) an agreement on a unit they own.
     */
    public function index(Request $request)
    {
        $ownerId = $request->user()->id;

        $tenants = User::where('role', UserRole::TENANT)
            ->where(fn ($q) => $q
                ->where('invited_by', $ownerId)
                ->orWhereHas('agreements.unit.property', fn ($qq) =>
                    $qq->where('owner_id', $ownerId)
                )
            )
            ->latest()
            ->get();

        return TenantResource::collection($tenants);
    }

    public function store(InviteTenantRequest $request, TenantInvites $invites)
    {
        return $this->invite($request, $invites);
    }

    public function invite(InviteTenantRequest $request, TenantInvites $invites)
    {
        $tenant = User::create(array_merge($request->validated(), [
            'role'       => UserRole::TENANT,
            'status'     => 'invited',
            'invited_at' => now(),
            'invited_by' => $request->user()->id,
        ]));

        // Emailed set-password link (queued). The tenant becomes `active` when they accept.
        $invites->send($tenant);

        return (new TenantResource($tenant))->response()->setStatusCode(201);
    }

    public function show(Request $request, User $tenant)
    {
        abort_if($tenant->role !== UserRole::TENANT, 404);
        $this->authorizeTenantAccess($request, $tenant);

        return new TenantResource($tenant);
    }

    public function update(UpdateTenantRequest $request, User $tenant)
    {
        abort_if($tenant->role !== UserRole::TENANT, 404);
        $this->authorizeTenantAccess($request, $tenant);

        $tenant->update($request->toModelAttributes());

        return new TenantResource($tenant);
    }

    public function destroy(Request $request, User $tenant)
    {
        abort_if($tenant->role !== UserRole::TENANT, 404);
        $this->authorizeTenantAccess($request, $tenant);

        $tenant->delete();

        return response()->json(null, 204);
    }

    private function authorizeTenantAccess(Request $request, User $tenant): void
    {
        if ($tenant->invited_by === $request->user()->id) {
            return;
        }

        $isOwner = $tenant->agreements()
            ->whereHas('unit.property', fn ($q) =>
                $q->where('owner_id', $request->user()->id)
            )
            ->exists();

        abort_unless($isOwner, 403);
    }
}
