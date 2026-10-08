<?php
// backend/app/Http/Controllers/Api/Admin/OwnerController.php
namespace App\Http\Controllers\Api\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SuspendOwnerRequest;
use App\Http\Requests\Admin\WarnOwnerRequest;
use App\Http\Resources\Admin\AdminOwnerResource;
use App\Http\Resources\Admin\AdminPropertySummaryResource;
use App\Http\Resources\Admin\AdminTenantResource;
use App\Http\Resources\Admin\AuditEntryResource;
use App\Models\Property;
use App\Models\User;
use App\Notifications\OwnerWarning;
use App\Services\AuditLogger;
use App\Support\OwnerTenantsQuery;
use App\Support\PlanCaps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class OwnerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('perPage', 20)));

        $query = User::query()->where('role', UserRole::OWNER)
            ->withCount(['properties', 'ownedUnits as units_count']);

        if ($q = trim((string) $request->query('q', ''))) {
            $like = '%' . $q . '%';
            $query->where(fn ($w) => $w
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('business_name', 'like', $like));
        }
        if ($plan = $request->query('plan')) {
            $query->where('plan_tier', $plan);
        }
        if ($status = $request->query('status')) {
            $status === 'suspended' ? $query->whereNotNull('suspended_at') : $query->whereNull('suspended_at');
        }
        if ($request->boolean('overdue')) {
            $query->whereHas('properties.units.agreements.invoices', fn ($i) => $i->where('status', InvoiceStatus::OVERDUE));
        }

        $owners = $query->latest()->get();

        if ($request->boolean('overCap')) {
            $owners = $owners->filter(function (User $o) {
                $cap = PlanCaps::unitsCap($o->plan_tier);

                return $cap !== null && $o->units_count > $cap;
            })->values();
        }

        $total = $owners->count();
        $page = max(1, (int) $request->integer('page', 1));
        $slice = $owners->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => AdminOwnerResource::collection($slice)->resolve(),
            'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'lastPage' => max(1, (int) ceil($total / $perPage))],
        ]);
    }

    public function show(User $owner): AdminOwnerResource
    {
        $this->assertOwner($owner);

        return new AdminOwnerResource($owner);
    }

    public function properties(User $owner): JsonResponse
    {
        $this->assertOwner($owner);
        $properties = Property::where('owner_id', $owner->id)->with('units')->latest()->get();

        return response()->json(AdminPropertySummaryResource::collection($properties)->resolve());
    }

    public function tenants(User $owner): JsonResponse
    {
        $this->assertOwner($owner);
        $tenants = OwnerTenantsQuery::for($owner->id)
            ->with(['inviter:id,name', 'agreements.unit.property:id,name,owner_id', 'agreements.unit.property.owner:id,name'])
            ->latest()->get();

        return response()->json(AdminTenantResource::collection($tenants)->resolve());
    }

    public function history(User $owner): JsonResponse
    {
        $this->assertOwner($owner);

        $entries = Activity::inLog(AuditLogger::LOG_NAME)
            ->where('subject_type', User::class)->where('subject_id', $owner->id)
            ->with(['causer', 'subject'])
            ->latest('created_at')->latest('id')
            ->get();

        $rows = AuditEntryResource::collection($entries)->resolve();
        $rows[] = [
            'id'          => 'signup-' . $owner->id,
            'action'      => 'owner.signup',
            'actorId'     => null,
            'actorName'   => null,
            'subjectType' => 'user',
            'subjectId'   => $owner->id,
            'subjectName' => $owner->name,
            'before'      => (object) [],
            'after'       => (object) ['planTier' => $owner->plan_tier?->value ?? 'free'],
            'reason'      => null,
            'ip'          => null,
            'createdAt'   => $owner->created_at?->toISOString(),
        ];

        return response()->json($rows);
    }

    public function warn(WarnOwnerRequest $request, User $owner, AuditLogger $audit): JsonResponse
    {
        $this->assertOwner($owner);
        $data = $request->validated();
        $text = OwnerWarning::text($data['template'], $data['suspendOn'], $data['extraLine'] ?? null);

        $owner->notify(new OwnerWarning($data['template'], $data['suspendOn'], $data['extraLine'] ?? null));
        $audit->record(AuditLogger::OWNER_WARNED, $owner, [], [
            'template'  => $data['template'],
            'suspendOn' => $data['suspendOn'],
            'extraLine' => $data['extraLine'] ?? null,
            'text'      => $text,
        ]);

        return response()->json(null, 204);
    }

    public function suspend(SuspendOwnerRequest $request, User $owner, AuditLogger $audit): JsonResponse
    {
        $this->assertOwner($owner);
        abort_if($owner->isSuspended(), 409, 'Owner is already suspended.');

        $before = ['status' => 'active'];
        $owner->update(['suspended_at' => now(), 'suspension_reason' => $request->string('reason')]);
        $audit->record(AuditLogger::OWNER_SUSPENDED, $owner, $before, ['status' => 'suspended'], $request->string('reason'));

        return response()->json((new AdminOwnerResource($owner->fresh()))->resolve());
    }

    public function unsuspend(User $owner, AuditLogger $audit): JsonResponse
    {
        $this->assertOwner($owner);
        abort_unless($owner->isSuspended(), 409, 'Owner is not suspended.');

        $before = ['status' => 'suspended', 'suspensionReason' => $owner->suspension_reason];
        $owner->update(['suspended_at' => null, 'suspension_reason' => null]);
        $audit->record(AuditLogger::OWNER_UNSUSPENDED, $owner, $before, ['status' => 'active']);

        return response()->json((new AdminOwnerResource($owner->fresh()))->resolve());
    }

    private function assertOwner(User $user): void
    {
        abort_if($user->role !== UserRole::OWNER, 404);
    }
}
