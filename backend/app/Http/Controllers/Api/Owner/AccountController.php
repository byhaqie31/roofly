<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteOnboardingRequest;
use App\Http\Requests\UpdateAccountNotificationsRequest;
use App\Http\Requests\UpdateAccountPreferencesRequest;
use App\Http\Requests\UpdateAccountProfileRequest;
use App\Http\Resources\AuthUserResource;
use App\Http\Resources\OwnerAccountResource;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): OwnerAccountResource
    {
        return new OwnerAccountResource($request->user()->fresh());
    }

    public function updateProfile(UpdateAccountProfileRequest $request): OwnerAccountResource
    {
        $request->user()->update($request->toModelAttributes());

        return new OwnerAccountResource($request->user()->fresh());
    }

    public function updatePreferences(UpdateAccountPreferencesRequest $request): OwnerAccountResource
    {
        $current = $request->user()->owner_preferences ?? OwnerAccountResource::defaultPreferences();
        $request->user()->update(['owner_preferences' => array_merge($current, $request->validated())]);

        return new OwnerAccountResource($request->user()->fresh());
    }

    public function updateNotifications(UpdateAccountNotificationsRequest $request): OwnerAccountResource
    {
        $current = $request->user()->notification_preferences ?? OwnerAccountResource::defaultNotifications();
        $request->user()->update(['notification_preferences' => array_merge($current, $request->validated())]);

        return new OwnerAccountResource($request->user()->fresh());
    }

    public function plans(): JsonResponse
    {
        return response()->json([
            ['tier' => 'free',     'priceRm' => 0,   'unitsCap' => 2,           'description' => 'owner.settings.plan.tiers.free'],
            ['tier' => 'starter',  'priceRm' => 29,  'unitsCap' => 5,           'description' => 'owner.settings.plan.tiers.starter'],
            ['tier' => 'pro',      'priceRm' => 79,  'unitsCap' => 25,          'description' => 'owner.settings.plan.tiers.pro'],
            ['tier' => 'business', 'priceRm' => 199, 'unitsCap' => 'unlimited', 'description' => 'owner.settings.plan.tiers.business'],
        ]);
    }

    public function completeOnboarding(CompleteOnboardingRequest $request, AuditLogger $audit): AuthUserResource
    {
        $user = $request->user();
        $before = ['purposes' => $user->purposes, 'onboardedAt' => $user->onboarded_at];
        $user->update([
            'purposes'     => array_values(array_unique($request->validated('purposes'))),
            'onboarded_at' => $user->onboarded_at ?? now(),
        ]);
        $audit->record(AuditLogger::ACCOUNT_ONBOARDED, $user, $before, ['purposes' => $user->purposes]);

        return new AuthUserResource($user->fresh());
    }

    public function updateChecklist(Request $request, AuditLogger $audit): AuthUserResource
    {
        $data = $request->validate(['dismissed' => 'required|boolean']);
        $user = $request->user();
        $user->update(['checklist_dismissed_at' => $data['dismissed'] ? now() : null]);
        $audit->record($data['dismissed'] ? AuditLogger::ACCOUNT_CHECKLIST_DISMISSED : AuditLogger::ACCOUNT_CHECKLIST_RESTORED, $user);

        return new AuthUserResource($user->fresh());
    }

    public function setPassword(Request $request, AuditLogger $audit): AuthUserResource|JsonResponse
    {
        $user = $request->user();
        if ($user->hasPassword()) {
            return response()->json([
                'message' => 'A password is already set.',
                'errors'  => ['password' => ['A password is already set.']],
            ], 422);
        }
        $data = $request->validate(['password' => 'required|string|min:8|confirmed']);
        $user->update(['password' => $data['password']]); // 'hashed' cast
        $audit->record(AuditLogger::ACCOUNT_PASSWORD_SET, $user);

        return new AuthUserResource($user->fresh());
    }
}
