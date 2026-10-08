<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Roofly API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api and protected by Sanctum where noted.
| Structure mirrors the frontend service layer (useProperties, useTenants, …)
| so each service swap is a one-to-one mapping.
|
| Auth: POST /api/auth/* are public. Everything else requires sanctum auth.
| Role guards: 'role:owner' and 'role:tenant' middleware (Spatie Permission).
|
*/

// ── Public: Auth ─────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register',      [\App\Http\Controllers\Api\Auth\RegisterController::class, 'store']);
    Route::post('login',         [\App\Http\Controllers\Api\Auth\LoginController::class, 'store']);
    Route::post('google',        [\App\Http\Controllers\Api\Auth\GoogleLoginController::class, 'store'])->middleware('throttle:10,1');
    Route::post('magic-link',    [\App\Http\Controllers\Api\Auth\MagicLinkController::class, 'store']);
    Route::get('magic-link/{token}', [\App\Http\Controllers\Api\Auth\MagicLinkController::class, 'authenticate']);
    Route::post('forgot-password', [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
    Route::post('reset-password',  [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
    // Tenant accepts the emailed invite (spec 2026-10-07 § 4) — sets password, activates, logs in.
    Route::post('accept-invite',   [\App\Http\Controllers\Api\Auth\AcceptTenantInviteController::class, 'store'])->middleware('throttle:5,1');
});

// ── Public: analytics beacon (spec: admin analytics § 3) ─────────────────────
Route::post('track', [\App\Http\Controllers\Api\TrackController::class, 'store'])->middleware('throttle:track');

// ── Public: coming-soon waitlist (first-party, replaces the Web3Forms relay) ──
Route::post('waitlist', [\App\Http\Controllers\Api\WaitlistController::class, 'store'])->middleware('throttle:waitlist');

// ── Public: Admin Portal auth (spec § 4) ─────────────────────────────────────
Route::prefix('admin/auth')->group(function () {
    Route::post('login',         [\App\Http\Controllers\Api\Admin\AdminLoginController::class, 'store']);
    Route::post('accept-invite', [\App\Http\Controllers\Api\Admin\AcceptInviteController::class, 'store']);
});

// ── Protected ─────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'touch-active'])->group(function () {

    Route::post('auth/logout', [\App\Http\Controllers\Api\Auth\LoginController::class, 'destroy']);
    Route::get('auth/me',      [\App\Http\Controllers\Api\Auth\LoginController::class, 'show']);

    // In-app help button — owners + tenants (role checked in the controller; outside
    // the owner group's not-suspended guard so a suspended owner can still reach us).
    Route::post('support/enquiries', [\App\Http\Controllers\Api\SupportEnquiryController::class, 'store'])->middleware('throttle:support');

    // ── Owner routes ─────────────────────────────────────────────────────────
    Route::middleware(['role:owner', 'not-suspended'])->group(function () {

        // Dashboard — single aggregated payload (stats + income series + attention feed)
        Route::get('dashboard', [\App\Http\Controllers\Api\Owner\DashboardController::class, 'index']);

        // Account / settings
        Route::get('account',                          [\App\Http\Controllers\Api\Owner\AccountController::class, 'show']);
        Route::patch('account/profile',                [\App\Http\Controllers\Api\Owner\AccountController::class, 'updateProfile']);
        Route::patch('account/preferences',            [\App\Http\Controllers\Api\Owner\AccountController::class, 'updatePreferences']);
        Route::patch('account/notifications',          [\App\Http\Controllers\Api\Owner\AccountController::class, 'updateNotifications']);
        Route::patch('account/onboarding',             [\App\Http\Controllers\Api\Owner\AccountController::class, 'completeOnboarding']);
        Route::patch('account/checklist',              [\App\Http\Controllers\Api\Owner\AccountController::class, 'updateChecklist']);
        Route::post('account/password',                [\App\Http\Controllers\Api\Owner\AccountController::class, 'setPassword']);
        Route::get('plans',                            [\App\Http\Controllers\Api\Owner\AccountController::class, 'plans']);

        // Properties
        Route::apiResource('properties', \App\Http\Controllers\Api\Owner\PropertyController::class);

        // Co-owners (nested under properties)
        Route::get('properties/{property}/co-owners',           [\App\Http\Controllers\Api\Owner\PropertyCoOwnerController::class, 'index']);
        Route::post('properties/{property}/co-owners',          [\App\Http\Controllers\Api\Owner\PropertyCoOwnerController::class, 'store']);
        Route::put('properties/{property}/co-owners',           [\App\Http\Controllers\Api\Owner\PropertyCoOwnerController::class, 'sync']);
        Route::delete('properties/{property}/co-owners/{coOwner}', [\App\Http\Controllers\Api\Owner\PropertyCoOwnerController::class, 'destroy']);

        // Units — nested for list/create, flat for item ops (matches useUnits.ts)
        Route::get('units',                        [\App\Http\Controllers\Api\Owner\UnitController::class, 'all']);
        Route::get('properties/{property}/units',  [\App\Http\Controllers\Api\Owner\UnitController::class, 'index']);
        Route::post('properties/{property}/units', [\App\Http\Controllers\Api\Owner\UnitController::class, 'store']);
        Route::get('units/{unit}',                 [\App\Http\Controllers\Api\Owner\UnitController::class, 'show']);
        Route::patch('units/{unit}',               [\App\Http\Controllers\Api\Owner\UnitController::class, 'update']);
        Route::delete('units/{unit}',              [\App\Http\Controllers\Api\Owner\UnitController::class, 'destroy']);

        // Tenants
        Route::post('tenants/invite', [\App\Http\Controllers\Api\Owner\TenantController::class, 'invite']);
        Route::post('tenants/{tenant}/invite-link', [\App\Http\Controllers\Api\Owner\TenantController::class, 'inviteLink']); // copy/share backup, no mail
        Route::apiResource('tenants', \App\Http\Controllers\Api\Owner\TenantController::class);

        // Agreements (+ review flow, spec 2026-10-07 agreement-review)
        Route::apiResource('agreements', \App\Http\Controllers\Api\Owner\AgreementController::class);
        Route::post('agreements/{agreement}/send',     [\App\Http\Controllers\Api\Owner\AgreementController::class, 'send']);
        Route::post('agreements/{agreement}/withdraw', [\App\Http\Controllers\Api\Owner\AgreementController::class, 'withdraw']);

        // Invoices
        Route::get('invoices',                         [\App\Http\Controllers\Api\Owner\InvoiceController::class, 'index']);
        Route::get('invoices/{invoice}',               [\App\Http\Controllers\Api\Owner\InvoiceController::class, 'show']);
        Route::patch('invoices/{invoice}',             [\App\Http\Controllers\Api\Owner\InvoiceController::class, 'updateStatus']);
        Route::patch('invoices/{invoice}/status',      [\App\Http\Controllers\Api\Owner\InvoiceController::class, 'updateStatus']);
        Route::post('invoices/{invoice}/send',         [\App\Http\Controllers\Api\Owner\InvoiceController::class, 'send']);
        Route::post('invoices/{invoice}/payments',     [\App\Http\Controllers\Api\Owner\InvoiceController::class, 'recordPayment']);

        // Tenant transfer claims → confirm / reject (spec 2026-10-08 payout-accounts-duitnow)
        Route::post('payments/{payment}/confirm',      [\App\Http\Controllers\Api\Owner\PaymentClaimController::class, 'confirm']);
        Route::post('payments/{payment}/reject',       [\App\Http\Controllers\Api\Owner\PaymentClaimController::class, 'reject']);

        // Payout accounts (spec 2026-10-08 payout-accounts-duitnow)
        Route::get('payout-accounts',                          [\App\Http\Controllers\Api\Owner\PayoutAccountController::class, 'index']);
        Route::post('payout-accounts',                         [\App\Http\Controllers\Api\Owner\PayoutAccountController::class, 'store']);
        Route::patch('payout-accounts/{payoutAccount}',        [\App\Http\Controllers\Api\Owner\PayoutAccountController::class, 'update']);
        Route::post('payout-accounts/{payoutAccount}/default', [\App\Http\Controllers\Api\Owner\PayoutAccountController::class, 'setDefault']);
        Route::delete('payout-accounts/{payoutAccount}',       [\App\Http\Controllers\Api\Owner\PayoutAccountController::class, 'destroy']);

        // Maintenance tickets
        Route::apiResource('tickets', \App\Http\Controllers\Api\Owner\TicketController::class);
        Route::patch('tickets/{ticket}/status',        [\App\Http\Controllers\Api\Owner\TicketController::class, 'updateStatus']);
        Route::post('tickets/{ticket}/comments',       [\App\Http\Controllers\Api\Owner\TicketCommentController::class, 'store']);

        // Reports (aggregation — no new tables, just computed reads)
        Route::get('reports/dashboard',               [\App\Http\Controllers\Api\Owner\ReportController::class, 'dashboard']);
        Route::get('reports/yearly/{year}',           [\App\Http\Controllers\Api\Owner\ReportController::class, 'yearly']);
        Route::get('reports/yearly/{year}/export',    [\App\Http\Controllers\Api\Owner\ReportController::class, 'exportCsv']);
    });

    // ── Tenant routes ─────────────────────────────────────────────────────────
    Route::middleware('role:tenant')->prefix('me')->group(function () {

        // Active agreement for the signed-in tenant
        Route::get('agreement',    [\App\Http\Controllers\Api\Tenant\TenantAgreementController::class, 'show']);
        // Review an agreement the owner sent (spec 2026-10-07 agreement-review)
        Route::post('agreements/{agreement}/accept',          [\App\Http\Controllers\Api\Tenant\TenantAgreementReviewController::class, 'accept']);
        Route::post('agreements/{agreement}/request-changes', [\App\Http\Controllers\Api\Tenant\TenantAgreementReviewController::class, 'requestChanges']);

        // Invoices scoped to the tenant
        Route::get('invoices',             [\App\Http\Controllers\Api\Tenant\TenantInvoiceController::class, 'index']);
        Route::post('invoices/{invoice}/claim', [\App\Http\Controllers\Api\Tenant\TenantInvoiceController::class, 'claim']); // "I've paid" by transfer
        Route::post('invoices/{invoice}/pay', [\App\Http\Controllers\Api\Tenant\TenantInvoiceController::class, 'pay']);     // simulated gateway, 403 unless ONLINE_PAYMENTS

        // Tickets filed by this tenant
        Route::get('tickets',              [\App\Http\Controllers\Api\Tenant\TenantTicketController::class, 'index']);
        Route::get('tickets/{ticket}',     [\App\Http\Controllers\Api\Tenant\TenantTicketController::class, 'show']);
        Route::post('tickets',             [\App\Http\Controllers\Api\Tenant\TenantTicketController::class, 'store']);
        Route::post('tickets/{ticket}/comments', [\App\Http\Controllers\Api\Tenant\TenantTicketController::class, 'addComment']);

        // Profile (reads from + writes to the tenant's own user record)
        Route::get('profile',              [\App\Http\Controllers\Api\Tenant\TenantProfileController::class, 'show']);
        Route::patch('profile',            [\App\Http\Controllers\Api\Tenant\TenantProfileController::class, 'update']);
        // First-run onboarding: core profile fields + onboarded_at (spec 2026-10-07 § 4.4)
        Route::patch('onboarding',         [\App\Http\Controllers\Api\Tenant\TenantOnboardingController::class, 'store']);
    });

    // ── Admin routes (spec § 9). Every write goes through AuditLogger. ──────
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('permissions', [\App\Http\Controllers\Api\Admin\PermissionController::class, 'index'])
            ->middleware('can:' . \App\Support\AdminPermissions::ADMINS_MANAGE);

        $P = \App\Support\AdminPermissions::class;
        $Owner = \App\Http\Controllers\Api\Admin\OwnerController::class;

        Route::get('dashboard', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'index'])
            ->middleware('can:' . $P::DASHBOARD_VIEW);

        Route::middleware('can:' . $P::OWNERS_VIEW)->group(function () use ($Owner) {
            Route::get('owners',                     [$Owner, 'index']);
            Route::get('owners/{owner}',             [$Owner, 'show']);
            Route::get('owners/{owner}/properties',  [$Owner, 'properties']);
            Route::get('owners/{owner}/tenants',     [$Owner, 'tenants']);
            Route::get('owners/{owner}/history',     [$Owner, 'history']);
        });
        Route::post('owners/{owner}/warn',      [$Owner, 'warn'])->middleware('can:' . $P::OWNERS_WARN);
        Route::post('owners/{owner}/suspend',   [$Owner, 'suspend'])->middleware('can:' . $P::OWNERS_SUSPEND);
        Route::post('owners/{owner}/unsuspend', [$Owner, 'unsuspend'])->middleware('can:' . $P::OWNERS_SUSPEND);

        $Tenant = \App\Http\Controllers\Api\Admin\TenantController::class;
        Route::middleware('can:' . $P::TENANTS_VIEW)->group(function () use ($Tenant) {
            Route::get('tenants',                         [$Tenant, 'index']);
            Route::get('tenants/{tenant}',                [$Tenant, 'show']);
            Route::post('tenants/{tenant}/resend-invite', [$Tenant, 'resendInvite']);
        });

        $AdminUser = \App\Http\Controllers\Api\Admin\AdminUserController::class;
        Route::middleware('can:' . $P::ADMINS_MANAGE)->group(function () use ($AdminUser) {
            Route::get('admins',                          [$AdminUser, 'index']);
            Route::post('admins',                         [$AdminUser, 'store']);
            Route::patch('admins/{admin}',                [$AdminUser, 'update']);
            Route::post('admins/{admin}/resend-invite',   [$AdminUser, 'resendInvite']);
        });

        $Audit = \App\Http\Controllers\Api\Admin\AuditController::class;
        Route::get('audit',            [$Audit, 'index']);   // audit.view → all, else own (in controller)
        Route::get('audit/export.csv', [$Audit, 'export'])->middleware('can:' . $P::AUDIT_VIEW);

        $Analytics = \App\Http\Controllers\Api\Admin\AnalyticsController::class;
        Route::middleware('can:' . $P::ANALYTICS_VIEW)->group(function () use ($Analytics, $P) {
            Route::get('analytics/overview', [$Analytics, 'overview']);
            Route::get('analytics/leads',            [$Analytics, 'leads']);
            Route::get('analytics/leads/export.csv', [$Analytics, 'export']);   // before {lead}
            Route::get('analytics/leads/{lead}',     [$Analytics, 'lead']);
            Route::post('analytics/leads/{lead}/invite', [$Analytics, 'invite'])->middleware('can:' . $P::BROADCAST_SEND);
        });

        $Enquiry = \App\Http\Controllers\Api\Admin\EnquiryController::class;
        Route::middleware('can:' . $P::SUPPORT_MANAGE)->group(function () use ($Enquiry) {
            Route::get('enquiries',               [$Enquiry, 'index']);
            Route::patch('enquiries/{enquiry}',   [$Enquiry, 'update']);
        });
    });

    // ── Billplz webhook (no role guard — validated by X-Signature) ────────────
    Route::post('webhooks/billplz', [\App\Http\Controllers\Api\WebhookController::class, 'billplz'])
        ->withoutMiddleware('auth:sanctum');
});
