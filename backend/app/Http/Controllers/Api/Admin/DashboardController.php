<?php
// backend/app/Http/Controllers/Api/Admin/DashboardController.php
namespace App\Http\Controllers\Api\Admin;

use App\Enums\AgreementStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Support\PlanCaps;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Platform dashboard (spec § 7). Counts only — never an amount. Mirrors
 * frontend demo/services/admin/dashboard.ts; keep both in lock-step.
 */
class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $now = now();
        $owners = User::where('role', UserRole::OWNER)->withCount(['properties', 'ownedUnits as units_count'])->get();
        $tenants = User::where('role', UserRole::TENANT)->get(['id', 'status', 'invited_by', 'invited_at', 'first_login_at', 'created_at']);

        $unitsTotal = Unit::count();
        $unitsOccupied = Unit::where('status', UnitStatus::OCCUPIED)->count();

        // ── Series: trailing 12 months, oldest first ─────────────────────────
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = $now->copy()->subMonthsNoOverflow($i)->format('Y-m');
        }
        $bucket = fn (Collection $rows, string $col) => collect($months)->map(
            fn ($m) => $rows->filter(fn ($r) => optional($r->{$col})->format('Y-m') === $m)->count()
        )->values()->all();

        $invoices = Invoice::get(['id', 'created_at']);
        $payments = Payment::where('status', PaymentStatus::SUCCESSFUL)->get(['id', 'paid_at']);
        $acceptance = collect($months)->map(function ($m) use ($tenants) {
            $invited = $tenants->filter(fn ($t) => optional($t->invited_at)->format('Y-m') === $m);
            if ($invited->isEmpty()) {
                return 0;
            }

            return (int) round($invited->whereNotNull('first_login_at')->count() / $invited->count() * 100);
        })->values()->all();

        // ── Attention feed ───────────────────────────────────────────────────
        $attention = [];
        $push = function (string $kind, User $o, string $meta) use (&$attention) {
            $attention[] = ['kind' => $kind, 'ownerId' => $o->id, 'ownerName' => $o->name, 'meta' => $meta, 'link' => "/admin/owners/{$o->id}"];
        };
        $overdueByOwner = Invoice::where('invoices.status', InvoiceStatus::OVERDUE)
            ->join('agreements', 'agreements.id', '=', 'invoices.agreement_id')
            ->join('units', 'units.id', '=', 'agreements.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->selectRaw('properties.owner_id as owner_id, count(*) as c')
            ->groupBy('properties.owner_id')->pluck('c', 'owner_id');
        $staleByOwner = $tenants->filter(fn ($t) => $t->status === 'invited' && $t->invited_at && $t->invited_at->lt($now->copy()->subDays(7)))
            ->groupBy('invited_by')->map->count();

        foreach ($owners as $o) {
            $cap = PlanCaps::unitsCap($o->plan_tier);
            if ($cap !== null && $o->units_count > $cap) {
                $push('over_cap', $o, "{$o->units_count}/{$cap}");
            }
            if (($overdueByOwner[$o->id] ?? 0) >= 3) {
                $push('overdue_3plus', $o, $overdueByOwner[$o->id] . ' overdue');
            }
            if (($staleByOwner[$o->id] ?? 0) > 0) {
                $push('invite_stale_7d', $o, $staleByOwner[$o->id] . ' pending');
            }
            if ($o->properties_count === 0 && $o->created_at->lt($now->copy()->subDays(7))) {
                $push('no_property_7d', $o, (int) $o->created_at->diffInDays($now) . 'd');
            }
            if ($o->isSuspended()) {
                $push('suspended', $o, $o->suspended_at->toDateString());
            }
        }

        return response()->json([
            'tiles' => [
                'owners'     => ['total' => $owners->count(), 'active' => $owners->whereNull('suspended_at')->count(), 'suspended' => $owners->whereNotNull('suspended_at')->count()],
                'tenants'    => ['total' => $tenants->count(), 'invitedPending' => $tenants->where('status', 'invited')->count()],
                'properties' => Property::count(),
                'units'      => ['total' => $unitsTotal, 'occupiedPct' => $unitsTotal > 0 ? (int) round($unitsOccupied / $unitsTotal * 100) : 0],
                'agreementsActive'      => Agreement::where('status', AgreementStatus::ACTIVE)->count(),
                'agreementsExpiring30d' => Agreement::where('status', AgreementStatus::ACTIVE)
                    ->whereBetween('end_date', [$now->toDateString(), $now->copy()->addDays(30)->toDateString()])->count(),
                'supportOpen' => 0, // SP2
            ],
            'series' => [
                'months'               => $months,
                'ownerSignups'         => $bucket($owners, 'created_at'),
                'invoicesIssued'       => $bucket($invoices, 'created_at'),
                'invoicesPaid'         => $bucket($payments, 'paid_at'),
                'inviteAcceptanceRate' => $acceptance,
            ],
            'attention' => $attention,
        ]);
    }
}
