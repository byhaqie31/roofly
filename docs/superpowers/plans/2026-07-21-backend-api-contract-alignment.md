# Backend API Contract Alignment (Option A) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Laravel backend serve exactly the camelCase contract defined by `frontend/app/types/*.ts` + `frontend/app/services/*.ts`, proven by PHPUnit feature tests.

**Architecture:** API Resource layer for all responses (camelCase, renames, `withoutWrapping`), FormRequest per write endpoint (camelCase validation → `toModelAttributes()` snake_case mapping), a small `EnsureRole` middleware replacing unregistered Spatie route middleware, one migration adding `users.status` + `users.invited_by`, expand-envelope resources for the frontend's `listWithRefs` calls, and a `DemoSeeder` mirroring the frontend mocks.

**Tech Stack:** Laravel 11, PHPUnit 12 (sqlite `:memory:` per phpunit.xml), Sanctum, existing Enums in `app/Enums/`.

**Spec:** `docs/superpowers/specs/2026-07-21-backend-api-contract-alignment-design.md` — read it first.

## Global Constraints

- **NO GIT COMMITS anywhere in this plan.** The user reviews the finished build first. This overrides the default commit steps of any skill.
- All work under `/Users/BHQIMBP16/Developer/roofly/backend` unless stated.
- Responses: camelCase keys, exactly matching the frontend type field-for-field. Never `{ data: … }` wrapping.
- Renames: `amount_cents→amount`, `late_fee_cents→lateFee`, `rent_amount_cents→rentAmount`, `deposit_amount_cents→depositAmount`, `share_pct→sharePct`, `is_primary→isPrimary`, `personal_info→personal`, `emergency_contact→emergencyContact`.
- Dates: `date` columns emit `YYYY-MM-DD`; timestamps emit ISO 8601 (`->toISOString()`).
- JSON blob interiors (`ownership`, `utilities`, `personal_info`, `emergency_contact`, preferences) are stored and emitted **camelCase verbatim** — no interior transformation.
- Money is integer sen end-to-end.
- Test command: `php artisan test` (or `./vendor/bin/phpunit`). Every feature test uses `Illuminate\Foundation\Testing\RefreshDatabase` and `Laravel\Sanctum\Sanctum::actingAs($user)`.
- Existing test files `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` may fail or be deleted — delete both in Task 0.

---

### Task 0: Environment bootstrap (no deliverable code)

**Files:**
- Delete: `backend/tests/Feature/ExampleTest.php`, `backend/tests/Unit/ExampleTest.php`

- [ ] **Step 1: Verify PHP + Composer available**

Run: `php -v && composer -V` (host). If missing, run everything inside docker instead: `docker compose up -d backend` then prefix all commands with `docker exec roofly-backend`. Record which mode you're in; use it for every subsequent Run step.

- [ ] **Step 2: Install dependencies**

Run: `cd backend && composer install --no-interaction`
Expected: `vendor/` created, no errors. (No `.env` needed for tests — phpunit.xml sets env.)

- [ ] **Step 3: Delete example tests, verify test runner**

Run: `rm tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php && php artisan test`
Expected: `No tests executed` or pass with 0 tests — runner works, sqlite in-memory OK.

---

### Task 1: `EnsureRole` middleware + `withoutWrapping` + CORS

**Files:**
- Create: `backend/app/Http/Middleware/EnsureRole.php`
- Create: `backend/config/cors.php`
- Modify: `backend/bootstrap/app.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Test: `backend/tests/Feature/RoleMiddlewareTest.php`

**Interfaces:**
- Produces: route middleware alias `role` (usage `role:owner`, `role:tenant`) checking the `users.role` enum column. All later tasks assume `role:` guards work.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_reach_owner_routes(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->getJson('/api/properties')->assertOk();
    }

    public function test_tenant_is_blocked_from_owner_routes(): void
    {
        Sanctum::actingAs(User::factory()->tenant()->create());
        $this->getJson('/api/properties')->assertForbidden();
    }

    public function test_owner_is_blocked_from_tenant_routes(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->getJson('/api/me/invoices')->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/properties')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RoleMiddlewareTest`
Expected: FAIL — `Target class [role] does not exist` (alias unregistered).

- [ ] **Step 3: Implement middleware + registration + wrapping + CORS**

`app/Http/Middleware/EnsureRole.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role guard reading the users.role enum column directly.
 * (Spatie Permission stays installed for future granular permissions,
 * but nothing is wired to it yet — one role system, not two.)
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        abort_if(! $user || $user->role->value !== $role, 403);

        return $next($request);
    }
}
```

`bootstrap/app.php` — replace the `->withMiddleware(...)` block:
```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
```

`app/Providers/AppServiceProvider.php` — in `boot()` add (with `use Illuminate\Http\Resources\Json\JsonResource;`):
```php
    public function boot(): void
    {
        JsonResource::withoutWrapping();
    }
```

`config/cors.php`:
```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RoleMiddlewareTest`
Expected: PASS (4 tests). Note: `/api/properties` still returns snake_case — that's fine here; only status codes are asserted.

---

### Task 2: Migration — `users.status` + `users.invited_by`

**Files:**
- Create: `backend/database/migrations/2026_07_21_000001_add_tenant_status_to_users_table.php`
- Modify: `backend/app/Models/User.php` (fillable + relationship)
- Modify: `backend/database/factories/UserFactory.php` (tenant state)
- Test: `backend/tests/Feature/UserTenantColumnsTest.php`

**Interfaces:**
- Produces: `users.status` (nullable string: `invited|active|notice_given|moved_out`; null for owners/admins), `users.invited_by` (nullable uuid FK users, null-on-delete). `UserFactory::tenant()` now sets `status='active'`, and a new state `invitedTenant()` sets `status='invited'`, `invited_at=now()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTenantColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_status_and_inviter_are_persisted(): void
    {
        $owner  = User::factory()->owner()->create();
        $tenant = User::factory()->tenant()->create([
            'status'     => 'notice_given',
            'invited_by' => $owner->id,
        ]);

        $this->assertSame('notice_given', $tenant->fresh()->status);
        $this->assertSame($owner->id, $tenant->fresh()->invited_by);
        $this->assertNull($owner->fresh()->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UserTenantColumnsTest`
Expected: FAIL — column not found / value null.

- [ ] **Step 3: Implement migration + model + factory**

Migration:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tenant lifecycle — null for owners/admins. String (not DB enum)
            // so sqlite ALTERs cleanly; values enforced by FormRequests.
            $table->string('status', 20)->nullable()->after('invited_at');
            // Owner who invited this tenant — links pre-agreement tenants
            // to their owner so they appear in GET /tenants.
            $table->foreignUuid('invited_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn('status');
        });
    }
};
```

`User.php`: add `'status'` and `'invited_by'` to `$fillable`, and this relationship after `properties()`:
```php
    public function invitedTenants(): HasMany
    {
        return $this->hasMany(User::class, 'invited_by');
    }
```

`UserFactory.php`: replace `tenant()` and add `invitedTenant()`:
```php
    public function tenant(): static
    {
        return $this->state(fn () => ['role' => UserRole::TENANT, 'status' => 'active']);
    }

    public function invitedTenant(): static
    {
        return $this->state(fn () => [
            'role'       => UserRole::TENANT,
            'status'     => 'invited',
            'invited_at' => now(),
        ]);
    }
```

- [ ] **Step 4: Run tests**

Run: `php artisan test`
Expected: PASS (Tasks 1–2 tests).

---

### Task 3: Base API Resources

**Files:**
- Create: `backend/app/Http/Resources/PropertyResource.php`, `PropertyCoOwnerResource.php`, `UnitResource.php`, `TenantResource.php`, `AgreementResource.php`, `InvoiceResource.php`, `PaymentResource.php`, `TicketResource.php`, `TicketCommentResource.php`, `AuthUserResource.php`
- Create: `backend/database/factories/PropertyFactory.php`, `UnitFactory.php`, `AgreementFactory.php`, `InvoiceFactory.php`, `PaymentFactory.php`, `TicketFactory.php`, `TicketCommentFactory.php`, `PropertyCoOwnerFactory.php`
- Test: `backend/tests/Unit/ResourcesTest.php`

**Interfaces:**
- Produces: `new XResource($model)->resolve()` returns the exact camelCase array per frontend type. Later tasks return these from controllers. Factories produce valid models for every entity (all later tests consume them).

- [ ] **Step 1: Write factories** (needed by the test; models all `HasFactory`)

`database/factories/PropertyFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->owner(),
            'name'     => fake()->streetName() . ' Residence',
            'type'     => 'condo',
            'address'  => fake()->streetAddress(),
            'city'     => 'Kuala Lumpur',
            'state'    => 'W.P. Kuala Lumpur',
            'postcode' => '50450',
        ];
    }
}
```

`database/factories/PropertyCoOwnerFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyCoOwnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name'        => fake()->name(),
            'share_pct'   => 100,
            'is_primary'  => true,
        ];
    }
}
```

`database/factories/UnitFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'label'       => 'Unit ' . fake()->numerify('##-##'),
            'status'      => 'vacant',
        ];
    }
}
```

`database/factories/AgreementFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgreementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id'              => Unit::factory(),
            'tenant_id'            => User::factory()->tenant(),
            'start_date'           => '2026-01-01',
            'end_date'             => '2026-12-31',
            'rent_amount_cents'    => 180000,
            'deposit_amount_cents' => 360000,
            'late_fee_cents'       => 5000,
            'rent_due_day'         => 1,
            'status'               => 'active',
        ];
    }
}
```

`database/factories/InvoiceFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Agreement;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agreement_id'   => Agreement::factory(),
            'invoice_number' => 'INV-' . fake()->unique()->numerify('####'),
            'amount_cents'   => 180000,
            'late_fee_cents' => 0,
            'due_date'       => '2026-07-01',
            'status'         => 'pending',
        ];
    }
}
```

`database/factories/PaymentFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id'   => Invoice::factory(),
            'amount_cents' => 180000,
            'method'       => 'fpx',
            'status'       => 'successful',
            'paid_at'      => now(),
        ];
    }
}
```

`database/factories/TicketFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id'       => Unit::factory(),
            'reporter_id'   => User::factory()->tenant(),
            'reporter_role' => 'tenant',
            'category'      => 'plumbing',
            'priority'      => 'medium',
            'title'         => fake()->sentence(4),
            'description'   => fake()->paragraph(),
            'status'        => 'new',
        ];
    }
}
```

`database/factories/TicketCommentFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id'   => Ticket::factory(),
            'author_id'   => User::factory()->tenant(),
            'author_role' => 'tenant',
            'body'        => fake()->sentence(),
        ];
    }
}
```

Note: check each model's `$fillable`/columns if a factory insert fails (e.g. `payments` may have `reference`, `paid_at` nullable) — adjust values, never the migration.

- [ ] **Step 2: Write the failing resource test**

```php
<?php

namespace Tests\Unit;

use App\Http\Resources\AgreementResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\TenantResource;
use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_resource_shape(): void
    {
        $invoice = Invoice::factory()->create(['amount_cents' => 180000, 'late_fee_cents' => 5000]);
        $out = (new InvoiceResource($invoice))->resolve();

        $this->assertSame(
            ['id', 'agreementId', 'invoiceNumber', 'amount', 'lateFee', 'dueDate', 'status', 'createdAt'],
            array_keys($out)
        );
        $this->assertSame(180000, $out['amount']);
        $this->assertSame(5000, $out['lateFee']);
        $this->assertSame('2026-07-01', $out['dueDate']);
        $this->assertSame('pending', $out['status']);
    }

    public function test_agreement_resource_shape(): void
    {
        $agreement = Agreement::factory()->create();
        $out = (new AgreementResource($agreement))->resolve();

        $this->assertSame(
            ['id', 'unitId', 'tenantId', 'startDate', 'endDate', 'rentAmount', 'depositAmount', 'lateFee', 'rentDueDay', 'status', 'createdAt'],
            array_keys($out)
        );
        $this->assertSame(180000, $out['rentAmount']);
        $this->assertSame('2026-01-01', $out['startDate']);
    }

    public function test_property_resource_shape_with_co_owners_and_blobs(): void
    {
        $property = Property::factory()->create([
            'ownership' => ['titleType' => 'freehold', 'purchasePrice' => 45000000],
            'utilities' => ['tnbAccountNo' => '123456'],
        ]);
        PropertyCoOwner::factory()->create(['property_id' => $property->id, 'user_id' => $property->owner_id]);
        $out = (new PropertyResource($property->load('coOwners')))->resolve();

        $this->assertSame(
            ['id', 'ownerId', 'name', 'internalLabel', 'type', 'notes', 'address', 'city', 'state', 'postcode',
             'yearBuilt', 'builtUpSqft', 'landSqft', 'bedrooms', 'bathrooms', 'parkingLots', 'furnishing',
             'ownership', 'utilities', 'coOwners', 'createdAt'],
            array_keys($out)
        );
        // Blob interiors pass through camelCase verbatim
        $this->assertSame('freehold', $out['ownership']['titleType']);
        $coOwner = (array) $out['coOwners'][0]->resolve();
        $this->assertSame(['id', 'name', 'sharePct', 'isPrimary'], array_keys($coOwner));
        $this->assertSame(100.0, $coOwner['sharePct']);
        $this->assertTrue($coOwner['isPrimary']);
    }

    public function test_tenant_resource_shape(): void
    {
        $tenant = User::factory()->invitedTenant()->create([
            'personal_info'     => ['icNumber' => '880314-14-5687', 'monthlyIncome' => 650000],
            'emergency_contact' => ['name' => 'Ali', 'phone' => '+60 12', 'relationship' => 'Brother'],
        ]);
        $out = (new TenantResource($tenant))->resolve();

        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'status', 'invitedAt', 'createdAt', 'personal', 'emergencyContact'],
            array_keys($out)
        );
        $this->assertSame('invited', $out['status']);
        $this->assertSame(650000, $out['personal']['monthlyIncome']);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=ResourcesTest`
Expected: FAIL — resource classes don't exist.

- [ ] **Step 4: Implement the resources**

All in `app/Http/Resources/`, all `extends Illuminate\Http\Resources\Json\JsonResource` with namespace `App\Http\Resources;`. `toArray($request)` bodies:

`InvoiceResource.php`:
```php
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'agreementId'   => $this->agreement_id,
            'invoiceNumber' => $this->invoice_number,
            'amount'        => $this->amount_cents,
            'lateFee'       => $this->late_fee_cents,
            'dueDate'       => $this->due_date->format('Y-m-d'),
            'status'        => $this->status,
            'createdAt'     => $this->created_at?->toISOString(),
        ];
    }
```

`AgreementResource.php`:
```php
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'unitId'        => $this->unit_id,
            'tenantId'      => $this->tenant_id,
            'startDate'     => $this->start_date->format('Y-m-d'),
            'endDate'       => $this->end_date->format('Y-m-d'),
            'rentAmount'    => $this->rent_amount_cents,
            'depositAmount' => $this->deposit_amount_cents,
            'lateFee'       => $this->late_fee_cents,
            'rentDueDay'    => $this->rent_due_day,
            'status'        => $this->status,
            'createdAt'     => $this->created_at?->toISOString(),
        ];
    }
```
(If `start_date`/`end_date` aren't carbon-cast on the model, add `'start_date' => 'date', 'end_date' => 'date'` to `Agreement::casts()`.)

`PropertyResource.php`:
```php
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'ownerId'       => $this->owner_id,
            'name'          => $this->name,
            'internalLabel' => $this->internal_label,
            'type'          => $this->type,
            'notes'         => $this->notes,
            'address'       => $this->address,
            'city'          => $this->city,
            'state'         => $this->state,
            'postcode'      => $this->postcode,
            'yearBuilt'     => $this->year_built,
            'builtUpSqft'   => $this->built_up_sqft,
            'landSqft'      => $this->land_sqft,
            'bedrooms'      => $this->bedrooms,
            'bathrooms'     => $this->bathrooms,
            'parkingLots'   => $this->parking_lots,
            'furnishing'    => $this->furnishing,
            'ownership'     => $this->ownership,
            'utilities'     => $this->utilities,
            'coOwners'      => PropertyCoOwnerResource::collection($this->coOwners),
            'createdAt'     => $this->created_at?->toISOString(),
        ];
    }
```

`PropertyCoOwnerResource.php`:
```php
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'sharePct'  => (float) $this->share_pct,
            'isPrimary' => (bool) $this->is_primary,
        ];
    }
```

`UnitResource.php`:
```php
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'propertyId' => $this->property_id,
            'label'      => $this->label,
            'bedrooms'   => $this->bedrooms,
            'bathrooms'  => $this->bathrooms,
            'sqft'       => $this->sqft,
            'status'     => $this->status,
            'createdAt'  => $this->created_at?->toISOString(),
        ];
    }
```

`TenantResource.php` (wraps a `User` with role tenant):
```php
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'status'           => $this->status,
            'invitedAt'        => $this->invited_at?->toISOString(),
            'createdAt'        => $this->created_at?->toISOString(),
            'personal'         => $this->personal_info,
            'emergencyContact' => $this->emergency_contact,
        ];
    }
```

`PaymentResource.php`:
```php
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'invoiceId' => $this->invoice_id,
            'amount'    => $this->amount_cents,
            'method'    => $this->method,
            'status'    => $this->status,
            'paidAt'    => $this->paid_at?->toISOString(),
            'reference' => $this->reference,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
```
(Ensure `Payment::casts()` has `'paid_at' => 'datetime'`; add if missing.)

`TicketResource.php`:
```php
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'unitId'       => $this->unit_id,
            'reporterId'   => $this->reporter_id,
            'reporterRole' => $this->reporter_role,
            'category'     => $this->category,
            'priority'     => $this->priority,
            'title'        => $this->title,
            'description'  => $this->description,
            'status'       => $this->status,
            'createdAt'    => $this->created_at?->toISOString(),
            'updatedAt'    => $this->updated_at?->toISOString(),
            'resolvedAt'   => $this->resolved_at?->toISOString(),
        ];
    }
```
(Ensure `Ticket::casts()` has `'resolved_at' => 'datetime'`.)

`TicketCommentResource.php`:
```php
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'ticketId'   => $this->ticket_id,
            'authorId'   => $this->author_id,
            'authorRole' => $this->author_role,
            'body'       => $this->body,
            'createdAt'  => $this->created_at?->toISOString(),
        ];
    }
```

`AuthUserResource.php`:
```php
    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role'  => $this->role,
        ];
    }
```

Backed enums (PHP `enum ... : string`) serialize to their value in JSON automatically — emitting `$this->status` etc. directly is correct.

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=ResourcesTest`
Expected: PASS. Then `php artisan test` — all green.

---

### Task 4: Auth endpoints → `AuthUserResource`

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Auth/LoginController.php`, `RegisterController.php`
- Test: `backend/tests/Feature/AuthContractTest.php`

**Interfaces:**
- Produces: `POST /api/auth/login` & `POST /api/auth/register` → `{user: AuthUser, token}` ; `GET /api/auth/me` → `AuthUser` (bare). AuthUser = `{id, name, email, phone, role}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_auth_user_shape(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create());
        $res = $this->getJson('/api/auth/me')->assertOk();
        $this->assertSame(['id', 'name', 'email', 'phone', 'role'], array_keys($res->json()));
        $this->assertSame('owner', $res->json('role'));
    }

    public function test_login_returns_user_and_token(): void
    {
        User::factory()->owner()->create(['email' => 'a@b.my', 'password' => Hash::make('secret123')]);
        $res = $this->postJson('/api/auth/login', ['email' => 'a@b.my', 'password' => 'secret123'])->assertOk();
        $this->assertSame(['user', 'token'], array_keys($res->json()));
        $this->assertSame(['id', 'name', 'email', 'phone', 'role'], array_keys($res->json('user')));
    }

    public function test_register_creates_owner(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'name' => 'New Owner', 'email' => 'n@o.my', 'phone' => '+60 12',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertCreated();
        $this->assertSame('owner', $res->json('user.role'));
    }
}
```

- [ ] **Step 2: Run to verify it fails** — `php artisan test --filter=AuthContractTest`. Expected: FAIL on key-set assertions (raw user has many more keys).

- [ ] **Step 3: Implement** — in both controllers wrap user output: `'user' => (new AuthUserResource($user))->resolve()`, and `show()` returns `new AuthUserResource($request->user())`. Import `App\Http\Resources\AuthUserResource`.

- [ ] **Step 4: Run tests** — `php artisan test --filter=AuthContractTest`. Expected: PASS.

---

### Task 5: Properties + co-owners contract

**Files:**
- Create: `backend/app/Http/Requests/StorePropertyRequest.php`, `UpdatePropertyRequest.php`, `SyncCoOwnersRequest.php`
- Modify: `backend/app/Http/Controllers/Api/Owner/PropertyController.php`, `PropertyCoOwnerController.php`
- Test: `backend/tests/Feature/PropertyContractTest.php`

**Interfaces:**
- Consumes: `PropertyResource`, `PropertyCoOwnerResource` (Task 3); `role:` middleware (Task 1).
- Produces: `GET /api/properties` → `Property[]`; `POST` (camelCase `PropertyInput`) → 201 `Property`; `GET/PATCH/DELETE /api/properties/{id}`; `PUT /api/properties/{id}/co-owners` body `{coOwners: [{id?, name, sharePct, isPrimary}]}` → `PropertyCoOwner[]`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        Sanctum::actingAs($this->owner);
    }

    public function test_index_returns_bare_camel_case_array(): void
    {
        $p = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $p->id, 'user_id' => $this->owner->id]);

        $res = $this->getJson('/api/properties')->assertOk();
        $this->assertIsList($res->json());           // bare array, no {data:…}
        $row = $res->json()[0];
        $this->assertArrayHasKey('ownerId', $row);
        $this->assertArrayHasKey('coOwners', $row);
        $this->assertArrayNotHasKey('owner_id', $row);
    }

    public function test_store_accepts_camel_case_tier1_input(): void
    {
        $res = $this->postJson('/api/properties', [
            'name' => 'Vista Residence', 'type' => 'condo',
            'address' => '12 Jalan Ampang', 'city' => 'Kuala Lumpur',
            'state' => 'W.P. Kuala Lumpur', 'postcode' => '50450',
        ])->assertCreated();
        $this->assertSame('Vista Residence', $res->json('name'));
        // Auto-seeded primary co-owner
        $this->assertCount(1, $res->json('coOwners'));
        $this->assertTrue($res->json('coOwners.0.isPrimary'));
    }

    public function test_update_accepts_camel_case_tier2_fields_and_blobs(): void
    {
        $p = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $p->id, 'user_id' => $this->owner->id]);

        $res = $this->patchJson("/api/properties/{$p->id}", [
            'builtUpSqft' => 1200,
            'parkingLots' => 2,
            'ownership'   => ['titleType' => 'leasehold', 'purchasePrice' => 45000000],
        ])->assertOk();
        $this->assertSame(1200, $res->json('builtUpSqft'));
        $this->assertSame('leasehold', $res->json('ownership.titleType'));
        $this->assertSame(45000000, $p->fresh()->ownership['purchasePrice']); // stored verbatim
    }

    public function test_co_owner_sync_enforces_invariants(): void
    {
        $p = Property::factory()->create(['owner_id' => $this->owner->id]);

        // sum != 100 rejected
        $this->putJson("/api/properties/{$p->id}/co-owners", [
            'coOwners' => [['name' => 'A', 'sharePct' => 60, 'isPrimary' => true]],
        ])->assertStatus(422);

        // valid sync
        $res = $this->putJson("/api/properties/{$p->id}/co-owners", [
            'coOwners' => [
                ['name' => 'A', 'sharePct' => 60, 'isPrimary' => true],
                ['name' => 'B', 'sharePct' => 40, 'isPrimary' => false],
            ],
        ])->assertOk();
        $this->assertSame(['id', 'name', 'sharePct', 'isPrimary'], array_keys($res->json()[0]));
    }

    public function test_other_owner_cannot_see_my_property(): void
    {
        $p = Property::factory()->create(['owner_id' => $this->owner->id]);
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->getJson("/api/properties/{$p->id}")->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify fail** — `php artisan test --filter=PropertyContractTest`. Expected: FAIL (snake_case output; camelCase input ignored).

- [ ] **Step 3: Implement FormRequests**

`app/Http/Requests/StorePropertyRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:owner middleware gates the route
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'type'     => 'required|in:condo,landed,shoplot,room',
            'address'  => 'required|string|max:500',
            'city'     => 'required|string|max:100',
            'state'    => ['required', Rule::in(self::MY_STATES)],
            'postcode' => 'required|digits:5',
        ];
    }

    public const MY_STATES = [
        'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan',
        'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah',
        'Sarawak', 'Selangor', 'Terengganu',
        'W.P. Kuala Lumpur', 'W.P. Labuan', 'W.P. Putrajaya',
    ];

    /** Column-keyed payload for Property::create(). */
    public function toModelAttributes(): array
    {
        return $this->validated(); // Tier-1 keys are identical in both casings
    }
}
```

`app/Http/Requests/UpdatePropertyRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'sometimes|string|max:255',
            'internalLabel' => 'nullable|string|max:255',
            'type'          => 'sometimes|in:condo,landed,shoplot,room',
            'notes'         => 'nullable|string',
            'address'       => 'sometimes|string|max:500',
            'city'          => 'sometimes|string|max:100',
            'state'         => ['sometimes', Rule::in(StorePropertyRequest::MY_STATES)],
            'postcode'      => 'sometimes|digits:5',
            'yearBuilt'     => 'nullable|integer|min:1900|max:2100',
            'builtUpSqft'   => 'nullable|integer|min:1',
            'landSqft'      => 'nullable|integer|min:1',
            'bedrooms'      => 'nullable|integer|min:0|max:20',
            'bathrooms'     => 'nullable|integer|min:0|max:20',
            'parkingLots'   => 'nullable|integer|min:0',
            'furnishing'    => 'nullable|in:unfurnished,partial,fully',
            'ownership'     => 'nullable|array',   // camelCase interior stored verbatim
            'utilities'     => 'nullable|array',
        ];
    }

    public function toModelAttributes(): array
    {
        $v = $this->validated();
        $map = [
            'internalLabel' => 'internal_label',
            'yearBuilt'     => 'year_built',
            'builtUpSqft'   => 'built_up_sqft',
            'landSqft'      => 'land_sqft',
            'parkingLots'   => 'parking_lots',
        ];
        $out = [];
        foreach ($v as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }
        return $out;
    }
}
```

`app/Http/Requests/SyncCoOwnersRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncCoOwnersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coOwners'              => 'required|array|min:1',
            'coOwners.*.id'         => 'nullable|string',
            'coOwners.*.name'       => 'required|string|max:255',
            'coOwners.*.sharePct'   => 'required|numeric|min:0.01|max:100',
            'coOwners.*.isPrimary'  => 'required|boolean',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $rows = $this->input('coOwners', []);
                $total = array_sum(array_column($rows, 'sharePct'));
                if (abs($total - 100) > 0.01) {
                    $validator->errors()->add('coOwners', 'Co-owner shares must sum to 100%.');
                }
                if (count(array_filter($rows, fn ($c) => $c['isPrimary'] ?? false)) !== 1) {
                    $validator->errors()->add('coOwners', 'Exactly one co-owner must be marked as primary.');
                }
            },
        ];
    }

    /** Rows keyed for property_co_owners columns. */
    public function toRows(): array
    {
        return array_map(fn ($c) => [
            'name'       => $c['name'],
            'share_pct'  => $c['sharePct'],
            'is_primary' => $c['isPrimary'],
        ], $this->validated()['coOwners']);
    }
}
```

- [ ] **Step 4: Rewrite the controllers**

`PropertyController` — every method returns Resources; requests swap in:
```php
<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        return PropertyResource::collection(
            Property::with('coOwners')
                ->where('owner_id', $request->user()->id)
                ->latest()
                ->get()
        );
    }

    public function store(StorePropertyRequest $request)
    {
        $property = Property::create(array_merge($request->toModelAttributes(), [
            'owner_id' => $request->user()->id,
        ]));

        PropertyCoOwner::create([
            'property_id' => $property->id,
            'user_id'     => $request->user()->id,
            'name'        => $request->user()->name,
            'share_pct'   => 100.00,
            'is_primary'  => true,
        ]);

        return (new PropertyResource($property->load('coOwners')))
            ->response()->setStatusCode(201);
    }

    public function show(Request $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);

        return new PropertyResource($property->load('coOwners'));
    }

    public function update(UpdatePropertyRequest $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);

        $property->update($request->toModelAttributes());

        return new PropertyResource($property->load('coOwners'));
    }

    public function destroy(Request $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);
        $property->delete();

        return response()->json(null, 204);
    }
}
```

`PropertyCoOwnerController` — `index` returns `PropertyCoOwnerResource::collection($property->coOwners)`; `sync(SyncCoOwnersRequest $request, Property $property)` keeps the ownership `abort_if`, then deletes + recreates from `$request->toRows()`, returns `PropertyCoOwnerResource::collection($property->coOwners()->get())`. `store`/`destroy`: swap validation keys to camelCase (`sharePct`, `isPrimary`) mapping to columns, return Resources. (Frontend only uses `sync` via `PUT`; keep the others consistent anyway.)

- [ ] **Step 5: Run tests** — `php artisan test --filter=PropertyContractTest` → PASS; then full `php artisan test` → PASS.

---

### Task 6: Units — nested + flat routes

**Files:**
- Create: `backend/app/Http/Requests/StoreUnitRequest.php`, `UpdateUnitRequest.php`
- Modify: `backend/app/Http/Controllers/Api/Owner/UnitController.php`, `backend/routes/api.php`
- Test: `backend/tests/Feature/UnitContractTest.php`

**Interfaces:**
- Consumes: `UnitResource` (Task 3).
- Produces: `GET /api/units` (all of owner's units), `GET /api/properties/{property}/units`, `POST /api/properties/{property}/units` (body `{label, bedrooms?, bathrooms?, sqft?, status}`), `GET/PATCH/DELETE /api/units/{unit}`. All emit `Unit` shape.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->property = Property::factory()->create(['owner_id' => $this->owner->id]);
        Sanctum::actingAs($this->owner);
    }

    public function test_flat_index_lists_all_owner_units_camel_case(): void
    {
        Unit::factory()->create(['property_id' => $this->property->id]);
        Unit::factory()->create(); // another owner's unit — must not appear

        $res = $this->getJson('/api/units')->assertOk();
        $this->assertCount(1, $res->json());
        $this->assertSame(
            ['id', 'propertyId', 'label', 'bedrooms', 'bathrooms', 'sqft', 'status', 'createdAt'],
            array_keys($res->json()[0])
        );
    }

    public function test_flat_update_and_delete(): void
    {
        $unit = Unit::factory()->create(['property_id' => $this->property->id]);

        $this->patchJson("/api/units/{$unit->id}", ['status' => 'occupied'])
            ->assertOk()->assertJsonPath('status', 'occupied');
        $this->deleteJson("/api/units/{$unit->id}")->assertNoContent();
    }

    public function test_nested_create(): void
    {
        $this->postJson("/api/properties/{$this->property->id}/units", [
            'label' => 'A-12-3', 'bedrooms' => 3, 'status' => 'vacant',
        ])->assertCreated()->assertJsonPath('label', 'A-12-3');
    }

    public function test_flat_routes_block_other_owners(): void
    {
        $unit = Unit::factory()->create(['property_id' => $this->property->id]);
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->getJson("/api/units/{$unit->id}")->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify fail** — `php artisan test --filter=UnitContractTest`. Expected: FAIL — `GET /api/units` is 404 (route missing).

- [ ] **Step 3: Implement requests, controller, routes**

`StoreUnitRequest` rules: `label required|string|max:255`, `bedrooms/bathrooms nullable|integer|min:0|max:20`, `sqft nullable|integer|min:1`, `status nullable|in:vacant,occupied,maintenance`; `toModelAttributes()` returns `$this->validated()` (all keys already snake-safe). `UpdateUnitRequest` same with `sometimes`.

`UnitController` — replace entirely:
```php
<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /** GET /units — every unit across the owner's properties. */
    public function all(Request $request)
    {
        return UnitResource::collection(
            Unit::whereHas('property', fn ($q) =>
                $q->where('owner_id', $request->user()->id)
            )->latest()->get()
        );
    }

    /** GET /properties/{property}/units */
    public function index(Request $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);

        return UnitResource::collection($property->units);
    }

    /** POST /properties/{property}/units */
    public function store(StoreUnitRequest $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);

        $unit = $property->units()->create($request->toModelAttributes());

        return (new UnitResource($unit))->response()->setStatusCode(201);
    }

    /** GET /units/{unit} */
    public function show(Request $request, Unit $unit)
    {
        $this->authorizeOwner($request, $unit);

        return new UnitResource($unit);
    }

    /** PATCH /units/{unit} */
    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        $this->authorizeOwner($request, $unit);
        $unit->update($request->toModelAttributes());

        return new UnitResource($unit);
    }

    /** DELETE /units/{unit} */
    public function destroy(Request $request, Unit $unit)
    {
        $this->authorizeOwner($request, $unit);
        $unit->delete();

        return response()->json(null, 204);
    }

    private function authorizeOwner(Request $request, Unit $unit): void
    {
        abort_if($unit->property->owner_id !== $request->user()->id, 403);
    }
}
```

`routes/api.php` — inside the owner group, replace `Route::apiResource('properties.units', …)` with:
```php
        // Units — nested for list/create, flat for item ops (matches useUnits.ts)
        Route::get('units',                        [\App\Http\Controllers\Api\Owner\UnitController::class, 'all']);
        Route::get('properties/{property}/units',  [\App\Http\Controllers\Api\Owner\UnitController::class, 'index']);
        Route::post('properties/{property}/units', [\App\Http\Controllers\Api\Owner\UnitController::class, 'store']);
        Route::get('units/{unit}',                 [\App\Http\Controllers\Api\Owner\UnitController::class, 'show']);
        Route::patch('units/{unit}',               [\App\Http\Controllers\Api\Owner\UnitController::class, 'update']);
        Route::delete('units/{unit}',              [\App\Http\Controllers\Api\Owner\UnitController::class, 'destroy']);
```

- [ ] **Step 4: Run tests** — `php artisan test --filter=UnitContractTest` → PASS; full suite → PASS.

---

### Task 7: Tenants — invite route + index scope + camelCase profile

**Files:**
- Create: `backend/app/Http/Requests/InviteTenantRequest.php`, `UpdateTenantRequest.php`
- Modify: `backend/app/Http/Controllers/Api/Owner/TenantController.php`, `backend/routes/api.php`
- Test: `backend/tests/Feature/TenantContractTest.php`

**Interfaces:**
- Consumes: `TenantResource` (Task 3); `users.status`/`invited_by` (Task 2).
- Produces: `POST /api/tenants/invite` body `{name, email, phone}` → 201 `Tenant` (`status='invited'`); `GET /api/tenants` scope = invited-by-me OR agreement-on-my-property; `PATCH /api/tenants/{id}` accepts `{name?, email?, phone?, status?, personal?, emergencyContact?}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        Sanctum::actingAs($this->owner);
    }

    public function test_invite_creates_invited_tenant(): void
    {
        $res = $this->postJson('/api/tenants/invite', [
            'name' => 'Aminah Binti Yusof', 'email' => 'aminah@x.my', 'phone' => '+60 12-345 6789',
        ])->assertCreated();

        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'status', 'invitedAt', 'createdAt', 'personal', 'emergencyContact'],
            array_keys($res->json())
        );
        $this->assertSame('invited', $res->json('status'));
        $this->assertNotNull($res->json('invitedAt'));
    }

    public function test_index_includes_invited_tenants_without_agreements(): void
    {
        // The old bug: invited tenants were invisible until they had an agreement.
        $this->postJson('/api/tenants/invite', [
            'name' => 'No Agreement Yet', 'email' => 'nay@x.my', 'phone' => '+60 1',
        ])->assertCreated();

        $res = $this->getJson('/api/tenants')->assertOk();
        $this->assertCount(1, $res->json());
        $this->assertSame('No Agreement Yet', $res->json('0.name'));
    }

    public function test_index_includes_agreement_tenants_and_excludes_strangers(): void
    {
        $unit = Unit::factory()->create([
            'property_id' => Property::factory()->create(['owner_id' => $this->owner->id])->id,
        ]);
        $agreementTenant = User::factory()->tenant()->create();
        Agreement::factory()->create(['unit_id' => $unit->id, 'tenant_id' => $agreementTenant->id]);
        User::factory()->tenant()->create(); // stranger

        $res = $this->getJson('/api/tenants')->assertOk();
        $this->assertCount(1, $res->json());
        $this->assertSame($agreementTenant->id, $res->json('0.id'));
    }

    public function test_update_accepts_camel_case_patch(): void
    {
        $tenant = User::factory()->invitedTenant()->create(['invited_by' => $this->owner->id]);

        $res = $this->patchJson("/api/tenants/{$tenant->id}", [
            'status'           => 'notice_given',
            'personal'         => ['icNumber' => '880314-14-5687', 'monthlyIncome' => 650000],
            'emergencyContact' => ['name' => 'Ali', 'phone' => '+60 13', 'relationship' => 'Brother'],
        ])->assertOk();

        $this->assertSame('notice_given', $res->json('status'));
        $this->assertSame(650000, $res->json('personal.monthlyIncome'));
        $this->assertSame('Ali', $tenant->fresh()->emergency_contact['name']); // stored verbatim
    }
}
```

- [ ] **Step 2: Run to verify fail** — `php artisan test --filter=TenantContractTest`. Expected: FAIL — `/api/tenants/invite` 404, index scope, shapes.

- [ ] **Step 3: Implement**

`InviteTenantRequest` rules: `name required|string|max:255`, `email required|email|max:255|unique:users,email`, `phone required|string|max:30`. No mapping needed.

`UpdateTenantRequest`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'sometimes|string|max:255',
            'email'            => 'sometimes|email|max:255|unique:users,email,' . $this->route('tenant')->id,
            'phone'            => 'sometimes|string|max:30',
            'status'           => 'sometimes|in:invited,active,notice_given,moved_out',
            'personal'         => 'nullable|array',   // camelCase interior stored verbatim
            'emergencyContact' => 'nullable|array',
        ];
    }

    public function toModelAttributes(): array
    {
        $v = $this->validated();
        $map = ['personal' => 'personal_info', 'emergencyContact' => 'emergency_contact'];
        $out = [];
        foreach ($v as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }
        return $out;
    }
}
```

`TenantController` — rewrite: `index` scope `role=tenant AND (invited_by=me OR whereHas agreement chain)`:
```php
    public function index(Request $request)
    {
        $ownerId = $request->user()->id;

        $tenants = User::where('role', UserRole::TENANT)
            ->where(fn ($q) => $q
                ->where('invited_by', $ownerId)
                ->orWhereHas('agreements.unit.property', fn ($qq) =>
                    $qq->where('owner_id', $ownerId)
                )
            )
            ->latest()
            ->get();

        return TenantResource::collection($tenants);
    }

    public function invite(InviteTenantRequest $request)
    {
        $tenant = User::create(array_merge($request->validated(), [
            'role'       => UserRole::TENANT,
            'status'     => 'invited',
            'invited_at' => now(),
            'invited_by' => $request->user()->id,
        ]));

        // TODO Phase 3: dispatch magic-link invite notification

        return (new TenantResource($tenant))->response()->setStatusCode(201);
    }
```
`show`/`update`/`destroy` keep the existing tenant-role + access checks (`authorizeTenantAccess` must also allow `invited_by === owner`) and return `TenantResource`; `update` uses `UpdateTenantRequest->toModelAttributes()`. Keep the existing `store` route working via `InviteTenantRequest` too (same behavior as invite).

`routes/api.php` — **before** `Route::apiResource('tenants', …)` add:
```php
        Route::post('tenants/invite', [\App\Http\Controllers\Api\Owner\TenantController::class, 'invite']);
```
(Order matters: `tenants/invite` must not be captured by `tenants/{tenant}`.)

- [ ] **Step 4: Run tests** — `php artisan test --filter=TenantContractTest` → PASS; full suite → PASS.

---

### Task 8: Agreements + expand envelope

**Files:**
- Create: `backend/app/Http/Requests/StoreAgreementRequest.php`, `UpdateAgreementRequest.php`
- Create: `backend/app/Http/Resources/AgreementWithRefsResource.php`
- Modify: `backend/app/Http/Controllers/Api/Owner/AgreementController.php`
- Test: `backend/tests/Feature/AgreementContractTest.php`

**Interfaces:**
- Consumes: base Resources (Task 3).
- Produces: `GET /api/agreements` → `Agreement[]`; with `?expand=unit,property,tenant` → `[{agreement, unit|null, property|null, tenant|null}]`; `POST` accepts camelCase `AgreementInput` (`unitId, tenantId, startDate, endDate, rentAmount, depositAmount, lateFee, rentDueDay, status`). `AgreementWithRefsResource` is reused by Task 11 (`/me/agreement`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgreementContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $property->id, 'user_id' => $this->owner->id]);
        $this->unit = Unit::factory()->create(['property_id' => $property->id]);
        Sanctum::actingAs($this->owner);
    }

    public function test_plain_index_is_agreement_shape(): void
    {
        Agreement::factory()->create(['unit_id' => $this->unit->id]);
        $res = $this->getJson('/api/agreements')->assertOk();
        $this->assertSame(
            ['id', 'unitId', 'tenantId', 'startDate', 'endDate', 'rentAmount', 'depositAmount', 'lateFee', 'rentDueDay', 'status', 'createdAt'],
            array_keys($res->json()[0])
        );
    }

    public function test_expand_returns_withrefs_envelopes(): void
    {
        Agreement::factory()->create(['unit_id' => $this->unit->id]);
        $res = $this->getJson('/api/agreements?expand=unit,property,tenant')->assertOk();
        $row = $res->json()[0];
        $this->assertSame(['agreement', 'unit', 'property', 'tenant'], array_keys($row));
        $this->assertSame($this->unit->id, $row['unit']['id']);
        $this->assertArrayHasKey('coOwners', $row['property']);
        $this->assertArrayHasKey('status', $row['tenant']);
    }

    public function test_store_accepts_camel_case_input(): void
    {
        $tenant = User::factory()->tenant()->create();
        $res = $this->postJson('/api/agreements', [
            'unitId' => $this->unit->id, 'tenantId' => $tenant->id,
            'startDate' => '2026-08-01', 'endDate' => '2027-07-31',
            'rentAmount' => 180000, 'depositAmount' => 360000, 'lateFee' => 5000,
            'rentDueDay' => 1, 'status' => 'active',
        ])->assertCreated();
        $this->assertSame(180000, $res->json('rentAmount'));
        $this->assertSame('2026-08-01', $res->json('startDate'));
    }

    public function test_store_rejects_unit_of_other_owner(): void
    {
        $foreignUnit = Unit::factory()->create();
        $this->postJson('/api/agreements', [
            'unitId' => $foreignUnit->id, 'tenantId' => User::factory()->tenant()->create()->id,
            'startDate' => '2026-08-01', 'endDate' => '2027-07-31',
            'rentAmount' => 180000, 'depositAmount' => 360000, 'lateFee' => 0,
            'rentDueDay' => 1, 'status' => 'draft',
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify fail** — `php artisan test --filter=AgreementContractTest`.

- [ ] **Step 3: Implement**

`StoreAgreementRequest`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unitId'        => 'required|uuid|exists:units,id',
            'tenantId'      => 'required|uuid|exists:users,id',
            'startDate'     => 'required|date',
            'endDate'       => 'required|date|after:startDate',
            'rentAmount'    => 'required|integer|min:1',
            'depositAmount' => 'required|integer|min:0',
            'lateFee'       => 'nullable|integer|min:0',
            'rentDueDay'    => 'required|integer|min:1|max:28',
            'status'        => 'nullable|in:draft,active,expired,terminated',
        ];
    }

    public function toModelAttributes(): array
    {
        $v = $this->validated();
        $map = [
            'unitId'        => 'unit_id',
            'tenantId'      => 'tenant_id',
            'startDate'     => 'start_date',
            'endDate'       => 'end_date',
            'rentAmount'    => 'rent_amount_cents',
            'depositAmount' => 'deposit_amount_cents',
            'lateFee'       => 'late_fee_cents',
            'rentDueDay'    => 'rent_due_day',
        ];
        $out = [];
        foreach ($v as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }
        return $out;
    }
}
```
`UpdateAgreementRequest`: same map, all rules `sometimes`/`nullable`, no `unitId`/`tenantId` (frontend `AgreementUpdate` allows them, but changing parties post-creation is owner-error-prone; still include both as `sometimes|uuid|exists:…` for contract fidelity).

`AgreementWithRefsResource` (expects `unit.property.coOwners` + `tenant` eager-loaded):
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Envelope matching frontend AgreementWithRefs: {agreement, unit, property, tenant}. */
class AgreementWithRefsResource extends JsonResource
{
    public function toArray($request): array
    {
        $unit     = $this->unit;
        $property = $unit?->property;

        return [
            'agreement' => new AgreementResource($this->resource),
            'unit'      => $unit ? new UnitResource($unit) : null,
            'property'  => $property ? new PropertyResource($property) : null,
            'tenant'    => $this->tenant ? new TenantResource($this->tenant) : null,
        ];
    }
}
```

`AgreementController` rewrite:
- `index(Request $request)`: base query as today; if `$request->filled('expand')` eager-load `['unit.property.coOwners', 'tenant']` and return `AgreementWithRefsResource::collection(...)`, else return `AgreementResource::collection(...)` (no eager loads needed).
- `store(StoreAgreementRequest $request)`: ownership check on the unit (`abort_if($unit->property->owner_id !== …, 403)`), create from `toModelAttributes()`, return `(new AgreementResource($agreement))->response()->setStatusCode(201)`.
- `show`: keep `authorizeOwner`, return `new AgreementResource($agreement)`.
- `update(UpdateAgreementRequest …)`: `authorizeOwner`, update from `toModelAttributes()`, return `AgreementResource`.
- `destroy`: unchanged behavior, 204.

- [ ] **Step 4: Run tests** — filter, then full suite → PASS.

---

### Task 9: Invoices + payments contract

**Files:**
- Create: `backend/app/Http/Requests/RecordPaymentRequest.php`, `UpdateInvoiceStatusRequest.php`
- Create: `backend/app/Http/Resources/InvoiceWithRefsResource.php`
- Modify: `backend/app/Http/Controllers/Api/Owner/InvoiceController.php`
- Test: `backend/tests/Feature/InvoiceContractTest.php`

**Interfaces:**
- Consumes: base Resources (Task 3); `AgreementWithRefs` pattern (Task 8).
- Produces: `GET /api/invoices` → `Invoice[]`; `?expand=agreement,unit,property,tenant,payments` → `[{invoice, agreement|null, unit|null, property|null, tenant|null, payments: Payment[]}]`; `PATCH /api/invoices/{id}/status` `{status}` → `Invoice`; `POST /api/invoices/{id}/payments` (camelCase `PaymentInput`) → 201 `{payment: Payment, invoice: Invoice}`; `POST /api/invoices/{id}/send` → `{sentAt}`. `InvoiceWithRefsResource` reused by Task 11 (`/me/invoices`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $property->id, 'user_id' => $this->owner->id]);
        $unit = Unit::factory()->create(['property_id' => $property->id]);
        $agreement = Agreement::factory()->create(['unit_id' => $unit->id]);
        $this->invoice = Invoice::factory()->create(['agreement_id' => $agreement->id]);
        Sanctum::actingAs($this->owner);
    }

    public function test_expand_returns_invoice_envelopes(): void
    {
        Payment::factory()->create(['invoice_id' => $this->invoice->id]);
        $res = $this->getJson('/api/invoices?expand=agreement,unit,property,tenant,payments')->assertOk();
        $row = $res->json()[0];
        $this->assertSame(['invoice', 'agreement', 'unit', 'property', 'tenant', 'payments'], array_keys($row));
        $this->assertSame(180000, $row['invoice']['amount']);
        $this->assertSame(180000, $row['payments'][0]['amount']);
        $this->assertSame(['id', 'invoiceId', 'amount', 'method', 'status', 'paidAt', 'reference', 'createdAt'], array_keys($row['payments'][0]));
    }

    public function test_record_payment_accepts_camel_case_and_marks_paid(): void
    {
        $res = $this->postJson("/api/invoices/{$this->invoice->id}/payments", [
            'invoiceId' => $this->invoice->id, // sent by frontend, path param wins
            'amount'    => 185000,
            'method'    => 'transfer',
            'paidAt'    => '2026-07-15T10:00:00.000Z',
            'reference' => 'MBB-123',
        ])->assertCreated();

        $this->assertSame(['payment', 'invoice'], array_keys($res->json()));
        $this->assertSame(185000, $res->json('payment.amount'));
        $this->assertSame('paid', $res->json('invoice.status'));
    }

    public function test_update_status(): void
    {
        $this->patchJson("/api/invoices/{$this->invoice->id}/status", ['status' => 'overdue'])
            ->assertOk()->assertJsonPath('status', 'overdue');
    }

    public function test_send_returns_sent_at(): void
    {
        $res = $this->postJson("/api/invoices/{$this->invoice->id}/send")->assertOk();
        $this->assertSame(['sentAt'], array_keys($res->json()));
    }
}
```

- [ ] **Step 2: Run to verify fail** — `php artisan test --filter=InvoiceContractTest`.

- [ ] **Step 3: Implement**

`RecordPaymentRequest` rules: `amount required|integer|min:1`, `method required|in:fpx,card,cash,transfer`, `paidAt required|date`, `reference nullable|string|max:255` (ignore any `invoiceId` in body). `toModelAttributes()` maps `amount→amount_cents`, `paidAt→paid_at`, passes `method`/`reference`.

`UpdateInvoiceStatusRequest` rules: `status required|in:pending,paid,overdue,cancelled`.

`InvoiceWithRefsResource` (expects `agreement.unit.property.coOwners`, `agreement.tenant`, `payments` loaded):
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Envelope matching frontend InvoiceWithRefs. */
class InvoiceWithRefsResource extends JsonResource
{
    public function toArray($request): array
    {
        $agreement = $this->agreement;
        $unit      = $agreement?->unit;
        $property  = $unit?->property;

        return [
            'invoice'   => new InvoiceResource($this->resource),
            'agreement' => $agreement ? new AgreementResource($agreement) : null,
            'unit'      => $unit ? new UnitResource($unit) : null,
            'property'  => $property ? new PropertyResource($property) : null,
            'tenant'    => $agreement?->tenant ? new TenantResource($agreement->tenant) : null,
            'payments'  => PaymentResource::collection($this->payments),
        ];
    }
}
```

`InvoiceController` rewrite:
- `index`: keep owner scoping + `status/year/month` filters; if `expand` present eager-load `['agreement.unit.property.coOwners', 'agreement.tenant', 'payments']` → `InvoiceWithRefsResource::collection`, else `InvoiceResource::collection` (no loads).
- `show`: `InvoiceResource` (or envelope when `expand` present — mirror index).
- `updateStatus(UpdateInvoiceStatusRequest …)`: update, return `new InvoiceResource($invoice)`.
- `send`: return `response()->json(['sentAt' => now()->toISOString()])`.
- `recordPayment(RecordPaymentRequest …)`: create Payment (`status => PaymentStatus::SUCCESSFUL`, `invoice_id` from route), mark invoice paid, return:
```php
        return response()->json([
            'payment' => (new PaymentResource($payment))->resolve(),
            'invoice' => (new InvoiceResource($invoice->fresh()))->resolve(),
        ], 201);
```

- [ ] **Step 4: Run tests** — filter, then full suite → PASS.

---

### Task 10: Tickets + comments contract

**Files:**
- Create: `backend/app/Http/Requests/StoreTicketRequest.php`, `UpdateTicketStatusRequest.php`, `StoreTicketCommentRequest.php`
- Create: `backend/app/Http/Resources/TicketWithRefsResource.php`
- Modify: `backend/app/Http/Controllers/Api/Owner/TicketController.php`, `TicketCommentController.php`
- Test: `backend/tests/Feature/TicketContractTest.php`

**Interfaces:**
- Consumes: base Resources (Task 3).
- Produces: `GET /api/tickets` → `Ticket[]`; `?expand=unit,property,reporter,comments` → `[{ticket, unit|null, property|null, reporter|null, comments: TicketComment[]}]` (comments ascending by `created_at`; `reporter` null when `reporterRole==='owner'`); `GET /api/tickets/{id}?expand=…` → single envelope; `POST /api/tickets` camelCase `TicketInput`; `PATCH /api/tickets/{id}/status` with transition validation; `POST /api/tickets/{id}/comments` `{body}` → 201 `TicketComment`. `TicketWithRefsResource` reused by Task 11.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $property->id, 'user_id' => $this->owner->id]);
        $this->unit = Unit::factory()->create(['property_id' => $property->id]);
        Sanctum::actingAs($this->owner);
    }

    public function test_expand_returns_ticket_envelopes_with_sorted_comments(): void
    {
        $ticket = Ticket::factory()->create(['unit_id' => $this->unit->id]);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'created_at' => '2026-07-02']);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'created_at' => '2026-07-01']);

        $res = $this->getJson('/api/tickets?expand=unit,property,reporter,comments')->assertOk();
        $row = $res->json()[0];
        $this->assertSame(['ticket', 'unit', 'property', 'reporter', 'comments'], array_keys($row));
        $this->assertNotNull($row['reporter']); // factory reporterRole=tenant
        $this->assertTrue($row['comments'][0]['createdAt'] < $row['comments'][1]['createdAt']);
        $this->assertSame(['id', 'ticketId', 'authorId', 'authorRole', 'body', 'createdAt'], array_keys($row['comments'][0]));
    }

    public function test_owner_reported_ticket_has_null_reporter_in_envelope(): void
    {
        Ticket::factory()->create([
            'unit_id' => $this->unit->id,
            'reporter_id' => $this->owner->id, 'reporter_role' => 'owner',
        ]);
        $res = $this->getJson('/api/tickets?expand=unit,property,reporter,comments')->assertOk();
        $this->assertNull($res->json('0.reporter'));
    }

    public function test_store_accepts_camel_case_input(): void
    {
        $res = $this->postJson('/api/tickets', [
            'unitId' => $this->unit->id, 'category' => 'plumbing', 'priority' => 'high',
            'title' => 'Leaking sink', 'description' => 'Kitchen sink leaks.',
            'reporterId' => 'ignored', 'reporterRole' => 'owner',
        ])->assertCreated();
        $this->assertSame('owner', $res->json('reporterRole'));
        $this->assertSame($this->owner->id, $res->json('reporterId')); // server-derived
    }

    public function test_status_transition_validation(): void
    {
        $ticket = Ticket::factory()->create(['unit_id' => $this->unit->id, 'status' => 'new']);
        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'reopened'])->assertStatus(422);
        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertOk()->assertJsonPath('status', 'resolved');
        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_owner_comment(): void
    {
        $ticket = Ticket::factory()->create(['unit_id' => $this->unit->id]);
        $res = $this->postJson("/api/tickets/{$ticket->id}/comments", ['body' => 'On it.'])->assertCreated();
        $this->assertSame('owner', $res->json('authorRole'));
    }
}
```

- [ ] **Step 2: Run to verify fail** — `php artisan test --filter=TicketContractTest`.

- [ ] **Step 3: Implement**

`StoreTicketRequest` rules: `unitId required|uuid|exists:units,id`, `category required|in:plumbing,electrical,appliance,structural,pest,other`, `priority required|in:low,medium,high,urgent`, `title required|string|max:100`, `description required|string`. (`reporterId`/`reporterRole` from the frontend body are ignored — server derives them.) `toModelAttributes()` maps `unitId→unit_id`, passes the rest.

`UpdateTicketStatusRequest` rules: `status required|in:new,in_progress,resolved,reopened`.

`StoreTicketCommentRequest` rules: `body required|string`. (`ticketId`/`authorId`/`authorRole` in body ignored.)

`TicketWithRefsResource` (expects `unit.property.coOwners`, `reporter`, `comments` loaded):
```php
<?php

namespace App\Http\Resources;

use App\Enums\ReporterRole;
use Illuminate\Http\Resources\Json\JsonResource;

/** Envelope matching frontend TicketWithRefs. Reporter is null for owner-reported tickets. */
class TicketWithRefsResource extends JsonResource
{
    public function toArray($request): array
    {
        $unit     = $this->unit;
        $property = $unit?->property;
        $isTenantReporter = $this->reporter_role === ReporterRole::TENANT;

        return [
            'ticket'   => new TicketResource($this->resource),
            'unit'     => $unit ? new UnitResource($unit) : null,
            'property' => $property ? new PropertyResource($property) : null,
            'reporter' => $isTenantReporter && $this->reporter ? new TenantResource($this->reporter) : null,
            'comments' => TicketCommentResource::collection(
                $this->comments->sortBy('created_at')->values()
            ),
        ];
    }
}
```

`TicketController` rewrite:
- `index`: owner scope as today; `expand` present → eager-load `['unit.property.coOwners', 'reporter', 'comments']`, return `TicketWithRefsResource::collection`; else `TicketResource::collection`.
- `show`: same expand branch for the single envelope.
- `store(StoreTicketRequest …)`: unit ownership check, create with `reporter_id => $request->user()->id`, `reporter_role => ReporterRole::OWNER`, return `TicketResource` 201.
- `update`: swap validation into camelCase rules inline or keep fields (category/priority/title/description are already casing-identical) — return `TicketResource`.
- `updateStatus(UpdateTicketStatusRequest …)`: keep `canTransitionTo` logic + `resolved_at` stamping, return `TicketResource`.
- `TicketCommentController::store(StoreTicketCommentRequest …)`: same behavior, return `(new TicketCommentResource($comment))->response()->setStatusCode(201)`.

- [ ] **Step 4: Run tests** — filter, then full suite → PASS.

---

### Task 11: Tenant `/me/*` endpoints

**Files:**
- Create: `backend/app/Http/Requests/UpdateTenantProfileRequest.php`
- Modify: `backend/app/Http/Controllers/Api/Tenant/TenantAgreementController.php`, `TenantInvoiceController.php`, `TenantTicketController.php`, `TenantProfileController.php`
- Test: `backend/tests/Feature/TenantShellContractTest.php`

**Interfaces:**
- Consumes: `AgreementWithRefsResource` (8), `InvoiceWithRefsResource` (9), `TicketWithRefsResource` (10), `TenantResource`/`TicketResource`/`TicketCommentResource` (3).
- Produces: `GET /api/me/agreement?expand=…` → envelope or `null` (200); `GET /api/me/invoices?expand=…` → envelopes; `POST /api/me/invoices/{id}/pay` → `{payment, invoice}`; `GET /api/me/tickets?expand=…` / `GET /api/me/tickets/{id}` / `POST /api/me/tickets` (camelCase `TicketInput`, unit derived from active agreement) / `POST /api/me/tickets/{id}/comments`; `GET|PATCH /api/me/profile` → Tenant-profile shape `{id, name, email, phone, personal, emergencyContact}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantShellContractTest extends TestCase
{
    use RefreshDatabase;

    private User $tenant;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->owner()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $property->id, 'user_id' => $owner->id]);
        $this->unit = Unit::factory()->create(['property_id' => $property->id]);
        $this->tenant = User::factory()->tenant()->create();
        Sanctum::actingAs($this->tenant);
    }

    public function test_me_agreement_prefers_active_and_returns_envelope(): void
    {
        Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id, 'status' => 'expired', 'start_date' => '2025-01-01']);
        $active = Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id, 'status' => 'active']);

        $res = $this->getJson('/api/me/agreement?expand=unit,property,tenant')->assertOk();
        $this->assertSame(['agreement', 'unit', 'property', 'tenant'], array_keys($res->json()));
        $this->assertSame($active->id, $res->json('agreement.id'));
    }

    public function test_me_agreement_falls_back_to_recent_non_draft_then_null(): void
    {
        // draft only → null (200, not 404)
        Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id, 'status' => 'draft']);
        $this->getJson('/api/me/agreement')->assertOk();
        $this->assertNull($this->getJson('/api/me/agreement')->json());

        $expired = Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id, 'status' => 'expired']);
        $this->assertSame($expired->id, $this->getJson('/api/me/agreement')->json('id'));
    }

    public function test_me_invoices_returns_envelopes_scoped_to_me(): void
    {
        $mine = Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id]);
        Invoice::factory()->create(['agreement_id' => $mine->id]);
        Invoice::factory()->create(); // someone else's

        $res = $this->getJson('/api/me/invoices?expand=agreement,unit,property,tenant,payments')->assertOk();
        $this->assertCount(1, $res->json());
        $this->assertSame(['invoice', 'agreement', 'unit', 'property', 'tenant', 'payments'], array_keys($res->json()[0]));
    }

    public function test_me_pay_creates_successful_payment(): void
    {
        $agreement = Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['agreement_id' => $agreement->id, 'late_fee_cents' => 5000]);

        $res = $this->postJson("/api/me/invoices/{$invoice->id}/pay", ['method' => 'fpx'])->assertCreated();
        $this->assertSame(185000, $res->json('payment.amount')); // amount + lateFee
        $this->assertSame('paid', $res->json('invoice.status'));
    }

    public function test_me_ticket_flow_derives_unit_from_active_agreement(): void
    {
        Agreement::factory()->create(['unit_id' => $this->unit->id, 'tenant_id' => $this->tenant->id, 'status' => 'active']);

        $res = $this->postJson('/api/me/tickets', [
            'category' => 'electrical', 'priority' => 'urgent',
            'title' => 'No power', 'description' => 'Whole unit down.',
            'unitId' => 'ignored', 'reporterId' => 'ignored', 'reporterRole' => 'tenant',
        ])->assertCreated();
        $this->assertSame($this->unit->id, $res->json('unitId'));
        $this->assertSame('tenant', $res->json('reporterRole'));

        $list = $this->getJson('/api/me/tickets?expand=unit,property,reporter,comments')->assertOk();
        $this->assertSame(['ticket', 'unit', 'property', 'reporter', 'comments'], array_keys($list->json()[0]));
    }

    public function test_me_profile_roundtrip_camel_case(): void
    {
        $res = $this->getJson('/api/me/profile')->assertOk();
        $this->assertSame(['id', 'name', 'email', 'phone', 'personal', 'emergencyContact'], array_keys($res->json()));

        $patch = $this->patchJson('/api/me/profile', [
            'personal'         => ['icNumber' => '880314-14-5687', 'occupation' => 'Engineer'],
            'emergencyContact' => ['name' => 'Ali', 'phone' => '+60 13', 'relationship' => 'Brother'],
        ])->assertOk();
        $this->assertSame('Engineer', $patch->json('personal.occupation'));
        $this->assertSame('Ali', $this->tenant->fresh()->emergency_contact['name']);
    }
}
```

- [ ] **Step 2: Run to verify fail** — `php artisan test --filter=TenantShellContractTest`.

- [ ] **Step 3: Implement**

`TenantAgreementController::show` — selection rule + envelope/null:
```php
    public function show(Request $request)
    {
        $base = Agreement::where('tenant_id', $request->user()->id);

        $agreement = (clone $base)->where('status', 'active')->latest()->first()
            ?? (clone $base)->where('status', '!=', 'draft')->orderByDesc('start_date')->first();

        if (! $agreement) {
            return response()->json(null);
        }

        if ($request->filled('expand')) {
            $agreement->load(['unit.property.coOwners', 'tenant']);

            return new AgreementWithRefsResource($agreement);
        }

        return new AgreementResource($agreement);
    }
```

`TenantInvoiceController` — `index`: keep tenant scoping; `expand` branch → eager-load `['agreement.unit.property.coOwners', 'agreement.tenant', 'payments']` → `InvoiceWithRefsResource::collection`; else `InvoiceResource::collection`. `pay`: keep logic, respond with resolved `PaymentResource`/`InvoiceResource` in the `{payment, invoice}` envelope (201), exactly like Task 9's `recordPayment`.

`TenantTicketController` — `index`/`show`: add `expand` branch → `TicketWithRefsResource` (eager-load `['unit.property.coOwners', 'reporter', 'comments']`); `store`: validate via a camelCase inline `$request->validate(['category' => …, 'priority' => …, 'title' => …, 'description' => …])` (same rules as `StoreTicketRequest` minus `unitId`), derive unit from active agreement as today, return `TicketResource` 201; `addComment`: `StoreTicketCommentRequest`, return `TicketCommentResource` 201.

`UpdateTenantProfileRequest`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'sometimes|string|max:255',
            'phone'            => 'sometimes|string|max:30',
            'personal'         => 'nullable|array',   // camelCase interior stored verbatim
            'emergencyContact' => 'nullable|array',
        ];
    }

    public function toModelAttributes(): array
    {
        $v = $this->validated();
        $map = ['personal' => 'personal_info', 'emergencyContact' => 'emergency_contact'];
        $out = [];
        foreach ($v as $key => $value) {
            $out[$map[$key] ?? $key] = $value;
        }
        return $out;
    }
}
```

`TenantProfileController` — `show` returns:
```php
        return response()->json([
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'phone'            => $user->phone,
            'personal'         => $user->personal_info,
            'emergencyContact' => $user->emergency_contact,
        ]);
```
`update(UpdateTenantProfileRequest $request)` → `$request->user()->update($request->toModelAttributes()); return $this->show($request);`

- [ ] **Step 4: Run tests** — filter, then full suite → PASS.

---

### Task 12: Owner account + plans contract

**Files:**
- Create: `backend/app/Http/Resources/OwnerAccountResource.php`
- Create: `backend/app/Http/Requests/UpdateAccountProfileRequest.php`, `UpdateAccountPreferencesRequest.php`, `UpdateAccountNotificationsRequest.php`
- Modify: `backend/app/Http/Controllers/Api/Owner/AccountController.php`
- Test: `backend/tests/Feature/AccountContractTest.php`

**Interfaces:**
- Consumes: `users` JSON prefs columns.
- Produces: `GET /api/account` and all three `PATCH /api/account/*` → `OwnerAccount` = `{profile: {id, name, email, phone, photoUrl, businessName, bankAccountLast4}, preferences: {locale, theme, moneyLocale}, notifications: {events, channels}, planTier}`; `GET /api/plans` → `[{tier, priceRm, unitsCap, description}]`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->owner()->create());
    }

    public function test_show_returns_owner_account_envelope_with_defaults(): void
    {
        $res = $this->getJson('/api/account')->assertOk();
        $this->assertSame(['profile', 'preferences', 'notifications', 'planTier'], array_keys($res->json()));
        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'photoUrl', 'businessName', 'bankAccountLast4'],
            array_keys($res->json('profile'))
        );
        $this->assertSame(['locale' => 'en', 'theme' => 'system', 'moneyLocale' => 'en-MY'], $res->json('preferences'));
        $this->assertSame('free', $res->json('planTier'));
    }

    public function test_patch_profile_returns_full_envelope(): void
    {
        $res = $this->patchJson('/api/account/profile', ['businessName' => 'Roofly Homes'])->assertOk();
        $this->assertSame('Roofly Homes', $res->json('profile.businessName'));
        $this->assertSame(['profile', 'preferences', 'notifications', 'planTier'], array_keys($res->json()));
    }

    public function test_patch_preferences_merges(): void
    {
        $this->patchJson('/api/account/preferences', ['theme' => 'dark'])->assertOk();
        $res = $this->getJson('/api/account');
        $this->assertSame('dark', $res->json('preferences.theme'));
        $this->assertSame('en', $res->json('preferences.locale')); // untouched default kept
    }

    public function test_plans_camel_case(): void
    {
        $res = $this->getJson('/api/plans')->assertOk();
        $this->assertSame(['tier', 'priceRm', 'unitsCap', 'description'], array_keys($res->json()[0]));
        $this->assertSame('unlimited', $res->json('3.unitsCap'));
    }
}
```

- [ ] **Step 2: Run to verify fail** — `php artisan test --filter=AccountContractTest`.

- [ ] **Step 3: Implement**

`OwnerAccountResource` (wraps a `User`):
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Frontend OwnerAccount envelope — see frontend/app/types/owner.ts. */
class OwnerAccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'profile' => [
                'id'               => $this->id,
                'name'             => $this->name,
                'email'            => $this->email,
                'phone'            => $this->phone,
                'photoUrl'         => null, // Phase 4 — file storage
                'businessName'     => $this->business_name,
                'bankAccountLast4' => $this->bank_account_last4,
            ],
            'preferences' => $this->owner_preferences
                ?? ['locale' => 'en', 'theme' => 'system', 'moneyLocale' => 'en-MY'],
            'notifications' => $this->notification_preferences
                ?? [
                    'events'   => ['rent_reminder' => true, 'agreement_expiry' => true, 'payment_received' => true, 'ticket_update' => true, 'invite_accepted' => true],
                    'channels' => ['email' => true, 'whatsapp' => false, 'in_app' => true],
                ],
            'planTier' => $this->plan_tier,
        ];
    }
}
```

Requests: `UpdateAccountProfileRequest` (`name sometimes|string|max:255`, `phone sometimes|string|max:30`, `businessName nullable|string|max:255`; map `businessName→business_name`). `UpdateAccountPreferencesRequest` (`locale sometimes|in:en,ms`, `theme sometimes|in:light,dark,system`, `moneyLocale sometimes|in:en-MY`) — merged into `owner_preferences` blob camelCase verbatim. `UpdateAccountNotificationsRequest` (`events sometimes|array`, `channels sometimes|array`) — merged into `notification_preferences` verbatim.

`AccountController` rewrite: all four user-facing methods end with `return new OwnerAccountResource($request->user()->fresh());`. `updatePreferences`/`updateNotifications` merge the validated camelCase payload over the current blob (`array_merge`). `plans()` returns the same four tiers with camelCase keys `tier, priceRm, unitsCap, description`.

- [ ] **Step 4: Run tests** — filter, then full suite → PASS.

---

### Task 13: DemoSeeder mirroring frontend mocks

**Files:**
- Create: `backend/database/seeders/DemoSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Test: `backend/tests/Feature/DemoSeederTest.php`

**Interfaces:**
- Consumes: all models + Task 2 columns.
- Produces: `php artisan db:seed` builds the demo world content-identical to `frontend/app/mocks/*.ts`.

- [ ] **Step 1: Read the source mock data** (this is a data-port, not new design):

Read every file: `frontend/app/mocks/owner.ts`, `properties.ts` (185 lines), `units.ts`, `tenants.ts`, `agreements.ts`, `invoices.ts` (invoices + payments), `tickets.ts` (tickets + comments). Port **every record** with its exact field values (names, amounts in sen, dates, statuses, blob contents — blob keys stay camelCase). Mock ids (`p-1`, `t-aminah`) are not UUIDs — replace with fixed deterministic UUIDs defined as class constants so cross-references stay stable:

```php
    private const OWNER_ID     = '00000000-0000-4000-8000-000000000001';
    private const TENANT_AMINAH = '00000000-0000-4000-8000-000000000101';
    // …one constant per mock id, grouped by entity (properties 2xx, units 3xx,
    // agreements 4xx, invoices 5xx, payments 6xx, tickets 7xx, comments 8xx)
```

- [ ] **Step 2: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_world_matches_mock_counts_and_anchors(): void
    {
        $this->seed(DemoSeeder::class);

        // Counts must equal the number of records in each frontend mock file —
        // update these numbers from the actual mocks while porting.
        $this->assertSame(1, User::where('role', 'owner')->count());
        $this->assertTrue(User::where('email', 'like', '%aminah%')->orWhere('name', 'like', '%Aminah%')->exists());
        $this->assertGreaterThan(0, Property::count());
        $this->assertGreaterThan(0, Unit::count());
        $this->assertGreaterThan(0, Agreement::count());
        $this->assertGreaterThan(0, Invoice::count());
        $this->assertGreaterThan(0, Ticket::count());

        // Anchor: Aminah has an active agreement (tenant shell depends on it)
        $aminah = User::where('name', 'like', '%Aminah%')->where('role', 'tenant')->first();
        $this->assertNotNull($aminah);
        $this->assertTrue(Agreement::where('tenant_id', $aminah->id)->where('status', 'active')->exists());

        // Anchor: a notice_given tenant exists (dashboard needs-attention feed)
        $this->assertTrue(User::where('status', 'notice_given')->exists());

        // Idempotent-ish: reseeding must not crash on unique constraints
        $this->seed(DemoSeeder::class);
    }
}
```

After porting, **replace the `assertGreaterThan(0, …)` lines with exact counts** from the mock files (e.g. if `propertiesMock` has 4 entries, `assertSame(4, Property::count())`).

- [ ] **Step 3: Run to verify fail**, then implement `DemoSeeder`:

Structure: `run()` wraps everything in `DB::transaction`; use `Model::updateOrCreate(['id' => self::CONST], [...])` per record so reseeding is idempotent. Owner password: `Hash::make('password')`. Tenants: `status` from mock, `invited_by => self::OWNER_ID`, `personal_info`/`emergency_contact` blobs camelCase verbatim from the mocks. Example property record (port the real values from `properties.ts`):

```php
        Property::updateOrCreate(['id' => self::PROP_1], [
            'owner_id' => self::OWNER_ID,
            'name'     => '…exact name from propertiesMock[0]…',
            'type'     => '…', 'address' => '…', 'city' => '…',
            'state'    => '…', 'postcode' => '…',
            'ownership' => [ /* exact camelCase blob from the mock */ ],
            'utilities' => [ /* exact camelCase blob from the mock */ ],
        ]);
```

`DatabaseSeeder::run()` → `$this->call(DemoSeeder::class);`

- [ ] **Step 4: Run tests** — `php artisan test --filter=DemoSeederTest` → PASS (with exact counts filled in); full suite → PASS.

---

### Task 14: Full verification sweep

**Files:** none new.

- [ ] **Step 1: Full test suite**

Run: `php artisan test`
Expected: ALL PASS. Record the count.

- [ ] **Step 2: Route table sanity**

Run: `php artisan route:list --path=api`
Expected: every path called by `frontend/app/services/*.ts` exists: `properties` CRUD + `co-owners` sync, `units` flat + nested, `tenants` + `tenants/invite`, `agreements` CRUD, `invoices` + `status|send|payments`, `tickets` + `status|comments`, `me/agreement`, `me/invoices(+pay)`, `me/tickets(+comments)`, `me/profile`, `account/*`, `plans`, `auth/*`. Cross-check against the grep list in the spec.

- [ ] **Step 3: Static check**

Run: `./vendor/bin/pint --test || true` (if pint installed; informational only — do not reformat unrelated files).

- [ ] **Step 4: Report** — summarize to the user: tests green, route map complete, NO commits made; list every created/modified file for their review.
