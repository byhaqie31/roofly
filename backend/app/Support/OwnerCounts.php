<?php

namespace App\Support;

use App\Enums\AgreementStatus;
use App\Enums\InvoiceStatus;
use App\Enums\TicketStatus;
use App\Enums\UnitStatus;
use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;

/** The admin counts strip for one owner (spec § 6) — counts only, never amounts. */
final class OwnerCounts
{
    /** @return array{properties:int,units:int,unitsOccupied:int,tenants:int,agreementsActive:int,agreementsExpiring30d:int,invoicesOverdue:int,ticketsOpen:int} */
    public static function for(User $owner): array
    {
        $propertyIds = Property::where('owner_id', $owner->id)->pluck('id');
        $unitIds = Unit::whereIn('property_id', $propertyIds)->pluck('id');
        $agreementIds = Agreement::whereIn('unit_id', $unitIds)->pluck('id');

        return [
            'properties'            => $propertyIds->count(),
            'units'                 => $unitIds->count(),
            'unitsOccupied'         => Unit::whereIn('id', $unitIds)->where('status', UnitStatus::OCCUPIED)->count(),
            'tenants'               => OwnerTenantsQuery::for($owner->id)->count(),
            'agreementsActive'      => Agreement::whereIn('id', $agreementIds)->where('status', AgreementStatus::ACTIVE)->count(),
            'agreementsExpiring30d' => Agreement::whereIn('id', $agreementIds)->where('status', AgreementStatus::ACTIVE)
                ->whereBetween('end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
            'invoicesOverdue'       => Invoice::whereIn('agreement_id', $agreementIds)->where('status', InvoiceStatus::OVERDUE)->count(),
            'ticketsOpen'           => Ticket::whereIn('unit_id', $unitIds)
                ->whereIn('status', [TicketStatus::NEW, TicketStatus::IN_PROGRESS, TicketStatus::REOPENED])->count(),
        ];
    }
}
