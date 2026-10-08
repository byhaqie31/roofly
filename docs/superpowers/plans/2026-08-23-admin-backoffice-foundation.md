# Admin back office — sub-project 1: Foundation — implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A third shell (`/admin/*`) with its own Admin Portal login, per-admin permissions (super-admin + Operations preset), an audit log, a platform dashboard, and summary-only owner/tenant control with warn / suspend — backend `/api/admin/*` first, then the Nuxt admin shell in both demo and API adapters.

**Architecture:** Backend: `users` gains admin/suspension columns, Spatie Permission holds the fixed permission list, one `AuditLogger` wraps Spatie ActivityLog (`log_name = admin`), Resources pin the visibility tier and contract tests pin the key sets. Frontend: `services/contracts/admin/*` → `demo/services/admin/*` (against `demo/data/admin.ts`) → `services/api/admin/*` → `services/useAdminX.ts` selectors; pages only import selectors. `features.admin` is forced off in demo.

**Tech Stack:** Laravel 13 + Sanctum + Spatie Permission 8 + Spatie ActivityLog 4 (PHPUnit, sqlite in-memory) · Nuxt 4 + Vue 3 + Pinia + Reka UI + TanStack Table + vue-i18n (en + ms).

**Spec:** [docs/superpowers/specs/2026-08-23-admin-backoffice-foundation-design.md](../specs/2026-08-23-admin-backoffice-foundation-design.md) — every decision there is locked; this plan does not re-open any. Adapter rules: [docs/superpowers/specs/2026-08-23-demo-adapter-split-design.md](../specs/2026-08-23-demo-adapter-split-design.md).

## Global constraints

- **No git commits or pushes in any task.** Leave everything in the working tree; the user commits after review.
- **No Playwright / browser automation.** Gates are static: `docker exec roofly-backend php artisan test` and `docker exec roofly-frontend npm run typecheck`.
- Typecheck has **5 known pre-existing errors** (`InvoiceViewModal.vue` Tone, `payments.vue` Tone + possibly-undefined, `Icon.vue` + `EmptyState.vue` lucide IconProps). Gate = those 5 and **0 new**.
- Import direction: `frontend/app/demo/**` never imports `useApi`; `frontend/app/services/api/**` never imports `~/demo`; no `if (useMock)` anywhere. Final grep in Task 26.
- Money: integer sen only; **the admin shows no money at all** — counts only.
- Strings: sentence case, every admin label in both `i18n/locales/en.json` and `ms.json`, never a literal `@` in a translation value.
- Font weights 400 / 600 only. Reka `<TabsRoot v-model>` (never `v-model:value`).
- Mobile patterns per UI-STANDARDS § 11 (tabs → `<Select>` under `sm`, tables → cards under `sm`, section headers stack).
- Admins are `users` rows with `role = admin`. No impersonation. Admin sees summaries only (spec § 6).
- Backend response keys are camelCase; list endpoints return `{ data: T[], meta: { page, perPage, total, lastPage } }`.
- API-mode credentials after this plan: super-admin `admin@roofly.my` / `password`, ops admin `ops@roofly.my` / `password` (both from `DemoSeeder`).

## File map

**Backend (create)**
```
database/migrations/2026_08_23_000001_add_admin_fields_to_users_table.php
database/migrations/2026_08_23_000002_create_admin_invites_table.php
database/seeders/AdminPermissionSeeder.php
app/Support/AdminPermissions.php          permission catalogue + Operations preset (single source)
app/Support/PlanCaps.php                  plan tier → units cap
app/Support/OwnerCounts.php               counts strip for one owner
app/Support/OwnerTenantsQuery.php         "tenants of owner X" query (shared by admin + dashboard)
app/Services/AuditLogger.php
app/Models/AdminInvite.php
app/Http/Middleware/EnsureNotSuspended.php
app/Http/Middleware/TouchLastActive.php
app/Http/Controllers/Api/Admin/{AdminLoginController,AcceptInviteController,DashboardController,
  OwnerController,TenantController,AdminUserController,PermissionController,AuditController}.php
app/Http/Requests/Admin/{WarnOwnerRequest,SuspendOwnerRequest,StoreAdminRequest,UpdateAdminRequest,AcceptInviteRequest}.php
app/Http/Resources/Admin/{AdminOwnerResource,AdminPropertySummaryResource,AdminTenantResource,AdminUserResource,AuditEntryResource}.php
app/Notifications/{OwnerWarning,AdminInvite}.php
tests/Feature/Admin/{AdminAuthTest,AdminPermissionTest,AuditLoggerTest,SuspensionTest,AdminResourcesTest,
  AdminOwnerTest,AdminTenantTest,AdminUserTest,AdminAuditTest,AdminDashboardTest}.php
```
**Backend (modify)** `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `routes/api.php`, `app/Http/Resources/AuthUserResource.php`, `app/Http/Controllers/Api/Auth/LoginController.php`, `config/app.php`, `database/seeders/DemoSeeder.php`, `tests/Feature/AuthContractTest.php`, `tests/Feature/DemoSeederTest.php`.

**Frontend (create)**
```
app/types/admin.ts
app/services/contracts/admin/{dashboard,owners,tenants,admins,audit}.ts
app/demo/data/admin.ts
app/demo/services/admin/{dashboard,owners,tenants,admins,audit}.ts
app/services/api/admin/{dashboard,owners,tenants,admins,audit}.ts
app/services/{useAdminDashboard,useAdminOwners,useAdminTenants,useAdminAdmins,useAdminAudit}.ts
app/composables/useAdminPermissions.ts
app/middleware/env.global.ts                (renamed from demo-only.global.ts)
app/layouts/{admin,auth-admin}.vue
app/components/admin/{SidebarNav,StatTile,AttentionList,OwnerStatusPill,WarnOwnerModal,SuspendOwnerModal,
  AdminFormModal,AuditTable,DataTableShell}.vue
app/pages/admin/{login,accept-invite,index,audit,settings}.vue
app/pages/admin/owners/{index,[id]}.vue
app/pages/admin/tenants/{index,[id]}.vue
app/pages/suspended.vue
```
**Frontend (modify)** `nuxt.config.ts`, `app/composables/useEnv.ts`, `app/types/auth.ts`, `app/services/contracts/auth.ts`, `app/demo/auth.ts`, `app/services/api/auth.ts`, `app/stores/auth.ts`, `app/composables/useApi.ts`, `app/middleware/auth.global.ts`, `app/pages/index.vue`, `app/assets/css/tokens.css`, `tailwind.config.ts`, `i18n/locales/{en,ms}.json`, `docs/frontend/UI-STANDARDS.md`, `docs/frontend/MOCK-POC.md`, `.claude/CLAUDE.md`.

---

## Part A — Backend

### Task 1: Migration + User model admin fields + AdminInvite model + factory states

**Files:**
- Create: `backend/database/migrations/2026_08_23_000001_add_admin_fields_to_users_table.php`
- Create: `backend/database/migrations/2026_08_23_000002_create_admin_invites_table.php`
- Create: `backend/app/Models/AdminInvite.php`
- Modify: `backend/app/Models/User.php`
- Modify: `backend/database/factories/UserFactory.php`
- Test: `backend/tests/Feature/Admin/UserAdminColumnsTest.php`

**Interfaces produced:**
- `User` attributes: `is_super_admin: bool`, `suspended_at: ?Carbon`, `suspension_reason: ?string`, `last_active_at: ?Carbon`, `disabled_at: ?Carbon`, `first_login_at: ?Carbon`; helpers `isSuspended(): bool`, `isDisabled(): bool`; relation `adminInvites(): HasMany`.
- `AdminInvite` model: `user_id`, `token_hash`, `expires_at`, `accepted_at`; `isUsable(): bool`.
- `UserFactory::admin()`, `UserFactory::superAdmin()`, `UserFactory::suspended(string $reason = 'Unpaid subscription')`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Admin/UserAdminColumnsTest.php
namespace Tests\Feature\Admin;

use App\Models\AdminInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdminColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_columns_default_and_cast(): void
    {
        $u = User::factory()->owner()->create();
        $this->assertFalse($u->is_super_admin);
        $this->assertNull($u->suspended_at);
        $this->assertFalse($u->isSuspended());
        $this->assertFalse($u->isDisabled());

        $u->update(['suspended_at' => now(), 'suspension_reason' => 'Unpaid subscription']);
        $this->assertTrue($u->fresh()->isSuspended());
    }

    public function test_factory_states(): void
    {
        $super = User::factory()->superAdmin()->create();
        $this->assertSame('admin', $super->role->value);
        $this->assertTrue($super->is_super_admin);

        $ops = User::factory()->admin()->create();
        $this->assertSame('admin', $ops->role->value);
        $this->assertFalse($ops->is_super_admin);

        $this->assertTrue(User::factory()->owner()->suspended()->create()->isSuspended());
    }

    public function test_admin_invite_usability(): void
    {
        $admin = User::factory()->admin()->create();
        $live = AdminInvite::create(['user_id' => $admin->id, 'token_hash' => hash('sha256', 'a'), 'expires_at' => now()->addDay()]);
        $expired = AdminInvite::create(['user_id' => $admin->id, 'token_hash' => hash('sha256', 'b'), 'expires_at' => now()->subDay()]);
        $used = AdminInvite::create(['user_id' => $admin->id, 'token_hash' => hash('sha256', 'c'), 'expires_at' => now()->addDay(), 'accepted_at' => now()]);

        $this->assertTrue($live->isUsable());
        $this->assertFalse($expired->isUsable());
        $this->assertFalse($used->isUsable());
        $this->assertCount(3, $admin->adminInvites);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec roofly-backend php artisan test --filter UserAdminColumnsTest`
Expected: FAIL — `no such column: is_super_admin` / `Call to undefined method superAdmin()`.

- [ ] **Step 3: Write the migrations**

```php
<?php
// backend/database/migrations/2026_08_23_000001_add_admin_fields_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('role');
            $table->timestamp('suspended_at')->nullable()->after('invited_by');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
            $table->timestamp('last_active_at')->nullable()->after('suspension_reason');
            $table->timestamp('first_login_at')->nullable()->after('last_active_at');
            $table->timestamp('disabled_at')->nullable()->after('first_login_at'); // admins only
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_super_admin', 'suspended_at', 'suspension_reason', 'last_active_at', 'first_login_at', 'disabled_at']);
        });
    }
};
```

```php
<?php
// backend/database/migrations/2026_08_23_000002_create_admin_invites_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_invites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_invites');
    }
};
```

- [ ] **Step 4: Write the AdminInvite model**

```php
<?php
// backend/app/Models/AdminInvite.php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminInvite extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'accepted_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
```

- [ ] **Step 5: Extend the User model**

In `backend/app/Models/User.php`:

Add to `$fillable` (after `'invited_by'`):
```php
        'is_super_admin',
        'suspended_at',
        'suspension_reason',
        'last_active_at',
        'first_login_at',
        'disabled_at',
```
Add to `casts()`:
```php
            'is_super_admin'           => 'boolean',
            'suspended_at'             => 'datetime',
            'last_active_at'           => 'datetime',
            'first_login_at'           => 'datetime',
            'disabled_at'              => 'datetime',
```
Add relation + helpers (after `ticketComments()` / in Helpers section):
```php
    public function adminInvites(): HasMany
    {
        return $this->hasMany(AdminInvite::class, 'user_id');
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }
```
Also change `getActivitylogOptions()` so the heartbeat column never spams the model log:
```php
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()
            ->logExcept(['last_active_at', 'first_login_at']);
    }
```

- [ ] **Step 6: Add factory states**

In `backend/database/factories/UserFactory.php` add after `invitedTenant()`:
```php
    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::ADMIN, 'is_super_admin' => false]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['role' => UserRole::ADMIN, 'is_super_admin' => true]);
    }

    public function suspended(string $reason = 'Unpaid subscription'): static
    {
        return $this->state(fn () => ['suspended_at' => now(), 'suspension_reason' => $reason]);
    }
```

- [ ] **Step 7: Run the gate**

Run: `docker exec roofly-backend php artisan test`
Expected: all green, including `UserAdminColumnsTest` (3 tests).

---

### Task 2: Permission catalogue, seeder, super-admin Gate bypass, `can:` on admin routes

**Files:**
- Create: `backend/app/Support/AdminPermissions.php`
- Create: `backend/database/seeders/AdminPermissionSeeder.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Modify: `backend/routes/api.php` (add the admin group skeleton + `GET admin/permissions`)
- Create: `backend/app/Http/Controllers/Api/Admin/PermissionController.php`
- Test: `backend/tests/Feature/Admin/AdminPermissionTest.php`

**Interfaces produced:**
- `AdminPermissions::ALL` — ordered `array<string, array{preset: bool}>`; `AdminPermissions::keys(): string[]`; `AdminPermissions::operationsPreset(): string[]`; per-key constants (`AdminPermissions::OWNERS_VIEW` …).
- `AdminPermissionSeeder` — idempotent; every test that grants permissions calls `$this->seed(AdminPermissionSeeder::class)` in `setUp`.
- Route group: `Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])` inside the protected group. Later tasks add routes into this group.
- `GET /api/admin/permissions` → `{ permissions: [{key, preset}], preset: string[] }`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Admin/AdminPermissionTest.php
namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
    }

    public function test_seeder_creates_all_keys_and_is_idempotent(): void
    {
        $this->assertSame(13, \Spatie\Permission\Models\Permission::count());
        $this->seed(AdminPermissionSeeder::class);
        $this->assertSame(13, \Spatie\Permission\Models\Permission::count());
        $this->assertContains('owners.view', AdminPermissions::operationsPreset());
        $this->assertNotContains('admins.manage', AdminPermissions::operationsPreset());
    }

    public function test_permissions_endpoint_requires_admins_manage(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/admin/permissions')->assertForbidden();
    }

    public function test_permissions_endpoint_returns_catalogue(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo(AdminPermissions::ADMINS_MANAGE);
        Sanctum::actingAs($admin);

        $res = $this->getJson('/api/admin/permissions')->assertOk();
        $this->assertSame(['permissions', 'preset'], array_keys($res->json()));
        $this->assertSame(['key', 'preset'], array_keys($res->json('permissions.0')));
        $this->assertCount(13, $res->json('permissions'));
    }

    public function test_super_admin_bypasses_every_check(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->getJson('/api/admin/permissions')->assertOk();
    }

    public function test_owner_and_tenant_are_blocked_from_admin_routes(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->getJson('/api/admin/permissions')->assertForbidden();
        Sanctum::actingAs(User::factory()->tenant()->create());
        $this->getJson('/api/admin/permissions')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec roofly-backend php artisan test --filter AdminPermissionTest`
Expected: FAIL — class `AdminPermissionSeeder` not found.

- [ ] **Step 3: Write the catalogue**

```php
<?php
// backend/app/Support/AdminPermissions.php
namespace App\Support;

/**
 * The fixed admin permission list (spec § 5). Single source for the seeder,
 * the `can:` middleware keys in routes/api.php, and GET /api/admin/permissions.
 * Never hard-code a key string elsewhere — reference the constants.
 */
final class AdminPermissions
{
    public const DASHBOARD_VIEW    = 'dashboard.view';
    public const OWNERS_VIEW       = 'owners.view';
    public const TENANTS_VIEW      = 'tenants.view';
    public const OWNERS_WARN       = 'owners.warn';
    public const OWNERS_SUSPEND    = 'owners.suspend';
    public const OWNERS_PLAN       = 'owners.plan';
    public const SUPPORT_MANAGE    = 'support.manage';
    public const BROADCAST_SEND    = 'broadcast.send';
    public const SETTINGS_CHANNELS = 'settings.channels';
    public const SETTINGS_FLAGS    = 'settings.flags';
    public const ADMINS_MANAGE     = 'admins.manage';
    public const AUDIT_VIEW        = 'audit.view';
    public const USERS_DELETE      = 'users.delete';

    public const GUARD = 'web';

    /** key => ['preset' => in Operations preset]. Order is the display order. */
    public const ALL = [
        self::DASHBOARD_VIEW    => ['preset' => true],
        self::OWNERS_VIEW       => ['preset' => true],
        self::TENANTS_VIEW      => ['preset' => true],
        self::OWNERS_WARN       => ['preset' => true],
        self::OWNERS_SUSPEND    => ['preset' => true],
        self::OWNERS_PLAN       => ['preset' => false],
        self::SUPPORT_MANAGE    => ['preset' => true],
        self::BROADCAST_SEND    => ['preset' => true],
        self::SETTINGS_CHANNELS => ['preset' => false],
        self::SETTINGS_FLAGS    => ['preset' => false],
        self::ADMINS_MANAGE     => ['preset' => false],
        self::AUDIT_VIEW        => ['preset' => false],
        self::USERS_DELETE      => ['preset' => false],
    ];

    /** @return string[] */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /** @return string[] */
    public static function operationsPreset(): array
    {
        return array_keys(array_filter(self::ALL, fn ($p) => $p['preset']));
    }

    /** @return array<int, array{key: string, preset: bool}> */
    public static function catalogue(): array
    {
        return array_map(
            fn ($key, $meta) => ['key' => $key, 'preset' => $meta['preset']],
            array_keys(self::ALL),
            self::ALL,
        );
    }
}
```

- [ ] **Step 4: Write the seeder**

```php
<?php
// backend/database/seeders/AdminPermissionSeeder.php
namespace Database\Seeders;

use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AdminPermissions::keys() as $key) {
            Permission::findOrCreate($key, AdminPermissions::GUARD);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```

- [ ] **Step 5: Super-admin bypass in AppServiceProvider**

Replace `backend/app/Providers/AppServiceProvider.php` `boot()`:
```php
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        // Super-admins pass every ability check (spec § 5). Returning null
        // lets Spatie's own Gate::before resolve normal permissions.
        Gate::before(function ($user) {
            return ($user instanceof User && $user->is_super_admin) ? true : null;
        });
    }
```
Add imports: `use App\Models\User;` and `use Illuminate\Support\Facades\Gate;`.

- [ ] **Step 6: Controller + route group skeleton**

```php
<?php
// backend/app/Http/Controllers/Api/Admin/PermissionController.php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPermissions;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'permissions' => AdminPermissions::catalogue(),
            'preset'      => AdminPermissions::operationsPreset(),
        ]);
    }
}
```

In `backend/routes/api.php`, inside the `auth:sanctum` group, after the tenant `/me` group and before the webhook, add:
```php
    // ── Admin routes (spec § 9). Every write goes through AuditLogger. ──────
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('permissions', [\App\Http\Controllers\Api\Admin\PermissionController::class, 'index'])
            ->middleware('can:' . \App\Support\AdminPermissions::ADMINS_MANAGE);
    });
```

- [ ] **Step 7: Run the gate**

Run: `docker exec roofly-backend php artisan test`
Expected: green. `AdminPermissionTest` 5 tests pass (Laravel registers the `can` middleware alias by default; Spatie's `HasRoles` on `User` + `Gate::before` make `can:owners.view` resolve).

---

### Task 3: AuditLogger

**Files:**
- Create: `backend/app/Services/AuditLogger.php`
- Test: `backend/tests/Feature/Admin/AuditLoggerTest.php`

**Interfaces produced:**
- `AuditLogger::record(string $action, ?Model $subject = null, array $before = [], array $after = [], ?string $reason = null): Activity` — actor = `auth()->user()`; `log_name = 'admin'`, `event = $action`, `description = $action`, `properties = {before, after, reason, ip}`.
- Action name constants on the same class: `ADMIN_LOGIN`, `ADMIN_INVITE_SENT`, `ADMIN_INVITE_ACCEPTED`, `ADMIN_PERMISSIONS_CHANGED`, `ADMIN_DISABLED`, `ADMIN_ENABLED`, `OWNER_WARNED`, `OWNER_SUSPENDED`, `OWNER_UNSUSPENDED`, `TENANT_INVITE_RESENT`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Admin/AuditLoggerTest.php
namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_writes_admin_log_entry_with_actor_subject_and_properties(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        $this->actingAs($admin);

        $entry = app(AuditLogger::class)->record(
            AuditLogger::OWNER_SUSPENDED,
            $owner,
            ['suspendedAt' => null],
            ['suspendedAt' => '2026-08-23T00:00:00Z'],
            'Unpaid subscription for 2 months',
        );

        $this->assertInstanceOf(Activity::class, $entry);
        $row = Activity::inLog('admin')->latest('id')->first();
        $this->assertSame('owner.suspended', $row->event);
        $this->assertSame($admin->id, $row->causer_id);
        $this->assertSame(User::class, $row->subject_type);
        $this->assertSame($owner->id, $row->subject_id);
        $this->assertSame(['before', 'after', 'reason', 'ip'], array_keys($row->properties->toArray()));
        $this->assertSame('Unpaid subscription for 2 months', $row->properties['reason']);
    }

    public function test_record_without_subject(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        app(AuditLogger::class)->record(AuditLogger::ADMIN_LOGIN);
        $row = Activity::inLog('admin')->latest('id')->first();
        $this->assertSame('admin.login', $row->event);
        $this->assertNull($row->subject_id);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec roofly-backend php artisan test --filter AuditLoggerTest`
Expected: FAIL — class `AuditLogger` not found.

- [ ] **Step 3: Write the service**

```php
<?php
// backend/app/Services/AuditLogger.php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * The one door for admin writes into the audit log (spec § 5). Stored in
 * Spatie ActivityLog with log_name = admin; the owner/tenant model logs
 * (log_name = default) are untouched.
 */
class AuditLogger
{
    public const LOG_NAME = 'admin';

    public const ADMIN_LOGIN               = 'admin.login';
    public const ADMIN_INVITE_SENT         = 'admin.invite_sent';
    public const ADMIN_INVITE_ACCEPTED     = 'admin.invite_accepted';
    public const ADMIN_PERMISSIONS_CHANGED = 'admin.permissions_changed';
    public const ADMIN_DISABLED            = 'admin.disabled';
    public const ADMIN_ENABLED             = 'admin.enabled';
    public const OWNER_WARNED              = 'owner.warned';
    public const OWNER_SUSPENDED           = 'owner.suspended';
    public const OWNER_UNSUSPENDED         = 'owner.unsuspended';
    public const TENANT_INVITE_RESENT      = 'tenant.invite_resent';

    /** Every SP1 action, for validation of the audit filter. */
    public const ACTIONS = [
        self::ADMIN_LOGIN, self::ADMIN_INVITE_SENT, self::ADMIN_INVITE_ACCEPTED,
        self::ADMIN_PERMISSIONS_CHANGED, self::ADMIN_DISABLED, self::ADMIN_ENABLED,
        self::OWNER_WARNED, self::OWNER_SUSPENDED, self::OWNER_UNSUSPENDED,
        self::TENANT_INVITE_RESENT,
    ];

    public function record(
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
    ): Activity {
        $log = activity(self::LOG_NAME)
            ->event($action)
            ->withProperties([
                'before' => $before,
                'after'  => $after,
                'reason' => $reason,
                'ip'     => request()?->ip(),
            ]);

        if ($actor = auth()->user()) {
            $log->causedBy($actor);
        }
        if ($subject !== null) {
            $log->performedOn($subject);
        }

        return $log->log($action);
    }
}
```

- [ ] **Step 4: Run the gate**

Run: `docker exec roofly-backend php artisan test`
Expected: green.

---

### Task 4: Admin auth — login, accept-invite, customer login rejects admins, `AuthUserResource` permissions, `TouchLastActive`

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/AdminLoginController.php`
- Create: `backend/app/Http/Controllers/Api/Admin/AcceptInviteController.php`
- Create: `backend/app/Http/Requests/Admin/AcceptInviteRequest.php`
- Create: `backend/app/Http/Middleware/TouchLastActive.php`
- Modify: `backend/app/Http/Resources/AuthUserResource.php`
- Modify: `backend/app/Http/Controllers/Api/Auth/LoginController.php`
- Modify: `backend/bootstrap/app.php`, `backend/routes/api.php`
- Modify: `backend/tests/Feature/AuthContractTest.php` (key set gains 2 keys)
- Test: `backend/tests/Feature/Admin/AdminAuthTest.php`

**Interfaces produced:**
- `POST /api/admin/auth/login {email,password}` → `{ user: AuthUser }` (201-free 200); 401 on non-admin / disabled / bad password. Logs `admin.login`.
- `POST /api/admin/auth/accept-invite {token,password,password_confirmation}` → `{ user: AuthUser }`; 422 on bad/expired token. Logs `admin.invite_accepted`.
- `AuthUser` JSON: `{ id, name, email, phone, role, permissions: string[], isSuperAdmin: bool }` — `permissions` is `[]` for owners/tenants; super-admin gets the full catalogue so the frontend needs no special-casing.
- Middleware alias `touch-active` (applied to the whole `auth:sanctum` group): writes `users.last_active_at` at most every 10 minutes, quietly.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Admin/AdminAuthTest.php
namespace Tests\Feature\Admin;

use App\Models\AdminInvite;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = ['id', 'name', 'email', 'phone', 'role', 'permissions', 'isSuperAdmin'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
    }

    public function test_admin_login_accepts_admin_and_logs(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'ops@x.my', 'password' => Hash::make('secret123')]);
        $admin->givePermissionTo(AdminPermissions::OWNERS_VIEW);

        $res = $this->postJson('/api/admin/auth/login', ['email' => 'ops@x.my', 'password' => 'secret123'])->assertOk();
        $this->assertSame(['user'], array_keys($res->json()));
        $this->assertSame(self::KEYS, array_keys($res->json('user')));
        $this->assertSame(['owners.view'], $res->json('user.permissions'));
        $this->assertFalse($res->json('user.isSuperAdmin'));
        $this->assertSame('admin.login', Activity::inLog('admin')->latest('id')->first()->event);
        $this->assertNotNull($admin->fresh()->first_login_at);
    }

    public function test_super_admin_login_returns_full_catalogue(): void
    {
        User::factory()->superAdmin()->create(['email' => 'su@x.my', 'password' => Hash::make('secret123')]);
        $res = $this->postJson('/api/admin/auth/login', ['email' => 'su@x.my', 'password' => 'secret123'])->assertOk();
        $this->assertTrue($res->json('user.isSuperAdmin'));
        $this->assertSame(AdminPermissions::keys(), $res->json('user.permissions'));
    }

    public function test_admin_login_rejects_owner_disabled_admin_and_bad_password(): void
    {
        User::factory()->owner()->create(['email' => 'o@x.my', 'password' => Hash::make('secret123')]);
        $this->postJson('/api/admin/auth/login', ['email' => 'o@x.my', 'password' => 'secret123'])->assertUnauthorized();

        User::factory()->admin()->create(['email' => 'd@x.my', 'password' => Hash::make('secret123'), 'disabled_at' => now()]);
        $this->postJson('/api/admin/auth/login', ['email' => 'd@x.my', 'password' => 'secret123'])->assertUnauthorized();

        User::factory()->admin()->create(['email' => 'a@x.my', 'password' => Hash::make('secret123')]);
        $this->postJson('/api/admin/auth/login', ['email' => 'a@x.my', 'password' => 'wrong'])->assertUnauthorized();
    }

    public function test_customer_login_rejects_admin(): void
    {
        User::factory()->admin()->create(['email' => 'a@x.my', 'password' => Hash::make('secret123')]);
        $this->postJson('/api/auth/login', ['email' => 'a@x.my', 'password' => 'secret123'])->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_me_includes_permissions_for_every_role(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());
        $res = $this->getJson('/api/auth/me')->assertOk();
        $this->assertSame(self::KEYS, array_keys($res->json()));
        $this->assertSame([], $res->json('permissions'));
        $this->assertFalse($res->json('isSuperAdmin'));
    }

    public function test_accept_invite_sets_password_and_logs_in(): void
    {
        $admin = User::factory()->admin()->create(['password' => null]);
        AdminInvite::create(['user_id' => $admin->id, 'token_hash' => hash('sha256', 'plain-token'), 'expires_at' => now()->addDays(7)]);

        $res = $this->postJson('/api/admin/auth/accept-invite', [
            'token' => 'plain-token', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertOk();
        $this->assertSame(self::KEYS, array_keys($res->json('user')));
        $this->assertTrue(Hash::check('secret123', $admin->fresh()->password));
        $this->assertNotNull(AdminInvite::first()->accepted_at);
        $this->assertSame('admin.invite_accepted', Activity::inLog('admin')->latest('id')->first()->event);

        // Token is single-use.
        $this->postJson('/api/admin/auth/accept-invite', [
            'token' => 'plain-token', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertUnprocessable();
    }

    public function test_accept_invite_rejects_expired_token(): void
    {
        $admin = User::factory()->admin()->create(['password' => null]);
        AdminInvite::create(['user_id' => $admin->id, 'token_hash' => hash('sha256', 'old'), 'expires_at' => now()->subDay()]);
        $this->postJson('/api/admin/auth/accept-invite', ['token' => 'old', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertUnprocessable();
    }

    public function test_touch_last_active_is_throttled(): void
    {
        $owner = User::factory()->owner()->create(['last_active_at' => null]);
        Sanctum::actingAs($owner);
        $this->getJson('/api/auth/me')->assertOk();
        $first = $owner->fresh()->last_active_at;
        $this->assertNotNull($first);

        $this->travel(5)->minutes();
        $this->getJson('/api/auth/me')->assertOk();
        $this->assertTrue($owner->fresh()->last_active_at->equalTo($first));

        $this->travel(6)->minutes();
        $this->getJson('/api/auth/me')->assertOk();
        $this->assertTrue($owner->fresh()->last_active_at->greaterThan($first));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec roofly-backend php artisan test --filter AdminAuthTest`
Expected: FAIL — 404 on `/api/admin/auth/login`.

- [ ] **Step 3: AuthUserResource with permissions**

```php
<?php
// backend/app/Http/Resources/AuthUserResource.php
namespace App\Http\Resources;

use App\Support\AdminPermissions;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    public function toArray($request): array
    {
        $isAdmin = $this->role?->value === 'admin';
        $permissions = ! $isAdmin
            ? []
            : ($this->is_super_admin
                ? AdminPermissions::keys()
                : $this->getPermissionNames()->values()->all());

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'role'         => $this->role?->value,
            'permissions'  => $permissions,
            'isSuperAdmin' => (bool) $this->is_super_admin,
        ];
    }
}
```

Update `backend/tests/Feature/AuthContractTest.php` — both `assertSame(['id', 'name', 'email', 'phone', 'role'], …)` lines become `['id', 'name', 'email', 'phone', 'role', 'permissions', 'isSuperAdmin']`.

- [ ] **Step 4: Customer login rejects admins + sets first_login_at**

Replace `store()` in `backend/app/Http/Controllers/Api/Auth/LoginController.php`:
```php
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = Auth::user();

        // Admins use the Admin Portal (POST /api/admin/auth/login); the
        // customer form is never a back door into the back office.
        if ($user->isAdmin()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->first_login_at === null) {
            $user->forceFill(['first_login_at' => now()])->saveQuietly();
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => (new AuthUserResource($user))->resolve(),
            'token' => $token,
        ]);
    }
```

- [ ] **Step 5: Admin login + accept-invite controllers**

```php
<?php
// backend/app/Http/Controllers/Api/Admin/AdminLoginController.php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthUserResource;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController extends Controller
{
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = Auth::user();
        if (! $user->isAdmin() || $user->isDisabled()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->first_login_at === null) {
            $user->forceFill(['first_login_at' => now()])->saveQuietly();
        }

        $audit->record(AuditLogger::ADMIN_LOGIN, $user);

        return response()->json(['user' => (new AuthUserResource($user))->resolve()]);
    }
}
```

```php
<?php
// backend/app/Http/Requests/Admin/AcceptInviteRequest.php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guest route; the token is the authorisation
    }

    public function rules(): array
    {
        return [
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
```

```php
<?php
// backend/app/Http/Controllers/Api/Admin/AcceptInviteController.php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcceptInviteRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\AdminInvite;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AcceptInviteController extends Controller
{
    public function store(AcceptInviteRequest $request, AuditLogger $audit): JsonResponse
    {
        $invite = AdminInvite::where('token_hash', hash('sha256', $request->string('token')))->first();

        if ($invite === null || ! $invite->isUsable() || ! $invite->user?->isAdmin() || $invite->user->isDisabled()) {
            throw ValidationException::withMessages(['token' => 'This invite link is invalid or has expired.']);
        }

        $user = $invite->user;
        $user->forceFill([
            'password'       => Hash::make($request->string('password')),
            'first_login_at' => $user->first_login_at ?? now(),
        ])->save();
        $invite->update(['accepted_at' => now()]);

        Auth::guard('web')->login($user);
        $audit->record(AuditLogger::ADMIN_INVITE_ACCEPTED, $user);

        return response()->json(['user' => (new AuthUserResource($user))->resolve()]);
    }
}
```

- [ ] **Step 6: TouchLastActive middleware**

```php
<?php
// backend/app/Http/Middleware/TouchLastActive.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Heartbeat for owners, tenants and admins — at most one write per 10 min. */
class TouchLastActive
{
    public const THROTTLE_MINUTES = 10;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ($user->last_active_at === null || $user->last_active_at->lt(now()->subMinutes(self::THROTTLE_MINUTES)))) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
```

In `backend/bootstrap/app.php` add aliases:
```php
        $middleware->alias([
            'role'          => \App\Http\Middleware\EnsureRole::class,
            'touch-active'  => \App\Http\Middleware\TouchLastActive::class,
            'not-suspended' => \App\Http\Middleware\EnsureNotSuspended::class, // Task 5
        ]);
```
(Create the `EnsureNotSuspended` class in Task 5 before running that task's tests; for this task's gate, temporarily omit the `not-suspended` line or add it together with Task 5's class — simplest: add only `touch-active` now, and `not-suspended` in Task 5.)

- [ ] **Step 7: Routes**

In `backend/routes/api.php`:
- Add to the public section:
```php
// ── Public: Admin Portal auth (spec § 4) ─────────────────────────────────────
Route::prefix('admin/auth')->group(function () {
    Route::post('login',         [\App\Http\Controllers\Api\Admin\AdminLoginController::class, 'store']);
    Route::post('accept-invite', [\App\Http\Controllers\Api\Admin\AcceptInviteController::class, 'store']);
});
```
- Change `Route::middleware('auth:sanctum')->group(` to `Route::middleware(['auth:sanctum', 'touch-active'])->group(`.

- [ ] **Step 8: Run the gate**

Run: `docker exec roofly-backend php artisan test`
Expected: green (8 new tests + updated `AuthContractTest`).

---

### Task 5: Suspension enforcement (`EnsureNotSuspended`)

**Files:**
- Create: `backend/app/Http/Middleware/EnsureNotSuspended.php`
- Modify: `backend/bootstrap/app.php` (alias `not-suspended`), `backend/routes/api.php` (owner group)
- Test: `backend/tests/Feature/Admin/SuspensionTest.php`

**Interfaces produced:** suspended owner on any `role:owner` route → `403 { code: "account_suspended", message }`. `/auth/me`, `/auth/logout` and all `/me/*` (tenant) routes are unaffected.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Admin/SuspensionTest.php
namespace Tests\Feature\Admin;

use App\Models\Agreement;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_owner_gets_403_account_suspended_on_owner_routes(): void
    {
        $owner = User::factory()->owner()->suspended('Unpaid subscription')->create();
        Sanctum::actingAs($owner);

        $res = $this->getJson('/api/properties')->assertForbidden();
        $this->assertSame('account_suspended', $res->json('code'));
        $this->assertSame(['code', 'message'], array_keys($res->json()));
        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_suspended_owner_can_still_probe_me_and_logout(): void
    {
        Sanctum::actingAs(User::factory()->owner()->suspended()->create());
        $this->getJson('/api/auth/me')->assertOk();
    }

    public function test_suspended_owners_tenant_is_unaffected(): void
    {
        $owner = User::factory()->owner()->suspended()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $unit = Unit::factory()->create(['property_id' => $property->id]);
        $tenant = User::factory()->tenant()->create(['invited_by' => $owner->id]);
        Agreement::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'status' => 'active']);

        Sanctum::actingAs($tenant);
        $this->getJson('/api/me/agreement')->assertOk();
    }

    public function test_unsuspended_owner_regains_access(): void
    {
        $owner = User::factory()->owner()->suspended()->create();
        Sanctum::actingAs($owner);
        $this->getJson('/api/properties')->assertForbidden();
        $owner->update(['suspended_at' => null, 'suspension_reason' => null]);
        $this->getJson('/api/properties')->assertOk();
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec roofly-backend php artisan test --filter SuspensionTest`
Expected: FAIL — `/api/properties` returns 200 for the suspended owner.

- [ ] **Step 3: Middleware + wiring**

```php
<?php
// backend/app/Http/Middleware/EnsureNotSuspended.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a suspended owner from the owner API (spec § 8). Applied to the
 * role:owner group only — the owner's tenants keep working, and /auth/me
 * still answers so the frontend can show /suspended instead of "bad login".
 */
class EnsureNotSuspended
{
    public const CODE = 'account_suspended';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->isSuspended()) {
            return response()->json([
                'code'    => self::CODE,
                'message' => 'This account has been suspended. Please contact support.',
            ], 403);
        }

        return $next($request);
    }
}
```

`backend/bootstrap/app.php` — add `'not-suspended' => \App\Http\Middleware\EnsureNotSuspended::class,` to the alias array.

`backend/routes/api.php` — change `Route::middleware('role:owner')->group(` to `Route::middleware(['role:owner', 'not-suspended'])->group(`.

- [ ] **Step 4: Run the gate**

Run: `docker exec roofly-backend php artisan test`
Expected: green.

---

### Task 6: Support helpers (`PlanCaps`, `OwnerCounts`, `OwnerTenantsQuery`) + admin Resources with pinned key sets

**Files:**
- Create: `backend/app/Support/PlanCaps.php`, `backend/app/Support/OwnerCounts.php`, `backend/app/Support/OwnerTenantsQuery.php`
- Create: `backend/app/Http/Resources/Admin/AdminOwnerResource.php`, `AdminPropertySummaryResource.php`, `AdminTenantResource.php`
- Test: `backend/tests/Feature/Admin/AdminResourcesTest.php`

**Interfaces produced:**
- `PlanCaps::unitsCap(?PlanTier $tier): ?int` — free 2, starter 5, pro 25, business `null` (unlimited). Same numbers as `AccountController::plans()`.
- `OwnerTenantsQuery::for(string $ownerId): Builder` — tenants invited by the owner OR holding an agreement on a unit they own (the same predicate `Owner\TenantController::index` uses today).
- `OwnerCounts::for(User $owner): array{properties, units, unitsOccupied, tenants, agreementsActive, agreementsExpiring30d, invoicesOverdue, ticketsOpen}` (all ints).
- `AdminOwnerResource` keys (exact order): `id, name, email, phone, businessName, planTier, unitsUsed, unitsCap, status, suspendedAt, suspensionReason, createdAt, lastActiveAt, counts`. `status` is `"active" | "suspended"`. `unitsCap` is `int | null`.
- `AdminPropertySummaryResource` keys: `id, name, address, type, unitsTotal, unitsOccupied, createdAt`; `address = {line, postcode, city, state}`. Requires `units` eager-loaded or uses `withCount`.
- `AdminTenantResource` keys: `id, name, email, phone, status, ownerId, ownerName, propertyName, unitLabel, invitedAt, acceptedAt, createdAt`. Expects the query to eager-load `inviter:id,name` and `agreements.unit.property:id,name,owner_id`; `acceptedAt = first_login_at`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Admin/AdminResourcesTest.php
namespace Tests\Feature\Admin;

use App\Http\Resources\Admin\AdminOwnerResource;
use App\Http\Resources\Admin\AdminPropertySummaryResource;
use App\Http\Resources\Admin\AdminTenantResource;
use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Support\OwnerCounts;
use App\Support\PlanCaps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The privacy line (spec § 6). If a future change adds a key here, this
 * test fails on purpose — widen the tier deliberately, never by accident.
 */
class AdminResourcesTest extends TestCase
{
    use RefreshDatabase;

    public const OWNER_KEYS = ['id', 'name', 'email', 'phone', 'businessName', 'planTier', 'unitsUsed', 'unitsCap', 'status', 'suspendedAt', 'suspensionReason', 'createdAt', 'lastActiveAt', 'counts'];
    public const COUNT_KEYS = ['properties', 'units', 'unitsOccupied', 'tenants', 'agreementsActive', 'agreementsExpiring30d', 'invoicesOverdue', 'ticketsOpen'];
    public const PROPERTY_KEYS = ['id', 'name', 'address', 'type', 'unitsTotal', 'unitsOccupied', 'createdAt'];
    public const TENANT_KEYS = ['id', 'name', 'email', 'phone', 'status', 'ownerId', 'ownerName', 'propertyName', 'unitLabel', 'invitedAt', 'acceptedAt', 'createdAt'];

    public function test_owner_resource_emits_exactly_the_summary_tier(): void
    {
        $owner = User::factory()->owner()->create(['plan_tier' => 'starter', 'bank_account_last4' => '4521']);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        Unit::factory()->create(['property_id' => $property->id, 'status' => 'occupied']);
        Unit::factory()->create(['property_id' => $property->id, 'status' => 'vacant']);

        $json = (new AdminOwnerResource($owner))->resolve();
        $this->assertSame(self::OWNER_KEYS, array_keys($json));
        $this->assertSame(self::COUNT_KEYS, array_keys($json['counts']));
        $this->assertSame('active', $json['status']);
        $this->assertSame(2, $json['unitsUsed']);
        $this->assertSame(5, $json['unitsCap']);
        $this->assertStringNotContainsString('4521', json_encode($json));
    }

    public function test_owner_resource_reports_suspension_and_unlimited_cap(): void
    {
        $owner = User::factory()->owner()->suspended('Late')->create(['plan_tier' => 'business']);
        $json = (new AdminOwnerResource($owner))->resolve();
        $this->assertSame('suspended', $json['status']);
        $this->assertSame('Late', $json['suspensionReason']);
        $this->assertNull($json['unitsCap']);
    }

    public function test_owner_counts(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $unit = Unit::factory()->create(['property_id' => $property->id, 'status' => 'occupied']);
        $tenant = User::factory()->tenant()->create(['invited_by' => $owner->id]);
        $agreement = Agreement::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'status' => 'active', 'end_date' => now()->addDays(10)->toDateString()]);
        Invoice::factory()->create(['agreement_id' => $agreement->id, 'status' => 'overdue']);
        Invoice::factory()->create(['agreement_id' => $agreement->id, 'status' => 'paid']);
        Ticket::factory()->create(['unit_id' => $unit->id, 'status' => 'new']);
        Ticket::factory()->create(['unit_id' => $unit->id, 'status' => 'resolved']);

        $this->assertSame([
            'properties' => 1, 'units' => 1, 'unitsOccupied' => 1, 'tenants' => 1,
            'agreementsActive' => 1, 'agreementsExpiring30d' => 1, 'invoicesOverdue' => 1, 'ticketsOpen' => 1,
        ], OwnerCounts::for($owner));
    }

    public function test_property_summary_resource(): void
    {
        $property = Property::factory()->create(['ownership' => ['purchasePrice' => 123], 'utilities' => ['tnb' => 'x']]);
        Unit::factory()->create(['property_id' => $property->id, 'status' => 'occupied']);
        $json = (new AdminPropertySummaryResource($property->load('units')))->resolve();
        $this->assertSame(self::PROPERTY_KEYS, array_keys($json));
        $this->assertSame(['line', 'postcode', 'city', 'state'], array_keys($json['address']));
        $this->assertSame(1, $json['unitsTotal']);
        $this->assertStringNotContainsString('purchasePrice', json_encode($json));
    }

    public function test_tenant_resource(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'Owner One']);
        $property = Property::factory()->create(['owner_id' => $owner->id, 'name' => 'Suria']);
        $unit = Unit::factory()->create(['property_id' => $property->id, 'label' => 'A-1']);
        $tenant = User::factory()->tenant()->create(['invited_by' => $owner->id, 'first_login_at' => now(), 'personal_info' => ['icNumber' => '880314-14-5687']]);
        Agreement::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'status' => 'active']);

        $json = (new AdminTenantResource($tenant->load(['inviter:id,name', 'agreements.unit.property:id,name,owner_id'])))->resolve();
        $this->assertSame(self::TENANT_KEYS, array_keys($json));
        $this->assertSame('Owner One', $json['ownerName']);
        $this->assertSame('Suria', $json['propertyName']);
        $this->assertSame('A-1', $json['unitLabel']);
        $this->assertNotNull($json['acceptedAt']);
        $this->assertStringNotContainsString('880314', json_encode($json));
    }

    public function test_plan_caps(): void
    {
        $this->assertSame(2, PlanCaps::unitsCap(\App\Enums\PlanTier::FREE));
        $this->assertNull(PlanCaps::unitsCap(\App\Enums\PlanTier::BUSINESS));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec roofly-backend php artisan test --filter AdminResourcesTest`
Expected: FAIL — classes not found.

- [ ] **Step 3: Support classes**

```php
<?php
// backend/app/Support/PlanCaps.php
namespace App\Support;

use App\Enums\PlanTier;

/** Plan tier → units cap. Null = unlimited. Mirrors Owner\AccountController::plans(). */
final class PlanCaps
{
    public static function unitsCap(?PlanTier $tier): ?int
    {
        return match ($tier) {
            PlanTier::STARTER  => 5,
            PlanTier::PRO      => 25,
            PlanTier::BUSINESS => null,
            default            => 2, // FREE and unset
        };
    }
}
```

```php
<?php
// backend/app/Support/OwnerTenantsQuery.php
namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Tenants visible to one owner: invited by them, or on an agreement for a unit they own. */
final class OwnerTenantsQuery
{
    public static function for(string $ownerId): Builder
    {
        return User::query()
            ->where('role', UserRole::TENANT)
            ->where(fn ($q) => $q
                ->where('invited_by', $ownerId)
                ->orWhereHas('agreements.unit.property', fn ($qq) => $qq->where('owner_id', $ownerId))
            );
    }
}
```

```php
<?php
// backend/app/Support/OwnerCounts.php
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
```

- [ ] **Step 4: Resources**

```php
<?php
// backend/app/Http/Resources/Admin/AdminOwnerResource.php
namespace App\Http\Resources\Admin;

use App\Support\OwnerCounts;
use App\Support\PlanCaps;
use Illuminate\Http\Resources\Json\JsonResource;

/** Spec § 6 owner tier. Key set pinned by AdminResourcesTest — do not add keys casually. */
class AdminOwnerResource extends JsonResource
{
    public function toArray($request): array
    {
        $counts = OwnerCounts::for($this->resource);

        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'businessName'     => $this->business_name,
            'planTier'         => $this->plan_tier?->value ?? 'free',
            'unitsUsed'        => $counts['units'],
            'unitsCap'         => PlanCaps::unitsCap($this->plan_tier),
            'status'           => $this->isSuspended() ? 'suspended' : 'active',
            'suspendedAt'      => $this->suspended_at?->toISOString(),
            'suspensionReason' => $this->suspension_reason,
            'createdAt'        => $this->created_at?->toISOString(),
            'lastActiveAt'     => $this->last_active_at?->toISOString(),
            'counts'           => $counts,
        ];
    }
}
```

```php
<?php
// backend/app/Http/Resources/Admin/AdminPropertySummaryResource.php
namespace App\Http\Resources\Admin;

use App\Enums\UnitStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/** Spec § 6 property summary — no ownership / utilities / documents / prices. Load `units` first. */
class AdminPropertySummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        $units = $this->units;

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'address'       => [
                'line'     => $this->address,
                'postcode' => $this->postcode,
                'city'     => $this->city,
                'state'    => $this->state,
            ],
            'type'          => $this->type?->value,
            'unitsTotal'    => $units->count(),
            'unitsOccupied' => $units->where('status', UnitStatus::OCCUPIED)->count(),
            'createdAt'     => $this->created_at?->toISOString(),
        ];
    }
}
```

```php
<?php
// backend/app/Http/Resources/Admin/AdminTenantResource.php
namespace App\Http\Resources\Admin;

use App\Enums\AgreementStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Spec § 6 tenant tier — identity + placement only. Load
 * `inviter:id,name` and `agreements.unit.property:id,name,owner_id` first.
 */
class AdminTenantResource extends JsonResource
{
    public function toArray($request): array
    {
        $agreement = $this->agreements
            ->sortByDesc(fn ($a) => ($a->status === AgreementStatus::ACTIVE ? 1 : 0) . $a->start_date)
            ->first();
        $property = $agreement?->unit?->property;

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'status'       => $this->status,
            'ownerId'      => $this->invited_by ?? $property?->owner_id,
            'ownerName'    => $this->inviter?->name ?? $property?->owner?->name,
            'propertyName' => $property?->name,
            'unitLabel'    => $agreement?->unit?->label,
            'invitedAt'    => $this->invited_at?->toISOString(),
            'acceptedAt'   => $this->first_login_at?->toISOString(),
            'createdAt'    => $this->created_at?->toISOString(),
        ];
    }
}
```

Add the `inviter` relation to `backend/app/Models/User.php` (next to `invitedTenants()`):
```php
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
```
with `use Illuminate\Database\Eloquent\Relations\BelongsTo;`.

- [ ] **Step 5: Run the gate**

Run: `docker exec roofly-backend php artisan test`
Expected: green (6 new tests).

---

### Task 7: Admin owners — list, detail, properties, tenants, history, warn, suspend, unsuspend

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/OwnerController.php`
- Create: `backend/app/Http/Requests/Admin/WarnOwnerRequest.php`, `SuspendOwnerRequest.php`
- Create: `backend/app/Notifications/OwnerWarning.php`
- Create: `backend/app/Http/Resources/Admin/AuditEntryResource.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Admin/AdminOwnerTest.php`

**Interfaces produced:**
- `GET /api/admin/owners?q=&plan=&status=&overCap=1&overdue=1&page=&perPage=` → `{ data: AdminOwner[], meta: {page, perPage, total, lastPage} }`, newest first. `perPage` default 20, max 100.
- `GET /api/admin/owners/{owner}` → `AdminOwner`; 404 for non-owner ids.
- `GET /api/admin/owners/{owner}/properties` → `AdminPropertySummary[]`
- `GET /api/admin/owners/{owner}/tenants` → `AdminTenant[]`
- `GET /api/admin/owners/{owner}/history` → `AuditEntry[]` (admin-log entries whose subject is this owner, plus `owner.signup` synthesised from `created_at`, newest first)
- `POST /api/admin/owners/{owner}/warn { template: "payment_overdue", suspendOn: "YYYY-MM-DD", extraLine?: string }` → 204; sends `OwnerWarning` by mail; logs `owner.warned` with `after = {template, suspendOn, extraLine, text}`.
- `POST /api/admin/owners/{owner}/suspend { reason: string ≥10 }` → `AdminOwner`; 409 if already suspended.
- `POST /api/admin/owners/{owner}/unsuspend` → `AdminOwner`; 409 if not suspended.
- `AuditEntryResource` keys: `id, action, actorId, actorName, subjectType, subjectId, subjectName, before, after, reason, ip, createdAt`. `subjectType` is the short class name lowercased (`"user"`), `subjectName` resolved from the loaded subject when it is a `User`.
- `OwnerWarning::text(string $template, string $suspendOn, ?string $extraLine): string` builds the sent message (also logged).

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Admin/AdminOwnerTest.php
namespace Tests\Feature\Admin;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\OwnerWarning;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminOwnerTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        $this->ops = User::factory()->admin()->create();
        $this->ops->givePermissionTo(AdminPermissions::operationsPreset());
        Sanctum::actingAs($this->ops);
    }

    public function test_list_is_paginated_searchable_and_filterable(): void
    {
        User::factory()->owner()->create(['name' => 'Alpha Aziz', 'plan_tier' => 'pro']);
        User::factory()->owner()->create(['name' => 'Beta Bakar', 'business_name' => 'Beta Homes']);
        User::factory()->owner()->suspended()->create(['name' => 'Gamma Ghani']);
        User::factory()->tenant()->create(['name' => 'Alpha Tenant']);

        $res = $this->getJson('/api/admin/owners')->assertOk();
        $this->assertSame(['data', 'meta'], array_keys($res->json()));
        $this->assertSame(['page', 'perPage', 'total', 'lastPage'], array_keys($res->json('meta')));
        $this->assertSame(3, $res->json('meta.total'));
        $this->assertSame(AdminResourcesTest::OWNER_KEYS, array_keys($res->json('data.0')));

        $this->assertSame(1, $this->getJson('/api/admin/owners?q=beta homes')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/owners?plan=pro')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/owners?status=suspended')->json('meta.total'));
        $this->assertSame(2, $this->getJson('/api/admin/owners?status=active')->json('meta.total'));
        $this->assertSame(2, $this->getJson('/api/admin/owners?perPage=2')->json('meta.lastPage'));
    }

    public function test_list_filters_over_cap_and_overdue(): void
    {
        $over = User::factory()->owner()->create(['plan_tier' => 'free']); // cap 2
        $p = Property::factory()->create(['owner_id' => $over->id]);
        Unit::factory()->count(3)->create(['property_id' => $p->id]);

        $due = User::factory()->owner()->create();
        $p2 = Property::factory()->create(['owner_id' => $due->id]);
        $u2 = Unit::factory()->create(['property_id' => $p2->id]);
        $a2 = Agreement::factory()->create(['unit_id' => $u2->id, 'tenant_id' => User::factory()->tenant()->create()->id]);
        Invoice::factory()->create(['agreement_id' => $a2->id, 'status' => 'overdue']);

        $this->assertSame([$over->id], array_column($this->getJson('/api/admin/owners?overCap=1')->json('data'), 'id'));
        $this->assertSame([$due->id], array_column($this->getJson('/api/admin/owners?overdue=1')->json('data'), 'id'));
    }

    public function test_show_properties_tenants_history(): void
    {
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        Unit::factory()->create(['property_id' => $property->id]);
        User::factory()->invitedTenant()->create(['invited_by' => $owner->id]);

        $this->getJson("/api/admin/owners/{$owner->id}")->assertOk()->assertJsonPath('id', $owner->id);
        $this->getJson('/api/admin/owners/' . User::factory()->tenant()->create()->id)->assertNotFound();

        $props = $this->getJson("/api/admin/owners/{$owner->id}/properties")->assertOk();
        $this->assertSame(AdminResourcesTest::PROPERTY_KEYS, array_keys($props->json()[0]));

        $tenants = $this->getJson("/api/admin/owners/{$owner->id}/tenants")->assertOk();
        $this->assertSame(AdminResourcesTest::TENANT_KEYS, array_keys($tenants->json()[0]));

        $this->postJson("/api/admin/owners/{$owner->id}/suspend", ['reason' => 'Unpaid subscription x2'])->assertOk();
        $history = $this->getJson("/api/admin/owners/{$owner->id}/history")->assertOk();
        $this->assertSame(['id', 'action', 'actorId', 'actorName', 'subjectType', 'subjectId', 'subjectName', 'before', 'after', 'reason', 'ip', 'createdAt'], array_keys($history->json()[0]));
        $this->assertSame('owner.suspended', $history->json('0.action'));
        $this->assertSame('owner.signup', $history->json('1.action'));
    }

    public function test_warn_sends_mail_notification_and_logs(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();

        $this->postJson("/api/admin/owners/{$owner->id}/warn", [
            'template' => 'payment_overdue', 'suspendOn' => '2026-09-01', 'extraLine' => 'Reply to this email if you need help.',
        ])->assertNoContent();

        Notification::assertSentTo($owner, OwnerWarning::class, fn ($n, $channels) => $channels === ['mail']);
        $row = Activity::inLog('admin')->where('event', 'owner.warned')->first();
        $this->assertSame($owner->id, $row->subject_id);
        $this->assertSame('payment_overdue', $row->properties['after']['template']);
        $this->assertStringContainsString('2026-09-01', $row->properties['after']['text']);

        $this->postJson("/api/admin/owners/{$owner->id}/warn", ['template' => 'nope', 'suspendOn' => '2026-09-01'])->assertUnprocessable();
    }

    public function test_suspend_and_unsuspend_with_audit_and_conflicts(): void
    {
        $owner = User::factory()->owner()->create();

        $this->postJson("/api/admin/owners/{$owner->id}/suspend", ['reason' => 'short'])->assertUnprocessable();

        $res = $this->postJson("/api/admin/owners/{$owner->id}/suspend", ['reason' => 'Unpaid subscription for two months'])->assertOk();
        $this->assertSame('suspended', $res->json('status'));
        $this->assertTrue($owner->fresh()->isSuspended());
        $this->assertSame('Unpaid subscription for two months', Activity::inLog('admin')->where('event', 'owner.suspended')->first()->properties['reason']);

        $this->postJson("/api/admin/owners/{$owner->id}/suspend", ['reason' => 'Unpaid subscription for two months'])->assertStatus(409);

        $res = $this->postJson("/api/admin/owners/{$owner->id}/unsuspend")->assertOk();
        $this->assertSame('active', $res->json('status'));
        $this->assertNull($owner->fresh()->suspension_reason);
        $this->assertSame(1, Activity::inLog('admin')->where('event', 'owner.unsuspended')->count());

        $this->postJson("/api/admin/owners/{$owner->id}/unsuspend")->assertStatus(409);
    }

    public function test_permissions_are_enforced_per_route(): void
    {
        $viewer = User::factory()->admin()->create();
        $viewer->givePermissionTo(AdminPermissions::OWNERS_VIEW);
        Sanctum::actingAs($viewer);
        $owner = User::factory()->owner()->create();

        $this->getJson('/api/admin/owners')->assertOk();
        $this->postJson("/api/admin/owners/{$owner->id}/warn", ['template' => 'payment_overdue', 'suspendOn' => '2026-09-01'])->assertForbidden();
        $this->postJson("/api/admin/owners/{$owner->id}/suspend", ['reason' => 'Unpaid subscription for two months'])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec roofly-backend php artisan test --filter AdminOwnerTest`
Expected: FAIL — 404s.

- [ ] **Step 3: Requests + notification + resource**

```php
<?php
// backend/app/Http/Requests/Admin/WarnOwnerRequest.php
namespace App\Http\Requests\Admin;

use App\Notifications\OwnerWarning;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarnOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // can:owners.warn on the route
    }

    public function rules(): array
    {
        return [
            'template'  => ['required', Rule::in(OwnerWarning::TEMPLATES)],
            'suspendOn' => 'required|date_format:Y-m-d|after:today',
            'extraLine' => 'nullable|string|max:500',
        ];
    }
}
```

```php
<?php
// backend/app/Http/Requests/Admin/SuspendOwnerRequest.php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SuspendOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // can:owners.suspend on the route
    }

    public function rules(): array
    {
        return ['reason' => 'required|string|min:10|max:1000'];
    }
}
```

```php
<?php
// backend/app/Notifications/OwnerWarning.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Payment-warning notice to an owner (spec § 8). SP1 ships mail only; SP2
 * adds whatsapp/sms by extending via() to read the owner's enabled channels
 * — callers (OwnerController::warn) never change.
 */
class OwnerWarning extends Notification implements ShouldQueue
{
    use Queueable;

    public const TEMPLATE_PAYMENT_OVERDUE = 'payment_overdue';
    public const TEMPLATES = [self::TEMPLATE_PAYMENT_OVERDUE];

    public function __construct(
        public readonly string $template,
        public readonly string $suspendOn,
        public readonly ?string $extraLine = null,
    ) {}

    public static function text(string $template, string $suspendOn, ?string $extraLine = null): string
    {
        $body = match ($template) {
            self::TEMPLATE_PAYMENT_OVERDUE => "Your Roofly subscription payment is overdue; your account will be suspended on {$suspendOn} unless settled.",
        };

        return $extraLine ? "{$body}\n\n{$extraLine}" : $body;
    }

    public function via(object $notifiable): array
    {
        return ['mail']; // SP2: read $notifiable->notification_preferences['channels']
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lines = explode("\n\n", self::text($this->template, $this->suspendOn, $this->extraLine));

        $mail = (new MailMessage)->subject('Action needed: your Roofly subscription');
        foreach ($lines as $line) {
            $mail->line($line);
        }

        return $mail->line('If you have already settled this, you can ignore this notice.');
    }
}
```

```php
<?php
// backend/app/Http/Resources/Admin/AuditEntryResource.php
namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** One admin audit row (Spatie Activity with log_name = admin). Load `causer` and `subject`. */
class AuditEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        $props = $this->properties ?? collect();

        return [
            'id'          => (string) $this->id,
            'action'      => $this->event ?? $this->description,
            'actorId'     => $this->causer_id,
            'actorName'   => $this->causer?->name,
            'subjectType' => $this->subject_type ? Str::lower(class_basename($this->subject_type)) : null,
            'subjectId'   => $this->subject_id,
            'subjectName' => $this->subject instanceof User ? $this->subject->name : null,
            'before'      => (object) ($props['before'] ?? []),
            'after'       => (object) ($props['after'] ?? []),
            'reason'      => $props['reason'] ?? null,
            'ip'          => $props['ip'] ?? null,
            'createdAt'   => $this->created_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 4: Controller**

```php
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
            ->with(['inviter:id,name', 'agreements.unit.property:id,name,owner_id'])
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
```

`ownedUnits` is a new relation — add to `backend/app/Models/User.php` (next to `properties()`):
```php
    public function ownedUnits(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Unit::class, Property::class, 'owner_id', 'property_id');
    }
```

- [ ] **Step 5: Routes**

Inside the `Route::prefix('admin')->middleware('role:admin')` group add:
```php
        $P = \App\Support\AdminPermissions::class;
        $Owner = \App\Http\Controllers\Api\Admin\OwnerController::class;

        Route::middleware("can:{$P::OWNERS_VIEW}")->group(function () use ($Owner) {
            Route::get('owners',                     [$Owner, 'index']);
            Route::get('owners/{owner}',             [$Owner, 'show']);
            Route::get('owners/{owner}/properties',  [$Owner, 'properties']);
            Route::get('owners/{owner}/tenants',     [$Owner, 'tenants']);
            Route::get('owners/{owner}/history',     [$Owner, 'history']);
        });
        Route::post('owners/{owner}/warn',      [$Owner, 'warn'])->middleware("can:{$P::OWNERS_WARN}");
        Route::post('owners/{owner}/suspend',   [$Owner, 'suspend'])->middleware("can:{$P::OWNERS_SUSPEND}");
        Route::post('owners/{owner}/unsuspend', [$Owner, 'unsuspend'])->middleware("can:{$P::OWNERS_SUSPEND}");
```
(`{owner}` binds `User` by uuid; the controller's `assertOwner` 404s non-owners. Because the `User` model uses `SoftDeletes`, deleted owners 404 naturally.)

- [ ] **Step 6: Run the gate**

Run: `docker exec roofly-backend php artisan test`
Expected: green (6 new tests). `QUEUE_CONNECTION=sync` in phpunit.xml so `ShouldQueue` notifications dispatch inline under `Notification::fake()`.

---

### Task 8: Admin tenants — list, detail, resend invite

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/TenantController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Admin/AdminTenantTest.php`

**Interfaces produced:**
- `GET /api/admin/tenants?q=&status=&ownerId=&page=&perPage=` → `{data: AdminTenant[], meta}`.
- `GET /api/admin/tenants/{tenant}` → `AdminTenant`; 404 for non-tenants.
- `POST /api/admin/tenants/{tenant}/resend-invite` → 204; 409 unless `status = invited`; bumps `invited_at`; logs `tenant.invite_resent`. (The actual magic-link mail is Phase 2's `MagicLinkController` — SP1 records the action; a `// TODO Phase 2` marks the dispatch point exactly like `Owner\TenantController::invite`.)

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Admin/AdminTenantTest.php
namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        $ops = User::factory()->admin()->create();
        $ops->givePermissionTo(AdminPermissions::TENANTS_VIEW);
        Sanctum::actingAs($ops);
    }

    public function test_list_search_and_filters(): void
    {
        $o1 = User::factory()->owner()->create();
        $o2 = User::factory()->owner()->create();
        User::factory()->tenant()->create(['name' => 'Aminah Yusof', 'invited_by' => $o1->id]);
        User::factory()->invitedTenant()->create(['name' => 'Lim Li Wei', 'invited_by' => $o2->id]);
        User::factory()->owner()->create(['name' => 'Aminah Owner']);

        $res = $this->getJson('/api/admin/tenants')->assertOk();
        $this->assertSame(2, $res->json('meta.total'));
        $this->assertSame(AdminResourcesTest::TENANT_KEYS, array_keys($res->json('data.0')));
        $this->assertSame(1, $this->getJson('/api/admin/tenants?q=aminah')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/tenants?status=invited')->json('meta.total'));
        $this->assertSame(1, $this->getJson("/api/admin/tenants?ownerId={$o2->id}")->json('meta.total'));
    }

    public function test_show_404s_for_non_tenant(): void
    {
        $tenant = User::factory()->tenant()->create();
        $this->getJson("/api/admin/tenants/{$tenant->id}")->assertOk()->assertJsonPath('id', $tenant->id);
        $this->getJson('/api/admin/tenants/' . User::factory()->owner()->create()->id)->assertNotFound();
    }

    public function test_resend_invite_only_for_invited_and_logs(): void
    {
        $invited = User::factory()->invitedTenant()->create(['invited_at' => now()->subDays(9)]);
        $this->postJson("/api/admin/tenants/{$invited->id}/resend-invite")->assertNoContent();
        $this->assertTrue($invited->fresh()->invited_at->isToday());
        $this->assertSame('tenant.invite_resent', Activity::inLog('admin')->latest('id')->first()->event);

        $active = User::factory()->tenant()->create();
        $this->postJson("/api/admin/tenants/{$active->id}/resend-invite")->assertStatus(409);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec roofly-backend php artisan test --filter AdminTenantTest`
Expected: FAIL — 404s.

- [ ] **Step 3: Controller + routes**

```php
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
    private const WITH = ['inviter:id,name', 'agreements.unit.property:id,name,owner_id'];

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
```

Routes (inside the admin group):
```php
        $Tenant = \App\Http\Controllers\Api\Admin\TenantController::class;
        Route::middleware("can:{$P::TENANTS_VIEW}")->group(function () use ($Tenant) {
            Route::get('tenants',                             [$Tenant, 'index']);
            Route::get('tenants/{tenant}',                    [$Tenant, 'show']);
            Route::post('tenants/{tenant}/resend-invite',     [$Tenant, 'resendInvite']);
        });
```

- [ ] **Step 4: Run the gate**

Run: `docker exec roofly-backend php artisan test`
Expected: green.

---

### Task 9: Admin users — list, create (invite), update permissions / enable / disable, resend invite; last-super-admin rule

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/AdminUserController.php`
- Create: `backend/app/Http/Requests/Admin/StoreAdminRequest.php`, `UpdateAdminRequest.php`
- Create: `backend/app/Http/Resources/Admin/AdminUserResource.php`
- Create: `backend/app/Notifications/AdminInvite.php`
- Modify: `backend/config/app.php` (add `frontend_url`), `backend/routes/api.php`
- Test: `backend/tests/Feature/Admin/AdminUserTest.php`

**Interfaces produced:**
- `AdminUserResource` keys: `id, name, email, permissions, isSuperAdmin, status, lastActiveAt, createdAt`; `status ∈ "invited" | "active" | "disabled"` (`disabled_at` → disabled; else `first_login_at` null → invited; else active).
- `GET /api/admin/admins` → `AdminUser[]` (all `role = admin`, newest first).
- `POST /api/admin/admins {name, email, permissions: string[], isSuperAdmin?: bool}` → 201 `AdminUser`; creates user (no password), an `AdminInvite` (7 days), sends `AdminInvite` notification (mail) with `{frontend_url}/admin/accept-invite?token=…`; logs `admin.invite_sent`. Only a super-admin may set `isSuperAdmin = true` (403 otherwise).
- `PATCH /api/admin/admins/{admin} {permissions?: string[], isSuperAdmin?: bool, disabled?: bool}` → `AdminUser`; logs `admin.permissions_changed` / `admin.disabled` / `admin.enabled`. 422 `"There must always be at least one enabled super-admin."` when the change would leave zero enabled super-admins; an admin cannot disable themselves (422).
- `POST /api/admin/admins/{admin}/resend-invite` → 204; 409 if already active; logs `admin.invite_sent`.
- `config('app.frontend_url')` = `env('FRONTEND_URL', 'http://localhost:3000')`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Admin/AdminUserTest.php
namespace Tests\Feature\Admin;

use App\Models\AdminInvite;
use App\Models\User;
use App\Notifications\AdminInvite as AdminInviteNotification;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = ['id', 'name', 'email', 'permissions', 'isSuperAdmin', 'status', 'lastActiveAt', 'createdAt'];

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        Notification::fake();
        $this->super = User::factory()->superAdmin()->create(['first_login_at' => now()]);
        Sanctum::actingAs($this->super);
    }

    public function test_list_returns_admins_with_status(): void
    {
        $invited = User::factory()->admin()->create(['password' => null]);
        User::factory()->admin()->create(['first_login_at' => now(), 'disabled_at' => now()]);

        $res = $this->getJson('/api/admin/admins')->assertOk();
        $this->assertCount(3, $res->json());
        $this->assertSame(self::KEYS, array_keys($res->json()[0]));
        $byId = collect($res->json())->keyBy('id');
        $this->assertSame('invited', $byId[$invited->id]['status']);
        $this->assertSame('active', $byId[$this->super->id]['status']);
        $this->assertContains('disabled', $byId->pluck('status'));
    }

    public function test_create_sends_invite_assigns_permissions_and_logs(): void
    {
        $res = $this->postJson('/api/admin/admins', [
            'name' => 'Ops One', 'email' => 'ops1@roofly.my', 'permissions' => AdminPermissions::operationsPreset(),
        ])->assertCreated();

        $this->assertSame(self::KEYS, array_keys($res->json()));
        $this->assertSame('invited', $res->json('status'));
        $this->assertSame(AdminPermissions::operationsPreset(), $res->json('permissions'));

        $user = User::where('email', 'ops1@roofly.my')->first();
        $this->assertSame('admin', $user->role->value);
        $this->assertNull($user->password);
        $this->assertCount(1, $user->adminInvites);
        Notification::assertSentTo($user, AdminInviteNotification::class);
        $this->assertSame('admin.invite_sent', Activity::inLog('admin')->latest('id')->first()->event);

        $this->postJson('/api/admin/admins', ['name' => 'X', 'email' => 'ops1@roofly.my', 'permissions' => []])->assertUnprocessable();
        $this->postJson('/api/admin/admins', ['name' => 'X', 'email' => 'y@roofly.my', 'permissions' => ['not.a.key']])->assertUnprocessable();
    }

    public function test_only_super_admin_can_grant_super_admin(): void
    {
        $manager = User::factory()->admin()->create(['first_login_at' => now()]);
        $manager->givePermissionTo(AdminPermissions::ADMINS_MANAGE);
        Sanctum::actingAs($manager);

        $this->postJson('/api/admin/admins', ['name' => 'S', 'email' => 's@roofly.my', 'permissions' => [], 'isSuperAdmin' => true])->assertForbidden();
        $this->postJson('/api/admin/admins', ['name' => 'S', 'email' => 's@roofly.my', 'permissions' => []])->assertCreated();
    }

    public function test_update_permissions_disable_enable_with_audit(): void
    {
        $ops = User::factory()->admin()->create(['first_login_at' => now()]);
        $ops->givePermissionTo(AdminPermissions::OWNERS_VIEW);

        $res = $this->patchJson("/api/admin/admins/{$ops->id}", ['permissions' => [AdminPermissions::OWNERS_VIEW, AdminPermissions::AUDIT_VIEW]])->assertOk();
        $this->assertSame([AdminPermissions::OWNERS_VIEW, AdminPermissions::AUDIT_VIEW], $res->json('permissions'));
        $log = Activity::inLog('admin')->where('event', 'admin.permissions_changed')->first();
        $this->assertSame(['owners.view'], $log->properties['before']['permissions']);

        $this->patchJson("/api/admin/admins/{$ops->id}", ['disabled' => true])->assertOk()->assertJsonPath('status', 'disabled');
        $this->assertSame(1, Activity::inLog('admin')->where('event', 'admin.disabled')->count());

        $this->patchJson("/api/admin/admins/{$ops->id}", ['disabled' => false])->assertOk()->assertJsonPath('status', 'active');
        $this->assertSame(1, Activity::inLog('admin')->where('event', 'admin.enabled')->count());
    }

    public function test_last_super_admin_cannot_be_disabled_or_demoted_and_self_disable_is_blocked(): void
    {
        $this->patchJson("/api/admin/admins/{$this->super->id}", ['disabled' => true])->assertUnprocessable();
        $this->patchJson("/api/admin/admins/{$this->super->id}", ['isSuperAdmin' => false])->assertUnprocessable();

        $second = User::factory()->superAdmin()->create(['first_login_at' => now()]);
        $this->patchJson("/api/admin/admins/{$second->id}", ['isSuperAdmin' => false])->assertOk()->assertJsonPath('isSuperAdmin', false);

        // Self-disable is blocked even with another super-admin around.
        User::factory()->superAdmin()->create(['first_login_at' => now()]);
        $this->patchJson("/api/admin/admins/{$this->super->id}", ['disabled' => true])->assertUnprocessable();
    }

    public function test_resend_invite(): void
    {
        $invited = User::factory()->admin()->create(['password' => null]);
        AdminInvite::create(['user_id' => $invited->id, 'token_hash' => hash('sha256', 'old'), 'expires_at' => now()->addDay()]);

        $this->postJson("/api/admin/admins/{$invited->id}/resend-invite")->assertNoContent();
        $this->assertCount(2, $invited->fresh()->adminInvites);
        $this->assertNotNull(AdminInvite::where('token_hash', hash('sha256', 'old'))->first()->accepted_at, 'old token is voided');
        Notification::assertSentTo($invited, AdminInviteNotification::class);

        $this->postJson("/api/admin/admins/{$this->super->id}/resend-invite")->assertStatus(409);
    }

    public function test_routes_need_admins_manage(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/admin/admins')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec roofly-backend php artisan test --filter AdminUserTest`
Expected: FAIL — 404s.

- [ ] **Step 3: Config, notification, requests, resource**

`backend/config/app.php` — add inside the returned array (after `'url' => …`):
```php
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
```

```php
<?php
// backend/app/Notifications/AdminInvite.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $plainToken) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/admin/accept-invite?token=' . $this->plainToken;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited to the Roofly admin portal')
            ->line("Hi {$notifiable->name}, you've been given access to the Roofly admin portal.")
            ->line('Anda telah diberi akses ke portal admin Roofly.')
            ->action('Set your password', $this->url())
            ->line('This link expires in 7 days. / Pautan ini tamat tempoh dalam 7 hari.');
    }
}
```

```php
<?php
// backend/app/Http/Requests/Admin/StoreAdminRequest.php
namespace App\Http\Requests\Admin;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only a super-admin may mint another super-admin (spec § 5).
        return ! $this->boolean('isSuperAdmin') || (bool) $this->user()?->is_super_admin;
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users,email',
            'permissions'   => 'present|array',
            'permissions.*' => [Rule::in(AdminPermissions::keys())],
            'isSuperAdmin'  => 'sometimes|boolean',
        ];
    }
}
```

```php
<?php
// backend/app/Http/Requests/Admin/UpdateAdminRequest.php
namespace App\Http\Requests\Admin;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->has('isSuperAdmin') || (bool) $this->user()?->is_super_admin;
    }

    public function rules(): array
    {
        return [
            'permissions'   => 'sometimes|array',
            'permissions.*' => [Rule::in(AdminPermissions::keys())],
            'isSuperAdmin'  => 'sometimes|boolean',
            'disabled'      => 'sometimes|boolean',
        ];
    }
}
```

```php
<?php
// backend/app/Http/Resources/Admin/AdminUserResource.php
namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'email'        => $this->email,
            'permissions'  => $this->getPermissionNames()->values()->all(),
            'isSuperAdmin' => (bool) $this->is_super_admin,
            'status'       => $this->isDisabled() ? 'disabled' : ($this->first_login_at === null ? 'invited' : 'active'),
            'lastActiveAt' => $this->last_active_at?->toISOString(),
            'createdAt'    => $this->created_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 4: Controller**

```php
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
```

Routes (inside the admin group):
```php
        $AdminUser = \App\Http\Controllers\Api\Admin\AdminUserController::class;
        Route::middleware("can:{$P::ADMINS_MANAGE}")->group(function () use ($AdminUser) {
            Route::get('admins',                          [$AdminUser, 'index']);
            Route::post('admins',                         [$AdminUser, 'store']);
            Route::patch('admins/{admin}',                [$AdminUser, 'update']);
            Route::post('admins/{admin}/resend-invite',   [$AdminUser, 'resendInvite']);
        });
```

- [ ] **Step 5: Run the gate**

Run: `docker exec roofly-backend php artisan test`
Expected: green (7 new tests). If `FormRequest::authorize()` returning false yields 403 — that is the intended "only super-admin grants super-admin" response.

---

### Task 10: Audit log list + CSV export

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/AuditController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Admin/AdminAuditTest.php`

**Interfaces produced:**
- `GET /api/admin/audit?actorId=&action=&subjectType=&subjectId=&from=YYYY-MM-DD&to=YYYY-MM-DD&page=&perPage=` → `{data: AuditEntry[], meta}`, newest first. Without `audit.view` the query is forced to `causer_id = me`.
- `GET /api/admin/audit/export.csv` (same filters) → `text/csv` with header `id,createdAt,action,actorName,subjectType,subjectId,subjectName,reason,before,after` (before/after JSON-encoded).

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Admin/AdminAuditTest.php
namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;
    private User $ops;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        $this->auditor = User::factory()->admin()->create();
        $this->auditor->givePermissionTo(AdminPermissions::AUDIT_VIEW);
        $this->ops = User::factory()->admin()->create();
        $this->owner = User::factory()->owner()->create();

        $this->actingAs($this->ops);
        app(AuditLogger::class)->record(AuditLogger::OWNER_WARNED, $this->owner, [], ['template' => 'payment_overdue']);
        $this->actingAs($this->auditor);
        app(AuditLogger::class)->record(AuditLogger::ADMIN_LOGIN, $this->auditor);
    }

    public function test_audit_view_sees_all_with_filters(): void
    {
        Sanctum::actingAs($this->auditor);
        $res = $this->getJson('/api/admin/audit')->assertOk();
        $this->assertSame(2, $res->json('meta.total'));
        $this->assertSame('admin.login', $res->json('data.0.action'));

        $this->assertSame(1, $this->getJson("/api/admin/audit?actorId={$this->ops->id}")->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/audit?action=owner.warned')->json('meta.total'));
        $this->assertSame(1, $this->getJson("/api/admin/audit?subjectType=user&subjectId={$this->owner->id}")->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/admin/audit?to=2000-01-01')->json('meta.total'));
        $this->assertSame(2, $this->getJson('/api/admin/audit?from=2000-01-01')->json('meta.total'));
    }

    public function test_without_audit_view_only_own_entries(): void
    {
        Sanctum::actingAs($this->ops);
        $res = $this->getJson('/api/admin/audit')->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
        $this->assertSame($this->ops->id, $res->json('data.0.actorId'));
        // Filtering for someone else still returns nothing.
        $this->assertSame(0, $this->getJson("/api/admin/audit?actorId={$this->auditor->id}")->json('meta.total'));
    }

    public function test_csv_export_requires_audit_view(): void
    {
        Sanctum::actingAs($this->ops);
        $this->get('/api/admin/audit/export.csv')->assertForbidden();

        Sanctum::actingAs($this->auditor);
        $res = $this->get('/api/admin/audit/export.csv')->assertOk();
        $this->assertStringStartsWith('text/csv', $res->headers->get('content-type'));
        $lines = explode("\n", trim($res->getContent()));
        $this->assertSame('id,createdAt,action,actorName,subjectType,subjectId,subjectName,reason,before,after', $lines[0]);
        $this->assertCount(3, $lines);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec roofly-backend php artisan test --filter AdminAuditTest`
Expected: FAIL — 404s.

- [ ] **Step 3: Controller + routes**

```php
<?php
// backend/app/Http/Controllers/Api/Admin/AuditController.php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditEntryResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    private const SUBJECT_TYPES = ['user' => User::class];

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('perPage', 25)));
        $page = $this->query($request)->paginate($perPage, ['*'], 'page', max(1, (int) $request->integer('page', 1)));

        return response()->json([
            'data' => AuditEntryResource::collection($page->items())->resolve(),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'createdAt', 'action', 'actorName', 'subjectType', 'subjectId', 'subjectName', 'reason', 'before', 'after']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach (AuditEntryResource::collection($rows)->resolve() as $r) {
                    fputcsv($out, [
                        $r['id'], $r['createdAt'], $r['action'], $r['actorName'], $r['subjectType'], $r['subjectId'],
                        $r['subjectName'], $r['reason'], json_encode($r['before']), json_encode($r['after']),
                    ]);
                }
            });
            fclose($out);
        }, 'roofly-audit-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(Request $request): Builder
    {
        $user = $request->user();
        $q = Activity::inLog(AuditLogger::LOG_NAME)->with(['causer', 'subject'])
            ->latest('created_at')->latest('id');

        // Without audit.view you only ever see what you did yourself (spec § 5).
        if (! $user->can(AdminPermissions::AUDIT_VIEW)) {
            $q->where('causer_id', $user->id);
        } elseif ($actorId = $request->query('actorId')) {
            $q->where('causer_id', $actorId);
        }
        if ($action = $request->query('action')) {
            $q->where('event', $action);
        }
        if ($type = $request->query('subjectType')) {
            $q->where('subject_type', self::SUBJECT_TYPES[$type] ?? $type);
        }
        if ($subjectId = $request->query('subjectId')) {
            $q->where('subject_id', $subjectId);
        }
        if ($from = $request->query('from')) {
            $q->where('created_at', '>=', $from . ' 00:00:00');
        }
        if ($to = $request->query('to')) {
            $q->where('created_at', '<=', $to . ' 23:59:59');
        }

        return $q;
    }
}
```

Routes (inside the admin group):
```php
        $Audit = \App\Http\Controllers\Api\Admin\AuditController::class;
        Route::get('audit',            [$Audit, 'index']);   // audit.view → all, else own (in controller)
        Route::get('audit/export.csv', [$Audit, 'export'])->middleware("can:{$P::AUDIT_VIEW}");
```

- [ ] **Step 4: Run the gate**

Run: `docker exec roofly-backend php artisan test`
Expected: green.

---

### Task 11: Admin dashboard payload

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/DashboardController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Admin/AdminDashboardTest.php`

**Interfaces produced:** `GET /api/admin/dashboard` →
```
{
  tiles: { owners: {total, active, suspended}, tenants: {total, invitedPending}, properties, units: {total, occupiedPct},
           agreementsActive, agreementsExpiring30d, supportOpen },
  series: { months: string[12 "YYYY-MM"], ownerSignups: number[12], invoicesIssued: number[12], invoicesPaid: number[12], inviteAcceptanceRate: number[12] },
  attention: [{ kind, ownerId, ownerName, meta, link }]
}
```
`kind ∈ over_cap | overdue_3plus | invite_stale_7d | no_property_7d | suspended`; `link = "/admin/owners/{id}"`. `supportOpen = 0` until SP2. `inviteAcceptanceRate[i]` = % of tenants invited in month i who have `first_login_at` (0 when none invited).

- [ ] **Step 1: Write the failing test**

```php
<?php
// backend/tests/Feature/Admin/AdminDashboardTest.php
namespace Tests\Feature\Admin;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo(AdminPermissions::DASHBOARD_VIEW);
        Sanctum::actingAs($admin);
    }

    public function test_payload_shape_and_attention_kinds(): void
    {
        // over_cap: free plan (cap 2) with 3 units
        $over = User::factory()->owner()->create(['name' => 'Over Cap', 'plan_tier' => 'free']);
        $p = Property::factory()->create(['owner_id' => $over->id]);
        Unit::factory()->count(3)->create(['property_id' => $p->id, 'status' => 'occupied']);

        // overdue_3plus
        $due = User::factory()->owner()->create(['name' => 'Overdue']);
        $p2 = Property::factory()->create(['owner_id' => $due->id]);
        $u2 = Unit::factory()->create(['property_id' => $p2->id]);
        $a2 = Agreement::factory()->create(['unit_id' => $u2->id, 'tenant_id' => User::factory()->tenant()->create()->id, 'status' => 'active']);
        Invoice::factory()->count(3)->create(['agreement_id' => $a2->id, 'status' => 'overdue']);

        // invite_stale_7d
        $stale = User::factory()->owner()->create(['name' => 'Stale']);
        Property::factory()->create(['owner_id' => $stale->id]);
        User::factory()->invitedTenant()->create(['invited_by' => $stale->id, 'invited_at' => now()->subDays(9)]);

        // no_property_7d
        $empty = User::factory()->owner()->create(['name' => 'Empty', 'created_at' => now()->subDays(10)]);

        // suspended
        User::factory()->owner()->suspended()->create(['name' => 'Suspended']);

        $res = $this->getJson('/api/admin/dashboard')->assertOk();
        $this->assertSame(['tiles', 'series', 'attention'], array_keys($res->json()));
        $this->assertSame(['owners', 'tenants', 'properties', 'units', 'agreementsActive', 'agreementsExpiring30d', 'supportOpen'], array_keys($res->json('tiles')));
        $this->assertSame(['total', 'active', 'suspended'], array_keys($res->json('tiles.owners')));
        $this->assertSame(5, $res->json('tiles.owners.total'));
        $this->assertSame(1, $res->json('tiles.owners.suspended'));
        $this->assertSame(1, $res->json('tiles.tenants.invitedPending'));
        $this->assertSame(75, $res->json('tiles.units.occupiedPct')); // 3 occupied of 4
        $this->assertSame(0, $res->json('tiles.supportOpen'));

        $this->assertSame(['months', 'ownerSignups', 'invoicesIssued', 'invoicesPaid', 'inviteAcceptanceRate'], array_keys($res->json('series')));
        $this->assertCount(12, $res->json('series.months'));
        $this->assertCount(12, $res->json('series.ownerSignups'));
        $this->assertSame(now()->format('Y-m'), $res->json('series.months.11'));

        $kinds = collect($res->json('attention'))->pluck('kind')->all();
        foreach (['over_cap', 'overdue_3plus', 'invite_stale_7d', 'no_property_7d', 'suspended'] as $kind) {
            $this->assertContains($kind, $kinds, "missing attention kind {$kind}");
        }
        $this->assertSame(['kind', 'ownerId', 'ownerName', 'meta', 'link'], array_keys($res->json('attention.0')));
        $this->assertStringStartsWith('/admin/owners/', $res->json('attention.0.link'));
        $this->assertStringNotContainsString('amount', json_encode($res->json()));
    }

    public function test_requires_dashboard_view(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec roofly-backend php artisan test --filter AdminDashboardTest`
Expected: FAIL — 404.

- [ ] **Step 3: Controller**

```php
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
        $overdueByOwner = Invoice::where('status', InvoiceStatus::OVERDUE)
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
                $push('no_property_7d', $o, $o->created_at->diffInDays($now) . 'd');
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
```

Route (inside the admin group):
```php
        Route::get('dashboard', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'index'])
            ->middleware("can:{$P::DASHBOARD_VIEW}");
```

- [ ] **Step 4: Run the gate**

Run: `docker exec roofly-backend php artisan test`
Expected: green.

---

### Task 12: DemoSeeder admins + final routes file review

**Files:**
- Modify: `backend/database/seeders/DemoSeeder.php`, `backend/tests/Feature/DemoSeederTest.php`
- Verify: `backend/routes/api.php` matches spec § 9 route table

**Interfaces produced:** API-mode logins `admin@roofly.my` / `password` (super-admin) and `ops@roofly.my` / `password` (Operations preset). Both have `first_login_at` set so they show as `active`.

- [ ] **Step 1: Extend the seeder test**

In `backend/tests/Feature/DemoSeederTest.php` add after the tenant count assertion:
```php
        $this->assertSame(2, User::where('role', 'admin')->count());
        $super = User::where('email', 'admin@roofly.my')->first();
        $this->assertTrue($super->is_super_admin);
        $ops = User::where('email', 'ops@roofly.my')->first();
        $this->assertFalse($ops->is_super_admin);
        $this->assertTrue($ops->hasPermissionTo('owners.suspend'));
        $this->assertFalse($ops->hasPermissionTo('admins.manage'));
        $this->assertSame(13, \Spatie\Permission\Models\Permission::count());
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec roofly-backend php artisan test --filter DemoSeederTest`
Expected: FAIL — 0 admins.

- [ ] **Step 3: Seed admins**

In `backend/database/seeders/DemoSeeder.php`:
- Add constants:
```php
    // ── Admins (spec § 9) ──────────────────────────────────────────────────
    private const ADMIN_SUPER = '00000000-0000-4000-8000-000000000901';
    private const ADMIN_OPS = '00000000-0000-4000-8000-000000000902';
```
- In `run()` make the first two lines of the transaction:
```php
            $this->call(AdminPermissionSeeder::class);
            $this->seedAdmins();
```
- Add the method after `seedOwner()`:
```php
    // ── Admins ──────────────────────────────────────────────────────────────

    private function seedAdmins(): void
    {
        $super = User::updateOrCreate(['id' => self::ADMIN_SUPER], [
            'name' => 'Baihaqie (super-admin)',
            'email' => 'admin@roofly.my',
            'phone' => null,
            'role' => 'admin',
            'is_super_admin' => true,
            'password' => Hash::make('password'),
            'first_login_at' => Carbon::parse('2026-08-01T01:00:00Z'),
        ]);
        $super->syncPermissions([]); // super-admin bypasses checks; no rows needed

        $ops = User::updateOrCreate(['id' => self::ADMIN_OPS], [
            'name' => 'Ops Admin',
            'email' => 'ops@roofly.my',
            'phone' => null,
            'role' => 'admin',
            'is_super_admin' => false,
            'password' => Hash::make('password'),
            'first_login_at' => Carbon::parse('2026-08-02T01:00:00Z'),
        ]);
        $ops->syncPermissions(AdminPermissions::operationsPreset());
    }
```
with `use App\Support\AdminPermissions;` added to the imports.

- [ ] **Step 4: Review `routes/api.php` against spec § 9**

The admin group must now contain exactly: `dashboard`, `owners` ×5 GET + `warn`/`suspend`/`unsuspend`, `tenants` ×2 GET + `resend-invite`, `permissions`, `admins` ×4, `audit`, `audit/export.csv`; plus the two guest `admin/auth/*` routes. Run `docker exec roofly-backend php artisan route:list --path=admin` and compare.

- [ ] **Step 5: Run the gate + reseed the dev DB**

Run: `docker exec roofly-backend php artisan test`
Expected: all green.
Run: `docker exec roofly-backend php artisan migrate --seed` (dev MySQL) so the browser walk has the two admins.

---

## Part B — Frontend

Every frontend task's gate is `docker exec roofly-frontend npm run typecheck` → the 5 known errors only. Tasks 13–16 are the foundation (env, auth, guard, services); 17–27 are the shell and screens.

### Task 13: `useEnv` admin flags, `AuthUser` permissions, `useAdminPermissions`

**Files:**
- Modify: `frontend/nuxt.config.ts`, `frontend/app/composables/useEnv.ts`, `frontend/app/types/auth.ts`
- Create: `frontend/app/composables/useAdminPermissions.ts`, `frontend/app/types/admin.ts` (the permission key union only — the rest of this file is filled in Task 16)

**Interfaces produced:**
- `useEnv()` gains `features: { documents: boolean; admin: boolean }` and `isAdminHost: boolean`. `features.admin` is `false` whenever `isDemo`, else `NUXT_PUBLIC_FEATURE_ADMIN !== "false"`.
- `AuthUser` gains `permissions: AdminPermission[]` and `isSuperAdmin: boolean`.
- `useAdminPermissions()` → `{ can(key: AdminPermission): boolean }` — `true` for super-admins, else membership in `auth.user.permissions`. Non-admins always `false`.
- `AdminPermission` union + `ADMIN_PERMISSIONS` ordered list (same 13 keys as backend `AdminPermissions::ALL`).

- [ ] **Step 1: nuxt.config**

In `frontend/nuxt.config.ts` `runtimeConfig.public.features` add:
```ts
        // Admin back office (spec 2026-08-23). Default on for uat/prod; useEnv()
        // forces it off in demo regardless of this value.
        admin: process.env.NUXT_PUBLIC_FEATURE_ADMIN !== "false",
```

- [ ] **Step 2: useEnv**

Replace the return block in `frontend/app/composables/useEnv.ts` with:
```ts
  // Hostname rule, not a separate app: admin.roofly.my serves the same build
  // and env.global.ts sends "/" to "/admin" there. SSR-safe via useRequestURL.
  const hostname = useRequestURL().hostname;
  const isAdminHost = hostname === "admin.roofly.my" || hostname.startsWith("admin.");

  return {
    env,
    isDemo,
    isUat,
    isProduction,
    isAdminHost,

    // Data layer — demo uses curated mocks forever; uat/prod follow the
    // service-level useMock flag (which itself flips off per-endpoint as the
    // backend lands). isDemo wins over the runtime flag so demo always sees
    // its mock data even if NUXT_PUBLIC_USE_MOCK is left at "false".
    useMock: isDemo || config.public.useMock,

    // Feature flags. `admin` is never on in demo — demo-roofly must not show
    // the back office (spec § 2).
    features: {
      documents: config.public.features.documents,
      admin: !isDemo && config.public.features.admin,
    },

    // UI feature flags
    showDemoShortcuts: isDemo,
    showFloatingFeedback: isDemo && Boolean(config.public.demoFeedbackUrl),
    showEnvBanner: isUat,
    redirectRootToDemo: isDemo,
  };
```

- [ ] **Step 3: Permission key type + AuthUser**

```ts
// frontend/app/types/admin.ts  (Task 16 appends the entity types below this)
/** Mirrors backend App\Support\AdminPermissions::ALL — same keys, same order. */
export const ADMIN_PERMISSIONS = [
  "dashboard.view",
  "owners.view",
  "tenants.view",
  "owners.warn",
  "owners.suspend",
  "owners.plan",
  "support.manage",
  "broadcast.send",
  "settings.channels",
  "settings.flags",
  "admins.manage",
  "audit.view",
  "users.delete",
] as const;

export type AdminPermission = (typeof ADMIN_PERMISSIONS)[number];
```

```ts
// frontend/app/types/auth.ts
import type { AdminPermission } from "~/types/admin";

export type UserRole = "owner" | "tenant" | "admin";

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
  /** Admin only — `[]` for owners and tenants. Super-admins get the full list. */
  permissions: AdminPermission[];
  isSuperAdmin: boolean;
}
```

- [ ] **Step 4: useAdminPermissions**

```ts
// frontend/app/composables/useAdminPermissions.ts
import { computed } from "vue";
import type { AdminPermission } from "~/types/admin";

/**
 * UI-side permission check (spec § 5). Hides / disables controls only —
 * the API is the enforcement. Super-admins pass everything.
 */
export const useAdminPermissions = () => {
  const auth = useAuthStore();

  const can = (key: AdminPermission): boolean => {
    const u = auth.user;
    if (!u || u.role !== "admin") return false;
    return u.isSuperAdmin || u.permissions.includes(key);
  };

  const isSuperAdmin = computed(() => auth.user?.role === "admin" && auth.user.isSuperAdmin);

  return { can, isSuperAdmin };
};
```

- [ ] **Step 5: Fix every `AuthUser` literal**

`frontend/app/demo/auth.ts` — `userFor()` and `register()` now need `permissions: [], isSuperAdmin: false` on every literal (the admin branch is rewritten properly in Task 14; for now add the two fields to all three literals).

- [ ] **Step 6: Gate**

Run: `docker exec roofly-frontend npm run typecheck`
Expected: the 5 known errors only.

---

### Task 14: Auth — `loginAdmin` / `acceptAdminInvite` in contract, demo, API, store; `/suspended` handling

**Files:**
- Modify: `frontend/app/services/contracts/auth.ts`, `frontend/app/demo/auth.ts`, `frontend/app/services/api/auth.ts`, `frontend/app/stores/auth.ts`, `frontend/app/composables/useApi.ts`
- Create: `frontend/app/pages/suspended.vue`
- Modify: `frontend/i18n/locales/en.json`, `frontend/i18n/locales/ms.json` (add `auth.admin.*`, `suspended.*`)

**Interfaces produced:**
- `AuthAdapter.loginAdmin(email, password): Promise<AuthUser>`; `AuthAdapter.acceptAdminInvite(token, password): Promise<AuthUser>`.
- `useAuthStore().loginAdmin(email, password)`, `useAuthStore().acceptAdminInvite(token, password)`.
- Demo: `admin@…` → super-admin (`isSuperAdmin: true`, all permissions); `ops@…` → Operations preset; anything else → throws. Demo customer `login()` throws for `admin`/`ops` prefixes. `DEMO_SUPER_ADMIN_ID = "a-super"`, `DEMO_OPS_ADMIN_ID = "a-ops"` exported (Task 16's seed data uses them as audit actors).
- `useApi` maps a 403 with `data.code === "account_suspended"` → `navigateTo("/suspended")`.

- [ ] **Step 1: Contract**

```ts
// frontend/app/services/contracts/auth.ts
import type { AuthUser } from "~/types/auth";

export interface RegisterPayload {
  name: string;
  email: string;
  phone: string;
  password: string;
}

export interface AuthAdapter {
  login(email: string, password: string): Promise<AuthUser>;
  register(payload: RegisterPayload): Promise<AuthUser>;
  logout(): Promise<void>;
  /** Boot hydration. Resolves `null` when not signed in — that case never throws. */
  fetchMe(): Promise<AuthUser | null>;

  // ── Admin Portal (spec § 4) — separate login, same session underneath ──
  /** Rejects (throws) for any non-admin or disabled admin. */
  loginAdmin(email: string, password: string): Promise<AuthUser>;
  /** Sets the invited admin's password from the emailed token and logs them in. */
  acceptAdminInvite(token: string, password: string): Promise<AuthUser>;
}
```

- [ ] **Step 2: Demo adapter**

Replace the body of `frontend/app/demo/auth.ts` from `const roleFor` down with:
```ts
export const DEMO_SUPER_ADMIN_ID = "a-super";
export const DEMO_OPS_ADMIN_ID = "a-ops";

/** Operations preset — mirrors backend AdminPermissions::operationsPreset(). */
const OPS_PRESET: AdminPermission[] = [
  "dashboard.view", "owners.view", "tenants.view", "owners.warn",
  "owners.suspend", "support.manage", "broadcast.send",
];

const isAdminEmail = (email: string) =>
  email.startsWith("admin") || email.startsWith("ops");

const customerUserFor = (email: string): AuthUser =>
  email.startsWith("tenant")
    ? {
        id: DEMO_TENANT_ID,
        name: "Aminah Binti Yusof",
        email,
        phone: "+60 12-345 6789",
        role: "tenant",
        permissions: [],
        isSuperAdmin: false,
      }
    : {
        id: DEMO_OWNER_ID,
        name: "Cik Aminah",
        email,
        phone: null,
        role: "owner",
        permissions: [],
        isSuperAdmin: false,
      };

const adminUserFor = (email: string): AuthUser =>
  email.startsWith("admin")
    ? {
        id: DEMO_SUPER_ADMIN_ID,
        name: "Baihaqie (super-admin)",
        email,
        phone: null,
        role: "admin",
        permissions: [...ADMIN_PERMISSIONS],
        isSuperAdmin: true,
      }
    : {
        id: DEMO_OPS_ADMIN_ID,
        name: "Ops Admin",
        email,
        phone: null,
        role: "admin",
        permissions: OPS_PRESET,
        isSuperAdmin: false,
      };

const delay = () => new Promise((r) => setTimeout(r, 300));

export const demoAuth: AuthAdapter = {
  async login(email) {
    await delay();
    // The customer form is never a back door into the admin (spec § 4).
    if (isAdminEmail(email)) throw new Error("Invalid credentials");
    const user = customerUserFor(email);
    persist(user);
    return user;
  },

  async register(payload) {
    await delay();
    const user: AuthUser = {
      id: DEMO_OWNER_ID,
      name: payload.name,
      email: payload.email,
      phone: payload.phone,
      role: "owner",
      permissions: [],
      isSuperAdmin: false,
    };
    persist(user);
    return user;
  },

  async logout() {
    persist(null);
  },

  async fetchMe() {
    return restore();
  },

  async loginAdmin(email) {
    await delay();
    if (!isAdminEmail(email)) throw new Error("Invalid credentials");
    const user = adminUserFor(email);
    persist(user);
    return user;
  },

  async acceptAdminInvite(_token) {
    await delay();
    const user = adminUserFor("ops@roofly.my");
    persist(user);
    return user;
  },
};
```
Update imports at the top: `import type { AuthUser } from "~/types/auth";` and `import { ADMIN_PERMISSIONS, type AdminPermission } from "~/types/admin";` (drop the now-unused `UserRole` import).

- [ ] **Step 3: API adapter**

Append to `frontend/app/services/api/auth.ts` inside `apiAuth`:
```ts
  async loginAdmin(email, password) {
    const { request } = useApi();
    await request("/../sanctum/csrf-cookie");
    const res = await request<{ user: AuthUser }>("/admin/auth/login", {
      method: "POST",
      body: { email, password },
    });
    return res.user;
  },

  async acceptAdminInvite(token, password) {
    const { request } = useApi();
    await request("/../sanctum/csrf-cookie");
    const res = await request<{ user: AuthUser }>("/admin/auth/accept-invite", {
      method: "POST",
      body: { token, password, password_confirmation: password },
    });
    return res.user;
  },
```

- [ ] **Step 4: Store actions**

Add to `actions` in `frontend/app/stores/auth.ts` after `register`:
```ts
    async loginAdmin(email: string, password: string) {
      this.loading = true;
      try {
        this.user = await adapter().loginAdmin(email, password);
      } finally {
        this.loading = false;
      }
    },

    async acceptAdminInvite(token: string, password: string) {
      this.loading = true;
      try {
        this.user = await adapter().acceptAdminInvite(token, password);
      } finally {
        this.loading = false;
      }
    },
```

- [ ] **Step 5: useApi — suspended + admin 401 bounce**

Replace `onResponseError` in `frontend/app/composables/useApi.ts`:
```ts
    onResponseError({ request: req, response }) {
      const url = typeof req === "string" ? req : req.url;

      // Suspended owner (spec § 8): the API answers 403 account_suspended on
      // every owner route; show the full-page notice instead of an error toast.
      if (
        response.status === 403 &&
        (response._data as { code?: string } | undefined)?.code === "account_suspended"
      ) {
        navigateTo("/suspended");
        return;
      }

      if (response.status !== 401) return;
      // Auth probes are allowed to 401 without a redirect: /auth/me is the
      // boot "am I logged in?" check, /auth/login + /admin/auth/* are failed sign-ins.
      if (url.includes("/auth/me") || url.includes("/auth/login") || url.includes("/admin/auth/")) return;

      const auth = useAuthStore();
      const wasAdmin = auth.isAdmin;
      auth.$patch({ user: null });
      navigateTo(wasAdmin || url.includes("/admin/") ? "/admin/login" : "/auth/login");
    },
```

- [ ] **Step 6: Suspended page + strings**

```vue
<!-- frontend/app/pages/suspended.vue -->
<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";

definePageMeta({ layout: false });
const { t } = useI18n();
useHead({ title: () => t("suspended.title") });

const auth = useAuthStore();
const onLogout = async () => {
  await auth.logout();
  await navigateTo("/auth/login");
};
</script>

<template>
  <div class="min-h-dvh bg-surface-page text-ink flex items-center justify-center px-6">
    <Card padding="loose" class="w-full max-w-auth-card text-center">
      <Icon name="ShieldOff" :size="40" class="mx-auto text-ink-faint" />
      <h1 class="mt-4 text-display-sub font-semibold tracking-snug">{{ t("suspended.title") }}</h1>
      <p class="mt-3 text-body text-ink-muted">{{ t("suspended.body") }}</p>
      <a
        href="mailto:support@roofly.my"
        class="mt-6 inline-block text-body text-ink underline underline-offset-2"
      >
        {{ t("suspended.contact") }}
      </a>
      <div class="mt-8">
        <Button variant="ghost" @click="onLogout">{{ t("auth.logout") }}</Button>
      </div>
    </Card>
  </div>
</template>
```

`en.json` additions (top-level key `suspended`, and under `auth` a new `admin` object):
```json
"suspended": {
  "title": "Account suspended",
  "body": "Your Roofly account has been suspended. Your tenants can still sign in and pay as usual.",
  "contact": "Contact support to restore access"
},
"auth": {
  "…existing keys…": "…",
  "admin": {
    "title": "Admin portal",
    "subtitle": "Sign in with your Roofly team account.",
    "login": "Sign in",
    "acceptTitle": "Set your password",
    "acceptSubtitle": "Choose a password to finish setting up your admin account.",
    "newPassword": "New password",
    "confirmPassword": "Confirm password",
    "passwordMismatch": "Passwords do not match.",
    "passwordTooShort": "Use at least 8 characters.",
    "inviteInvalid": "This invite link is invalid or has expired.",
    "accept": "Set password and sign in",
    "notCustomer": "Not a Roofly team member?",
    "customerLogin": "Go to the customer login"
  }
}
```
`ms.json`:
```json
"suspended": {
  "title": "Akaun digantung",
  "body": "Akaun Roofly anda telah digantung. Penyewa anda masih boleh log masuk dan membayar seperti biasa.",
  "contact": "Hubungi sokongan untuk memulihkan akses"
},
"auth": {
  "admin": {
    "title": "Portal admin",
    "subtitle": "Log masuk dengan akaun pasukan Roofly anda.",
    "login": "Log masuk",
    "acceptTitle": "Tetapkan kata laluan anda",
    "acceptSubtitle": "Pilih kata laluan untuk melengkapkan akaun admin anda.",
    "newPassword": "Kata laluan baharu",
    "confirmPassword": "Sahkan kata laluan",
    "passwordMismatch": "Kata laluan tidak sepadan.",
    "passwordTooShort": "Gunakan sekurang-kurangnya 8 aksara.",
    "inviteInvalid": "Pautan jemputan ini tidak sah atau telah tamat tempoh.",
    "accept": "Tetapkan kata laluan dan log masuk",
    "notCustomer": "Bukan ahli pasukan Roofly?",
    "customerLogin": "Ke log masuk pelanggan"
  }
}
```

- [ ] **Step 7: Gate**

Run: `docker exec roofly-frontend npm run typecheck`
Expected: 5 known errors only. Also: `grep -rn "useApi" frontend/app/demo/` → no matches.

---

### Task 15: Route guard (`/admin` area) + `env.global.ts`

**Files:**
- Modify: `frontend/app/middleware/auth.global.ts`, `frontend/app/pages/index.vue`
- Rename: `frontend/app/middleware/demo-only.global.ts` → `frontend/app/middleware/env.global.ts` (then modify)

**Interfaces produced:** routing matrix per spec § 3:
- `/admin/login`, `/admin/accept-invite` are public.
- Unauthenticated on `/admin/**` → `/admin/login`. Authenticated non-admin on `/admin/**` → own shell root. Admin on `/owner/**` or `/tenant/**` → `/admin`. `/` for an admin → `/admin`.
- `features.admin === false` → any `/admin/**` throws 404. `isAdminHost` and path `/` → `/admin`.

- [ ] **Step 1: auth.global.ts**

```ts
// frontend/app/middleware/auth.global.ts
/**
 * Auth + role gate for the three app shells.
 *
 * `/owner/*`, `/tenant/*` and `/admin/*` are protected — marketing, `/auth/*`,
 * `/demo/*`, `/coming-soon`, `/suspended` and the two public admin pages
 * (`/admin/login`, `/admin/accept-invite`) keep their own routing (see
 * env.global.ts; its path set is disjoint from this one, so ordering doesn't matter).
 *
 * On a hard refresh of a protected page the boot plugin's `fetchMe()` may
 * still be in flight; we await `authReady` so we never bounce a logged-in
 * user to a login page mid-hydration. On the server pass (SSR) `authReady`
 * is false and there's no session cookie access, so we skip — the client
 * plugin + this same guard re-run on hydration.
 */
const ADMIN_PUBLIC = new Set(["/admin/login", "/admin/accept-invite"]);

const shellRootFor = (auth: ReturnType<typeof useAuthStore>) =>
  auth.isAdmin ? "/admin" : auth.isTenant ? "/tenant" : "/owner";

export default defineNuxtRouteMiddleware(async (to) => {
  const isOwnerArea = to.path === "/owner" || to.path.startsWith("/owner/");
  const isTenantArea = to.path === "/tenant" || to.path.startsWith("/tenant/");
  const isAdminArea = (to.path === "/admin" || to.path.startsWith("/admin/")) && !ADMIN_PUBLIC.has(to.path);
  if (!isOwnerArea && !isTenantArea && !isAdminArea) return;

  // SSR has no session cookie here; let the client guard decide post-hydration.
  if (import.meta.server) return;

  const auth = useAuthStore();
  // Wait out the boot hydration if it hasn't settled yet.
  if (!auth.authReady) {
    await auth.fetchMe();
  }

  if (!auth.isAuthenticated) {
    // Admins never see the customer login and vice-versa (spec § 3).
    return navigateTo(isAdminArea ? "/admin/login" : "/auth/login");
  }

  const inWrongShell =
    (isOwnerArea && !auth.isOwner) ||
    (isTenantArea && !auth.isTenant) ||
    (isAdminArea && !auth.isAdmin);
  if (inWrongShell) {
    return navigateTo(shellRootFor(auth));
  }
});
```

- [ ] **Step 2: env.global.ts**

`git mv frontend/app/middleware/demo-only.global.ts frontend/app/middleware/env.global.ts`, then replace the `defineNuxtRouteMiddleware` body:
```ts
export default defineNuxtRouteMiddleware((to) => {
  const { isDemo, isProduction, isAdminHost, features } = useEnv();
  const isDemoRoute = to.path === "/demo" || to.path.startsWith("/demo/");
  const isComingSoon = to.path === "/coming-soon";
  const isAdminRoute = to.path === "/admin" || to.path.startsWith("/admin/");

  // Admin back office is a feature flag — forced off in demo (spec § 2/3).
  if (isAdminRoute && !features.admin) {
    throw createError({ statusCode: 404, statusMessage: "Page not found" });
  }

  // admin.roofly.my — root goes straight to the back office.
  if (isAdminHost && to.path === "/") {
    return navigateTo("/admin", { redirectCode: 302 });
  }

  if (isDemo) {
    // Demo subdomain — /coming-soon doesn't apply here; bounce to /demo
    if (to.path === "/" || isComingSoon) {
      return navigateTo("/demo", { redirectCode: 302 });
    }
    return;
  }

  // uat / prod from here onwards
  if (isDemoRoute && isProduction) {
    throw createError({ statusCode: 404, statusMessage: "Page not found" });
  }

  if (to.path === "/") {
    const auth = useAuthStore();
    if (!auth.isAuthenticated) {
      return navigateTo("/coming-soon", { redirectCode: 302 });
    }
    // Authenticated → falls through to pages/index.vue (role-based redirect)
  }
});
```
Extend the doc-comment routing matrix at the top of the file with two rows: `/admin/*` (demo: 404 · uat/prod: render when features.admin) and `/ on admin host` (→ /admin).

- [ ] **Step 3: pages/index.vue**

```ts
if (!auth.isAuthenticated) {
  await navigateTo("/auth/login");
} else if (auth.isAdmin) {
  await navigateTo("/admin");
} else if (auth.isOwner) {
  await navigateTo("/owner");
} else if (auth.isTenant) {
  await navigateTo("/tenant");
} else {
  await navigateTo("/auth/login");
}
```

- [ ] **Step 4: Gate**

Run: `docker exec roofly-frontend npm run typecheck`
Expected: 5 known errors only. `ls frontend/app/middleware` shows `auth.global.ts env.global.ts` only.

---

### Task 16: Admin types, contracts, demo seed data, demo adapters, API adapters, selectors

**Files:**
- Modify: `frontend/app/types/admin.ts` (append entity types)
- Create: `frontend/app/services/contracts/admin/{dashboard,owners,tenants,admins,audit}.ts`
- Create: `frontend/app/demo/data/admin.ts`
- Create: `frontend/app/demo/services/admin/{dashboard,owners,tenants,admins,audit}.ts`
- Create: `frontend/app/services/api/admin/{dashboard,owners,tenants,admins,audit}.ts`
- Create: `frontend/app/services/{useAdminDashboard,useAdminOwners,useAdminTenants,useAdminAdmins,useAdminAudit}.ts`

**Interfaces produced** (all camelCase, mirroring Part A exactly):
- Types: `AdminOwner`, `AdminOwnerCounts`, `AdminPropertySummary`, `AdminTenant`, `AdminUser`, `AuditEntry`, `AuditAction`, `Paginated<T>`, `AdminDashboardData`, `AdminAttentionItem`, `AdminAttentionKind`, `OwnerListQuery`, `TenantListQuery`, `AuditQuery`, `WarnOwnerInput`, `CreateAdminInput`, `UpdateAdminInput`, `PermissionCatalogue`.
- Services (one interface each): `AdminDashboardService.getDashboard()`; `AdminOwnersService.list/get/properties/tenants/history/warn/suspend/unsuspend`; `AdminTenantsService.list/get/resendInvite`; `AdminAdminsService.permissions/list/create/update/resendInvite`; `AdminAuditService.list/exportCsv`.
- Selectors: `useAdminDashboard()` (plain selector — the page-level composable with month labels lives in Task 19), `useAdminOwners()`, `useAdminTenants()`, `useAdminAdmins()`, `useAdminAudit()`.

- [ ] **Step 1: Types** — append to `frontend/app/types/admin.ts`:

```ts
// ── Shared ───────────────────────────────────────────────────────────────
export interface Paginated<T> {
  data: T[];
  meta: { page: number; perPage: number; total: number; lastPage: number };
}

export type PlanTier = "free" | "starter" | "pro" | "business";
export type OwnerStatus = "active" | "suspended";
export type TenantStatus = "invited" | "active" | "notice_given" | "moved_out";

// ── Owner (spec § 6 tier — summary only, never money) ──────────────────
export interface AdminOwnerCounts {
  properties: number;
  units: number;
  unitsOccupied: number;
  tenants: number;
  agreementsActive: number;
  agreementsExpiring30d: number;
  invoicesOverdue: number;
  ticketsOpen: number;
}

export interface AdminOwner {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  businessName: string | null;
  planTier: PlanTier;
  unitsUsed: number;
  unitsCap: number | null; // null = unlimited
  status: OwnerStatus;
  suspendedAt: string | null;
  suspensionReason: string | null;
  createdAt: string;
  lastActiveAt: string | null;
  counts: AdminOwnerCounts;
}

export interface AdminPropertySummary {
  id: string;
  name: string;
  address: { line: string | null; postcode: string | null; city: string | null; state: string | null };
  type: "condo" | "landed" | "shoplot" | "room" | null;
  unitsTotal: number;
  unitsOccupied: number;
  createdAt: string;
}

export interface AdminTenant {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  status: TenantStatus;
  ownerId: string | null;
  ownerName: string | null;
  propertyName: string | null;
  unitLabel: string | null;
  invitedAt: string | null;
  acceptedAt: string | null;
  createdAt: string;
}

// ── Admin users ──────────────────────────────────────────────────────────
export type AdminUserStatus = "invited" | "active" | "disabled";

export interface AdminUser {
  id: string;
  name: string;
  email: string;
  permissions: AdminPermission[];
  isSuperAdmin: boolean;
  status: AdminUserStatus;
  lastActiveAt: string | null;
  createdAt: string;
}

export interface PermissionCatalogue {
  permissions: { key: AdminPermission; preset: boolean }[];
  preset: AdminPermission[];
}

export interface CreateAdminInput {
  name: string;
  email: string;
  permissions: AdminPermission[];
  isSuperAdmin?: boolean;
}

export interface UpdateAdminInput {
  permissions?: AdminPermission[];
  isSuperAdmin?: boolean;
  disabled?: boolean;
}

// ── Audit ────────────────────────────────────────────────────────────────
export type AuditAction =
  | "admin.login"
  | "admin.invite_sent"
  | "admin.invite_accepted"
  | "admin.permissions_changed"
  | "admin.disabled"
  | "admin.enabled"
  | "owner.warned"
  | "owner.suspended"
  | "owner.unsuspended"
  | "tenant.invite_resent"
  | "owner.signup"; // synthesised in owner history only

export const AUDIT_ACTIONS: AuditAction[] = [
  "admin.login", "admin.invite_sent", "admin.invite_accepted", "admin.permissions_changed",
  "admin.disabled", "admin.enabled", "owner.warned", "owner.suspended", "owner.unsuspended",
  "tenant.invite_resent",
];

export interface AuditEntry {
  id: string;
  action: AuditAction;
  actorId: string | null;
  actorName: string | null;
  subjectType: "user" | null;
  subjectId: string | null;
  subjectName: string | null;
  before: Record<string, unknown>;
  after: Record<string, unknown>;
  reason: string | null;
  ip: string | null;
  createdAt: string;
}

// ── Queries / inputs ─────────────────────────────────────────────────────
export interface OwnerListQuery {
  q?: string;
  plan?: PlanTier;
  status?: OwnerStatus;
  overCap?: boolean;
  overdue?: boolean;
  page?: number;
  perPage?: number;
}

export interface TenantListQuery {
  q?: string;
  status?: TenantStatus;
  ownerId?: string;
  page?: number;
  perPage?: number;
}

export interface AuditQuery {
  actorId?: string;
  action?: AuditAction;
  subjectType?: "user";
  subjectId?: string;
  from?: string; // YYYY-MM-DD
  to?: string;
  page?: number;
  perPage?: number;
}

export type WarnTemplate = "payment_overdue";

export interface WarnOwnerInput {
  template: WarnTemplate;
  suspendOn: string; // YYYY-MM-DD
  extraLine?: string;
}

// ── Dashboard (spec § 7) ─────────────────────────────────────────────────
export type AdminAttentionKind =
  | "over_cap"
  | "overdue_3plus"
  | "invite_stale_7d"
  | "no_property_7d"
  | "suspended";

export interface AdminAttentionItem {
  kind: AdminAttentionKind;
  ownerId: string;
  ownerName: string;
  meta: string;
  link: string;
}

export interface AdminDashboardData {
  tiles: {
    owners: { total: number; active: number; suspended: number };
    tenants: { total: number; invitedPending: number };
    properties: number;
    units: { total: number; occupiedPct: number };
    agreementsActive: number;
    agreementsExpiring30d: number;
    supportOpen: number;
  };
  series: {
    months: string[]; // 12 × YYYY-MM, oldest first
    ownerSignups: number[];
    invoicesIssued: number[];
    invoicesPaid: number[];
    inviteAcceptanceRate: number[]; // 0–100
  };
  attention: AdminAttentionItem[];
}
```

- [ ] **Step 2: Contracts**

```ts
// frontend/app/services/contracts/admin/dashboard.ts
import type { AdminDashboardData } from "~/types/admin";

export interface AdminDashboardService {
  getDashboard(): Promise<AdminDashboardData>;
}
```

```ts
// frontend/app/services/contracts/admin/owners.ts
import type {
  AdminOwner, AdminPropertySummary, AdminTenant, AuditEntry, OwnerListQuery, Paginated, WarnOwnerInput,
} from "~/types/admin";

export interface AdminOwnersService {
  list(query: OwnerListQuery): Promise<Paginated<AdminOwner>>;
  get(id: string): Promise<AdminOwner | null>;
  properties(id: string): Promise<AdminPropertySummary[]>;
  tenants(id: string): Promise<AdminTenant[]>;
  history(id: string): Promise<AuditEntry[]>;
  warn(id: string, input: WarnOwnerInput): Promise<void>;
  suspend(id: string, reason: string): Promise<AdminOwner>;
  unsuspend(id: string): Promise<AdminOwner>;
}
```

```ts
// frontend/app/services/contracts/admin/tenants.ts
import type { AdminTenant, Paginated, TenantListQuery } from "~/types/admin";

export interface AdminTenantsService {
  list(query: TenantListQuery): Promise<Paginated<AdminTenant>>;
  get(id: string): Promise<AdminTenant | null>;
  resendInvite(id: string): Promise<void>;
}
```

```ts
// frontend/app/services/contracts/admin/admins.ts
import type { AdminUser, CreateAdminInput, PermissionCatalogue, UpdateAdminInput } from "~/types/admin";

export interface AdminAdminsService {
  permissions(): Promise<PermissionCatalogue>;
  list(): Promise<AdminUser[]>;
  create(input: CreateAdminInput): Promise<AdminUser>;
  update(id: string, patch: UpdateAdminInput): Promise<AdminUser>;
  resendInvite(id: string): Promise<void>;
}
```

```ts
// frontend/app/services/contracts/admin/audit.ts
import type { AuditEntry, AuditQuery, Paginated } from "~/types/admin";

export interface AdminAuditService {
  list(query: AuditQuery): Promise<Paginated<AuditEntry>>;
  /** Full filtered export as CSV text (caller triggers the download). */
  exportCsv(query: AuditQuery): Promise<string>;
}
```

- [ ] **Step 3: Demo seed data**

```ts
// frontend/app/demo/data/admin.ts
/**
 * Fake platform for the admin shell (spec § 9): 4 owners (free / starter /
 * pro, one suspended, one over cap), their tenants (some invited-pending),
 * 2 admins, ~30 audit rows. Demo-only — never imported by services/api/**.
 * Owner "o-aminah" is the same person as the owner-shell demo account, so
 * the two shells tell one story.
 */
import type {
  AdminOwner, AdminPropertySummary, AdminTenant, AdminUser, AuditEntry, AuditAction,
} from "~/types/admin";
import { ADMIN_PERMISSIONS } from "~/types/admin";
import { DEMO_OPS_ADMIN_ID, DEMO_SUPER_ADMIN_ID } from "~/demo/auth";

const OPS_PRESET = ADMIN_PERMISSIONS.filter((k) =>
  ["dashboard.view", "owners.view", "tenants.view", "owners.warn", "owners.suspend", "support.manage", "broadcast.send"].includes(k),
);

const daysAgo = (n: number) => new Date(Date.now() - n * 86_400_000).toISOString();
const dateOnly = (iso: string) => iso.slice(0, 10);

export const adminOwnersMock: AdminOwner[] = [
  {
    id: "o-aminah", name: "Cik Aminah", email: "aminah@roofly.my", phone: "+60 12-345 6789",
    businessName: "Aminah Properties", planTier: "free", unitsUsed: 8, unitsCap: 2,
    status: "active", suspendedAt: null, suspensionReason: null,
    createdAt: daysAgo(400), lastActiveAt: daysAgo(0),
    counts: { properties: 5, units: 8, unitsOccupied: 4, tenants: 5, agreementsActive: 3, agreementsExpiring30d: 1, invoicesOverdue: 1, ticketsOpen: 4 },
  },
  {
    id: "o-farid", name: "Farid Kamal", email: "farid@kamalhomes.my", phone: "+60 13-222 8899",
    businessName: "Kamal Homes", planTier: "starter", unitsUsed: 4, unitsCap: 5,
    status: "active", suspendedAt: null, suspensionReason: null,
    createdAt: daysAgo(210), lastActiveAt: daysAgo(2),
    counts: { properties: 2, units: 4, unitsOccupied: 4, tenants: 4, agreementsActive: 4, agreementsExpiring30d: 0, invoicesOverdue: 3, ticketsOpen: 1 },
  },
  {
    id: "o-mei", name: "Tan Mei Ling", email: "meiling@tanrealty.my", phone: "+60 16-777 1122",
    businessName: "Tan Realty", planTier: "pro", unitsUsed: 14, unitsCap: 25,
    status: "suspended", suspendedAt: daysAgo(5), suspensionReason: "Subscription unpaid for two billing cycles after warning.",
    createdAt: daysAgo(320), lastActiveAt: daysAgo(6),
    counts: { properties: 6, units: 14, unitsOccupied: 11, tenants: 12, agreementsActive: 11, agreementsExpiring30d: 2, invoicesOverdue: 0, ticketsOpen: 2 },
  },
  {
    id: "o-raj", name: "Rajesh Pillai", email: "rajesh.pillai@gmail.com", phone: null,
    businessName: null, planTier: "free", unitsUsed: 0, unitsCap: 2,
    status: "active", suspendedAt: null, suspensionReason: null,
    createdAt: daysAgo(12), lastActiveAt: daysAgo(11),
    counts: { properties: 0, units: 0, unitsOccupied: 0, tenants: 0, agreementsActive: 0, agreementsExpiring30d: 0, invoicesOverdue: 0, ticketsOpen: 0 },
  },
];

/** ownerId → properties (summary tier only) */
export const adminPropertiesMock: Record<string, AdminPropertySummary[]> = {
  "o-aminah": [
    { id: "p-1", name: "Suria KLCC Residences", address: { line: "Jalan Ampang", postcode: "50450", city: "Kuala Lumpur", state: "Kuala Lumpur" }, type: "condo", unitsTotal: 1, unitsOccupied: 1, createdAt: daysAgo(390) },
    { id: "p-2", name: "TTDI Terrace", address: { line: "Jalan Datuk Sulaiman", postcode: "60000", city: "Kuala Lumpur", state: "Kuala Lumpur" }, type: "landed", unitsTotal: 1, unitsOccupied: 1, createdAt: daysAgo(380) },
    { id: "p-3", name: "Wangsa Maju Flats", address: { line: "Jalan Wangsa Delima", postcode: "53300", city: "Kuala Lumpur", state: "Kuala Lumpur" }, type: "condo", unitsTotal: 2, unitsOccupied: 1, createdAt: daysAgo(300) },
    { id: "p-4", name: "USJ Shoplot", address: { line: "Jalan USJ 10/1", postcode: "47620", city: "Subang Jaya", state: "Selangor" }, type: "shoplot", unitsTotal: 1, unitsOccupied: 0, createdAt: daysAgo(250) },
    { id: "p-5", name: "Subang Rooms", address: { line: "Jalan SS15/4", postcode: "47500", city: "Subang Jaya", state: "Selangor" }, type: "room", unitsTotal: 3, unitsOccupied: 1, createdAt: daysAgo(200) },
  ],
  "o-farid": [
    { id: "p-f1", name: "Cyberjaya Studio Block", address: { line: "Persiaran Multimedia", postcode: "63000", city: "Cyberjaya", state: "Selangor" }, type: "condo", unitsTotal: 3, unitsOccupied: 3, createdAt: daysAgo(200) },
    { id: "p-f2", name: "Kajang Semi-D", address: { line: "Jalan Reko", postcode: "43000", city: "Kajang", state: "Selangor" }, type: "landed", unitsTotal: 1, unitsOccupied: 1, createdAt: daysAgo(150) },
  ],
  "o-mei": [
    { id: "p-m1", name: "Gurney Heights", address: { line: "Gurney Drive", postcode: "10250", city: "George Town", state: "Pulau Pinang" }, type: "condo", unitsTotal: 6, unitsOccupied: 5, createdAt: daysAgo(310) },
    { id: "p-m2", name: "Bayan Lepas Rooms", address: { line: "Jalan Tun Dr Awang", postcode: "11900", city: "Bayan Lepas", state: "Pulau Pinang" }, type: "room", unitsTotal: 8, unitsOccupied: 6, createdAt: daysAgo(280) },
  ],
  "o-raj": [],
};

export const adminTenantsMock: AdminTenant[] = [
  { id: "t-aminah", name: "Aminah Binti Yusof", email: "aminah.yusof@example.com", phone: "+60 12-345 6789", status: "active", ownerId: "o-aminah", ownerName: "Cik Aminah", propertyName: "Suria KLCC Residences", unitLabel: "A-12-3", invitedAt: daysAgo(370), acceptedAt: daysAgo(369), createdAt: daysAgo(370) },
  { id: "t-arif", name: "Arif Hakim", email: "arif.hakim@example.com", phone: "+60 17-888 1234", status: "active", ownerId: "o-aminah", ownerName: "Cik Aminah", propertyName: "Wangsa Maju Flats", unitLabel: "B-3-2", invitedAt: daysAgo(290), acceptedAt: daysAgo(288), createdAt: daysAgo(290) },
  { id: "t-li-wei", name: "Lim Li Wei", email: "limlw@example.com", phone: "+60 16-222 3344", status: "invited", ownerId: "o-aminah", ownerName: "Cik Aminah", propertyName: "TTDI Terrace", unitLabel: "Main", invitedAt: daysAgo(9), acceptedAt: null, createdAt: daysAgo(9) },
  { id: "t-ravi", name: "Ravi Kumar", email: "ravik@example.com", phone: "+60 13-456 7890", status: "moved_out", ownerId: "o-aminah", ownerName: "Cik Aminah", propertyName: "USJ Shoplot", unitLabel: "G-1", invitedAt: daysAgo(800), acceptedAt: daysAgo(799), createdAt: daysAgo(800) },
  { id: "t-siti", name: "Siti Khadijah Binti Rahim", email: "siti.khadijah@example.com", phone: "+60 11-2233 4455", status: "notice_given", ownerId: "o-aminah", ownerName: "Cik Aminah", propertyName: "Subang Rooms", unitLabel: "Master", invitedAt: daysAgo(560), acceptedAt: daysAgo(559), createdAt: daysAgo(560) },
  { id: "t-f1", name: "Nurul Izzah", email: "nurul.izzah@example.com", phone: "+60 19-100 2000", status: "active", ownerId: "o-farid", ownerName: "Farid Kamal", propertyName: "Cyberjaya Studio Block", unitLabel: "3-01", invitedAt: daysAgo(180), acceptedAt: daysAgo(179), createdAt: daysAgo(180) },
  { id: "t-f2", name: "Daniel Wong", email: "daniel.wong@example.com", phone: "+60 12-900 1234", status: "active", ownerId: "o-farid", ownerName: "Farid Kamal", propertyName: "Cyberjaya Studio Block", unitLabel: "3-02", invitedAt: daysAgo(170), acceptedAt: daysAgo(168), createdAt: daysAgo(170) },
  { id: "t-f3", name: "Priya Nair", email: "priya.nair@example.com", phone: "+60 14-333 4444", status: "active", ownerId: "o-farid", ownerName: "Farid Kamal", propertyName: "Cyberjaya Studio Block", unitLabel: "3-03", invitedAt: daysAgo(120), acceptedAt: daysAgo(119), createdAt: daysAgo(120) },
  { id: "t-f4", name: "Hafiz Rahman", email: "hafiz.r@example.com", phone: "+60 11-555 6666", status: "active", ownerId: "o-farid", ownerName: "Farid Kamal", propertyName: "Kajang Semi-D", unitLabel: "Main", invitedAt: daysAgo(140), acceptedAt: daysAgo(139), createdAt: daysAgo(140) },
  { id: "t-m1", name: "Chong Wei Jie", email: "chong.wj@example.com", phone: "+60 12-111 2222", status: "active", ownerId: "o-mei", ownerName: "Tan Mei Ling", propertyName: "Gurney Heights", unitLabel: "12-A", invitedAt: daysAgo(300), acceptedAt: daysAgo(299), createdAt: daysAgo(300) },
  { id: "t-m2", name: "Sarah Abdullah", email: "sarah.abd@example.com", phone: "+60 13-999 8888", status: "invited", ownerId: "o-mei", ownerName: "Tan Mei Ling", propertyName: "Bayan Lepas Rooms", unitLabel: "R-4", invitedAt: daysAgo(3), acceptedAt: null, createdAt: daysAgo(3) },
  { id: "t-m3", name: "Kevin Ooi", email: "kevin.ooi@example.com", phone: "+60 16-123 4567", status: "invited", ownerId: "o-mei", ownerName: "Tan Mei Ling", propertyName: "Bayan Lepas Rooms", unitLabel: "R-5", invitedAt: daysAgo(15), acceptedAt: null, createdAt: daysAgo(15) },
];

export const adminUsersMock: AdminUser[] = [
  { id: DEMO_SUPER_ADMIN_ID, name: "Baihaqie (super-admin)", email: "admin@roofly.my", permissions: [], isSuperAdmin: true, status: "active", lastActiveAt: daysAgo(0), createdAt: daysAgo(60) },
  { id: DEMO_OPS_ADMIN_ID, name: "Ops Admin", email: "ops@roofly.my", permissions: OPS_PRESET, isSuperAdmin: false, status: "active", lastActiveAt: daysAgo(1), createdAt: daysAgo(45) },
];

type Seed = [daysBack: number, actorId: string, action: AuditAction, subjectId: string | null, extra?: Partial<AuditEntry>];

const actorName = (id: string) => adminUsersMock.find((a) => a.id === id)?.name ?? null;
const subjectName = (id: string | null) =>
  id === null ? null
    : adminOwnersMock.find((o) => o.id === id)?.name
      ?? adminTenantsMock.find((t) => t.id === id)?.name
      ?? adminUsersMock.find((a) => a.id === id)?.name
      ?? null;

const seeds: Seed[] = [
  [45, DEMO_SUPER_ADMIN_ID, "admin.invite_sent", DEMO_OPS_ADMIN_ID, { after: { permissions: OPS_PRESET, isSuperAdmin: false } }],
  [44, DEMO_OPS_ADMIN_ID, "admin.invite_accepted", DEMO_OPS_ADMIN_ID],
  [44, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [40, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [33, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [30, DEMO_OPS_ADMIN_ID, "owner.warned", "o-mei", { after: { template: "payment_overdue", suspendOn: dateOnly(daysAgo(16)), extraLine: null, text: `Your Roofly subscription payment is overdue; your account will be suspended on ${dateOnly(daysAgo(16))} unless settled.` } }],
  [28, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [25, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [22, DEMO_OPS_ADMIN_ID, "tenant.invite_resent", "t-m3", { before: { invitedAt: daysAgo(29) }, after: { invitedAt: daysAgo(22) } }],
  [20, DEMO_SUPER_ADMIN_ID, "admin.permissions_changed", DEMO_OPS_ADMIN_ID, { before: { permissions: OPS_PRESET.filter((k) => k !== "broadcast.send"), isSuperAdmin: false }, after: { permissions: OPS_PRESET, isSuperAdmin: false } }],
  [18, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [16, DEMO_OPS_ADMIN_ID, "owner.warned", "o-mei", { after: { template: "payment_overdue", suspendOn: dateOnly(daysAgo(5)), extraLine: "Final notice.", text: `Your Roofly subscription payment is overdue; your account will be suspended on ${dateOnly(daysAgo(5))} unless settled.\n\nFinal notice.` } }],
  [15, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [14, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [12, DEMO_OPS_ADMIN_ID, "owner.warned", "o-farid", { after: { template: "payment_overdue", suspendOn: dateOnly(daysAgo(-2)), extraLine: null, text: `Your Roofly subscription payment is overdue; your account will be suspended on ${dateOnly(daysAgo(-2))} unless settled.` } }],
  [11, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [10, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [9, DEMO_OPS_ADMIN_ID, "tenant.invite_resent", "t-li-wei", { before: { invitedAt: daysAgo(20) }, after: { invitedAt: daysAgo(9) } }],
  [8, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [7, DEMO_OPS_ADMIN_ID, "owner.suspended", "o-farid", { before: { status: "active" }, after: { status: "suspended" }, reason: "Subscription unpaid after two warnings." }],
  [6, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [6, DEMO_SUPER_ADMIN_ID, "owner.unsuspended", "o-farid", { before: { status: "suspended", suspensionReason: "Subscription unpaid after two warnings." }, after: { status: "active" } }],
  [5, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [5, DEMO_OPS_ADMIN_ID, "owner.suspended", "o-mei", { before: { status: "active" }, after: { status: "suspended" }, reason: "Subscription unpaid for two billing cycles after warning." }],
  [4, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [3, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [2, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
  [1, DEMO_OPS_ADMIN_ID, "tenant.invite_resent", "t-m2", { before: { invitedAt: daysAgo(3) }, after: { invitedAt: daysAgo(1) } }],
  [1, DEMO_SUPER_ADMIN_ID, "admin.login", DEMO_SUPER_ADMIN_ID],
  [0, DEMO_OPS_ADMIN_ID, "admin.login", DEMO_OPS_ADMIN_ID],
];

export const auditMock: AuditEntry[] = seeds.map(([days, actorId, action, subjectId, extra], i) => ({
  id: `audit-${String(i + 1).padStart(3, "0")}`,
  action,
  actorId,
  actorName: actorName(actorId),
  subjectType: subjectId ? "user" : null,
  subjectId,
  subjectName: subjectName(subjectId),
  before: {},
  after: {},
  reason: null,
  ip: "203.0.113.42",
  createdAt: daysAgo(days),
  ...extra,
}));

/** Append a new demo audit row (demo adapters call this after every write). */
export const pushAudit = (entry: Omit<AuditEntry, "id" | "createdAt" | "ip" | "actorName" | "subjectName">): AuditEntry => {
  const row: AuditEntry = {
    ...entry,
    id: `audit-${String(auditMock.length + 1).padStart(3, "0")}`,
    actorName: entry.actorId ? actorName(entry.actorId) : null,
    subjectName: subjectName(entry.subjectId),
    ip: "203.0.113.42",
    createdAt: new Date().toISOString(),
  };
  auditMock.push(row);
  return row;
};
```

- [ ] **Step 4: Demo adapters**

```ts
// frontend/app/demo/services/admin/dashboard.ts
import type { AdminDashboardService } from "~/services/contracts/admin/dashboard";
import type { AdminAttentionItem, AdminDashboardData } from "~/types/admin";
import { adminOwnersMock, adminTenantsMock, adminPropertiesMock } from "~/demo/data/admin";

const ymKey = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
const DAY_MS = 86_400_000;

/** Demo mirror of backend Admin\DashboardController — keep in lock-step. */
const build = (): AdminDashboardData => {
  const now = new Date();
  const months: string[] = [];
  for (let i = 11; i >= 0; i--) months.push(ymKey(new Date(now.getFullYear(), now.getMonth() - i, 1)));

  const countBy = (dates: (string | null)[]) =>
    months.map((m) => dates.filter((d) => d && ymKey(new Date(d)) === m).length);

  const acceptance = months.map((m) => {
    const invited = adminTenantsMock.filter((t) => t.invitedAt && ymKey(new Date(t.invitedAt)) === m);
    if (invited.length === 0) return 0;
    return Math.round((invited.filter((t) => t.acceptedAt).length / invited.length) * 100);
  });

  // Synthetic but stable invoice counts for the chart (demo has no platform-wide invoice data).
  const invoicesIssued = months.map((_, i) => 18 + i * 2);
  const invoicesPaid = months.map((_, i) => 14 + i * 2 - (i % 3));

  const unitsTotal = adminOwnersMock.reduce((s, o) => s + o.counts.units, 0);
  const unitsOccupied = adminOwnersMock.reduce((s, o) => s + o.counts.unitsOccupied, 0);

  const attention: AdminAttentionItem[] = [];
  const push = (kind: AdminAttentionItem["kind"], ownerId: string, meta: string) => {
    const o = adminOwnersMock.find((x) => x.id === ownerId)!;
    attention.push({ kind, ownerId, ownerName: o.name, meta, link: `/admin/owners/${ownerId}` });
  };
  adminOwnersMock.forEach((o) => {
    if (o.unitsCap !== null && o.unitsUsed > o.unitsCap) push("over_cap", o.id, `${o.unitsUsed}/${o.unitsCap}`);
    if (o.counts.invoicesOverdue >= 3) push("overdue_3plus", o.id, `${o.counts.invoicesOverdue} overdue`);
    const stale = adminTenantsMock.filter((t) => t.ownerId === o.id && t.status === "invited" && t.invitedAt && now.getTime() - new Date(t.invitedAt).getTime() > 7 * DAY_MS).length;
    if (stale > 0) push("invite_stale_7d", o.id, `${stale} pending`);
    const ageDays = Math.floor((now.getTime() - new Date(o.createdAt).getTime()) / DAY_MS);
    if ((adminPropertiesMock[o.id]?.length ?? 0) === 0 && ageDays > 7) push("no_property_7d", o.id, `${ageDays}d`);
    if (o.status === "suspended") push("suspended", o.id, o.suspendedAt!.slice(0, 10));
  });

  return {
    tiles: {
      owners: { total: adminOwnersMock.length, active: adminOwnersMock.filter((o) => o.status === "active").length, suspended: adminOwnersMock.filter((o) => o.status === "suspended").length },
      tenants: { total: adminTenantsMock.length, invitedPending: adminTenantsMock.filter((t) => t.status === "invited").length },
      properties: Object.values(adminPropertiesMock).reduce((s, p) => s + p.length, 0),
      units: { total: unitsTotal, occupiedPct: unitsTotal ? Math.round((unitsOccupied / unitsTotal) * 100) : 0 },
      agreementsActive: adminOwnersMock.reduce((s, o) => s + o.counts.agreementsActive, 0),
      agreementsExpiring30d: adminOwnersMock.reduce((s, o) => s + o.counts.agreementsExpiring30d, 0),
      supportOpen: 0,
    },
    series: {
      months,
      ownerSignups: countBy(adminOwnersMock.map((o) => o.createdAt)),
      invoicesIssued,
      invoicesPaid,
      inviteAcceptanceRate: acceptance,
    },
    attention,
  };
};

export const demoAdminDashboard: AdminDashboardService = {
  async getDashboard() {
    return build();
  },
};
```

```ts
// frontend/app/demo/services/admin/owners.ts
import type { AdminOwnersService } from "~/services/contracts/admin/owners";
import type { AdminOwner, AuditEntry, Paginated } from "~/types/admin";
import { adminOwnersMock, adminPropertiesMock, adminTenantsMock, auditMock, pushAudit } from "~/demo/data/admin";

const actorId = () => useAuthStore().user?.id ?? null;

const paginate = <T>(rows: T[], page = 1, perPage = 20): Paginated<T> => ({
  data: rows.slice((page - 1) * perPage, page * perPage),
  meta: { page, perPage, total: rows.length, lastPage: Math.max(1, Math.ceil(rows.length / perPage)) },
});

export const demoAdminOwners: AdminOwnersService = {
  async list(query) {
    let rows = [...adminOwnersMock].sort((a, b) => b.createdAt.localeCompare(a.createdAt));
    const q = query.q?.trim().toLowerCase();
    if (q) rows = rows.filter((o) => [o.name, o.email, o.businessName ?? ""].some((s) => s.toLowerCase().includes(q)));
    if (query.plan) rows = rows.filter((o) => o.planTier === query.plan);
    if (query.status) rows = rows.filter((o) => o.status === query.status);
    if (query.overCap) rows = rows.filter((o) => o.unitsCap !== null && o.unitsUsed > o.unitsCap);
    if (query.overdue) rows = rows.filter((o) => o.counts.invoicesOverdue > 0);
    return structuredClone(paginate(rows, query.page, query.perPage));
  },

  async get(id) {
    const found = adminOwnersMock.find((o) => o.id === id);
    return found ? structuredClone(found) : null;
  },

  async properties(id) {
    return structuredClone(adminPropertiesMock[id] ?? []);
  },

  async tenants(id) {
    return structuredClone(adminTenantsMock.filter((t) => t.ownerId === id));
  },

  async history(id) {
    const owner = adminOwnersMock.find((o) => o.id === id);
    if (!owner) return [];
    const rows: AuditEntry[] = auditMock.filter((a) => a.subjectId === id);
    rows.push({
      id: `signup-${id}`, action: "owner.signup", actorId: null, actorName: null, subjectType: "user",
      subjectId: id, subjectName: owner.name, before: {}, after: { planTier: owner.planTier },
      reason: null, ip: null, createdAt: owner.createdAt,
    });
    return structuredClone(rows.sort((a, b) => b.createdAt.localeCompare(a.createdAt)));
  },

  async warn(id, input) {
    const owner = adminOwnersMock.find((o) => o.id === id);
    if (!owner) throw new Error(`Owner ${id} not found`);
    const text = `Your Roofly subscription payment is overdue; your account will be suspended on ${input.suspendOn} unless settled.` +
      (input.extraLine ? `\n\n${input.extraLine}` : "");
    pushAudit({ action: "owner.warned", actorId: actorId(), subjectType: "user", subjectId: id, before: {}, after: { ...input, extraLine: input.extraLine ?? null, text }, reason: null });
  },

  async suspend(id, reason) {
    const owner = adminOwnersMock.find((o) => o.id === id);
    if (!owner) throw new Error(`Owner ${id} not found`);
    if (owner.status === "suspended") throw new Error("Owner is already suspended.");
    owner.status = "suspended";
    owner.suspendedAt = new Date().toISOString();
    owner.suspensionReason = reason;
    pushAudit({ action: "owner.suspended", actorId: actorId(), subjectType: "user", subjectId: id, before: { status: "active" }, after: { status: "suspended" }, reason });
    return structuredClone(owner);
  },

  async unsuspend(id) {
    const owner = adminOwnersMock.find((o) => o.id === id);
    if (!owner) throw new Error(`Owner ${id} not found`);
    if (owner.status !== "suspended") throw new Error("Owner is not suspended.");
    const before = { status: "suspended", suspensionReason: owner.suspensionReason };
    owner.status = "active";
    owner.suspendedAt = null;
    owner.suspensionReason = null;
    pushAudit({ action: "owner.unsuspended", actorId: actorId(), subjectType: "user", subjectId: id, before, after: { status: "active" }, reason: null });
    return structuredClone(owner as AdminOwner);
  },
};
```

```ts
// frontend/app/demo/services/admin/tenants.ts
import type { AdminTenantsService } from "~/services/contracts/admin/tenants";
import { adminTenantsMock, pushAudit } from "~/demo/data/admin";

export const demoAdminTenants: AdminTenantsService = {
  async list(query) {
    let rows = [...adminTenantsMock].sort((a, b) => b.createdAt.localeCompare(a.createdAt));
    const q = query.q?.trim().toLowerCase();
    if (q) rows = rows.filter((t) => [t.name, t.email, t.phone ?? ""].some((s) => s.toLowerCase().includes(q)));
    if (query.status) rows = rows.filter((t) => t.status === query.status);
    if (query.ownerId) rows = rows.filter((t) => t.ownerId === query.ownerId);
    const page = query.page ?? 1;
    const perPage = query.perPage ?? 20;
    return structuredClone({
      data: rows.slice((page - 1) * perPage, page * perPage),
      meta: { page, perPage, total: rows.length, lastPage: Math.max(1, Math.ceil(rows.length / perPage)) },
    });
  },

  async get(id) {
    const found = adminTenantsMock.find((t) => t.id === id);
    return found ? structuredClone(found) : null;
  },

  async resendInvite(id) {
    const t = adminTenantsMock.find((x) => x.id === id);
    if (!t) throw new Error(`Tenant ${id} not found`);
    if (t.status !== "invited") throw new Error("Only pending invites can be resent.");
    const before = { invitedAt: t.invitedAt };
    t.invitedAt = new Date().toISOString();
    pushAudit({ action: "tenant.invite_resent", actorId: useAuthStore().user?.id ?? null, subjectType: "user", subjectId: id, before, after: { invitedAt: t.invitedAt }, reason: null });
  },
};
```

```ts
// frontend/app/demo/services/admin/admins.ts
import type { AdminAdminsService } from "~/services/contracts/admin/admins";
import type { AdminUser } from "~/types/admin";
import { ADMIN_PERMISSIONS } from "~/types/admin";
import { adminUsersMock, pushAudit } from "~/demo/data/admin";

const PRESET = ADMIN_PERMISSIONS.filter((k) =>
  ["dashboard.view", "owners.view", "tenants.view", "owners.warn", "owners.suspend", "support.manage", "broadcast.send"].includes(k),
);

const me = () => useAuthStore().user?.id ?? null;

export const demoAdminAdmins: AdminAdminsService = {
  async permissions() {
    return { permissions: ADMIN_PERMISSIONS.map((key) => ({ key, preset: PRESET.includes(key) })), preset: [...PRESET] };
  },

  async list() {
    return structuredClone([...adminUsersMock].sort((a, b) => b.createdAt.localeCompare(a.createdAt)));
  },

  async create(input) {
    if (adminUsersMock.some((a) => a.email === input.email)) throw new Error("Email already in use.");
    const created: AdminUser = {
      id: crypto.randomUUID(), name: input.name, email: input.email, permissions: [...input.permissions],
      isSuperAdmin: input.isSuperAdmin ?? false, status: "invited", lastActiveAt: null, createdAt: new Date().toISOString(),
    };
    adminUsersMock.push(created);
    pushAudit({ action: "admin.invite_sent", actorId: me(), subjectType: "user", subjectId: created.id, before: {}, after: { permissions: created.permissions, isSuperAdmin: created.isSuperAdmin }, reason: null });
    return structuredClone(created);
  },

  async update(id, patch) {
    const a = adminUsersMock.find((x) => x.id === id);
    if (!a) throw new Error(`Admin ${id} not found`);
    if (patch.disabled && id === me()) throw new Error("You cannot disable your own account.");
    const wouldDrop = (patch.disabled === true) || (patch.isSuperAdmin === false);
    if (a.isSuperAdmin && a.status !== "disabled" && wouldDrop) {
      const others = adminUsersMock.filter((x) => x.id !== id && x.isSuperAdmin && x.status !== "disabled").length;
      if (others === 0) throw new Error("There must always be at least one enabled super-admin.");
    }
    if (patch.permissions !== undefined || patch.isSuperAdmin !== undefined) {
      const before = { permissions: [...a.permissions], isSuperAdmin: a.isSuperAdmin };
      if (patch.permissions !== undefined) a.permissions = [...patch.permissions];
      if (patch.isSuperAdmin !== undefined) a.isSuperAdmin = patch.isSuperAdmin;
      pushAudit({ action: "admin.permissions_changed", actorId: me(), subjectType: "user", subjectId: id, before, after: { permissions: [...a.permissions], isSuperAdmin: a.isSuperAdmin }, reason: null });
    }
    if (patch.disabled === true && a.status !== "disabled") {
      a.status = "disabled";
      pushAudit({ action: "admin.disabled", actorId: me(), subjectType: "user", subjectId: id, before: { status: "active" }, after: { status: "disabled" }, reason: null });
    } else if (patch.disabled === false && a.status === "disabled") {
      a.status = a.lastActiveAt ? "active" : "invited";
      pushAudit({ action: "admin.enabled", actorId: me(), subjectType: "user", subjectId: id, before: { status: "disabled" }, after: { status: "active" }, reason: null });
    }
    return structuredClone(a);
  },

  async resendInvite(id) {
    const a = adminUsersMock.find((x) => x.id === id);
    if (!a) throw new Error(`Admin ${id} not found`);
    if (a.status !== "invited") throw new Error("This admin has already accepted their invite.");
    pushAudit({ action: "admin.invite_sent", actorId: me(), subjectType: "user", subjectId: id, before: {}, after: { resend: true }, reason: null });
  },
};
```

```ts
// frontend/app/demo/services/admin/audit.ts
import type { AdminAuditService } from "~/services/contracts/admin/audit";
import type { AuditEntry, AuditQuery } from "~/types/admin";
import { auditMock } from "~/demo/data/admin";
import { buildCsv } from "~/utils/csv";

const filter = (query: AuditQuery): AuditEntry[] => {
  const user = useAuthStore().user;
  const seesAll = !!user && (user.isSuperAdmin || user.permissions.includes("audit.view"));
  let rows = [...auditMock].sort((a, b) => b.createdAt.localeCompare(a.createdAt) || b.id.localeCompare(a.id));
  if (!seesAll) rows = rows.filter((r) => r.actorId === user?.id);
  else if (query.actorId) rows = rows.filter((r) => r.actorId === query.actorId);
  if (query.action) rows = rows.filter((r) => r.action === query.action);
  if (query.subjectType) rows = rows.filter((r) => r.subjectType === query.subjectType);
  if (query.subjectId) rows = rows.filter((r) => r.subjectId === query.subjectId);
  if (query.from) rows = rows.filter((r) => r.createdAt >= `${query.from}T00:00:00`);
  if (query.to) rows = rows.filter((r) => r.createdAt <= `${query.to}T23:59:59.999Z`);
  return rows;
};

export const demoAdminAudit: AdminAuditService = {
  async list(query) {
    const rows = filter(query);
    const page = query.page ?? 1;
    const perPage = query.perPage ?? 25;
    return structuredClone({
      data: rows.slice((page - 1) * perPage, page * perPage),
      meta: { page, perPage, total: rows.length, lastPage: Math.max(1, Math.ceil(rows.length / perPage)) },
    });
  },

  async exportCsv(query) {
    return buildCsv(
      ["id", "createdAt", "action", "actorName", "subjectType", "subjectId", "subjectName", "reason", "before", "after"],
      filter(query).map((r) => [r.id, r.createdAt, r.action, r.actorName, r.subjectType, r.subjectId, r.subjectName, r.reason, JSON.stringify(r.before), JSON.stringify(r.after)]),
    );
  },
};
```

- [ ] **Step 5: API adapters**

```ts
// frontend/app/services/api/admin/dashboard.ts
import type { AdminDashboardService } from "~/services/contracts/admin/dashboard";
import type { AdminDashboardData } from "~/types/admin";

export const apiAdminDashboard: AdminDashboardService = {
  getDashboard: () => useApi().request<AdminDashboardData>("/admin/dashboard"),
};
```

```ts
// frontend/app/services/api/admin/owners.ts
import type { AdminOwnersService } from "~/services/contracts/admin/owners";
import type { AdminOwner, AdminPropertySummary, AdminTenant, AuditEntry, Paginated } from "~/types/admin";

/** Drop undefined/false/empty so the query string only carries active filters. */
const clean = (q: Record<string, unknown>) =>
  Object.fromEntries(Object.entries(q).filter(([, v]) => v !== undefined && v !== "" && v !== false).map(([k, v]) => [k, v === true ? 1 : v]));

export const apiAdminOwners: AdminOwnersService = {
  list: (query) => useApi().request<Paginated<AdminOwner>>("/admin/owners", { query: clean({ ...query }) }),
  get: (id) => useApi().request<AdminOwner>(`/admin/owners/${id}`),
  properties: (id) => useApi().request<AdminPropertySummary[]>(`/admin/owners/${id}/properties`),
  tenants: (id) => useApi().request<AdminTenant[]>(`/admin/owners/${id}/tenants`),
  history: (id) => useApi().request<AuditEntry[]>(`/admin/owners/${id}/history`),
  warn: async (id, input) => {
    await useApi().request(`/admin/owners/${id}/warn`, { method: "POST", body: input });
  },
  suspend: (id, reason) => useApi().request<AdminOwner>(`/admin/owners/${id}/suspend`, { method: "POST", body: { reason } }),
  unsuspend: (id) => useApi().request<AdminOwner>(`/admin/owners/${id}/unsuspend`, { method: "POST" }),
};
```

```ts
// frontend/app/services/api/admin/tenants.ts
import type { AdminTenantsService } from "~/services/contracts/admin/tenants";
import type { AdminTenant, Paginated } from "~/types/admin";

const clean = (q: Record<string, unknown>) =>
  Object.fromEntries(Object.entries(q).filter(([, v]) => v !== undefined && v !== ""));

export const apiAdminTenants: AdminTenantsService = {
  list: (query) => useApi().request<Paginated<AdminTenant>>("/admin/tenants", { query: clean({ ...query }) }),
  get: (id) => useApi().request<AdminTenant>(`/admin/tenants/${id}`),
  resendInvite: async (id) => {
    await useApi().request(`/admin/tenants/${id}/resend-invite`, { method: "POST" });
  },
};
```

```ts
// frontend/app/services/api/admin/admins.ts
import type { AdminAdminsService } from "~/services/contracts/admin/admins";
import type { AdminUser, PermissionCatalogue } from "~/types/admin";

export const apiAdminAdmins: AdminAdminsService = {
  permissions: () => useApi().request<PermissionCatalogue>("/admin/permissions"),
  list: () => useApi().request<AdminUser[]>("/admin/admins"),
  create: (input) => useApi().request<AdminUser>("/admin/admins", { method: "POST", body: input }),
  update: (id, patch) => useApi().request<AdminUser>(`/admin/admins/${id}`, { method: "PATCH", body: patch }),
  resendInvite: async (id) => {
    await useApi().request(`/admin/admins/${id}/resend-invite`, { method: "POST" });
  },
};
```

```ts
// frontend/app/services/api/admin/audit.ts
import type { AdminAuditService } from "~/services/contracts/admin/audit";
import type { AuditEntry, Paginated } from "~/types/admin";

const clean = (q: Record<string, unknown>) =>
  Object.fromEntries(Object.entries(q).filter(([, v]) => v !== undefined && v !== ""));

export const apiAdminAudit: AdminAuditService = {
  list: (query) => useApi().request<Paginated<AuditEntry>>("/admin/audit", { query: clean({ ...query }) }),
  exportCsv: (query) =>
    useApi().request<string>("/admin/audit/export.csv", { query: clean({ ...query }), responseType: "text" }),
};
```

- [ ] **Step 6: Selectors** (auto-imported from `services/`)

```ts
// frontend/app/services/useAdminDashboard.ts
import type { AdminDashboardService } from "~/services/contracts/admin/dashboard";
import { demoAdminDashboard } from "~/demo/services/admin/dashboard";
import { apiAdminDashboard } from "~/services/api/admin/dashboard";

/** Demo → fake platform; otherwise the Laravel API. Chosen once per call. */
export const useAdminDashboard = (): AdminDashboardService =>
  useEnv().useMock ? demoAdminDashboard : apiAdminDashboard;
```
```ts
// frontend/app/services/useAdminOwners.ts
import type { AdminOwnersService } from "~/services/contracts/admin/owners";
import { demoAdminOwners } from "~/demo/services/admin/owners";
import { apiAdminOwners } from "~/services/api/admin/owners";

export const useAdminOwners = (): AdminOwnersService =>
  useEnv().useMock ? demoAdminOwners : apiAdminOwners;
```
```ts
// frontend/app/services/useAdminTenants.ts
import type { AdminTenantsService } from "~/services/contracts/admin/tenants";
import { demoAdminTenants } from "~/demo/services/admin/tenants";
import { apiAdminTenants } from "~/services/api/admin/tenants";

export const useAdminTenants = (): AdminTenantsService =>
  useEnv().useMock ? demoAdminTenants : apiAdminTenants;
```
```ts
// frontend/app/services/useAdminAdmins.ts
import type { AdminAdminsService } from "~/services/contracts/admin/admins";
import { demoAdminAdmins } from "~/demo/services/admin/admins";
import { apiAdminAdmins } from "~/services/api/admin/admins";

export const useAdminAdmins = (): AdminAdminsService =>
  useEnv().useMock ? demoAdminAdmins : apiAdminAdmins;
```
```ts
// frontend/app/services/useAdminAudit.ts
import type { AdminAuditService } from "~/services/contracts/admin/audit";
import { demoAdminAudit } from "~/demo/services/admin/audit";
import { apiAdminAudit } from "~/services/api/admin/audit";

export const useAdminAudit = (): AdminAuditService =>
  useEnv().useMock ? demoAdminAudit : apiAdminAudit;
```

- [ ] **Step 7: Gate**

Run: `docker exec roofly-frontend npm run typecheck` → 5 known errors only.
Run: `grep -rn "useApi" frontend/app/demo/; grep -rn "~/demo" frontend/app/services/api/; grep -rn "if (useMock" frontend/app/` → all empty.

---

### Task 17: Admin accent token, `auth-admin` layout, `/admin/login`, `/admin/accept-invite`

**Files:**
- Modify: `frontend/app/assets/css/tokens.css`, `frontend/tailwind.config.ts`, `docs/frontend/UI-STANDARDS.md`
- Create: `frontend/app/layouts/auth-admin.vue`, `frontend/app/pages/admin/login.vue`, `frontend/app/pages/admin/accept-invite.vue`

**Interfaces produced:**
- Tokens `--admin-accent` / `--admin-accent-soft` (light `#2f4f6b` / `rgba(47,79,107,0.14)`, dark `#7fa6c9` / `rgba(127,166,201,0.22)`), Tailwind `text-admin`, `bg-admin`, `bg-admin-soft`, `border-admin`.
- Layout `auth-admin`: single centred column on a charcoal page (`#1c1a17`) with a light form card — no marketing pane, no demo shortcuts, no signup link.

- [ ] **Step 1: Tokens**

`tokens.css` — add to `:root` after the brand accent block:
```css
  /* Admin shell accent — steel blue, so the back office never reads as the customer app */
  --admin-accent: #2f4f6b;
  --admin-accent-soft: rgba(47, 79, 107, 0.14);
```
to `[data-theme="dark"]`:
```css
  --admin-accent: #7fa6c9;
  --admin-accent-soft: rgba(127, 166, 201, 0.22);
```
and to the forced `[data-theme="light"]` block the same two light values.

`tailwind.config.ts` `colors` — add:
```ts
        admin: {
          DEFAULT: "var(--admin-accent)",
          soft: "var(--admin-accent-soft)",
        },
```

`docs/frontend/UI-STANDARDS.md` — add under § 1.5 (after the brand accent table) a short "Admin shell accent" note:
> `--admin-accent` (#2f4f6b light / #7fa6c9 dark) + `--admin-accent-soft`. Used only in `layouts/admin.vue`, `layouts/auth-admin.vue` and `components/admin/*` for active nav, wordmark and primary emphasis. Never in owner/tenant surfaces. Everything else in the admin (pills, buttons, cards) uses the shared tokens.

- [ ] **Step 2: auth-admin layout**

```vue
<!-- frontend/app/layouts/auth-admin.vue -->
<script setup lang="ts">
import { ShieldCheck } from "lucide-vue-next";
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";

const { t } = useI18n();
</script>

<template>
  <!-- Charcoal page, light card: deliberately not the customer auth chrome (spec § 3). -->
  <div
    class="min-h-dvh flex flex-col"
    style="background-color: #1c1a17; color: #f7f4ed"
  >
    <header class="flex items-center justify-between px-6 py-4">
      <NuxtLink to="/admin/login" class="inline-flex items-center gap-2 text-card-title font-semibold tracking-tight">
        <ShieldCheck :size="22" :stroke-width="1.75" style="color: #7fa6c9" />
        <span>Roofly.my · {{ t("auth.admin.title") }}</span>
      </NuxtLink>
      <div data-theme="dark"><LangSwitcher /></div>
    </header>

    <main class="flex-1 flex items-center justify-center px-6 py-10">
      <div
        data-theme="light"
        class="w-full max-w-auth-card rounded-xl border border-line-passive bg-surface-raised text-ink p-8 shadow-modal"
      >
        <slot />
      </div>
    </main>

    <footer class="px-6 py-4 text-center text-micro" style="color: rgba(247, 244, 237, 0.6)">
      © {{ new Date().getFullYear() }} Roofly.my · {{ t("common.tagline") }}
    </footer>
  </div>
</template>
```

- [ ] **Step 3: Login page**

```vue
<!-- frontend/app/pages/admin/login.vue -->
<script setup lang="ts">
import { ref } from "vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";

definePageMeta({ layout: "auth-admin" });

const { t } = useI18n();
useHead({ title: () => t("auth.admin.title") });

const auth = useAuthStore();
const email = ref("");
const password = ref("");
const error = ref<string | null>(null);

// Already signed in as an admin? Skip the form.
if (import.meta.client && auth.isAdmin) {
  await navigateTo("/admin");
}

const onSubmit = async () => {
  error.value = null;
  if (!email.value || !password.value) {
    error.value = t("validation.required");
    return;
  }
  try {
    await auth.loginAdmin(email.value, password.value);
    await navigateTo("/admin");
  } catch {
    error.value = t("auth.invalidCredentials");
  }
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("auth.admin.title") }}</h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("auth.admin.subtitle") }}</p>
    </header>

    <form class="space-y-4" @submit.prevent="onSubmit">
      <Input v-model="email" type="email" autocomplete="email" :label="t('auth.email')" size="lg" />
      <Input v-model="password" type="password" autocomplete="current-password" :label="t('auth.password')" size="lg" />
      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
      <Button type="submit" variant="primary" size="lg" :loading="auth.loading" block>
        {{ t("auth.admin.login") }}
      </Button>
    </form>

    <p class="mt-6 text-center text-caption text-ink-muted">
      {{ t("auth.admin.notCustomer") }}
      <NuxtLink to="/auth/login" class="text-ink underline underline-offset-2">{{ t("auth.admin.customerLogin") }}</NuxtLink>
    </p>
  </div>
</template>
```

- [ ] **Step 4: Accept-invite page**

```vue
<!-- frontend/app/pages/admin/accept-invite.vue -->
<script setup lang="ts">
import { computed, ref } from "vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";

definePageMeta({ layout: "auth-admin" });

const { t } = useI18n();
useHead({ title: () => t("auth.admin.acceptTitle") });

const route = useRoute();
const auth = useAuthStore();
const token = computed(() => String(route.query.token ?? ""));
const password = ref("");
const confirm = ref("");
const error = ref<string | null>(null);

const onSubmit = async () => {
  error.value = null;
  if (password.value.length < 8) {
    error.value = t("auth.admin.passwordTooShort");
    return;
  }
  if (password.value !== confirm.value) {
    error.value = t("auth.admin.passwordMismatch");
    return;
  }
  try {
    await auth.acceptAdminInvite(token.value, password.value);
    await navigateTo("/admin");
  } catch {
    error.value = t("auth.admin.inviteInvalid");
  }
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("auth.admin.acceptTitle") }}</h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("auth.admin.acceptSubtitle") }}</p>
    </header>

    <p v-if="!token" class="text-caption text-accent" role="alert">{{ t("auth.admin.inviteInvalid") }}</p>

    <form v-else class="space-y-4" @submit.prevent="onSubmit">
      <Input v-model="password" type="password" autocomplete="new-password" :label="t('auth.admin.newPassword')" size="lg" />
      <Input v-model="confirm" type="password" autocomplete="new-password" :label="t('auth.admin.confirmPassword')" size="lg" />
      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
      <Button type="submit" variant="primary" size="lg" :loading="auth.loading" block>
        {{ t("auth.admin.accept") }}
      </Button>
    </form>
  </div>
</template>
```

- [ ] **Step 5: Gate**

Run: `docker exec roofly-frontend npm run typecheck` → 5 known errors only.

---

### Task 18: `admin` layout, sidebar, shared admin components, base `admin.*` strings

**Files:**
- Create: `frontend/app/layouts/admin.vue`, `frontend/app/components/admin/SidebarNav.vue`, `frontend/app/components/admin/StatTile.vue`, `frontend/app/components/admin/OwnerStatusPill.vue`, `frontend/app/components/admin/DataTableShell.vue`
- Modify: `frontend/app/components/topbar/UserMenu.vue` (admin logout → `/admin/login`), `frontend/i18n/locales/{en,ms}.json`

**Interfaces produced:**
- Layout `admin` — same skeleton as `owner.vue` (desktop sidebar / mobile drawer / topbar) with `ShieldCheck` wordmark in `text-admin`, a thin `bg-admin` top border so it is visually distinct, no `DemoTourButton`.
- `AdminSidebarNav` items: Dashboard `/admin` (`dashboard.view`), Owners `/admin/owners` (`owners.view`), Tenants `/admin/tenants` (`tenants.view`), Audit `/admin/audit` (always — own entries), Settings `/admin/settings` (`admins.manage`). Items the admin can't use are hidden via `useAdminPermissions().can`.
- `<StatTile :label :value :help>` — count tile (no money), `value: number | string`.
- `<OwnerStatusPill :status>` — `active` → `Pill tone="active"`, `suspended` → `tone="terminated"`.
- `<DataTableShell :loading :empty-title :empty-description :range>` — wraps a TanStack table: renders slot `#table` from `sm:` up, slot `#cards` under `sm` (UI-STANDARDS § 11.14), shows loading / empty states and a pagination footer via slot `#pagination`.
- i18n namespace `admin.*` with `nav`, `common` (pagination / filters / actions), `status` values.

- [ ] **Step 1: Layout + sidebar**

```vue
<!-- frontend/app/layouts/admin.vue -->
<script setup lang="ts">
import { ShieldCheck, Menu } from "lucide-vue-next";
import { ref } from "vue";
import AdminSidebarNav from "~/components/admin/SidebarNav.vue";
import ThemeToggle from "~/components/topbar/ThemeToggle.vue";
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";
import UserMenu from "~/components/topbar/UserMenu.vue";
import MobileNavDrawer from "~/components/layout/MobileNavDrawer.vue";

const drawerOpen = ref(false);
const { t } = useI18n();
</script>

<template>
  <div class="min-h-dvh bg-surface-page text-ink flex border-t-4 border-admin">
    <aside class="hidden md:flex w-64 shrink-0 flex-col border-r border-line-passive px-3 py-4">
      <NuxtLink to="/admin" class="inline-flex items-center gap-2 px-3 py-2 mb-4 text-card-title font-semibold tracking-tight">
        <ShieldCheck :size="20" :stroke-width="1.75" class="text-admin" />
        <span>Roofly.my</span>
        <span class="ml-1 rounded-pill bg-admin-soft px-2 py-0.5 text-micro font-medium text-admin">{{ t("admin.nav.badge") }}</span>
      </NuxtLink>
      <AdminSidebarNav />
    </aside>

    <MobileNavDrawer v-model="drawerOpen" home-to="/admin">
      <AdminSidebarNav />
    </MobileNavDrawer>

    <div class="flex-1 flex flex-col min-w-0">
      <header class="h-16 flex items-center justify-between gap-2 px-4 md:px-6 border-b border-line-passive">
        <div class="flex items-center gap-2 md:hidden">
          <button
            type="button"
            class="inline-flex items-center justify-center w-9 h-9 rounded-sm text-ink-strong hover:bg-surface-hover focus-visible:shadow-focus transition"
            aria-label="Open menu"
            @click="drawerOpen = true"
          >
            <Menu :size="22" :stroke-width="1.5" />
          </button>
          <NuxtLink to="/admin" class="inline-flex items-center gap-2 text-card-title font-semibold tracking-tight">
            <ShieldCheck :size="20" :stroke-width="1.75" class="text-admin" />
            <span>Roofly.my</span>
          </NuxtLink>
        </div>
        <div class="flex items-center gap-2 ml-auto">
          <div class="hidden md:inline-flex md:items-center md:gap-1">
            <ThemeToggle />
            <LangSwitcher />
          </div>
          <UserMenu />
        </div>
      </header>
      <main class="flex-1 px-4 md:px-6 py-8 overflow-auto">
        <div class="max-w-app mx-auto"><slot /></div>
      </main>
    </div>
  </div>
</template>
```

```vue
<!-- frontend/app/components/admin/SidebarNav.vue -->
<script setup lang="ts">
import { LayoutDashboard, Building2, Users, ScrollText, Settings } from "lucide-vue-next";
import type { AdminPermission } from "~/types/admin";

const { t } = useI18n();
const { can } = useAdminPermissions();

type Item = { to: string; label: string; icon: unknown; exact?: boolean; needs?: AdminPermission };

const items = computed<Item[]>(() =>
  (
    [
      { to: "/admin", label: t("admin.nav.dashboard"), icon: LayoutDashboard, exact: true, needs: "dashboard.view" },
      { to: "/admin/owners", label: t("admin.nav.owners"), icon: Building2, needs: "owners.view" },
      { to: "/admin/tenants", label: t("admin.nav.tenants"), icon: Users, needs: "tenants.view" },
      { to: "/admin/audit", label: t("admin.nav.audit"), icon: ScrollText },
      { to: "/admin/settings", label: t("admin.nav.settings"), icon: Settings, needs: "admins.manage" },
    ] as Item[]
  ).filter((i) => !i.needs || can(i.needs)),
);
</script>

<template>
  <nav class="flex flex-col gap-0.5">
    <NuxtLink
      v-for="item in items"
      :key="item.to"
      :to="item.to"
      :exact-active-class="'bg-admin-soft text-admin'"
      :active-class="item.exact ? '' : 'bg-admin-soft text-admin'"
      class="flex items-center gap-3 px-4 py-2.5 rounded-sm text-caption text-ink-strong hover:bg-surface-hover focus-visible:shadow-focus transition"
    >
      <component :is="item.icon" :size="18" :stroke-width="1.5" />
      <span>{{ item.label }}</span>
    </NuxtLink>
  </nav>
</template>
```

- [ ] **Step 2: Shared components**

```vue
<!-- frontend/app/components/admin/StatTile.vue -->
<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
defineProps<{ label: string; value: number | string; help?: string }>();
</script>

<template>
  <Card padding="standard">
    <p class="text-caption text-ink-muted">{{ label }}</p>
    <p class="mt-2 text-display-sub font-semibold tracking-snug tabular-nums">{{ value }}</p>
    <p v-if="help" class="mt-1 text-micro text-ink-faint">{{ help }}</p>
  </Card>
</template>
```

```vue
<!-- frontend/app/components/admin/OwnerStatusPill.vue -->
<script setup lang="ts">
import Pill from "~/components/ui/Pill.vue";
import type { OwnerStatus } from "~/types/admin";
defineProps<{ status: OwnerStatus }>();
const { t } = useI18n();
</script>

<template>
  <Pill :tone="status === 'suspended' ? 'terminated' : 'active'">
    {{ t(`admin.status.owner.${status}`) }}
  </Pill>
</template>
```

```vue
<!-- frontend/app/components/admin/DataTableShell.vue -->
<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Button from "~/components/ui/Button.vue";

defineProps<{
  loading: boolean;
  empty: boolean;
  emptyTitle: string;
  emptyDescription?: string;
  page: number;
  lastPage: number;
  total: number;
}>();
defineEmits<{ "update:page": [page: number] }>();
const { t } = useI18n();
</script>

<template>
  <Card v-if="loading" padding="loose">
    <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
  </Card>
  <Card v-else-if="empty" padding="loose">
    <EmptyState icon="SearchX" :title="emptyTitle" :description="emptyDescription" />
  </Card>
  <template v-else>
    <!-- Desktop: the TanStack table. Mobile: card rows (UI-STANDARDS § 11.14). -->
    <Card padding="compact" class="hidden sm:block overflow-x-auto">
      <slot name="table" />
    </Card>
    <div class="sm:hidden space-y-3">
      <slot name="cards" />
    </div>
    <footer class="mt-4 flex items-center justify-between gap-3 text-caption text-ink-muted">
      <span>{{ t("admin.common.pageOf", { page, lastPage, total }) }}</span>
      <div class="flex gap-2">
        <Button variant="ghost" size="sm" :disabled="page <= 1" @click="$emit('update:page', page - 1)">{{ t("common.back") }}</Button>
        <Button variant="ghost" size="sm" :disabled="page >= lastPage" @click="$emit('update:page', page + 1)">{{ t("common.next") }}</Button>
      </div>
    </footer>
  </template>
</template>
```

- [ ] **Step 3: UserMenu logout target**

In `frontend/app/components/topbar/UserMenu.vue` change `onLogout`:
```ts
const onLogout = async () => {
  const wasAdmin = auth.isAdmin;
  await auth.logout();
  await navigateTo(wasAdmin ? "/admin/login" : isDemo ? "/demo" : "/auth/login");
};
```

- [ ] **Step 4: Base strings** — add a top-level `admin` object to both locale files (later tasks extend it; keep keys in this order):

`en.json`:
```json
"admin": {
  "nav": { "badge": "Admin", "dashboard": "Dashboard", "owners": "Owners", "tenants": "Tenants", "audit": "Audit log", "settings": "Settings" },
  "common": {
    "search": "Search", "filters": "Filters", "clearFilters": "Clear filters", "all": "All",
    "pageOf": "Page {page} of {lastPage} · {total} total", "noResults": "No results",
    "noResultsHelp": "Try a different search or clear the filters.", "back": "Back to list",
    "notFound": "Not found", "lastActive": "Last active", "never": "Never", "signedUp": "Signed up",
    "reason": "Reason", "confirm": "Confirm", "actions": "Actions", "exportCsv": "Export CSV", "unlimited": "Unlimited"
  },
  "status": {
    "owner": { "active": "Active", "suspended": "Suspended" },
    "tenant": { "invited": "Invited", "active": "Active", "notice_given": "Notice given", "moved_out": "Moved out" },
    "admin": { "invited": "Invited", "active": "Active", "disabled": "Disabled" }
  },
  "plan": { "free": "Free", "starter": "Starter", "pro": "Pro", "business": "Business" }
}
```
`ms.json`:
```json
"admin": {
  "nav": { "badge": "Admin", "dashboard": "Papan pemuka", "owners": "Pemilik", "tenants": "Penyewa", "audit": "Log audit", "settings": "Tetapan" },
  "common": {
    "search": "Cari", "filters": "Penapis", "clearFilters": "Kosongkan penapis", "all": "Semua",
    "pageOf": "Halaman {page} daripada {lastPage} · {total} jumlah", "noResults": "Tiada hasil",
    "noResultsHelp": "Cuba carian lain atau kosongkan penapis.", "back": "Kembali ke senarai",
    "notFound": "Tidak dijumpai", "lastActive": "Aktif terakhir", "never": "Tidak pernah", "signedUp": "Mendaftar",
    "reason": "Sebab", "confirm": "Sahkan", "actions": "Tindakan", "exportCsv": "Eksport CSV", "unlimited": "Tanpa had"
  },
  "status": {
    "owner": { "active": "Aktif", "suspended": "Digantung" },
    "tenant": { "invited": "Dijemput", "active": "Aktif", "notice_given": "Notis diberi", "moved_out": "Berpindah keluar" },
    "admin": { "invited": "Dijemput", "active": "Aktif", "disabled": "Dinyahaktifkan" }
  },
  "plan": { "free": "Percuma", "starter": "Starter", "pro": "Pro", "business": "Business" }
}
```

- [ ] **Step 5: Gate**

Run: `docker exec roofly-frontend npm run typecheck` → 5 known errors only.

---

### Task 19: Admin dashboard page (`/admin`)

**Files:**
- Create: `frontend/app/composables/useAdminDashboardData.ts`, `frontend/app/components/admin/AttentionList.vue`, `frontend/app/pages/admin/index.vue`
- Modify: `frontend/i18n/locales/{en,ms}.json` (`admin.dashboard.*`)

**Interfaces produced:**
- `useAdminDashboardData()` → `{ load(), loading, data, tiles, attention, signupSeries, invoiceSeries, acceptanceSeries }` where each series is `{ key, label, amount }[]` (the `MiniAreaChart` datum shape; `amount` here is a count — the chart's `formatRM` badge is suppressed via a new `format` prop, see Step 2).
- `<AttentionList :items>` — card-row list (UI-STANDARDS § 11.2): pill for `kind` + meta on top, owner name below, whole row links to `item.link`.

- [ ] **Step 1: Composable**

```ts
// frontend/app/composables/useAdminDashboardData.ts
import { computed, ref } from "vue";
import type { AdminAttentionItem, AdminDashboardData } from "~/types/admin";

const EMPTY: AdminDashboardData = {
  tiles: {
    owners: { total: 0, active: 0, suspended: 0 }, tenants: { total: 0, invitedPending: 0 },
    properties: 0, units: { total: 0, occupiedPct: 0 }, agreementsActive: 0, agreementsExpiring30d: 0, supportOpen: 0,
  },
  series: { months: [], ownerSignups: [], invoicesIssued: [], invoicesPaid: [], inviteAcceptanceRate: [] },
  attention: [],
};

/** Mirrors useDashboard(): one fetch, localised month labels client-side. */
export const useAdminDashboardData = () => {
  const loading = ref(true);
  const data = ref<AdminDashboardData | null>(null);

  const load = async () => {
    loading.value = true;
    try {
      data.value = await useAdminDashboard().getDashboard();
    } finally {
      loading.value = false;
    }
  };

  const label = (key: string) => new Date(`${key}-01`).toLocaleDateString("en-MY", { month: "short" });
  const series = (pick: (d: AdminDashboardData["series"]) => number[]) =>
    computed(() => {
      const s = data.value?.series ?? EMPTY.series;
      return s.months.map((key, i) => ({ key, label: label(key), amount: pick(s)[i] ?? 0 }));
    });

  return {
    load,
    loading,
    data,
    tiles: computed(() => data.value?.tiles ?? EMPTY.tiles),
    attention: computed<AdminAttentionItem[]>(() => data.value?.attention ?? []),
    signupSeries: series((s) => s.ownerSignups),
    invoiceSeries: series((s) => s.invoicesPaid),
    acceptanceSeries: series((s) => s.inviteAcceptanceRate),
  };
};
```

- [ ] **Step 2: `MiniAreaChart` count mode**

`frontend/app/components/ui/MiniAreaChart.vue` currently formats the hover/highlight badge with `formatRM`. Add an optional prop `format?: (n: number) => string` (default `formatRM`) and use `props.format(...)` wherever `formatRM(...)` is called inside the component. Owner pages are unaffected (default unchanged).

- [ ] **Step 3: AttentionList + page**

```vue
<!-- frontend/app/components/admin/AttentionList.vue -->
<script setup lang="ts">
import Pill from "~/components/ui/Pill.vue";
import type { AdminAttentionItem, AdminAttentionKind } from "~/types/admin";

defineProps<{ items: AdminAttentionItem[] }>();
const { t } = useI18n();

const tone: Record<AdminAttentionKind, "overdue" | "maintenance" | "draft" | "pending" | "terminated"> = {
  over_cap: "maintenance", overdue_3plus: "overdue", invite_stale_7d: "pending", no_property_7d: "draft", suspended: "terminated",
};
</script>

<template>
  <ul class="divide-y divide-line-passive">
    <li v-for="item in items" :key="`${item.kind}-${item.ownerId}`">
      <NuxtLink :to="item.link" class="block py-3 rounded-sm hover:bg-surface-hover focus-visible:shadow-focus transition -mx-2 px-2">
        <div class="flex items-center gap-2">
          <Pill :tone="tone[item.kind]">{{ t(`admin.dashboard.attention.kinds.${item.kind}`) }}</Pill>
          <span class="text-micro text-ink-faint tabular-nums">{{ item.meta }}</span>
        </div>
        <p class="mt-1 text-body font-medium text-ink">{{ item.ownerName }}</p>
      </NuxtLink>
    </li>
  </ul>
</template>
```

```vue
<!-- frontend/app/pages/admin/index.vue -->
<script setup lang="ts">
import { onMounted } from "vue";
import Card from "~/components/ui/Card.vue";
import MiniAreaChart from "~/components/ui/MiniAreaChart.vue";
import StatTile from "~/components/admin/StatTile.vue";
import AttentionList from "~/components/admin/AttentionList.vue";

definePageMeta({ layout: "admin" });
const { t } = useI18n();
useHead({ title: () => t("admin.dashboard.title") });

const dash = useAdminDashboardData();
onMounted(dash.load);

const count = (n: number) => String(n);
const pct = (n: number) => `${n}%`;
</script>

<template>
  <div>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("admin.dashboard.title") }}</h1>
      <p class="mt-2 text-caption text-ink-muted">{{ t("admin.dashboard.subtitle") }}</p>
    </header>

    <Card v-if="dash.loading.value" padding="loose">
      <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
    </Card>

    <template v-else>
      <section class="mb-6 grid grid-cols-1 gap-4 sm:mb-8 sm:grid-cols-2 sm:gap-6 2xl:grid-cols-4">
        <StatTile :label="t('admin.dashboard.tiles.owners')" :value="dash.tiles.value.owners.total"
          :help="t('admin.dashboard.tiles.ownersHelp', { active: dash.tiles.value.owners.active, suspended: dash.tiles.value.owners.suspended })" />
        <StatTile :label="t('admin.dashboard.tiles.tenants')" :value="dash.tiles.value.tenants.total"
          :help="t('admin.dashboard.tiles.tenantsHelp', { pending: dash.tiles.value.tenants.invitedPending })" />
        <StatTile :label="t('admin.dashboard.tiles.units')" :value="`${dash.tiles.value.units.occupiedPct}%`"
          :help="t('admin.dashboard.tiles.unitsHelp', { total: dash.tiles.value.units.total, properties: dash.tiles.value.properties })" />
        <StatTile :label="t('admin.dashboard.tiles.agreements')" :value="dash.tiles.value.agreementsActive"
          :help="t('admin.dashboard.tiles.agreementsHelp', { expiring: dash.tiles.value.agreementsExpiring30d })" />
      </section>

      <section class="mb-6 grid grid-cols-1 gap-4 sm:mb-8 sm:gap-6 lg:grid-cols-3">
        <Card padding="loose">
          <h2 class="text-card-title font-semibold text-ink">{{ t("admin.dashboard.charts.signups") }}</h2>
          <MiniAreaChart class="mt-4" :data="dash.signupSeries.value" :height="100" :format="count" :show-average-line="false" />
        </Card>
        <Card padding="loose">
          <h2 class="text-card-title font-semibold text-ink">{{ t("admin.dashboard.charts.invoicesPaid") }}</h2>
          <MiniAreaChart class="mt-4" :data="dash.invoiceSeries.value" :height="100" :format="count" :show-average-line="false" />
        </Card>
        <Card padding="loose">
          <h2 class="text-card-title font-semibold text-ink">{{ t("admin.dashboard.charts.acceptance") }}</h2>
          <MiniAreaChart class="mt-4" :data="dash.acceptanceSeries.value" :height="100" :format="pct" :show-average-line="false" />
        </Card>
      </section>

      <section>
        <Card padding="loose">
          <header class="mb-4">
            <h2 class="text-card-title font-semibold text-ink">{{ t("admin.dashboard.attention.title") }}</h2>
            <p class="mt-1 text-caption text-ink-muted">{{ t("admin.dashboard.attention.help") }}</p>
          </header>
          <p v-if="dash.attention.value.length === 0" class="text-body text-ink-muted">{{ t("admin.dashboard.attention.empty") }}</p>
          <AttentionList v-else :items="dash.attention.value" />
        </Card>
      </section>
    </template>
  </div>
</template>
```

- [ ] **Step 4: Strings** — add `admin.dashboard` to both locales:

`en.json`:
```json
"dashboard": {
  "title": "Platform dashboard",
  "subtitle": "How Roofly is doing across every owner.",
  "tiles": {
    "owners": "Owners", "ownersHelp": "{active} active · {suspended} suspended",
    "tenants": "Tenants", "tenantsHelp": "{pending} invites pending",
    "units": "Occupancy", "unitsHelp": "{total} units across {properties} properties",
    "agreements": "Active agreements", "agreementsHelp": "{expiring} ending within 30 days"
  },
  "charts": { "signups": "Owner sign-ups — 12 months", "invoicesPaid": "Invoices paid — 12 months", "acceptance": "Invite acceptance rate" },
  "attention": {
    "title": "Needs attention", "help": "Owners to look at this week.", "empty": "Nothing needs attention right now.",
    "kinds": { "over_cap": "Over plan cap", "overdue_3plus": "3+ overdue", "invite_stale_7d": "Stale invite", "no_property_7d": "No property yet", "suspended": "Suspended" }
  }
}
```
`ms.json`:
```json
"dashboard": {
  "title": "Papan pemuka platform",
  "subtitle": "Prestasi Roofly merentas semua pemilik.",
  "tiles": {
    "owners": "Pemilik", "ownersHelp": "{active} aktif · {suspended} digantung",
    "tenants": "Penyewa", "tenantsHelp": "{pending} jemputan belum diterima",
    "units": "Kadar penghunian", "unitsHelp": "{total} unit merentas {properties} hartanah",
    "agreements": "Perjanjian aktif", "agreementsHelp": "{expiring} tamat dalam 30 hari"
  },
  "charts": { "signups": "Pendaftaran pemilik — 12 bulan", "invoicesPaid": "Invois dibayar — 12 bulan", "acceptance": "Kadar penerimaan jemputan" },
  "attention": {
    "title": "Perlu perhatian", "help": "Pemilik untuk disemak minggu ini.", "empty": "Tiada yang perlu perhatian buat masa ini.",
    "kinds": { "over_cap": "Melebihi had pelan", "overdue_3plus": "3+ tertunggak", "invite_stale_7d": "Jemputan lama", "no_property_7d": "Belum ada hartanah", "suspended": "Digantung" }
  }
}
```

- [ ] **Step 5: Gate**

Run: `docker exec roofly-frontend npm run typecheck` → 5 known errors only.

---

### Task 20: Owners list (`/admin/owners`)

**Files:**
- Create: `frontend/app/pages/admin/owners/index.vue`
- Modify: `frontend/i18n/locales/{en,ms}.json` (`admin.owners.*` list keys)

**Interfaces produced:** server-side paginated TanStack table; query state `{ q, plan, status, overCap, overdue, page }` kept in `useRoute().query` so a refresh keeps filters. Row click → `/admin/owners/[id]`.

- [ ] **Step 1: Page**

```vue
<!-- frontend/app/pages/admin/owners/index.vue -->
<script setup lang="ts">
import { computed, h, onMounted, ref, watch } from "vue";
import { FlexRender, getCoreRowModel, useVueTable, type ColumnDef } from "@tanstack/vue-table";
import Card from "~/components/ui/Card.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";
import DataTableShell from "~/components/admin/DataTableShell.vue";
import OwnerStatusPill from "~/components/admin/OwnerStatusPill.vue";
import type { AdminOwner, OwnerListQuery, OwnerStatus, Paginated, PlanTier } from "~/types/admin";

definePageMeta({ layout: "admin" });
const { t } = useI18n();
useHead({ title: () => t("admin.nav.owners") });

const route = useRoute();
const router = useRouter();

const q = ref(String(route.query.q ?? ""));
const plan = ref<PlanTier | "all">((route.query.plan as PlanTier) ?? "all");
const status = ref<OwnerStatus | "all">((route.query.status as OwnerStatus) ?? "all");
const overCap = ref(route.query.overCap === "1");
const overdue = ref(route.query.overdue === "1");
const page = ref(Number(route.query.page ?? 1));

const loading = ref(true);
const result = ref<Paginated<AdminOwner>>({ data: [], meta: { page: 1, perPage: 20, total: 0, lastPage: 1 } });

const query = computed<OwnerListQuery>(() => ({
  q: q.value || undefined,
  plan: plan.value === "all" ? undefined : plan.value,
  status: status.value === "all" ? undefined : status.value,
  overCap: overCap.value || undefined,
  overdue: overdue.value || undefined,
  page: page.value,
}));

let debounce: ReturnType<typeof setTimeout> | null = null;
const load = async () => {
  loading.value = true;
  try {
    result.value = await useAdminOwners().list(query.value);
    router.replace({ query: Object.fromEntries(Object.entries({ ...query.value, overCap: overCap.value ? "1" : undefined, overdue: overdue.value ? "1" : undefined }).filter(([, v]) => v !== undefined)) as Record<string, string> });
  } finally {
    loading.value = false;
  }
};
onMounted(load);
watch([plan, status, overCap, overdue], () => { page.value = 1; load(); });
watch(page, load);
watch(q, () => {
  if (debounce) clearTimeout(debounce);
  debounce = setTimeout(() => { page.value = 1; load(); }, 300);
});

const filtersActive = computed(() => q.value !== "" || plan.value !== "all" || status.value !== "all" || overCap.value || overdue.value);
const clearFilters = () => { q.value = ""; plan.value = "all"; status.value = "all"; overCap.value = false; overdue.value = false; };

const planOptions = computed(() => [
  { value: "all", label: t("admin.common.all") },
  ...(["free", "starter", "pro", "business"] as PlanTier[]).map((p) => ({ value: p, label: t(`admin.plan.${p}`) })),
]);
const statusOptions = computed(() => [
  { value: "all", label: t("admin.common.all") },
  { value: "active", label: t("admin.status.owner.active") },
  { value: "suspended", label: t("admin.status.owner.suspended") },
]);

const fmtDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString("en-MY", { day: "2-digit", month: "short", year: "numeric" }) : t("admin.common.never"));
const capLabel = (o: AdminOwner) => `${o.unitsUsed} / ${o.unitsCap ?? "∞"}`;
const open = (o: AdminOwner) => router.push(`/admin/owners/${o.id}`);

const columns = computed<ColumnDef<AdminOwner>[]>(() => [
  { id: "name", header: () => t("admin.owners.columns.owner"), cell: (i) => h("div", { class: "min-w-0" }, [
      h("div", { class: "truncate text-body text-ink" }, i.row.original.name),
      h("div", { class: "truncate text-caption text-ink-muted" }, i.row.original.businessName ?? i.row.original.email),
    ]) },
  { id: "plan", header: () => t("admin.owners.columns.plan"), cell: (i) => h("span", { class: "text-caption" }, t(`admin.plan.${i.row.original.planTier}`)) },
  { id: "units", header: () => t("admin.owners.columns.units"), cell: (i) => h("span", {
      class: ["text-caption tabular-nums", i.row.original.unitsCap !== null && i.row.original.unitsUsed > i.row.original.unitsCap ? "text-status-overdue" : ""],
    }, capLabel(i.row.original)) },
  { id: "properties", header: () => t("admin.owners.columns.properties"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, String(i.row.original.counts.properties)) },
  { id: "tenants", header: () => t("admin.owners.columns.tenants"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, String(i.row.original.counts.tenants)) },
  { id: "overdue", header: () => t("admin.owners.columns.overdue"), cell: (i) => h("span", { class: ["text-caption tabular-nums", i.row.original.counts.invoicesOverdue > 0 ? "text-status-overdue" : ""] }, String(i.row.original.counts.invoicesOverdue)) },
  { id: "status", header: () => t("admin.owners.columns.status"), cell: (i) => h(OwnerStatusPill, { status: i.row.original.status }) },
  { id: "signedUp", header: () => t("admin.common.signedUp"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, fmtDate(i.row.original.createdAt)) },
  { id: "lastActive", header: () => t("admin.common.lastActive"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, fmtDate(i.row.original.lastActiveAt)) },
]);

const table = useVueTable({
  get data() { return result.value.data; },
  get columns() { return columns.value; },
  getCoreRowModel: getCoreRowModel(),
  manualPagination: true,
});
</script>

<template>
  <div>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("admin.owners.title") }}</h1>
      <p class="mt-2 text-caption text-ink-muted">{{ t("admin.owners.subtitle") }}</p>
    </header>

    <Card padding="compact" class="mb-4 sm:mb-6">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <Input v-model="q" :placeholder="t('admin.owners.searchPlaceholder')" class="lg:col-span-2" />
        <Select v-model="plan" :options="planOptions" />
        <Select v-model="status" :options="statusOptions" />
        <div class="flex items-center gap-4 text-caption text-ink-strong">
          <label class="inline-flex items-center gap-2"><input v-model="overCap" type="checkbox" class="accent-admin" />{{ t("admin.owners.filters.overCap") }}</label>
          <label class="inline-flex items-center gap-2"><input v-model="overdue" type="checkbox" class="accent-admin" />{{ t("admin.owners.filters.overdue") }}</label>
        </div>
      </div>
      <div v-if="filtersActive" class="mt-3">
        <Button variant="ghost" size="sm" @click="clearFilters">{{ t("admin.common.clearFilters") }}</Button>
      </div>
    </Card>

    <DataTableShell
      :loading="loading"
      :empty="result.data.length === 0"
      :empty-title="t('admin.common.noResults')"
      :empty-description="t('admin.common.noResultsHelp')"
      :page="result.meta.page"
      :last-page="result.meta.lastPage"
      :total="result.meta.total"
      @update:page="page = $event"
    >
      <template #table>
        <table class="w-full text-left">
          <thead>
            <tr v-for="hg in table.getHeaderGroups()" :key="hg.id" class="border-b border-line-passive">
              <th v-for="hd in hg.headers" :key="hd.id" class="px-3 py-2 text-micro font-medium uppercase tracking-wider text-ink-muted">
                <FlexRender :render="hd.column.columnDef.header" :props="hd.getContext()" />
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in table.getRowModel().rows" :key="row.id" class="border-b border-line-passive last:border-0 cursor-pointer hover:bg-surface-hover" @click="open(row.original)">
              <td v-for="cell in row.getVisibleCells()" :key="cell.id" class="px-3 py-3 align-top">
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </td>
            </tr>
          </tbody>
        </table>
      </template>
      <template #cards>
        <Card v-for="o in result.data" :key="o.id" padding="compact" class="cursor-pointer" @click="open(o)">
          <div class="flex items-center gap-2">
            <OwnerStatusPill :status="o.status" />
            <span class="text-micro text-ink-faint">{{ t(`admin.plan.${o.planTier}`) }} · {{ capLabel(o) }}</span>
          </div>
          <p class="mt-1 text-body font-medium text-ink">{{ o.name }}</p>
          <p class="text-caption text-ink-muted">{{ o.businessName ?? o.email }}</p>
          <p class="mt-1 text-micro text-ink-faint">
            {{ t("admin.owners.columns.properties") }} {{ o.counts.properties }} · {{ t("admin.owners.columns.tenants") }} {{ o.counts.tenants }} · {{ t("admin.owners.columns.overdue") }} {{ o.counts.invoicesOverdue }}
          </p>
        </Card>
      </template>
    </DataTableShell>
  </div>
</template>
```

- [ ] **Step 2: Strings** — add `admin.owners` (list keys; Task 21 adds detail keys into the same object):

`en.json`:
```json
"owners": {
  "title": "Owners", "subtitle": "Every landlord on the platform — summaries only.",
  "searchPlaceholder": "Search name, email or business",
  "filters": { "overCap": "Over cap", "overdue": "Has overdue" },
  "columns": { "owner": "Owner", "plan": "Plan", "units": "Units used / cap", "properties": "Properties", "tenants": "Tenants", "overdue": "Overdue", "status": "Status" }
}
```
`ms.json`:
```json
"owners": {
  "title": "Pemilik", "subtitle": "Setiap tuan rumah di platform — ringkasan sahaja.",
  "searchPlaceholder": "Cari nama, emel atau perniagaan",
  "filters": { "overCap": "Melebihi had", "overdue": "Ada tertunggak" },
  "columns": { "owner": "Pemilik", "plan": "Pelan", "units": "Unit guna / had", "properties": "Hartanah", "tenants": "Penyewa", "overdue": "Tertunggak", "status": "Status" }
}
```

- [ ] **Step 3: Gate**

Run: `docker exec roofly-frontend npm run typecheck` → 5 known errors only.

---

### Task 21: Owner detail (`/admin/owners/[id]`) with Warn + Suspend modals

**Files:**
- Create: `frontend/app/pages/admin/owners/[id].vue`, `frontend/app/components/admin/WarnOwnerModal.vue`, `frontend/app/components/admin/SuspendOwnerModal.vue`, `frontend/app/components/admin/AuditTable.vue`
- Modify: `frontend/i18n/locales/{en,ms}.json` (`admin.owners.detail.*`, `admin.audit.actions.*`)

**Interfaces produced:**
- `<WarnOwnerModal :open :owner @update:open @sent>` — template select (one option `payment_overdue`), suspend-on date (default +7 days), optional extra line, live preview of the text, Send → `useAdminOwners().warn()`.
- `<SuspendOwnerModal :open :owner :mode="'suspend' | 'unsuspend'" @update:open @done>` — reason textarea (≥ 10 chars) for suspend; confirm only for unsuspend; emits the updated `AdminOwner`.
- `<AuditTable :entries :show-actor>` — reusable rows for owner History and the Audit page: time · action label · actor · subject · reason, with before/after in a `<details>`.
- `admin.audit.actions.<action>` labels for all 11 actions (incl. `owner.signup`).

- [ ] **Step 1: Modals**

```vue
<!-- frontend/app/components/admin/WarnOwnerModal.vue -->
<script setup lang="ts">
import { computed, ref, watch } from "vue";
import Modal from "~/components/ui/Modal.vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import type { AdminOwner, WarnTemplate } from "~/types/admin";

const props = defineProps<{ open: boolean; owner: AdminOwner }>();
const emit = defineEmits<{ "update:open": [v: boolean]; sent: [] }>();
const { t } = useI18n();
const { show } = useToast();

const plus7 = () => new Date(Date.now() + 7 * 86_400_000).toISOString().slice(0, 10);
const template = ref<WarnTemplate>("payment_overdue");
const suspendOn = ref(plus7());
const extraLine = ref("");
const sending = ref(false);
const error = ref<string | null>(null);

watch(() => props.open, (o) => { if (o) { template.value = "payment_overdue"; suspendOn.value = plus7(); extraLine.value = ""; error.value = null; } });

const templateOptions = computed(() => [{ value: "payment_overdue", label: t("admin.owners.detail.warn.templates.payment_overdue") }]);
const preview = computed(() =>
  `Your Roofly subscription payment is overdue; your account will be suspended on ${suspendOn.value} unless settled.` + (extraLine.value ? `\n\n${extraLine.value}` : ""),
);

const send = async () => {
  error.value = null;
  if (!suspendOn.value || suspendOn.value <= new Date().toISOString().slice(0, 10)) { error.value = t("admin.owners.detail.warn.dateFuture"); return; }
  sending.value = true;
  try {
    await useAdminOwners().warn(props.owner.id, { template: template.value, suspendOn: suspendOn.value, extraLine: extraLine.value || undefined });
    show(t("admin.owners.detail.warn.sentToast"), "success");
    emit("sent");
    emit("update:open", false);
  } catch {
    error.value = t("common.genericError");
  } finally {
    sending.value = false;
  }
};
</script>

<template>
  <Modal :open="open" :title="t('admin.owners.detail.warn.title')" :description="t('admin.owners.detail.warn.description', { name: owner.name })" @update:open="$emit('update:open', $event)">
    <div class="space-y-4">
      <Select v-model="template" :options="templateOptions" :label="t('admin.owners.detail.warn.template')" />
      <Input v-model="suspendOn" type="date" :label="t('admin.owners.detail.warn.suspendOn')" />
      <Input v-model="extraLine" :label="t('admin.owners.detail.warn.extraLine')" :placeholder="t('admin.owners.detail.warn.extraLinePlaceholder')" />
      <div>
        <p class="mb-1.5 text-caption text-ink-strong">{{ t("admin.owners.detail.warn.preview") }}</p>
        <pre class="whitespace-pre-wrap rounded-sm border border-line-passive bg-surface-page p-3 text-caption text-ink font-sans">{{ preview }}</pre>
        <p class="mt-1 text-micro text-ink-faint">{{ t("admin.owners.detail.warn.channels") }}</p>
      </div>
      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
    </div>
    <template #footer>
      <Button variant="ghost" @click="$emit('update:open', false)">{{ t("common.cancel") }}</Button>
      <Button variant="primary" :loading="sending" @click="send">{{ t("admin.owners.detail.warn.send") }}</Button>
    </template>
  </Modal>
</template>
```

```vue
<!-- frontend/app/components/admin/SuspendOwnerModal.vue -->
<script setup lang="ts">
import { ref, watch } from "vue";
import Modal from "~/components/ui/Modal.vue";
import Button from "~/components/ui/Button.vue";
import type { AdminOwner } from "~/types/admin";

const props = defineProps<{ open: boolean; owner: AdminOwner; mode: "suspend" | "unsuspend" }>();
const emit = defineEmits<{ "update:open": [v: boolean]; done: [owner: AdminOwner] }>();
const { t } = useI18n();
const { show } = useToast();

const reason = ref("");
const busy = ref(false);
const error = ref<string | null>(null);
watch(() => props.open, (o) => { if (o) { reason.value = ""; error.value = null; } });

const confirm = async () => {
  error.value = null;
  if (props.mode === "suspend" && reason.value.trim().length < 10) { error.value = t("admin.owners.detail.suspend.reasonMin"); return; }
  busy.value = true;
  try {
    const updated = props.mode === "suspend"
      ? await useAdminOwners().suspend(props.owner.id, reason.value.trim())
      : await useAdminOwners().unsuspend(props.owner.id);
    show(t(`admin.owners.detail.${props.mode}.doneToast`), "success");
    emit("done", updated);
    emit("update:open", false);
  } catch {
    error.value = t("common.genericError");
  } finally {
    busy.value = false;
  }
};
</script>

<template>
  <Modal :open="open" :title="t(`admin.owners.detail.${mode}.title`)" :description="t(`admin.owners.detail.${mode}.description`, { name: owner.name })" size="sm" @update:open="$emit('update:open', $event)">
    <div v-if="mode === 'suspend'">
      <label class="block">
        <span class="mb-1.5 block text-caption text-ink-strong">{{ t("admin.common.reason") }}</span>
        <textarea v-model="reason" rows="3" class="w-full rounded-sm border border-line-passive bg-surface-page p-3 text-body outline-none focus:border-line-interactive focus:shadow-focus" />
      </label>
      <p class="mt-1 text-micro text-ink-faint">{{ t("admin.owners.detail.suspend.help") }}</p>
    </div>
    <p v-else class="text-body text-ink-muted">{{ t("admin.owners.detail.unsuspend.help") }}</p>
    <p v-if="error" class="mt-3 text-caption text-accent" role="alert">{{ error }}</p>
    <template #footer>
      <Button variant="ghost" @click="$emit('update:open', false)">{{ t("common.cancel") }}</Button>
      <Button :variant="mode === 'suspend' ? 'accent' : 'primary'" :loading="busy" @click="confirm">{{ t(`admin.owners.detail.${mode}.cta`) }}</Button>
    </template>
  </Modal>
</template>
```

```vue
<!-- frontend/app/components/admin/AuditTable.vue -->
<script setup lang="ts">
import Pill from "~/components/ui/Pill.vue";
import type { AuditEntry } from "~/types/admin";

withDefaults(defineProps<{ entries: AuditEntry[]; showActor?: boolean }>(), { showActor: true });
const { t } = useI18n();

const fmt = (iso: string) => new Date(iso).toLocaleString("en-MY", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
const tone = (action: string) =>
  action.endsWith("suspended") || action === "admin.disabled" ? "terminated"
    : action.endsWith("unsuspended") || action === "admin.enabled" ? "active"
      : action === "owner.warned" ? "maintenance" : "neutral";
const hasDiff = (e: AuditEntry) => Object.keys(e.before).length > 0 || Object.keys(e.after).length > 0;
</script>

<template>
  <ul class="divide-y divide-line-passive">
    <li v-for="e in entries" :key="e.id" class="py-3">
      <div class="flex flex-wrap items-center gap-2">
        <Pill :tone="tone(e.action)">{{ t(`admin.audit.actions.${e.action}`) }}</Pill>
        <span class="text-micro text-ink-faint tabular-nums">{{ fmt(e.createdAt) }}</span>
        <span v-if="showActor && e.actorName" class="text-micro text-ink-muted">· {{ e.actorName }}</span>
      </div>
      <p class="mt-1 text-body font-medium text-ink">
        {{ e.subjectName ?? "—" }}
        <span v-if="e.reason" class="ml-2 text-caption font-normal text-ink-muted">— {{ e.reason }}</span>
      </p>
      <details v-if="hasDiff(e)" class="mt-1">
        <summary class="cursor-pointer text-micro text-ink-muted">{{ t("admin.audit.details") }}</summary>
        <pre class="mt-1 overflow-x-auto rounded-sm bg-surface-page p-2 text-micro text-ink-muted">{{ JSON.stringify({ before: e.before, after: e.after }, null, 2) }}</pre>
      </details>
    </li>
  </ul>
</template>
```

- [ ] **Step 2: Detail page**

```vue
<!-- frontend/app/pages/admin/owners/[id].vue -->
<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { TabsRoot, TabsList, TabsTrigger, TabsContent } from "reka-ui";
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";
import Select from "~/components/ui/Select.vue";
import Pill from "~/components/ui/Pill.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import OwnerStatusPill from "~/components/admin/OwnerStatusPill.vue";
import WarnOwnerModal from "~/components/admin/WarnOwnerModal.vue";
import SuspendOwnerModal from "~/components/admin/SuspendOwnerModal.vue";
import AuditTable from "~/components/admin/AuditTable.vue";
import type { AdminOwner, AdminPropertySummary, AdminTenant, AuditEntry, TenantStatus } from "~/types/admin";

definePageMeta({ layout: "admin" });
const { t } = useI18n();
const route = useRoute();
const { can } = useAdminPermissions();
const { show } = useToast();

const id = route.params.id as string;
const owner = ref<AdminOwner | null>(null);
const loading = ref(true);
const activeTab = ref("overview");
const properties = ref<AdminPropertySummary[] | null>(null);
const tenants = ref<AdminTenant[] | null>(null);
const history = ref<AuditEntry[] | null>(null);
const showWarn = ref(false);
const showSuspend = ref(false);

useHead({ title: () => owner.value?.name ?? t("admin.nav.owners") });

onMounted(async () => {
  try { owner.value = await useAdminOwners().get(id); } finally { loading.value = false; }
});

// Lazy-load each tab once.
watch(activeTab, async (tab) => {
  if (tab === "properties" && properties.value === null) properties.value = await useAdminOwners().properties(id);
  if (tab === "tenants" && tenants.value === null) tenants.value = await useAdminOwners().tenants(id);
  if (tab === "history" && history.value === null) history.value = await useAdminOwners().history(id);
});

const tabOptions = computed(() => [
  { value: "overview", label: t("admin.owners.detail.tabs.overview") },
  { value: "properties", label: t("admin.owners.detail.tabs.properties") },
  { value: "tenants", label: t("admin.owners.detail.tabs.tenants") },
  { value: "history", label: t("admin.owners.detail.tabs.history") },
]);
const tabTriggerClass = "-mb-px border-b-2 border-transparent px-4 py-2 text-body text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus data-[state=active]:border-admin data-[state=active]:text-ink";

const fmtDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString("en-MY", { day: "2-digit", month: "short", year: "numeric" }) : t("admin.common.never"));
const usagePct = computed(() => !owner.value || owner.value.unitsCap === null ? 0 : Math.min(100, Math.round((owner.value.unitsUsed / owner.value.unitsCap) * 100)));
const overCap = computed(() => !!owner.value && owner.value.unitsCap !== null && owner.value.unitsUsed > owner.value.unitsCap);

const tenantTone = (s: TenantStatus) => (s === "invited" ? "draft" : s === "active" ? "active" : s === "notice_given" ? "maintenance" : "expired");

const onStatusChanged = (updated: AdminOwner) => { owner.value = updated; history.value = null; if (activeTab.value === "history") activeTab.value = "overview"; };
const onWarned = () => { history.value = null; };

const resendingId = ref<string | null>(null);
const resend = async (tenant: AdminTenant) => {
  resendingId.value = tenant.id;
  try {
    await useAdminTenants().resendInvite(tenant.id);
    show(t("admin.tenants.resendToast"), "success");
    tenants.value = await useAdminOwners().tenants(id);
  } catch { show(t("common.genericError"), "danger"); } finally { resendingId.value = null; }
};

const countKeys = ["properties", "units", "unitsOccupied", "tenants", "agreementsActive", "agreementsExpiring30d", "invoicesOverdue", "ticketsOpen"] as const;
</script>

<template>
  <div>
    <NuxtLink to="/admin/owners" class="mb-6 inline-flex items-center gap-1 text-caption text-ink-muted transition hover:text-ink">
      <Icon name="ArrowLeft" :size="14" />{{ t("admin.common.back") }}
    </NuxtLink>

    <Card v-if="loading" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
    <Card v-else-if="!owner" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("admin.common.notFound") }}</p></Card>

    <template v-else>
      <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-display-sub font-semibold tracking-snug">{{ owner.name }}</h1>
            <OwnerStatusPill :status="owner.status" />
          </div>
          <p class="mt-1 text-caption text-ink-muted">{{ owner.businessName ? `${owner.businessName} · ` : "" }}{{ owner.email }}{{ owner.phone ? ` · ${owner.phone}` : "" }}</p>
        </div>
        <div class="flex gap-2 self-start">
          <Button v-if="can('owners.warn')" variant="ghost" size="sm" @click="showWarn = true">
            <Icon name="BellRing" :size="14" class="mr-1" />{{ t("admin.owners.detail.actions.warn") }}
          </Button>
          <Button v-if="can('owners.suspend')" :variant="owner.status === 'suspended' ? 'primary' : 'accent'" size="sm" @click="showSuspend = true">
            {{ owner.status === "suspended" ? t("admin.owners.detail.actions.unsuspend") : t("admin.owners.detail.actions.suspend") }}
          </Button>
        </div>
      </header>

      <Card v-if="owner.status === 'suspended'" padding="compact" class="mb-6 border-status-terminated">
        <p class="text-caption text-ink"><span class="font-medium">{{ t("admin.owners.detail.suspendedSince", { date: fmtDate(owner.suspendedAt) }) }}</span> — {{ owner.suspensionReason }}</p>
      </Card>

      <TabsRoot v-model="activeTab">
        <div class="sm:hidden mb-4"><Select v-model="activeTab" :options="tabOptions" /></div>
        <TabsList class="hidden sm:flex mb-6 border-b border-line-passive">
          <TabsTrigger v-for="tab in tabOptions" :key="tab.value" :value="tab.value" :class="tabTriggerClass">{{ tab.label }}</TabsTrigger>
        </TabsList>

        <TabsContent value="overview">
          <div class="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
            <Card padding="standard">
              <p class="text-caption text-ink-muted">{{ t("admin.owners.detail.plan") }}</p>
              <p class="mt-2 text-card-title font-semibold">{{ t(`admin.plan.${owner.planTier}`) }}</p>
              <p class="mt-3 text-caption text-ink-strong tabular-nums">{{ owner.unitsUsed }} / {{ owner.unitsCap ?? t("admin.common.unlimited") }} {{ t("admin.owners.detail.unitsUsed") }}</p>
              <div v-if="owner.unitsCap !== null" class="mt-2 h-1.5 w-full overflow-hidden rounded-pill bg-line-passive">
                <div :class="['h-full', overCap ? 'bg-status-overdue' : 'bg-admin']" :style="{ width: `${usagePct}%` }" />
              </div>
              <p v-if="overCap" class="mt-2 text-micro text-status-overdue">{{ t("admin.owners.detail.overCap") }}</p>
            </Card>
            <Card padding="standard" class="lg:col-span-2">
              <p class="text-caption text-ink-muted">{{ t("admin.owners.detail.counts") }}</p>
              <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="k in countKeys" :key="k">
                  <dt class="text-micro text-ink-faint">{{ t(`admin.owners.detail.countLabels.${k}`) }}</dt>
                  <dd class="text-body font-semibold tabular-nums" :class="k === 'invoicesOverdue' && owner.counts[k] > 0 ? 'text-status-overdue' : ''">{{ owner.counts[k] }}</dd>
                </div>
              </dl>
              <p class="mt-4 text-micro text-ink-faint">{{ t("admin.common.signedUp") }} {{ fmtDate(owner.createdAt) }} · {{ t("admin.common.lastActive") }} {{ fmtDate(owner.lastActiveAt) }}</p>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="properties">
          <Card v-if="properties === null" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
          <Card v-else-if="properties.length === 0" padding="loose"><EmptyState icon="Building2" :title="t('admin.owners.detail.noProperties')" /></Card>
          <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Card v-for="p in properties" :key="p.id" padding="standard">
              <div class="flex items-center gap-2"><Pill tone="neutral">{{ p.type ?? "—" }}</Pill><span class="text-micro text-ink-faint tabular-nums">{{ p.unitsOccupied }} / {{ p.unitsTotal }} {{ t("admin.owners.detail.occupied") }}</span></div>
              <p class="mt-1 text-body font-medium text-ink">{{ p.name }}</p>
              <p class="text-caption text-ink-muted">{{ [p.address.line, p.address.postcode, p.address.city, p.address.state].filter(Boolean).join(", ") }}</p>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="tenants">
          <Card v-if="tenants === null" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
          <Card v-else-if="tenants.length === 0" padding="loose"><EmptyState icon="Users" :title="t('admin.owners.detail.noTenants')" /></Card>
          <Card v-else padding="compact">
            <ul class="divide-y divide-line-passive">
              <li v-for="tn in tenants" :key="tn.id" class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                <NuxtLink :to="`/admin/tenants/${tn.id}`" class="min-w-0 flex-1">
                  <div class="flex items-center gap-2"><Pill :tone="tenantTone(tn.status)">{{ t(`admin.status.tenant.${tn.status}`) }}</Pill><span class="text-micro text-ink-faint">{{ tn.propertyName ?? "—" }} · {{ tn.unitLabel ?? "—" }}</span></div>
                  <p class="mt-1 text-body font-medium text-ink">{{ tn.name }}</p>
                  <p class="text-caption text-ink-muted">{{ tn.email }}</p>
                </NuxtLink>
                <Button v-if="tn.status === 'invited' && can('tenants.view')" variant="ghost" size="sm" class="self-start" :loading="resendingId === tn.id" @click="resend(tn)">{{ t("admin.tenants.resendInvite") }}</Button>
              </li>
            </ul>
          </Card>
        </TabsContent>

        <TabsContent value="history">
          <Card v-if="history === null" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
          <Card v-else padding="compact"><AuditTable :entries="history" /></Card>
        </TabsContent>
      </TabsRoot>

      <WarnOwnerModal v-model:open="showWarn" :owner="owner" @sent="onWarned" />
      <SuspendOwnerModal v-model:open="showSuspend" :owner="owner" :mode="owner.status === 'suspended' ? 'unsuspend' : 'suspend'" @done="onStatusChanged" />
    </template>
  </div>
</template>
```

- [ ] **Step 3: Strings** — add into `admin.owners` a `detail` object, and a top-level `admin.audit.actions` + `admin.audit.details`, and `admin.tenants.resendInvite` / `resendToast` (the rest of `admin.tenants` comes in Task 22):

`en.json`:
```json
"detail": {
  "tabs": { "overview": "Overview", "properties": "Properties", "tenants": "Tenants", "history": "History" },
  "actions": { "warn": "Send warning", "suspend": "Suspend", "unsuspend": "Unsuspend" },
  "suspendedSince": "Suspended since {date}",
  "plan": "Plan", "unitsUsed": "units used", "overCap": "Over the plan cap — upgrade or warn.",
  "counts": "At a glance",
  "countLabels": { "properties": "Properties", "units": "Units", "unitsOccupied": "Occupied", "tenants": "Tenants", "agreementsActive": "Active agreements", "agreementsExpiring30d": "Expiring 30d", "invoicesOverdue": "Overdue invoices", "ticketsOpen": "Open tickets" },
  "noProperties": "No properties yet", "noTenants": "No tenants yet", "occupied": "occupied",
  "warn": {
    "title": "Send payment warning", "description": "Email {name} a notice on their enabled channels.",
    "template": "Template", "templates": { "payment_overdue": "Subscription payment overdue" },
    "suspendOn": "Suspend on", "extraLine": "Extra line (optional)", "extraLinePlaceholder": "e.g. Reply to this email if you need help.",
    "preview": "Preview", "channels": "Sent by email. WhatsApp and SMS arrive with support channels.",
    "send": "Send warning", "sentToast": "Warning sent", "dateFuture": "Pick a date after today."
  },
  "suspend": {
    "title": "Suspend owner", "description": "{name} will lose access to Roofly until unsuspended. Their tenants are unaffected.",
    "help": "At least 10 characters. The reason is recorded in the audit log, not shown to the owner.",
    "reasonMin": "Give a reason of at least 10 characters.", "cta": "Suspend", "doneToast": "Owner suspended"
  },
  "unsuspend": {
    "title": "Unsuspend owner", "description": "Restore {name}'s access to Roofly.",
    "help": "The owner can sign in again immediately.", "cta": "Unsuspend", "doneToast": "Owner unsuspended"
  }
}
```
and
```json
"audit": {
  "details": "Show details",
  "actions": {
    "admin.login": "Admin signed in", "admin.invite_sent": "Admin invited", "admin.invite_accepted": "Invite accepted",
    "admin.permissions_changed": "Permissions changed", "admin.disabled": "Admin disabled", "admin.enabled": "Admin enabled",
    "owner.warned": "Warning sent", "owner.suspended": "Owner suspended", "owner.unsuspended": "Owner unsuspended",
    "tenant.invite_resent": "Tenant invite resent", "owner.signup": "Owner signed up"
  }
},
"tenants": { "resendInvite": "Resend invite", "resendToast": "Invite resent" }
```
`ms.json`:
```json
"detail": {
  "tabs": { "overview": "Gambaran", "properties": "Hartanah", "tenants": "Penyewa", "history": "Sejarah" },
  "actions": { "warn": "Hantar amaran", "suspend": "Gantung", "unsuspend": "Pulihkan" },
  "suspendedSince": "Digantung sejak {date}",
  "plan": "Pelan", "unitsUsed": "unit digunakan", "overCap": "Melebihi had pelan — naik taraf atau beri amaran.",
  "counts": "Sekilas pandang",
  "countLabels": { "properties": "Hartanah", "units": "Unit", "unitsOccupied": "Dihuni", "tenants": "Penyewa", "agreementsActive": "Perjanjian aktif", "agreementsExpiring30d": "Tamat 30 hari", "invoicesOverdue": "Invois tertunggak", "ticketsOpen": "Tiket terbuka" },
  "noProperties": "Belum ada hartanah", "noTenants": "Belum ada penyewa", "occupied": "dihuni",
  "warn": {
    "title": "Hantar amaran pembayaran", "description": "Emel notis kepada {name} melalui saluran yang diaktifkan.",
    "template": "Templat", "templates": { "payment_overdue": "Bayaran langganan tertunggak" },
    "suspendOn": "Gantung pada", "extraLine": "Baris tambahan (pilihan)", "extraLinePlaceholder": "cth. Balas emel ini jika anda perlukan bantuan.",
    "preview": "Pratonton", "channels": "Dihantar melalui emel. WhatsApp dan SMS menyusul bersama saluran sokongan.",
    "send": "Hantar amaran", "sentToast": "Amaran dihantar", "dateFuture": "Pilih tarikh selepas hari ini."
  },
  "suspend": {
    "title": "Gantung pemilik", "description": "{name} akan hilang akses ke Roofly sehingga dipulihkan. Penyewa mereka tidak terjejas.",
    "help": "Sekurang-kurangnya 10 aksara. Sebab direkodkan dalam log audit, tidak ditunjukkan kepada pemilik.",
    "reasonMin": "Berikan sebab sekurang-kurangnya 10 aksara.", "cta": "Gantung", "doneToast": "Pemilik digantung"
  },
  "unsuspend": {
    "title": "Pulihkan pemilik", "description": "Pulihkan akses {name} ke Roofly.",
    "help": "Pemilik boleh log masuk semula serta-merta.", "cta": "Pulihkan", "doneToast": "Pemilik dipulihkan"
  }
}
```
```json
"audit": {
  "details": "Tunjukkan butiran",
  "actions": {
    "admin.login": "Admin log masuk", "admin.invite_sent": "Admin dijemput", "admin.invite_accepted": "Jemputan diterima",
    "admin.permissions_changed": "Kebenaran diubah", "admin.disabled": "Admin dinyahaktifkan", "admin.enabled": "Admin diaktifkan",
    "owner.warned": "Amaran dihantar", "owner.suspended": "Pemilik digantung", "owner.unsuspended": "Pemilik dipulihkan",
    "tenant.invite_resent": "Jemputan penyewa dihantar semula", "owner.signup": "Pemilik mendaftar"
  }
},
"tenants": { "resendInvite": "Hantar semula jemputan", "resendToast": "Jemputan dihantar semula" }
```

- [ ] **Step 4: Gate**

Run: `docker exec roofly-frontend npm run typecheck` → 5 known errors only. (`admin.audit.actions` keys contain dots — vue-i18n resolves `t("admin.audit.actions.owner.warned")` by walking nested objects, so define them as **nested** objects in the JSON: `"actions": { "admin": { "login": … }, "owner": { "warned": … }, "tenant": { "invite_resent": … } }`. Write them nested; the flat form above is only for readability.)

---

### Task 22: Tenants list + detail (`/admin/tenants`, `/admin/tenants/[id]`)

**Files:**
- Create: `frontend/app/pages/admin/tenants/index.vue`, `frontend/app/pages/admin/tenants/[id].vue`
- Modify: `frontend/i18n/locales/{en,ms}.json` (`admin.tenants.*`)

- [ ] **Step 1: List page** — same skeleton as Task 20 (route-synced filters, `DataTableShell`, cards under `sm`). Differences only:

```ts
// state
const q = ref(String(route.query.q ?? ""));
const status = ref<TenantStatus | "all">((route.query.status as TenantStatus) ?? "all");
const ownerId = ref(String(route.query.ownerId ?? ""));
const page = ref(Number(route.query.page ?? 1));
const query = computed<TenantListQuery>(() => ({ q: q.value || undefined, status: status.value === "all" ? undefined : status.value, ownerId: ownerId.value || undefined, page: page.value }));
// load → useAdminTenants().list(query.value)
// statusOptions: all + invited/active/notice_given/moved_out via t(`admin.status.tenant.${s}`)
// ownerId comes from the URL only (linked from an owner's page) — show a small "Filtered by owner" chip with a clear button when set.
```
Columns: name (+ email below) · phone · status pill (`tenantTone` from Task 21) · owner (`ownerName`) · property/unit (`propertyName · unitLabel`) · invited (`fmtDate(invitedAt)`) · accepted (`fmtDate(acceptedAt)`). Row click → `/admin/tenants/${id}`. Mobile card: pill + owner on top, name, email, property/unit.

- [ ] **Step 2: Detail page**

```vue
<!-- frontend/app/pages/admin/tenants/[id].vue -->
<script setup lang="ts">
import { onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";
import Pill from "~/components/ui/Pill.vue";
import AuditTable from "~/components/admin/AuditTable.vue";
import type { AdminTenant, AuditEntry, TenantStatus } from "~/types/admin";

definePageMeta({ layout: "admin" });
const { t } = useI18n();
const route = useRoute();
const { show } = useToast();

const id = route.params.id as string;
const tenant = ref<AdminTenant | null>(null);
const history = ref<AuditEntry[]>([]);
const loading = ref(true);
const resending = ref(false);

useHead({ title: () => tenant.value?.name ?? t("admin.nav.tenants") });

const load = async () => {
  tenant.value = await useAdminTenants().get(id);
  history.value = (await useAdminAudit().list({ subjectType: "user", subjectId: id, perPage: 50 })).data;
};
onMounted(async () => { try { await load(); } finally { loading.value = false; } });

const tone = (s: TenantStatus) => (s === "invited" ? "draft" : s === "active" ? "active" : s === "notice_given" ? "maintenance" : "expired");
const fmtDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString("en-MY", { day: "2-digit", month: "short", year: "numeric" }) : "—");

const resend = async () => {
  resending.value = true;
  try { await useAdminTenants().resendInvite(id); show(t("admin.tenants.resendToast"), "success"); await load(); }
  catch { show(t("common.genericError"), "danger"); }
  finally { resending.value = false; }
};
</script>

<template>
  <div>
    <NuxtLink to="/admin/tenants" class="mb-6 inline-flex items-center gap-1 text-caption text-ink-muted transition hover:text-ink"><Icon name="ArrowLeft" :size="14" />{{ t("admin.common.back") }}</NuxtLink>
    <Card v-if="loading" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
    <Card v-else-if="!tenant" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("admin.common.notFound") }}</p></Card>
    <template v-else>
      <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div class="flex items-center gap-2"><h1 class="text-display-sub font-semibold tracking-snug">{{ tenant.name }}</h1><Pill :tone="tone(tenant.status)">{{ t(`admin.status.tenant.${tenant.status}`) }}</Pill></div>
          <p class="mt-1 text-caption text-ink-muted">{{ tenant.email }}{{ tenant.phone ? ` · ${tenant.phone}` : "" }}</p>
        </div>
        <Button v-if="tenant.status === 'invited'" variant="ghost" size="sm" class="self-start" :loading="resending" @click="resend">{{ t("admin.tenants.resendInvite") }}</Button>
      </header>

      <div class="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
        <Card padding="standard" class="lg:col-span-1">
          <p class="text-caption text-ink-muted">{{ t("admin.tenants.detail.placement") }}</p>
          <dl class="mt-3 space-y-3 text-caption">
            <div><dt class="text-micro text-ink-faint">{{ t("admin.tenants.columns.owner") }}</dt><dd><NuxtLink v-if="tenant.ownerId" :to="`/admin/owners/${tenant.ownerId}`" class="text-ink underline underline-offset-2">{{ tenant.ownerName }}</NuxtLink><span v-else>—</span></dd></div>
            <div><dt class="text-micro text-ink-faint">{{ t("admin.tenants.columns.propertyUnit") }}</dt><dd class="text-ink">{{ tenant.propertyName ?? "—" }} · {{ tenant.unitLabel ?? "—" }}</dd></div>
            <div><dt class="text-micro text-ink-faint">{{ t("admin.tenants.columns.invited") }}</dt><dd class="text-ink tabular-nums">{{ fmtDate(tenant.invitedAt) }}</dd></div>
            <div><dt class="text-micro text-ink-faint">{{ t("admin.tenants.columns.accepted") }}</dt><dd class="text-ink tabular-nums">{{ fmtDate(tenant.acceptedAt) }}</dd></div>
          </dl>
          <p class="mt-4 text-micro text-ink-faint">{{ t("admin.tenants.detail.privacy") }}</p>
        </Card>
        <Card padding="compact" class="lg:col-span-2">
          <h2 class="px-2 pt-2 text-card-title font-semibold text-ink">{{ t("admin.tenants.detail.history") }}</h2>
          <p v-if="history.length === 0" class="p-4 text-body text-ink-muted">{{ t("admin.tenants.detail.noHistory") }}</p>
          <AuditTable v-else :entries="history" />
        </Card>
      </div>
    </template>
  </div>
</template>
```

- [ ] **Step 3: Strings** — extend `admin.tenants` (keep `resendInvite` / `resendToast` from Task 21):

`en.json`:
```json
"title": "Tenants", "subtitle": "Every tenant across every owner — identity and placement only.",
"searchPlaceholder": "Search name, email or phone", "filteredByOwner": "Filtered by owner",
"columns": { "tenant": "Tenant", "phone": "Phone", "status": "Status", "owner": "Owner", "propertyUnit": "Property / unit", "invited": "Invited", "accepted": "Accepted" },
"detail": { "placement": "Placement", "history": "History", "noHistory": "No admin actions on this tenant yet.", "privacy": "Personal details, documents and payments are never shown here." }
```
`ms.json`:
```json
"title": "Penyewa", "subtitle": "Setiap penyewa merentas semua pemilik — identiti dan penempatan sahaja.",
"searchPlaceholder": "Cari nama, emel atau telefon", "filteredByOwner": "Ditapis mengikut pemilik",
"columns": { "tenant": "Penyewa", "phone": "Telefon", "status": "Status", "owner": "Pemilik", "propertyUnit": "Hartanah / unit", "invited": "Dijemput", "accepted": "Diterima" },
"detail": { "placement": "Penempatan", "history": "Sejarah", "noHistory": "Belum ada tindakan admin ke atas penyewa ini.", "privacy": "Butiran peribadi, dokumen dan pembayaran tidak pernah ditunjukkan di sini." }
```

- [ ] **Step 4: Gate**

Run: `docker exec roofly-frontend npm run typecheck` → 5 known errors only.

---

### Task 23: Settings → Admins (`/admin/settings`)

**Files:**
- Create: `frontend/app/pages/admin/settings.vue`, `frontend/app/components/admin/AdminFormModal.vue`
- Modify: `frontend/i18n/locales/{en,ms}.json` (`admin.settings.*`)

**Interfaces produced:**
- `/admin/settings` — `TabsRoot` with one tab `admins` (SP2–4 tabs arrive later; the tab strip is already there so they slot in). Guarded in-page: without `admins.manage` the page shows an `EmptyState` "No access".
- `<AdminFormModal :open :catalogue :editing @update:open @saved>` — create (name, email, permission checklist pre-filled from `catalogue.preset`, super-admin toggle shown only to super-admins) or edit (permissions + super-admin). Calls `useAdminAdmins().create/update`.
- Table rows: name/email · permissions count (or "Super-admin") · status pill · last active · actions (Edit · Disable/Enable · Resend invite).

- [ ] **Step 1: Modal**

```vue
<!-- frontend/app/components/admin/AdminFormModal.vue -->
<script setup lang="ts">
import { computed, ref, watch } from "vue";
import Modal from "~/components/ui/Modal.vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";
import type { AdminPermission, AdminUser, PermissionCatalogue } from "~/types/admin";

const props = defineProps<{ open: boolean; catalogue: PermissionCatalogue; editing: AdminUser | null }>();
const emit = defineEmits<{ "update:open": [v: boolean]; saved: [admin: AdminUser] }>();
const { t } = useI18n();
const { show } = useToast();
const { isSuperAdmin } = useAdminPermissions();
const { toFieldErrors } = useApiError();

const name = ref("");
const email = ref("");
const superAdmin = ref(false);
const selected = ref<Set<AdminPermission>>(new Set());
const busy = ref(false);
const errors = ref<Record<string, string>>({});

watch(() => props.open, (o) => {
  if (!o) return;
  errors.value = {};
  name.value = props.editing?.name ?? "";
  email.value = props.editing?.email ?? "";
  superAdmin.value = props.editing?.isSuperAdmin ?? false;
  selected.value = new Set(props.editing ? props.editing.permissions : props.catalogue.preset);
});

const toggle = (key: AdminPermission) => { const s = new Set(selected.value); s.has(key) ? s.delete(key) : s.add(key); selected.value = s; };
const applyPreset = () => { selected.value = new Set(props.catalogue.preset); };
const isEdit = computed(() => props.editing !== null);

const save = async () => {
  errors.value = {};
  if (!isEdit.value && (!name.value.trim() || !email.value.trim())) { errors.value = { name: t("validation.required") }; return; }
  busy.value = true;
  try {
    const permissions = [...selected.value];
    const saved = isEdit.value
      ? await useAdminAdmins().update(props.editing!.id, { permissions, ...(isSuperAdmin.value ? { isSuperAdmin: superAdmin.value } : {}) })
      : await useAdminAdmins().create({ name: name.value.trim(), email: email.value.trim(), permissions, ...(isSuperAdmin.value && superAdmin.value ? { isSuperAdmin: true } : {}) });
    show(t(isEdit.value ? "admin.settings.admins.updatedToast" : "admin.settings.admins.invitedToast"), "success");
    emit("saved", saved);
    emit("update:open", false);
  } catch (e) {
    errors.value = toFieldErrors(e) ?? { form: (e as Error)?.message ?? t("common.genericError") };
  } finally {
    busy.value = false;
  }
};
</script>

<template>
  <Modal :open="open" :title="t(isEdit ? 'admin.settings.admins.editTitle' : 'admin.settings.admins.createTitle')" size="lg" @update:open="$emit('update:open', $event)">
    <div class="space-y-4">
      <template v-if="!isEdit">
        <Input v-model="name" :label="t('auth.fullName')" :error="errors.name" />
        <Input v-model="email" type="email" :label="t('auth.email')" :error="errors.email" />
      </template>
      <div>
        <div class="mb-2 flex items-center justify-between">
          <span class="text-caption text-ink-strong">{{ t("admin.settings.admins.permissions") }}</span>
          <Button variant="ghost" size="sm" @click="applyPreset">{{ t("admin.settings.admins.applyPreset") }}</Button>
        </div>
        <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <li v-for="p in catalogue.permissions" :key="p.key">
            <label class="flex items-start gap-2 rounded-sm border border-line-passive p-2 text-caption">
              <input type="checkbox" class="mt-0.5 accent-admin" :checked="selected.has(p.key)" :disabled="superAdmin" @change="toggle(p.key)" />
              <span><span class="text-ink">{{ t(`admin.settings.admins.keys.${p.key}`) }}</span><span class="block text-micro text-ink-faint">{{ p.key }}</span></span>
            </label>
          </li>
        </ul>
        <p v-if="errors.permissions" class="mt-1 text-caption text-accent">{{ errors.permissions }}</p>
      </div>
      <label v-if="isSuperAdmin" class="flex items-center gap-2 text-caption text-ink-strong">
        <input v-model="superAdmin" type="checkbox" class="accent-admin" />{{ t("admin.settings.admins.superAdmin") }}
        <span class="text-micro text-ink-faint">— {{ t("admin.settings.admins.superAdminHelp") }}</span>
      </label>
      <p v-if="errors.form || errors.isSuperAdmin || errors.disabled" class="text-caption text-accent" role="alert">{{ errors.form ?? errors.isSuperAdmin ?? errors.disabled }}</p>
    </div>
    <template #footer>
      <Button variant="ghost" @click="$emit('update:open', false)">{{ t("common.cancel") }}</Button>
      <Button variant="primary" :loading="busy" @click="save">{{ t(isEdit ? "common.save" : "admin.settings.admins.sendInvite") }}</Button>
    </template>
  </Modal>
</template>
```

- [ ] **Step 2: Page**

```vue
<!-- frontend/app/pages/admin/settings.vue -->
<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { TabsRoot, TabsList, TabsTrigger, TabsContent } from "reka-ui";
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import Pill from "~/components/ui/Pill.vue";
import Select from "~/components/ui/Select.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import AdminFormModal from "~/components/admin/AdminFormModal.vue";
import type { AdminUser, AdminUserStatus, PermissionCatalogue } from "~/types/admin";

definePageMeta({ layout: "admin" });
const { t } = useI18n();
useHead({ title: () => t("admin.nav.settings") });
const { can } = useAdminPermissions();
const { show } = useToast();
const auth = useAuthStore();

const activeTab = ref("admins");
const tabOptions = computed(() => [{ value: "admins", label: t("admin.settings.tabs.admins") }]);
const tabTriggerClass = "-mb-px border-b-2 border-transparent px-4 py-2 text-body text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus data-[state=active]:border-admin data-[state=active]:text-ink";

const admins = ref<AdminUser[]>([]);
const catalogue = ref<PermissionCatalogue | null>(null);
const loading = ref(true);
const showForm = ref(false);
const editing = ref<AdminUser | null>(null);
const busyId = ref<string | null>(null);

const load = async () => { admins.value = await useAdminAdmins().list(); };
onMounted(async () => {
  if (!can("admins.manage")) { loading.value = false; return; }
  try { [catalogue.value] = await Promise.all([useAdminAdmins().permissions(), load()]); } finally { loading.value = false; }
});

const openCreate = () => { editing.value = null; showForm.value = true; };
const openEdit = (a: AdminUser) => { editing.value = a; showForm.value = true; };
const onSaved = async () => { await load(); };

const toggleDisabled = async (a: AdminUser) => {
  busyId.value = a.id;
  try { await useAdminAdmins().update(a.id, { disabled: a.status !== "disabled" }); await load(); }
  catch (e) { show((e as { data?: { message?: string } })?.data?.message ?? (e as Error)?.message ?? t("common.genericError"), "danger"); }
  finally { busyId.value = null; }
};
const resend = async (a: AdminUser) => {
  busyId.value = a.id;
  try { await useAdminAdmins().resendInvite(a.id); show(t("admin.settings.admins.resentToast"), "success"); }
  catch { show(t("common.genericError"), "danger"); }
  finally { busyId.value = null; }
};

const tone = (s: AdminUserStatus) => (s === "active" ? "active" : s === "invited" ? "draft" : "expired");
const fmtDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString("en-MY", { day: "2-digit", month: "short", year: "numeric" }) : t("admin.common.never"));
</script>

<template>
  <div>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("admin.settings.title") }}</h1>
      <p class="mt-2 text-caption text-ink-muted">{{ t("admin.settings.subtitle") }}</p>
    </header>

    <Card v-if="!can('admins.manage')" padding="loose">
      <EmptyState icon="Lock" :title="t('admin.settings.noAccess')" :description="t('admin.settings.noAccessHelp')" />
    </Card>

    <TabsRoot v-else v-model="activeTab">
      <div class="sm:hidden mb-4"><Select v-model="activeTab" :options="tabOptions" /></div>
      <TabsList class="hidden sm:flex mb-6 border-b border-line-passive">
        <TabsTrigger v-for="tab in tabOptions" :key="tab.value" :value="tab.value" :class="tabTriggerClass">{{ tab.label }}</TabsTrigger>
      </TabsList>

      <TabsContent value="admins">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <p class="text-caption text-ink-muted">{{ t("admin.settings.admins.help") }}</p>
          <Button variant="primary" size="sm" class="self-start" @click="openCreate">{{ t("admin.settings.admins.create") }}</Button>
        </div>

        <Card v-if="loading" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
        <Card v-else padding="compact">
          <ul class="divide-y divide-line-passive">
            <li v-for="a in admins" :key="a.id" class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <Pill :tone="tone(a.status)">{{ t(`admin.status.admin.${a.status}`) }}</Pill>
                  <span class="text-micro text-ink-faint">{{ a.isSuperAdmin ? t("admin.settings.admins.superAdmin") : t("admin.settings.admins.permissionCount", { n: a.permissions.length }) }} · {{ t("admin.common.lastActive") }} {{ fmtDate(a.lastActiveAt) }}</span>
                </div>
                <p class="mt-1 text-body font-medium text-ink">{{ a.name }}<span v-if="a.id === auth.user?.id" class="ml-2 text-micro font-normal text-ink-faint">{{ t("admin.settings.admins.you") }}</span></p>
                <p class="text-caption text-ink-muted">{{ a.email }}</p>
              </div>
              <div class="flex flex-wrap gap-2 self-start">
                <Button variant="ghost" size="sm" @click="openEdit(a)">{{ t("admin.settings.admins.edit") }}</Button>
                <Button v-if="a.status === 'invited'" variant="ghost" size="sm" :loading="busyId === a.id" @click="resend(a)">{{ t("admin.settings.admins.resend") }}</Button>
                <Button v-if="a.id !== auth.user?.id" variant="ghost" size="sm" :loading="busyId === a.id" @click="toggleDisabled(a)">
                  {{ a.status === "disabled" ? t("admin.settings.admins.enable") : t("admin.settings.admins.disable") }}
                </Button>
              </div>
            </li>
          </ul>
        </Card>
      </TabsContent>
    </TabsRoot>

    <AdminFormModal v-if="catalogue" v-model:open="showForm" :catalogue="catalogue" :editing="editing" @saved="onSaved" />
  </div>
</template>
```

- [ ] **Step 3: Strings** — `admin.settings`:

`en.json`:
```json
"settings": {
  "title": "Settings", "subtitle": "Who on the team can do what.",
  "tabs": { "admins": "Admins" },
  "noAccess": "No access", "noAccessHelp": "Ask a super-admin for the admins.manage permission.",
  "admins": {
    "help": "Admins sign in at the admin portal. New admins get an email link to set their password.",
    "create": "Invite admin", "createTitle": "Invite an admin", "editTitle": "Edit admin",
    "permissions": "Permissions", "applyPreset": "Use Operations preset", "sendInvite": "Send invite",
    "superAdmin": "Super-admin", "superAdminHelp": "bypasses every permission", "permissionCount": "{n} permissions",
    "edit": "Edit", "resend": "Resend invite", "disable": "Disable", "enable": "Enable", "you": "(you)",
    "invitedToast": "Invite sent", "updatedToast": "Admin updated", "resentToast": "Invite resent",
    "keys": {
      "dashboard.view": "View platform dashboard", "owners.view": "View owners", "tenants.view": "View tenants",
      "owners.warn": "Send payment warnings", "owners.suspend": "Suspend / unsuspend owners", "owners.plan": "Change owner plans",
      "support.manage": "Manage support inbox", "broadcast.send": "Send broadcasts", "settings.channels": "Manage channel providers",
      "settings.flags": "Manage feature flags", "admins.manage": "Manage admins", "audit.view": "View full audit log", "users.delete": "Delete / anonymise users"
    }
  }
}
```
`ms.json`:
```json
"settings": {
  "title": "Tetapan", "subtitle": "Siapa dalam pasukan boleh buat apa.",
  "tabs": { "admins": "Admin" },
  "noAccess": "Tiada akses", "noAccessHelp": "Minta kebenaran admins.manage daripada super-admin.",
  "admins": {
    "help": "Admin log masuk di portal admin. Admin baharu menerima pautan emel untuk menetapkan kata laluan.",
    "create": "Jemput admin", "createTitle": "Jemput admin", "editTitle": "Sunting admin",
    "permissions": "Kebenaran", "applyPreset": "Guna pratetap Operasi", "sendInvite": "Hantar jemputan",
    "superAdmin": "Super-admin", "superAdminHelp": "melangkaui semua kebenaran", "permissionCount": "{n} kebenaran",
    "edit": "Sunting", "resend": "Hantar semula jemputan", "disable": "Nyahaktifkan", "enable": "Aktifkan", "you": "(anda)",
    "invitedToast": "Jemputan dihantar", "updatedToast": "Admin dikemas kini", "resentToast": "Jemputan dihantar semula",
    "keys": {
      "dashboard.view": "Lihat papan pemuka platform", "owners.view": "Lihat pemilik", "tenants.view": "Lihat penyewa",
      "owners.warn": "Hantar amaran pembayaran", "owners.suspend": "Gantung / pulihkan pemilik", "owners.plan": "Tukar pelan pemilik",
      "support.manage": "Urus peti masuk sokongan", "broadcast.send": "Hantar siaran", "settings.channels": "Urus penyedia saluran",
      "settings.flags": "Urus bendera ciri", "admins.manage": "Urus admin", "audit.view": "Lihat log audit penuh", "users.delete": "Padam / tanpa nama pengguna"
    }
  }
}
```
As in Task 21, write the dotted `keys` as **nested** objects in the JSON files (`"keys": { "dashboard": { "view": … }, "owners": { "view": …, "warn": … }, … }`).

- [ ] **Step 4: Gate**

Run: `docker exec roofly-frontend npm run typecheck` → 5 known errors only.

---

### Task 24: Audit log page (`/admin/audit`)

**Files:**
- Create: `frontend/app/pages/admin/audit.vue`
- Modify: `frontend/i18n/locales/{en,ms}.json` (`admin.audit.*` page keys)

- [ ] **Step 1: Page**

```vue
<!-- frontend/app/pages/admin/audit.vue -->
<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import Card from "~/components/ui/Card.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import AuditTable from "~/components/admin/AuditTable.vue";
import { AUDIT_ACTIONS, type AuditAction, type AuditEntry, type AuditQuery, type Paginated } from "~/types/admin";
import { downloadCsv } from "~/utils/csv";

definePageMeta({ layout: "admin" });
const { t } = useI18n();
useHead({ title: () => t("admin.nav.audit") });
const { can } = useAdminPermissions();

const action = ref<AuditAction | "all">("all");
const actorId = ref("");
const subjectId = ref("");
const from = ref("");
const to = ref("");
const page = ref(1);
const loading = ref(true);
const exporting = ref(false);
const result = ref<Paginated<AuditEntry>>({ data: [], meta: { page: 1, perPage: 25, total: 0, lastPage: 1 } });

const query = computed<AuditQuery>(() => ({
  action: action.value === "all" ? undefined : action.value,
  actorId: actorId.value || undefined,
  subjectId: subjectId.value || undefined,
  from: from.value || undefined,
  to: to.value || undefined,
  page: page.value,
}));

const load = async () => { loading.value = true; try { result.value = await useAdminAudit().list(query.value); } finally { loading.value = false; } };
onMounted(load);
watch([action, actorId, subjectId, from, to], () => { page.value = 1; load(); });
watch(page, load);

const actionOptions = computed(() => [
  { value: "all", label: t("admin.common.all") },
  ...AUDIT_ACTIONS.map((a) => ({ value: a, label: t(`admin.audit.actions.${a}`) })),
]);

const exportCsv = async () => {
  exporting.value = true;
  try {
    const csv = await useAdminAudit().exportCsv({ ...query.value, page: undefined });
    // downloadCsv builds from rows; we already have CSV text — reuse its blob path via a 1-row wrapper.
    const blob = new Blob(["﻿" + csv], { type: "text/csv;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url; a.download = `roofly-audit-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
  } finally { exporting.value = false; }
};
void downloadCsv; // keep the import for parity with reports.vue; remove if unused after review
</script>

<template>
  <div>
    <header class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 class="text-display-sub font-semibold tracking-snug">{{ t("admin.audit.title") }}</h1>
        <p class="mt-2 text-caption text-ink-muted">{{ can("audit.view") ? t("admin.audit.subtitleAll") : t("admin.audit.subtitleOwn") }}</p>
      </div>
      <Button v-if="can('audit.view')" variant="ghost" size="sm" class="self-start" :loading="exporting" @click="exportCsv">{{ t("admin.common.exportCsv") }}</Button>
    </header>

    <Card padding="compact" class="mb-4 sm:mb-6">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <Select v-model="action" :options="actionOptions" :label="t('admin.audit.filters.action')" />
        <Input v-if="can('audit.view')" v-model="actorId" :label="t('admin.audit.filters.actorId')" />
        <Input v-model="subjectId" :label="t('admin.audit.filters.subjectId')" />
        <Input v-model="from" type="date" :label="t('admin.audit.filters.from')" />
        <Input v-model="to" type="date" :label="t('admin.audit.filters.to')" />
      </div>
    </Card>

    <Card v-if="loading" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
    <Card v-else-if="result.data.length === 0" padding="loose"><EmptyState icon="ScrollText" :title="t('admin.common.noResults')" :description="t('admin.common.noResultsHelp')" /></Card>
    <template v-else>
      <Card padding="compact"><AuditTable :entries="result.data" /></Card>
      <footer class="mt-4 flex items-center justify-between gap-3 text-caption text-ink-muted">
        <span>{{ t("admin.common.pageOf", { page: result.meta.page, lastPage: result.meta.lastPage, total: result.meta.total }) }}</span>
        <div class="flex gap-2">
          <Button variant="ghost" size="sm" :disabled="page <= 1" @click="page--">{{ t("common.back") }}</Button>
          <Button variant="ghost" size="sm" :disabled="page >= result.meta.lastPage" @click="page++">{{ t("common.next") }}</Button>
        </div>
      </footer>
    </template>
  </div>
</template>
```
Remove the `downloadCsv` import and the `void downloadCsv;` line — the inline blob download is the whole implementation (it differs from `downloadCsv` only in taking ready-made CSV text). If you prefer, add an `downloadCsvText(filename, csv)` helper to `utils/csv.ts` and call that instead; either is acceptable, but do not leave an unused import.

- [ ] **Step 2: Strings** — extend `admin.audit` (keep `details` + `actions` from Task 21):

`en.json`:
```json
"title": "Audit log", "subtitleAll": "Every admin action on the platform, newest first.",
"subtitleOwn": "Your own actions, newest first. Ask a super-admin for audit.view to see everyone's.",
"filters": { "action": "Action", "actorId": "Actor id", "subjectId": "Subject id", "from": "From", "to": "To" }
```
`ms.json`:
```json
"title": "Log audit", "subtitleAll": "Setiap tindakan admin di platform, terbaharu dahulu.",
"subtitleOwn": "Tindakan anda sendiri, terbaharu dahulu. Minta audit.view daripada super-admin untuk melihat semua.",
"filters": { "action": "Tindakan", "actorId": "ID pelaku", "subjectId": "ID subjek", "from": "Dari", "to": "Hingga" }
```

- [ ] **Step 3: Gate**

Run: `docker exec roofly-frontend npm run typecheck` → 5 known errors only.

---

### Task 25: Docs — CLAUDE.md, MOCK-POC schema impact, UI-STANDARDS mobile note, `.env.example`

**Files:**
- Modify: `.claude/CLAUDE.md`, `docs/frontend/MOCK-POC.md`, `docs/frontend/UI-STANDARDS.md`, `.env.example` (repo root, if present) and `docker-compose.yml`

- [ ] **Step 1: CLAUDE.md** — under "Current state" add an **Admin shell** paragraph (five surfaces: dashboard, owners list/detail, tenants list/detail, settings → admins, audit; separate `/admin/login`; `features.admin` off in demo; credentials `admin@roofly.my` / `ops@roofly.my`). Under "Where things live" add `services/contracts/admin/`, `services/api/admin/`, `demo/services/admin/`, `demo/data/admin.ts`, `components/admin/`, `layouts/admin.vue` + `auth-admin.vue`, `middleware/env.global.ts` (renamed). Under "Locked-in conventions" add: "**Admin sees summaries only** — `AdminResourcesTest` pins the key sets; widen deliberately, never by adding a field to a Resource." Under "How to run" add the two admin credentials and `/admin/login`. Change the Stack line "Laravel 11" → "Laravel 13" (composer.json says `^13.8`).

- [ ] **Step 2: MOCK-POC.md** — new section "Admin back office (SP1)" listing the five surfaces, the `AdminOwner` / `AdminTenant` / `AdminPropertySummary` / `AdminUser` / `AuditEntry` shapes by name (link to `types/admin.ts`), and a brief **Schema impact**: `users.{is_super_admin, suspended_at, suspension_reason, last_active_at, first_login_at, disabled_at}`, `admin_invites`, Spatie `permissions` seeded from `AdminPermissions`, ActivityLog `log_name = admin`. Note SP2–4 hooks (channels on `OwnerWarning::via`, flags, subscriptions).

- [ ] **Step 3: UI-STANDARDS.md § 11** — add **11.15 Admin data tables** (the `DataTableShell` pattern: TanStack table from `sm:` up, card rows under `sm`, server-side pagination footer, filters card with grid `sm:grid-cols-2 lg:grid-cols-5`) and the § 1.5 admin accent note from Task 17 if not yet added.

- [ ] **Step 4: Compose / env** — in `docker-compose.yml` frontend `environment` add `- NUXT_PUBLIC_FEATURE_ADMIN=${NUXT_PUBLIC_FEATURE_ADMIN:-true}`; add the same key to the repo-root `.env.example` with a one-line comment ("admin back office; demo forces off").

- [ ] **Step 5: Gate**

No code changes; re-run `docker exec roofly-frontend npm run typecheck` once to be sure nothing drifted.

---

### Task 26: Final verification sweep

- [ ] **Step 1: Backend**

Run: `docker exec roofly-backend php artisan test`
Expected: all green; the `tests/Feature/Admin/` suite has ≥ 40 tests.

- [ ] **Step 2: Frontend**

Run: `docker exec roofly-frontend npm run typecheck 2>&1 | tail -20`
Expected: exactly the 5 known errors (`InvoiceViewModal.vue`, `payments.vue` ×2, `Icon.vue`, `EmptyState.vue`).

- [ ] **Step 3: Import-direction greps** (from repo root)

```bash
grep -rn "useApi" frontend/app/demo/               # expect: no output
grep -rn "~/demo" frontend/app/services/api/        # expect: no output
grep -rn "if (useMock" frontend/app/                # expect: no output
grep -rn "formatRM\|MoneyDisplay\|useMoney" frontend/app/pages/admin frontend/app/components/admin   # expect: no output (no money in admin)
grep -n '"[^"]*@[^"]*"' frontend/i18n/locales/en.json frontend/i18n/locales/ms.json | grep -v '{.@.}'  # expect: no bare @ in values
```

- [ ] **Step 4: Spec route table vs `route:list`**

Run: `docker exec roofly-backend php artisan route:list --path=admin`
Expected: the 22 routes from spec § 9 (2 guest + 20 protected), each protected route showing `auth:sanctum, touch-active, role:admin` and its `can:` (audit index has none).

- [ ] **Step 5: Hand-off summary for the user** — list what was built (backend + frontend), the check outputs verbatim, and the browser walk:
- API mode (`NUXT_PUBLIC_USE_MOCK=false`): `http://localhost:3000/admin/login` → `admin@roofly.my` / `password` (super-admin) and `ops@roofly.my` / `password` (ops; Settings hidden). Owner suspension round-trip: suspend `aminah@roofly.my` from `/admin/owners/<id>`, then in a private window log in as `aminah@roofly.my` → lands on `/suspended`; tenant `aminah.yusof@example.com` still works; unsuspend → owner dashboard loads.
- Demo mode (`NUXT_PUBLIC_USE_MOCK=true`, `NUXT_PUBLIC_APP_ENV=uat`): same URLs with `admin@roofly.my` / `ops@roofly.my` (any password). With `NUXT_PUBLIC_APP_ENV=demo`, `/admin/login` must 404.

---

## Self-review notes (done while writing)

**Spec coverage →** § 2 decisions: separate login (T4, T17), per-admin permissions + preset (T2, T9, T23), summary tier + pinned tests (T6), no money (T6/T11 assert, T26 grep), warn/suspend (T5, T7, T21), audit (T3, T10, T24), demo off (T13, T15), admins are users (T1, T9). § 3 shell/routing: T15, T17, T18. § 4 auth: T4, T14. § 5 permissions/admins/audit: T2, T9, T10, T23, T24. § 6 tier: T6. § 7 dashboard: T11, T19. § 8 owners/tenants/warn/suspend/enforcement: T5, T7, T8, T20, T21, T22. § 9 structure/migration/seed/routes: T1, T12, T16. § 10 backend tests: every listed case has a test in T2–T11; frontend Playwright is intentionally excluded per the session rules (user walks the browser). § 12 open points: accent colour (T17, `#2f4f6b`/`#7fa6c9`), invite wording EN+BM (T9 `AdminInvite`), cookie domain — unchanged (`SANCTUM_STATEFUL_DOMAINS` already env-driven; add `admin.roofly.my` to that list at deploy time — note for the user, no code).

**Known simplifications (deliberate):** owner list `overCap` filter is computed in PHP after the query (cap is a PHP `match`, not a column); fine at SP1 scale, revisit when plans move to a table in SP4. Tenant "resend invite" records the action and bumps `invited_at`; the actual magic-link mail is Phase 2 (`MagicLinkController` is still a 501 stub). Admin list pages keep page size fixed (20 / 25).

**Type consistency checked:** `AdminOwner`/`AdminTenant`/`AdminPropertySummary`/`AdminUser`/`AuditEntry` key names are identical in T6/T7/T9 Resources, T16 types, and the T20–T24 pages; `Paginated.meta = {page, perPage, total, lastPage}` everywhere; permission keys come from `AdminPermissions::ALL` ↔ `ADMIN_PERMISSIONS`; `AuditLogger` constants ↔ `AuditAction` union ↔ `admin.audit.actions` i18n keys (nested form).
