<?php
// backend/app/Http/Controllers/Api/Admin/AdminUserController.php
namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\AdminInvite;
use App\Models\User;
use App\Notifications\AdminInvite as AdminInviteNotification;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    private const INVITE_DAYS = 7;

    public function index(): JsonResponse
    {
        $admins = User::where('role', UserRole::ADMIN)->with('permissions')->latest()->get();

        return response()->json(AdminUserResource::collection($admins)->resolve());
    }

    public function store(StoreAdminRequest $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validated();

        $admin = User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'role'           => UserRole::ADMIN,
            'is_super_admin' => (bool) ($data['isSuperAdmin'] ?? false),
            'password'       => null,
        ]);
        $admin->syncPermissions($data['permissions']);

        $this->sendInvite($admin, $audit, ['permissions' => $data['permissions'], 'isSuperAdmin' => $admin->is_super_admin]);

        return response()->json((new AdminUserResource($admin->load('permissions')))->resolve(), 201);
    }

    public function update(UpdateAdminRequest $request, User $admin, AuditLogger $audit): JsonResponse
    {
        abort_if($admin->role !== UserRole::ADMIN, 404);
        $data = $request->validated();
        $actor = $request->user();

        if (array_key_exists('disabled', $data) && $data['disabled'] && $admin->is($actor)) {
            throw ValidationException::withMessages(['disabled' => 'You cannot disable your own account.']);
        }

        $wouldDisable = ($data['disabled'] ?? false) === true;
        $wouldDemote = array_key_exists('isSuperAdmin', $data) && $data['isSuperAdmin'] === false;
        if ($admin->is_super_admin && ! $admin->isDisabled() && ($wouldDisable || $wouldDemote)) {
            $others = User::where('role', UserRole::ADMIN)->where('is_super_admin', true)
                ->whereNull('disabled_at')->where('id', '!=', $admin->id)->count();
            if ($others === 0) {
                throw ValidationException::withMessages(['isSuperAdmin' => 'There must always be at least one enabled super-admin.']);
            }
        }

        if (array_key_exists('permissions', $data) || array_key_exists('isSuperAdmin', $data)) {
            $before = ['permissions' => $admin->getPermissionNames()->values()->all(), 'isSuperAdmin' => $admin->is_super_admin];
            if (array_key_exists('permissions', $data)) {
                $admin->syncPermissions($data['permissions']);
            }
            if (array_key_exists('isSuperAdmin', $data)) {
                $admin->update(['is_super_admin' => $data['isSuperAdmin']]);
            }
            $admin->unsetRelation('permissions');
            $after = ['permissions' => $admin->getPermissionNames()->values()->all(), 'isSuperAdmin' => $admin->is_super_admin];
            $audit->record(AuditLogger::ADMIN_PERMISSIONS_CHANGED, $admin, $before, $after);
        }

        if (array_key_exists('disabled', $data)) {
            if ($data['disabled'] && ! $admin->isDisabled()) {
                $admin->update(['disabled_at' => now()]);
                $admin->tokens()->delete();
                $audit->record(AuditLogger::ADMIN_DISABLED, $admin, ['status' => 'active'], ['status' => 'disabled']);
            } elseif (! $data['disabled'] && $admin->isDisabled()) {
                $admin->update(['disabled_at' => null]);
                $audit->record(AuditLogger::ADMIN_ENABLED, $admin, ['status' => 'disabled'], ['status' => 'active']);
            }
        }

        return response()->json((new AdminUserResource($admin->fresh()->load('permissions')))->resolve());
    }

    public function resendInvite(Request $request, User $admin, AuditLogger $audit): JsonResponse
    {
        abort_if($admin->role !== UserRole::ADMIN, 404);
        abort_if($admin->first_login_at !== null, 409, 'This admin has already accepted their invite.');

        $this->sendInvite($admin, $audit, ['resend' => true]);

        return response()->json(null, 204);
    }

    private function sendInvite(User $admin, AuditLogger $audit, array $after): void
    {
        // Void any live token so only the newest link works.
        AdminInvite::where('user_id', $admin->id)->whereNull('accepted_at')->update(['accepted_at' => now()]);

        $plain = Str::random(48);
        AdminInvite::create([
            'user_id'    => $admin->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(self::INVITE_DAYS),
        ]);
        $admin->notify(new AdminInviteNotification($plain));
        $audit->record(AuditLogger::ADMIN_INVITE_SENT, $admin, [], $after);
    }
}
