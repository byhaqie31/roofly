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
    Route::post('magic-link',    [\App\Http\Controllers\Api\Auth\MagicLinkController::class, 'store']);
    Route::get('magic-link/{token}', [\App\Http\Controllers\Api\Auth\MagicLinkController::class, 'authenticate']);
});

// ── Public: Admin Portal auth (spec § 4) ─────────────────────────────────────
Route::prefix('admin/auth')->group(function () {
    Route::post('login',         [\App\Http\Controllers\Api\Admin\AdminLoginController::class, 'store']);
    Route::post('accept-invite', [\App\Http\Controllers\Api\Admin\AcceptInviteController::class, 'store']);
});

// ── Protected ─────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'touch-active'])->group(function () {

    Route::post('auth/logout', [\App\Http\Controllers\Api\Auth\LoginController::class, 'destroy']);
    Route::get('auth/me',      [\App\Http\Controllers\Api\Auth\LoginController::class, 'show']);

    // ── Owner routes ─────────────────────────────────────────────────────────
    Route::middleware(['role:owner', 'not-suspended'])->group(function () {

        // Dashboard — single aggregated payload (stats + income series + attention feed)
        Route::get('dashboard', [\App\Http\Controllers\Api\Owner\DashboardController::class, 'index']);

        // Account / settings
        Route::get('account',                          [\App\Http\Controllers\Api\Owner\AccountController::class, 'show']);
        Route::patch('account/profile',                [\App\Http\Controllers\Api\Owner\AccountController::class, 'updateProfile']);
        Route::patch('account/preferences',            [\App\Http\Controllers\Api\Owner\AccountController::class, 'updatePreferences']);
        Route::patch('account/notifications',          [\App\Http\Controllers\Api\Owner\AccountController::class, 'updateNotifications']);
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
        Route::apiResource('tenants', \App\Http\Controllers\Api\Owner\TenantController::class);

        // Agreements
        Route::apiResource('agreements', \App\Http\Controllers\Api\Owner\AgreementController::class);

        // Invoices
        Route::get('invoices',                         [\App\Http\Controllers\Api\Owner\InvoiceController::class, 'index']);
        Route::get('invoices/{invoice}',               [\App\Http\Controllers\Api\Owner\InvoiceController::class, 'show']);
        Route::patch('invoices/{invoice}',             [\App\Http\Controllers\Api\Owner\InvoiceController::class, 'updateStatus']);
        Route::patch('invoices/{invoice}/status',      [\App\Http\Controllers\Api\Owner\InvoiceController::class, 'updateStatus']);
        Route::post('invoices/{invoice}/send',         [\App\Http\Controllers\Api\Owner\InvoiceController::class, 'send']);
        Route::post('invoices/{invoice}/payments',     [\App\Http\Controllers\Api\Owner\InvoiceController::class, 'recordPayment']);

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

        // Invoices scoped to the tenant
        Route::get('invoices',             [\App\Http\Controllers\Api\Tenant\TenantInvoiceController::class, 'index']);
        Route::post('invoices/{invoice}/pay', [\App\Http\Controllers\Api\Tenant\TenantInvoiceController::class, 'pay']);

        // Tickets filed by this tenant
        Route::get('tickets',              [\App\Http\Controllers\Api\Tenant\TenantTicketController::class, 'index']);
        Route::get('tickets/{ticket}',     [\App\Http\Controllers\Api\Tenant\TenantTicketController::class, 'show']);
        Route::post('tickets',             [\App\Http\Controllers\Api\Tenant\TenantTicketController::class, 'store']);
        Route::post('tickets/{ticket}/comments', [\App\Http\Controllers\Api\Tenant\TenantTicketController::class, 'addComment']);

        // Profile (reads from + writes to the tenant's own user record)
        Route::get('profile',              [\App\Http\Controllers\Api\Tenant\TenantProfileController::class, 'show']);
        Route::patch('profile',            [\App\Http\Controllers\Api\Tenant\TenantProfileController::class, 'update']);
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
    });

    // ── Billplz webhook (no role guard — validated by X-Signature) ────────────
    Route::post('webhooks/billplz', [\App\Http\Controllers\Api\WebhookController::class, 'billplz'])
        ->withoutMiddleware('auth:sanctum');
});
