<?php
// backend/app/Http/Controllers/Api/Admin/TenantController.php
namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminTenantResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    private const WITH = ['inviter:id,name', 'agreements.unit.property:id,name,owner_id', 'agreements.unit.property.owner:id,name'];

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('perPage', 20)));
        $query = User::query()->where('role', UserRole::TENANT)->with(self::WITH);

        if ($q = trim((string) $request->query('q', ''))) {
            $like = '%' . $q . '%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($ownerId = $request->query('ownerId')) {
            $query->where(fn ($w) => $w
                ->where('invited_by', $ownerId)
                ->orWhereHas('agreements.unit.property', fn ($p) => $p->where('owner_id', $ownerId)));
        }

        $page = $query->latest()->paginate($perPage, ['*'], 'page', max(1, (int) $request->integer('page', 1)));

        return response()->json([
            'data' => AdminTenantResource::collection($page->items())->resolve(),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
        ]);
    }

    public function show(User $tenant): AdminTenantResource
    {
        abort_if($tenant->role !== UserRole::TENANT, 404);

        return new AdminTenantResource($tenant->load(self::WITH));
    }

    public function resendInvite(User $tenant, AuditLogger $audit): JsonResponse
    {
        abort_if($tenant->role !== UserRole::TENANT, 404);
        abort_unless($tenant->status === 'invited', 409, 'Only pending invites can be resent.');

        $before = ['invitedAt' => $tenant->invited_at?->toISOString()];
        $tenant->update(['invited_at' => now()]);
        // TODO Phase 2: dispatch magic-link invite notification (see MagicLinkController)
        $audit->record(AuditLogger::TENANT_INVITE_RESENT, $tenant, $before, ['invitedAt' => $tenant->invited_at->toISOString()]);

        return response()->json(null, 204);
    }
}
