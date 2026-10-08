<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteTenantOnboardingRequest;
use App\Http\Resources\AuthUserResource;
use App\Services\AuditLogger;

/** PATCH /me/onboarding — save the core profile and stamp onboarded_at (spec 2026-10-07 § 4.4). */
class TenantOnboardingController extends Controller
{
    public function store(CompleteTenantOnboardingRequest $request, AuditLogger $audit): AuthUserResource
    {
        $user = $request->user();
        $before = ['onboardedAt' => $user->onboarded_at];

        $user->update(array_merge($request->toModelAttributes(), [
            'onboarded_at' => $user->onboarded_at ?? now(),
        ]));
        $audit->record(AuditLogger::ACCOUNT_ONBOARDED, $user, $before, ['onboardedAt' => $user->onboarded_at]);

        return new AuthUserResource($user->fresh());
    }
}
