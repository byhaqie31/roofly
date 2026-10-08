<?php

namespace App\Enums;

enum AgreementStatus: string
{
    case DRAFT = 'draft';
    /** Owner sent the terms; the tenant has not answered yet. */
    case PENDING_REVIEW = 'pending_review';
    /** Tenant agreed; the owner activates to start the tenancy (and invoices). */
    case ACCEPTED = 'accepted';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case TERMINATED = 'terminated';

    /** States the tenant's answer depends on — a term edit voids them. */
    public function isUnderReview(): bool
    {
        return $this === self::PENDING_REVIEW || $this === self::ACCEPTED;
    }
}
