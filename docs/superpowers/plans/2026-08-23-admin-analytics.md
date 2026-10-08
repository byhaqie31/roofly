# Admin analytics — implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** First-party analytics for roofly.my — five whitelisted events from the public/marketing pages into our DB, and an `/admin/analytics` page (tiles, charts, funnel, top pages/referrers, actionable leads list with per-lead event drawer) in both adapters.

**Architecture:** Frontend `useTrack()` beacons `POST /api/track`; `AnalyticsRecorder` writes `analytics_events` and upserts `leads`; `RegisterController` links registrations by email. Admin reads via `/api/admin/analytics/*` behind a new `analytics.view` permission. Frontend follows the adapter split: contract → demo adapter (seeded PRNG data) → API adapter → `useAdminAnalytics()` selector; page reuses `StatTile`, `MiniAreaChart` (`format` prop), `DataTableShell`, `NoAccess`.

**Tech Stack:** Laravel 13 + Sanctum + Spatie Permission/ActivityLog (PHPUnit, sqlite) · Nuxt 4 + Vue 3 + Reka UI + TanStack Table + vue-i18n (admin is English-only).

**Spec:** [docs/superpowers/specs/2026-08-23-admin-analytics-design.md](../specs/2026-08-23-admin-analytics-design.md) — binding. Foundation it builds on: [2026-08-23-admin-backoffice-foundation-design.md](../specs/2026-08-23-admin-backoffice-foundation-design.md).

## Global constraints

- **No git commits or pushes in any task** (user commits). No Playwright. Gates: `docker exec roofly-backend php artisan test` green; `docker exec roofly-frontend npm run typecheck` = exactly the 5 known errors (`InvoiceViewModal.vue` Tone, `payments.vue` ×2, `Icon.vue`, `EmptyState.vue`), 0 new; greps: no `useApi` under `frontend/app/demo/`, no `~/demo` under `frontend/app/services/api/`, no `if (useMock`, no `formatRM|MoneyDisplay|useMoney` under `pages/admin`/`components/admin`, **no `useTrack`/`track(` under `pages/owner`, `pages/tenant`, `pages/admin`**.
- Events are exactly: `page_view`, `demo_enter`, `demo_feedback_click`, `waitlist_signup`, `register`. Tracking never runs inside `/owner/**`, `/tenant/**`, `/admin/**`; it is a no-op in demo (`useEnv().useMock`) and when `NUXT_PUBLIC_TRACKING=false`.
- Privacy: store hashed IP (`sha256(ip . APP_KEY)`), user agent ≤ 255, props ≤ 2 KB; never emit `ip_hash`/`user_agent` from any Resource. Prune events > 13 months.
- New permission `analytics.view` is the 14th key and **in the Operations preset** (preset becomes 8 keys). New audit action `analytics.exported`.
- Admin UI: English-only (`en.json` only), sentence case, no money, `NoAccess` card when permission missing, list-page patterns from UI-STANDARDS § 11.15.
- Backend JSON camelCase; list envelope `{data, meta{page,perPage,total,lastPage}}`; routes use `'can:' . $P::CONST` concatenation.
- Vue gotcha: never reference a local variable inside a `defineProps` default (SFC compiler hoists `defineProps`).

## File map

**Backend create:** `database/migrations/2026_08_24_000001_create_analytics_events_table.php`, `…000002_create_leads_table.php`; `app/Models/{AnalyticsEvent,Lead}.php`; `database/factories/{AnalyticsEventFactory,LeadFactory}.php`; `app/Services/AnalyticsRecorder.php`; `app/Http/Requests/TrackRequest.php`, `app/Http/Requests/Admin/AnalyticsRangeRequest.php`; `app/Http/Controllers/Api/TrackController.php`, `app/Http/Controllers/Api/Admin/AnalyticsController.php`; `app/Http/Resources/Admin/{AdminLeadResource,LeadEventResource}.php`; `app/Console/Commands/PruneAnalyticsEvents.php`; `database/seeders/AnalyticsDemoSeeder.php`; `tests/Feature/Analytics/{AnalyticsModelsTest,AnalyticsRecorderTest,TrackTest,AdminAnalyticsOverviewTest,AdminLeadsTest,PruneAnalyticsTest}.php`.
**Backend modify:** `app/Support/AdminPermissions.php`, `app/Services/AuditLogger.php`, `app/Providers/AppServiceProvider.php` (rate limiter), `app/Http/Controllers/Api/Auth/RegisterController.php`, `routes/api.php`, `routes/console.php`, `database/seeders/DemoSeeder.php`, `tests/Feature/Admin/AdminPermissionTest.php` (13→14), `tests/Feature/DemoSeederTest.php` (13→14).
**Frontend create:** `app/types/analytics.ts`; `app/composables/useTrack.ts`; `app/demo/track.ts`; `app/services/api/track.ts`; `app/plugins/track.client.ts`; `app/services/contracts/admin/analytics.ts`; `app/demo/data/analytics.ts`; `app/demo/services/admin/analytics.ts`; `app/services/api/admin/analytics.ts`; `app/services/useAdminAnalytics.ts`; `app/components/admin/{FunnelStrip,SourcePill,EventList,LeadDrawer}.vue`; `app/pages/admin/analytics.vue`.
**Frontend modify:** `app/types/admin.ts` (permission + audit action), `app/demo/auth.ts` (`DEMO_OPS_PRESET`), `app/composables/useEnv.ts` (`trackingEnabled`), `nuxt.config.ts` (`tracking` runtime flag), `app/components/marketing/EmailCapture.vue`, `app/pages/demo/index.vue`, `app/components/auth/DemoLoginShortcuts.vue`, `app/components/demo/FloatingFeedback.vue`, `app/pages/auth/register.vue`, `app/components/admin/SidebarNav.vue`, `i18n/locales/en.json`, `.env.example`, `docker-compose.yml`, docs (`docs/backend/API-SPEC.md`, `docs/frontend/API-MAP.md`, `docs/frontend/UI-STANDARDS.md`, `.claude/CLAUDE.md`).

---

## Part A — Backend

### Task 1: Tables, models, factories

**Files:** create the two migrations, `AnalyticsEvent`, `Lead`, two factories; test `tests/Feature/Analytics/AnalyticsModelsTest.php`.

**Produces:**
- `AnalyticsEvent` (uuid, `$fillable`: visitor_id, event, path, referrer, utm, props, ip_hash, user_agent, created_at; casts utm/props array, created_at datetime; `const EVENTS = ['page_view','demo_enter','demo_feedback_click','waitlist_signup','register']`; no `updated_at` — `public $timestamps = false`).
- `Lead` (uuid, `$fillable`: email, visitor_id, source, first_seen_at, last_seen_at, converted_user_id; casts datetimes; `convertedUser(): BelongsTo`; `const SOURCES = ['waitlist','demo','register']`).
- Factories: `AnalyticsEvent::factory()->pageView()` / `->event(string $name)` / `->forVisitor(string $id)` / `->at(Carbon $t)`; `Lead::factory()->converted(User $u)`.

- [ ] **Step 1: Failing test**

```php
<?php
namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_whitelist_and_factory_states(): void
    {
        $this->assertSame(['page_view', 'demo_enter', 'demo_feedback_click', 'waitlist_signup', 'register'], AnalyticsEvent::EVENTS);
        $e = AnalyticsEvent::factory()->pageView()->forVisitor('11111111-1111-4111-8111-111111111111')->at(now()->subDay())->create(['path' => '/demo']);
        $this->assertSame('page_view', $e->event);
        $this->assertSame('/demo', $e->path);
        $this->assertTrue($e->created_at->isYesterday());
        $this->assertNull($e->updated_at ?? null);
    }

    public function test_lead_converts_to_user(): void
    {
        $owner = User::factory()->owner()->create();
        $lead = Lead::factory()->converted($owner)->create(['email' => $owner->email]);
        $this->assertSame($owner->id, $lead->convertedUser->id);
        $this->assertContains($lead->source, Lead::SOURCES);
    }
}
```

- [ ] **Step 2: Run** `docker exec roofly-backend php artisan test --filter AnalyticsModelsTest` → FAIL (class not found).

- [ ] **Step 3: Migrations**

```php
<?php // 2026_08_24_000001_create_analytics_events_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('visitor_id')->index();
            $table->string('event', 40);
            $table->string('path', 255)->nullable();
            $table->string('referrer', 255)->nullable();
            $table->json('utm')->nullable();
            $table->json('props')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['event', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('analytics_events'); }
};
```

```php
<?php // 2026_08_24_000002_create_leads_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 255)->unique();
            $table->uuid('visitor_id')->nullable()->index();
            $table->string('source', 20); // waitlist | demo | register
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->foreignUuid('converted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('leads'); }
};
```

- [ ] **Step 4: Models**

```php
<?php // app/Models/AnalyticsEvent.php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** One tracked event from a public/marketing page. Never exposes ip_hash/user_agent. */
class AnalyticsEvent extends Model
{
    use HasFactory, HasUuids;

    public const EVENTS = ['page_view', 'demo_enter', 'demo_feedback_click', 'waitlist_signup', 'register'];

    public $timestamps = false;

    protected $fillable = ['visitor_id', 'event', 'path', 'referrer', 'utm', 'props', 'ip_hash', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return ['utm' => 'array', 'props' => 'array', 'created_at' => 'datetime'];
    }
}
```

```php
<?php // app/Models/Lead.php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory, HasUuids;

    public const SOURCES = ['waitlist', 'demo', 'register'];

    protected $fillable = ['email', 'visitor_id', 'source', 'first_seen_at', 'last_seen_at', 'converted_user_id'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }
}
```

- [ ] **Step 5: Factories**

```php
<?php // database/factories/AnalyticsEventFactory.php
namespace Database\Factories;

use App\Models\AnalyticsEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** @extends Factory<AnalyticsEvent> */
class AnalyticsEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visitor_id' => (string) Str::uuid(),
            'event'      => 'page_view',
            'path'       => '/',
            'referrer'   => null,
            'utm'        => null,
            'props'      => null,
            'ip_hash'    => hash('sha256', '127.0.0.1'),
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
        ];
    }

    public function pageView(string $path = '/'): static { return $this->state(fn () => ['event' => 'page_view', 'path' => $path]); }
    public function event(string $name, array $props = []): static { return $this->state(fn () => ['event' => $name, 'props' => $props ?: null]); }
    public function forVisitor(string $visitorId): static { return $this->state(fn () => ['visitor_id' => $visitorId]); }
    public function at(Carbon $when): static { return $this->state(fn () => ['created_at' => $when]); }
}
```

```php
<?php // database/factories/LeadFactory.php
namespace Database\Factories;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Lead> */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email'         => fake()->unique()->safeEmail(),
            'visitor_id'    => (string) Str::uuid(),
            'source'        => 'waitlist',
            'first_seen_at' => now()->subDays(3),
            'last_seen_at'  => now(),
        ];
    }

    public function converted(User $user): static
    {
        return $this->state(fn () => ['converted_user_id' => $user->id, 'source' => 'register']);
    }
}
```

- [ ] **Step 6: Gate** `docker exec roofly-backend php artisan test` → green.

---

### Task 2: `analytics.view` permission + `analytics.exported` audit action

**Files:** modify `app/Support/AdminPermissions.php`, `app/Services/AuditLogger.php`, `tests/Feature/Admin/AdminPermissionTest.php`, `tests/Feature/DemoSeederTest.php`.

**Produces:** `AdminPermissions::ANALYTICS_VIEW = 'analytics.view'` (preset: true), inserted after `TENANTS_VIEW` in `ALL`; `AuditLogger::ANALYTICS_EXPORTED = 'analytics.exported'` added to `ACTIONS`.

- [ ] **Step 1: Update tests first.** In `AdminPermissionTest`: both `13` → `14`; add `$this->assertContains('analytics.view', AdminPermissions::operationsPreset());`. In `DemoSeederTest`: `13` → `14`; add `$this->assertTrue($ops->hasPermissionTo('analytics.view'));`.
- [ ] **Step 2: Run** `--filter "AdminPermissionTest|DemoSeederTest"` → FAIL on counts.
- [ ] **Step 3: Implement.** In `AdminPermissions`: `public const ANALYTICS_VIEW = 'analytics.view';` and in `ALL` after the `TENANTS_VIEW` row: `self::ANALYTICS_VIEW => ['preset' => true],`. In `AuditLogger`: `public const ANALYTICS_EXPORTED = 'analytics.exported';` and append to `ACTIONS`.
- [ ] **Step 4: Gate** full suite green (`DemoSeeder` reseeds the ops admin with `operationsPreset()`, so it picks the new key up automatically).

---

### Task 3: `AnalyticsRecorder`

**Files:** create `app/Services/AnalyticsRecorder.php`; test `tests/Feature/Analytics/AnalyticsRecorderTest.php`.

**Produces:**
```php
final class AnalyticsRecorder {
  public const MAX_PROPS_BYTES = 2048;
  /** @param array{visitorId:string,event:string,path?:?string,referrer?:?string,utm?:?array,props?:?array} $payload */
  public function record(array $payload, ?string $ip, ?string $userAgent): AnalyticsEvent;
  public function linkRegistration(User $user, ?string $visitorId = null): void;
  public static function hashIp(?string $ip): ?string;
}
```

- [ ] **Step 1: Failing test**

```php
<?php
namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use App\Services\AnalyticsRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsRecorderTest extends TestCase
{
    use RefreshDatabase;

    private const VID = '22222222-2222-4222-8222-222222222222';

    private function rec(): AnalyticsRecorder { return app(AnalyticsRecorder::class); }

    public function test_record_stores_event_with_hashed_ip_and_truncated_ua(): void
    {
        $e = $this->rec()->record(['visitorId' => self::VID, 'event' => 'page_view', 'path' => '/demo', 'referrer' => 'google.com', 'utm' => ['source' => 'fb']], '203.0.113.9', str_repeat('U', 400));
        $this->assertSame(hash('sha256', '203.0.113.9' . config('app.key')), $e->ip_hash);
        $this->assertSame(255, strlen($e->user_agent));
        $this->assertSame(['source' => 'fb'], $e->utm);
        $this->assertSame(0, Lead::count());
    }

    public function test_waitlist_signup_creates_lead_and_second_event_bumps_last_seen(): void
    {
        $this->travelTo(now()->subDays(2));
        $this->rec()->record(['visitorId' => self::VID, 'event' => 'waitlist_signup', 'props' => ['email' => 'Lead@Example.com']], null, null);
        $lead = Lead::first();
        $this->assertSame('lead@example.com', $lead->email);
        $this->assertSame('waitlist', $lead->source);
        $this->assertSame(self::VID, $lead->visitor_id);
        $first = $lead->first_seen_at;

        $this->travelBack();
        $this->rec()->record(['visitorId' => self::VID, 'event' => 'demo_enter', 'props' => ['role' => 'owner']], null, null);
        $lead->refresh();
        $this->assertTrue($lead->first_seen_at->equalTo($first));
        $this->assertTrue($lead->last_seen_at->gt($first));
        $this->assertSame(1, Lead::count());
    }

    public function test_register_event_converts_lead_by_email(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'new@owner.my']);
        Lead::factory()->create(['email' => 'new@owner.my', 'visitor_id' => self::VID]);
        $this->rec()->record(['visitorId' => self::VID, 'event' => 'register', 'props' => ['email' => 'new@owner.my', 'userId' => $owner->id]], null, null);
        $this->assertSame($owner->id, Lead::first()->converted_user_id);
        $this->assertSame('waitlist', Lead::first()->source, 'source is first-touch, not overwritten');
    }

    public function test_register_without_prior_lead_creates_converted_register_lead(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'fresh@owner.my']);
        $this->rec()->record(['visitorId' => self::VID, 'event' => 'register', 'props' => ['email' => 'fresh@owner.my', 'userId' => $owner->id]], null, null);
        $lead = Lead::first();
        $this->assertSame('register', $lead->source);
        $this->assertSame($owner->id, $lead->converted_user_id);
    }

    public function test_link_registration_by_email_with_different_visitor(): void
    {
        Lead::factory()->create(['email' => 'x@y.my', 'visitor_id' => '33333333-3333-4333-8333-333333333333']);
        $owner = User::factory()->owner()->create(['email' => 'x@y.my']);
        $this->rec()->linkRegistration($owner, null);
        $this->assertSame($owner->id, Lead::first()->converted_user_id);
        $this->assertSame(1, Lead::count());
    }

    public function test_record_rejects_unknown_event_and_oversized_props(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->rec()->record(['visitorId' => self::VID, 'event' => 'nope'], null, null);
    }
}
```

- [ ] **Step 2: Run** → FAIL.

- [ ] **Step 3: Implement**

```php
<?php // app/Services/AnalyticsRecorder.php
namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Writes tracked events and keeps the leads table in step (spec § 4).
 * The only PII stored is the email a person typed; IP is salted-hashed.
 */
final class AnalyticsRecorder
{
    public const MAX_PROPS_BYTES = 2048;

    public static function hashIp(?string $ip): ?string
    {
        return $ip === null ? null : hash('sha256', $ip . config('app.key'));
    }

    /** @param array{visitorId:string,event:string,path?:?string,referrer?:?string,utm?:?array,props?:?array} $payload */
    public function record(array $payload, ?string $ip, ?string $userAgent): AnalyticsEvent
    {
        $event = $payload['event'] ?? '';
        if (! in_array($event, AnalyticsEvent::EVENTS, true)) {
            throw new InvalidArgumentException("Unknown analytics event: {$event}");
        }
        if (! Str::isUuid($payload['visitorId'] ?? '')) {
            throw new InvalidArgumentException('visitorId must be a uuid');
        }
        $props = $payload['props'] ?? null;
        if ($props !== null && strlen(json_encode($props)) > self::MAX_PROPS_BYTES) {
            throw new InvalidArgumentException('props too large');
        }

        $row = AnalyticsEvent::create([
            'visitor_id' => $payload['visitorId'],
            'event'      => $event,
            'path'       => isset($payload['path']) ? Str::limit((string) $payload['path'], 255, '') : null,
            'referrer'   => isset($payload['referrer']) ? Str::limit((string) $payload['referrer'], 255, '') : null,
            'utm'        => $payload['utm'] ?? null,
            'props'      => $props,
            'ip_hash'    => self::hashIp($ip),
            'user_agent' => $userAgent === null ? null : substr($userAgent, 0, 255),
            'created_at' => now(),
        ]);

        $email = isset($props['email']) ? Str::lower(trim((string) $props['email'])) : null;
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Never trust a client-supplied userId: conversion is written only by linkRegistration() on the real registration.
            $this->touchLead($email, $payload['visitorId'], $event === 'register' ? 'register' : 'waitlist', null);
        } elseif ($event === 'demo_enter') {
            Lead::where('visitor_id', $payload['visitorId'])->update(['last_seen_at' => now()]);
        }

        return $row;
    }

    public function linkRegistration(User $user, ?string $visitorId = null): void
    {
        $this->touchLead(Str::lower($user->email), $visitorId, 'register', $user->id);
    }

    private function touchLead(string $email, ?string $visitorId, string $sourceIfNew, ?string $convertedUserId): void
    {
        $lead = Lead::firstOrNew(['email' => $email]);
        if (! $lead->exists) {
            $lead->source = $sourceIfNew;
            $lead->first_seen_at = now();
        }
        $lead->visitor_id ??= $visitorId;
        $lead->last_seen_at = now();
        if ($convertedUserId !== null && User::whereKey($convertedUserId)->exists()) {
            $lead->converted_user_id = $convertedUserId;
        }
        $lead->save();
    }
}
```

- [ ] **Step 4: Gate** full suite green.

---

### Task 4: `POST /api/track` + rate limit + register link

**Files:** create `app/Http/Requests/TrackRequest.php`, `app/Http/Controllers/Api/TrackController.php`; modify `app/Providers/AppServiceProvider.php` (RateLimiter `track`), `routes/api.php` (public), `app/Http/Controllers/Api/Auth/RegisterController.php`; test `tests/Feature/Analytics/TrackTest.php`.

**Produces:** `POST /api/track` → `204`; `422` on unknown event / bad uuid / oversized props / invalid email; `429` after 120 req/min per IP. `RegisterController` accepts optional `visitorId` and calls `linkRegistration`.

- [ ] **Step 1: Failing test**

```php
<?php
namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TrackTest extends TestCase
{
    use RefreshDatabase;

    private const VID = '44444444-4444-4444-8444-444444444444';

    public function test_accepts_whitelisted_event_as_guest(): void
    {
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'page_view', 'path' => '/coming-soon', 'referrer' => 'x.com'])->assertNoContent();
        $this->assertSame(1, AnalyticsEvent::count());
        $this->assertSame('/coming-soon', AnalyticsEvent::first()->path);
    }

    public function test_rejects_bad_payloads(): void
    {
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'evil'])->assertUnprocessable();
        $this->postJson('/api/track', ['visitorId' => 'nope', 'event' => 'page_view'])->assertUnprocessable();
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'waitlist_signup', 'props' => ['email' => 'not-an-email']])->assertUnprocessable();
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'page_view', 'props' => ['blob' => str_repeat('x', 3000)]])->assertUnprocessable();
        $this->assertSame(0, AnalyticsEvent::count());
    }

    public function test_waitlist_event_creates_lead(): void
    {
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'waitlist_signup', 'props' => ['email' => 'a@b.my']])->assertNoContent();
        $this->assertSame('a@b.my', Lead::first()->email);
    }

    public function test_is_rate_limited(): void
    {
        RateLimiter::clear('track:127.0.0.1');
        for ($i = 0; $i < 120; $i++) {
            $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'page_view'])->assertNoContent();
        }
        $this->postJson('/api/track', ['visitorId' => self::VID, 'event' => 'page_view'])->assertStatus(429);
    }

    public function test_register_links_lead_by_email(): void
    {
        Lead::factory()->create(['email' => 'n@o.my']);
        $res = $this->postJson('/api/auth/register', [
            'name' => 'New Owner', 'email' => 'n@o.my', 'phone' => '+60 12', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'visitorId' => self::VID,
        ])->assertCreated();
        $this->assertSame($res->json('user.id'), Lead::first()->converted_user_id);
    }
}
```

- [ ] **Step 2: Run** → FAIL (404).

- [ ] **Step 3: Request, controller, limiter, routes**

```php
<?php // app/Http/Requests/TrackRequest.php
namespace App\Http\Requests;

use App\Models\AnalyticsEvent;
use App\Services\AnalyticsRecorder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'visitorId'    => 'required|uuid',
            'event'        => ['required', Rule::in(AnalyticsEvent::EVENTS)],
            'path'         => 'nullable|string|max:255',
            'referrer'     => 'nullable|string|max:255',
            'utm'          => 'nullable|array:source,medium,campaign',
            'utm.*'        => 'nullable|string|max:100',
            'props'        => ['nullable', 'array', fn ($attr, $value, $fail) => strlen(json_encode($value)) > AnalyticsRecorder::MAX_PROPS_BYTES ? $fail('props too large') : null],
            'props.email'  => 'nullable|email|max:255',
            'props.userId' => 'nullable|uuid',
            'props.role'   => 'nullable|string|max:20',
            'at'           => 'nullable|date',
        ];
    }
}
```

```php
<?php // app/Http/Controllers/Api/TrackController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackRequest;
use App\Services\AnalyticsRecorder;
use Illuminate\Http\Response;

/** Public analytics beacon (spec § 3). Always 204 on success; clients ignore the body. */
class TrackController extends Controller
{
    public function store(TrackRequest $request, AnalyticsRecorder $recorder): Response
    {
        $recorder->record($request->validated(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
```

`AppServiceProvider::boot()` — add (with `use Illuminate\Cache\RateLimiting\Limit; use Illuminate\Http\Request; use Illuminate\Support\Facades\RateLimiter;`):
```php
        RateLimiter::for('track', fn (Request $request) => Limit::perMinute(120)->by('track:' . $request->ip()));
```

`routes/api.php` — in the public section:
```php
// ── Public: analytics beacon (spec: admin analytics § 3) ─────────────────────
Route::post('track', [\App\Http\Controllers\Api\TrackController::class, 'store'])->middleware('throttle:track');
```

`RegisterController::store` — add `'visitorId' => 'nullable|uuid',` to the rules, create the user from `Arr::except($data, ['visitorId'])`, then `app(AnalyticsRecorder::class)->linkRegistration($user, $data['visitorId'] ?? null);` before the response.

- [ ] **Step 4: Gate** full suite green.

---

### Task 5: Admin overview endpoint

**Files:** create `app/Http/Requests/Admin/AnalyticsRangeRequest.php`, `app/Http/Controllers/Api/Admin/AnalyticsController.php` (overview only; Task 6 adds leads); modify `routes/api.php`; test `tests/Feature/Analytics/AdminAnalyticsOverviewTest.php`.

**Produces:** `GET /api/admin/analytics/overview?from&to` (`can:analytics.view`) → payload per spec § 5. Range default last 30 days inclusive; custom capped at 366 days (422 beyond).

- [ ] **Step 1: Failing test**

```php
<?php
namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAnalyticsOverviewTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const C = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo(AdminPermissions::ANALYTICS_VIEW);
        Sanctum::actingAs($admin);
        $this->travelTo(now()->setDate(2026, 8, 20)->setTime(12, 0));
    }

    public function test_overview_shape_and_math(): void
    {
        // A: returning visitor (first seen 40 days ago), 2 views + demo + waitlist in range
        AnalyticsEvent::factory()->pageView('/')->forVisitor(self::A)->at(now()->subDays(40))->create();
        AnalyticsEvent::factory()->pageView('/')->forVisitor(self::A)->at(now()->subDays(3))->create(['referrer' => 'google.com']);
        AnalyticsEvent::factory()->pageView('/demo')->forVisitor(self::A)->at(now()->subDays(3))->create();
        AnalyticsEvent::factory()->event('demo_enter', ['role' => 'owner'])->forVisitor(self::A)->at(now()->subDays(3))->create();
        AnalyticsEvent::factory()->event('waitlist_signup', ['email' => 'a@x.my'])->forVisitor(self::A)->at(now()->subDays(2))->create();
        Lead::factory()->create(['email' => 'a@x.my', 'visitor_id' => self::A, 'first_seen_at' => now()->subDays(2)]);
        // B: new visitor, 1 view, registered
        $owner = User::factory()->owner()->create(['email' => 'b@x.my']);
        AnalyticsEvent::factory()->pageView('/')->forVisitor(self::B)->at(now()->subDay())->create();
        AnalyticsEvent::factory()->event('register', ['email' => 'b@x.my', 'userId' => $owner->id])->forVisitor(self::B)->at(now()->subDay())->create();
        Lead::factory()->converted($owner)->create(['email' => 'b@x.my', 'visitor_id' => self::B, 'first_seen_at' => now()->subDay()]);
        // C: outside range
        AnalyticsEvent::factory()->pageView('/')->forVisitor(self::C)->at(now()->subDays(45))->create();

        $res = $this->getJson('/api/admin/analytics/overview')->assertOk();
        $this->assertSame(['range', 'tiles', 'series', 'funnel', 'topPages', 'referrers'], array_keys($res->json()));
        $this->assertSame(30, $res->json('range.days'));
        $this->assertSame(['views', 'visitors', 'newVisitors', 'demoEntries', 'leads', 'registrations', 'conversionPct'], array_keys($res->json('tiles')));
        $this->assertSame(3, $res->json('tiles.views'));
        $this->assertSame(2, $res->json('tiles.visitors'));
        $this->assertSame(1, $res->json('tiles.newVisitors'));
        $this->assertSame(1, $res->json('tiles.demoEntries'));
        $this->assertSame(2, $res->json('tiles.leads'));
        $this->assertSame(1, $res->json('tiles.registrations'));
        $this->assertSame(50, $res->json('tiles.conversionPct'));
        $this->assertCount(30, $res->json('series.days'));
        $this->assertSame(now()->toDateString(), $res->json('series.days.29'));
        $this->assertSame(3, array_sum($res->json('series.views')));
        $this->assertSame(['visitors' => 2, 'demo' => 1, 'leads' => 2, 'registered' => 1], $res->json('funnel'));
        $this->assertSame(['path' => '/', 'views' => 2], $res->json('topPages.0'));
        $this->assertSame(['direct' => 2, 'google.com' => 1], collect($res->json('referrers'))->pluck('visitors', 'referrer')->all());
        $this->assertContains('direct', collect($res->json('referrers'))->pluck('referrer')->all());
        $this->assertStringNotContainsString('ip_hash', json_encode($res->json()));
    }

    public function test_custom_range_and_cap(): void
    {
        $this->getJson('/api/admin/analytics/overview?from=2026-08-01&to=2026-08-07')->assertOk()->assertJsonPath('range.days', 7);
        $this->getJson('/api/admin/analytics/overview?from=2025-01-01&to=2026-08-20')->assertUnprocessable();
        $this->getJson('/api/admin/analytics/overview?from=2026-08-10&to=2026-08-01')->assertUnprocessable();
    }

    public function test_requires_permission(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/admin/analytics/overview')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run** → FAIL (404).

- [ ] **Step 3: Implement**

```php
<?php // app/Http/Requests/Admin/AnalyticsRangeRequest.php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class AnalyticsRangeRequest extends FormRequest
{
    public const MAX_DAYS = 366;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            [$from, $to] = $this->range();
            if ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
                $v->errors()->add('to', 'Range may not exceed ' . self::MAX_DAYS . ' days.');
            }
        });
    }

    /** @return array{0: Carbon, 1: Carbon} inclusive day bounds (start of from, end of to) */
    public function range(): array
    {
        $to = $this->filled('to') ? Carbon::parse($this->string('to')) : now();
        $from = $this->filled('from') ? Carbon::parse($this->string('from')) : $to->copy()->subDays(29);

        return [$from->startOfDay(), $to->endOfDay()];
    }
}
```

```php
<?php // app/Http/Controllers/Api/Admin/AnalyticsController.php  (overview; Task 6 appends leads methods)
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsRangeRequest;
use App\Models\AnalyticsEvent;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Read-only platform analytics (spec § 5). Counts only — never money, never PII beyond lead email. */
class AnalyticsController extends Controller
{
    public function overview(AnalyticsRangeRequest $request): JsonResponse
    {
        [$from, $to] = $request->range();
        $days = (int) $from->diffInDays($to) + 1;

        $inRange = fn () => AnalyticsEvent::whereBetween('created_at', [$from, $to]);

        $visitorIds = $inRange()->distinct()->pluck('visitor_id');
        $firstSeen = AnalyticsEvent::whereIn('visitor_id', $visitorIds)
            ->selectRaw('visitor_id, MIN(created_at) as first_at')->groupBy('visitor_id')->pluck('first_at', 'visitor_id');
        $newVisitors = $firstSeen->filter(fn ($t) => $t >= $from->toDateTimeString())->count();

        $views = $inRange()->where('event', 'page_view')->count();
        $demoEntries = $inRange()->where('event', 'demo_enter')->count();
        $demoVisitors = $inRange()->where('event', 'demo_enter')->distinct()->count('visitor_id');
        $leads = Lead::whereBetween('first_seen_at', [$from, $to])->count();
        $registrations = $inRange()->where('event', 'register')->distinct()->count('visitor_id');
        $registeredLeads = Lead::whereBetween('first_seen_at', [$from, $to])->whereNotNull('converted_user_id')->count();

        // Daily series, oldest first.
        $dayKeys = [];
        for ($d = $from->copy(); $d <= $to; $d->addDay()) {
            $dayKeys[] = $d->toDateString();
        }
        $bucket = function (string $event, bool $distinctVisitor) use ($inRange, $dayKeys) {
            $q = $inRange();
            if ($event !== '*') { $q->where('event', $event); }
            $rows = $q->get(['visitor_id', 'created_at'])->groupBy(fn ($e) => $e->created_at->toDateString());
            return array_map(fn ($k) => isset($rows[$k]) ? ($distinctVisitor ? $rows[$k]->unique('visitor_id')->count() : $rows[$k]->count()) : 0, $dayKeys);
        };
        $leadsByDay = Lead::whereBetween('first_seen_at', [$from, $to])->get(['first_seen_at'])->groupBy(fn ($l) => $l->first_seen_at->toDateString());

        $topPages = $inRange()->where('event', 'page_view')->whereNotNull('path')
            ->select('path', DB::raw('count(*) as views'))->groupBy('path')->orderByDesc('views')->limit(10)->get()
            ->map(fn ($r) => ['path' => $r->path, 'views' => (int) $r->views])->values();
        $referrers = $inRange()->where('event', 'page_view')
            ->select(DB::raw("COALESCE(referrer, 'direct') as referrer"), DB::raw('count(distinct visitor_id) as visitors'))
            ->groupBy(DB::raw("COALESCE(referrer, 'direct')"))->orderByDesc('visitors')->limit(10)->get()
            ->map(fn ($r) => ['referrer' => $r->referrer, 'visitors' => (int) $r->visitors])->values();

        $visitors = $visitorIds->count();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days],
            'tiles' => [
                'views' => $views, 'visitors' => $visitors, 'newVisitors' => $newVisitors, 'demoEntries' => $demoEntries,
                'leads' => $leads, 'registrations' => $registrations,
                'conversionPct' => $visitors > 0 ? (int) round($registrations / $visitors * 100) : 0,
            ],
            'series' => [
                'days' => $dayKeys,
                'views' => $bucket('page_view', false),
                'visitors' => $bucket('*', true),
                'leads' => array_map(fn ($k) => isset($leadsByDay[$k]) ? $leadsByDay[$k]->count() : 0, $dayKeys),
                'registrations' => $bucket('register', true),
            ],
            'funnel' => ['visitors' => $visitors, 'demo' => $demoVisitors, 'leads' => $leads, 'registered' => $registeredLeads],
            'topPages' => $topPages,
            'referrers' => $referrers,
        ]);
    }
}
```

Route (inside the admin group, after the audit routes):
```php
        $Analytics = \App\Http\Controllers\Api\Admin\AnalyticsController::class;
        Route::middleware('can:' . $P::ANALYTICS_VIEW)->group(function () use ($Analytics) {
            Route::get('analytics/overview', [$Analytics, 'overview']);
        });
```

- [ ] **Step 4: Gate** full suite green. (sqlite: `count(distinct visitor_id)` and `COALESCE` are supported.)

---

### Task 6: Admin leads list / detail / CSV

**Files:** create `app/Http/Resources/Admin/{AdminLeadResource,LeadEventResource}.php`; modify `AnalyticsController` (add `leads`, `lead`, `export`), `routes/api.php`; test `tests/Feature/Analytics/AdminLeadsTest.php`.

**Produces:**
- `GET /admin/analytics/leads?q=&source=&converted=1&page=&perPage=` → `{data: AdminLead[], meta}` newest `last_seen_at` first.
- `GET /admin/analytics/leads/{lead}` → `AdminLead` + `events: LeadEvent[]` (last 20 by the lead's `visitor_id`).
- `GET /admin/analytics/leads/export.csv` (registered **before** `{lead}`) → CSV header `email,source,firstSeenAt,lastSeenAt,pageViews,demoEntered,convertedOwnerName`; logs `analytics.exported`.
- `AdminLeadResource` keys: `id, email, source, firstSeenAt, lastSeenAt, pageViews, demoEntered, convertedUserId, convertedOwnerName`. `LeadEventResource` keys: `id, event, path, props, createdAt`.

- [ ] **Step 1: Failing test**

```php
<?php
namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminLeadsTest extends TestCase
{
    use RefreshDatabase;

    public const LEAD_KEYS = ['id', 'email', 'source', 'firstSeenAt', 'lastSeenAt', 'pageViews', 'demoEntered', 'convertedUserId', 'convertedOwnerName'];
    private const VID = '55555555-5555-4555-8555-555555555555';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo(AdminPermissions::ANALYTICS_VIEW);
        Sanctum::actingAs($admin);
    }

    public function test_list_filters_and_resource_shape(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'Owner Z', 'email' => 'z@x.my']);
        Lead::factory()->converted($owner)->create(['email' => 'z@x.my', 'visitor_id' => self::VID]);
        Lead::factory()->create(['email' => 'wait@x.my', 'source' => 'waitlist']);
        AnalyticsEvent::factory()->pageView('/')->forVisitor(self::VID)->count(3)->create();
        AnalyticsEvent::factory()->event('demo_enter', ['role' => 'owner'])->forVisitor(self::VID)->create();

        $res = $this->getJson('/api/admin/analytics/leads')->assertOk();
        $this->assertSame(['data', 'meta'], array_keys($res->json()));
        $this->assertSame(2, $res->json('meta.total'));
        $row = collect($res->json('data'))->firstWhere('email', 'z@x.my');
        $this->assertSame(self::LEAD_KEYS, array_keys($row));
        $this->assertSame(3, $row['pageViews']);
        $this->assertTrue($row['demoEntered']);
        $this->assertSame('Owner Z', $row['convertedOwnerName']);

        $this->assertSame(1, $this->getJson('/api/admin/analytics/leads?q=wait')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/analytics/leads?source=register')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/analytics/leads?converted=1')->json('meta.total'));
    }

    public function test_show_includes_last_20_events_without_ip_or_ua(): void
    {
        $lead = Lead::factory()->create(['visitor_id' => self::VID]);
        AnalyticsEvent::factory()->pageView('/')->forVisitor(self::VID)->count(25)->create();
        $res = $this->getJson("/api/admin/analytics/leads/{$lead->id}")->assertOk();
        $this->assertSame(array_merge(self::LEAD_KEYS, ['events']), array_keys($res->json()));
        $this->assertCount(20, $res->json('events'));
        $this->assertSame(['id', 'event', 'path', 'props', 'createdAt'], array_keys($res->json('events.0')));
        $this->assertStringNotContainsString('ip_hash', json_encode($res->json()));
        $this->assertStringNotContainsString('PHPUnit', json_encode($res->json()));
    }

    public function test_export_csv_and_audit(): void
    {
        Lead::factory()->count(2)->create();
        $res = $this->get('/api/admin/analytics/leads/export.csv')->assertOk();
        $this->assertStringStartsWith('text/csv', $res->headers->get('content-type'));
        $lines = explode("\n", trim($res->streamedContent()));
        $this->assertSame('email,source,firstSeenAt,lastSeenAt,pageViews,demoEntered,convertedOwnerName', $lines[0]);
        $this->assertCount(3, $lines);
        $this->assertSame('analytics.exported', Activity::inLog('admin')->latest('id')->first()->event);
    }

    public function test_requires_permission(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/admin/analytics/leads')->assertForbidden();
    }
}
```

- [ ] **Step 2: Run** → FAIL.

- [ ] **Step 3: Resources + controller methods + routes**

```php
<?php // app/Http/Resources/Admin/AdminLeadResource.php
namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

/** Key set pinned by AdminLeadsTest. Expects `page_views_count` and `demo_entered` attributes set by the controller, and `convertedUser` loaded. */
class AdminLeadResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'email'              => $this->email,
            'source'             => $this->source,
            'firstSeenAt'        => $this->first_seen_at?->toISOString(),
            'lastSeenAt'         => $this->last_seen_at?->toISOString(),
            'pageViews'          => (int) ($this->page_views_count ?? 0),
            'demoEntered'        => (bool) ($this->demo_entered ?? false),
            'convertedUserId'    => $this->converted_user_id,
            'convertedOwnerName' => $this->convertedUser?->name,
        ];
    }
}
```

```php
<?php // app/Http/Resources/Admin/LeadEventResource.php
namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadEventResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'event'     => $this->event,
            'path'      => $this->path,
            'props'     => (object) ($this->props ?? []),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
```

Append to `AnalyticsController` (imports: `App\Http\Resources\Admin\AdminLeadResource`, `LeadEventResource`, `App\Services\AuditLogger`, `Illuminate\Database\Eloquent\Builder`, `Illuminate\Http\Request`, `Symfony\Component\HttpFoundation\StreamedResponse`):

```php
    private function leadQuery(Request $request): Builder
    {
        $q = Lead::query()->with('convertedUser:id,name')->orderByDesc('last_seen_at');
        if ($s = trim((string) $request->query('q', ''))) { $q->where('email', 'like', "%{$s}%"); }
        if ($src = $request->query('source')) { $q->where('source', $src); }
        if ($request->boolean('converted')) { $q->whereNotNull('converted_user_id'); }
        return $q;
    }

    /** Attaches page_views_count + demo_entered to each lead from its visitor's events (one query each). */
    private function decorate($leads): void
    {
        $vids = $leads->pluck('visitor_id')->filter()->values();
        $views = AnalyticsEvent::whereIn('visitor_id', $vids)->where('event', 'page_view')
            ->select('visitor_id', DB::raw('count(*) as c'))->groupBy('visitor_id')->pluck('c', 'visitor_id');
        $demo = AnalyticsEvent::whereIn('visitor_id', $vids)->where('event', 'demo_enter')->distinct()->pluck('visitor_id')->flip();
        foreach ($leads as $lead) {
            $lead->page_views_count = (int) ($views[$lead->visitor_id] ?? 0);
            $lead->demo_entered = isset($demo[$lead->visitor_id]);
        }
    }

    public function leads(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('perPage', 20)));
        $page = $this->leadQuery($request)->paginate($perPage, ['*'], 'page', max(1, (int) $request->integer('page', 1)));
        $items = collect($page->items());
        $this->decorate($items);

        return response()->json([
            'data' => AdminLeadResource::collection($items)->resolve(),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
        ]);
    }

    public function lead(Lead $lead): JsonResponse
    {
        $lead->load('convertedUser:id,name');
        $this->decorate(collect([$lead]));
        $events = $lead->visitor_id
            ? AnalyticsEvent::where('visitor_id', $lead->visitor_id)->latest('created_at')->limit(20)->get()
            : collect();

        return response()->json((new AdminLeadResource($lead))->resolve() + ['events' => LeadEventResource::collection($events)->resolve()]);
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $query = $this->leadQuery($request);
        $audit->record(AuditLogger::ANALYTICS_EXPORTED, null, [], ['filters' => $request->only('q', 'source', 'converted')]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'source', 'firstSeenAt', 'lastSeenAt', 'pageViews', 'demoEntered', 'convertedOwnerName']);
            $query->chunk(500, function ($rows) use ($out) {
                $this->decorate($rows);
                foreach (AdminLeadResource::collection($rows)->resolve() as $r) {
                    fputcsv($out, [$r['email'], $r['source'], $r['firstSeenAt'], $r['lastSeenAt'], $r['pageViews'], $r['demoEntered'] ? 'yes' : 'no', $r['convertedOwnerName']]);
                }
            });
            fclose($out);
        }, 'roofly-leads-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
```

Routes — inside the `can:analytics.view` group from Task 5, after `overview`:
```php
            Route::get('analytics/leads',            [$Analytics, 'leads']);
            Route::get('analytics/leads/export.csv', [$Analytics, 'export']);   // before {lead}
            Route::get('analytics/leads/{lead}',     [$Analytics, 'lead']);
```

- [ ] **Step 4: Gate** full suite green.

---

### Task 7: Prune command + schedule; demo seeder

**Files:** create `app/Console/Commands/PruneAnalyticsEvents.php`, `database/seeders/AnalyticsDemoSeeder.php`; modify `routes/console.php`, `database/seeders/DemoSeeder.php`; tests `tests/Feature/Analytics/PruneAnalyticsTest.php`, extend `tests/Feature/DemoSeederTest.php`.

**Produces:** `php artisan analytics:prune` (deletes `analytics_events` older than 13 months, 1 000-row chunks, prints count); scheduled `daily()`. `AnalyticsDemoSeeder` seeds 90 days of deterministic events (mt_srand(2026)) — ~15–40 page views/day across `/`, `/coming-soon`, `/demo`, `/auth/register`, ~25 % of visitors enter the demo, 40 leads (waitlist), 8 of which registered and are linked to… the single demo owner cannot absorb 8 registrations, so: 2 leads converted to the demo owner `aminah@roofly.my` is wrong too (unique email). **Rule:** converted leads use emails of **new owner users created by this seeder** (8 owners `lead01@example.com`…, role owner, password `password`, no properties) — they are real registered owners with empty portfolios, which also exercises the `no_property_7d` attention kind. `DemoSeederTest` owner count assertion changes from `1` to `9`.

- [ ] **Step 1: Failing tests**

```php
<?php // tests/Feature/Analytics/PruneAnalyticsTest.php
namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class PruneAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_only_events_older_than_13_months(): void
    {
        AnalyticsEvent::factory()->at(now()->subMonths(14))->count(3)->create();
        AnalyticsEvent::factory()->at(now()->subMonths(12))->count(2)->create();
        $this->artisan('analytics:prune')->expectsOutputToContain('Pruned 3')->assertExitCode(0);
        $this->assertSame(2, AnalyticsEvent::count());
    }

    public function test_is_scheduled_daily(): void
    {
        $events = collect(Schedule::events())->map(fn ($e) => $e->command ?? $e->description);
        $this->assertTrue($events->contains(fn ($c) => str_contains((string) $c, 'analytics:prune')));
    }
}
```
`DemoSeederTest` additions: `$this->assertSame(9, User::where('role','owner')->count());` (replace the existing `1`), `$this->assertGreaterThan(1000, \App\Models\AnalyticsEvent::count());`, `$this->assertSame(40, \App\Models\Lead::count());`, `$this->assertSame(8, \App\Models\Lead::whereNotNull('converted_user_id')->count());`, and after the reseed: `$this->assertSame(40, \App\Models\Lead::count());` (idempotent).

- [ ] **Step 2: Run** → FAIL.

- [ ] **Step 3: Command + schedule**

```php
<?php // app/Console/Commands/PruneAnalyticsEvents.php
namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use Illuminate\Console\Command;

class PruneAnalyticsEvents extends Command
{
    protected $signature = 'analytics:prune {--months=13}';
    protected $description = 'Delete analytics events older than N months (default 13)';

    public function handle(): int
    {
        $cutoff = now()->subMonths((int) $this->option('months'));
        $total = 0;
        do {
            $deleted = AnalyticsEvent::where('created_at', '<', $cutoff)->limit(1000)->delete();
            $total += $deleted;
        } while ($deleted > 0);
        $this->info("Pruned {$total} analytics events older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
```
`routes/console.php` — add `use Illuminate\Support\Facades\Schedule;` and `Schedule::command('analytics:prune')->daily();`. (Laravel 13 auto-discovers commands in `app/Console/Commands`.)

- [ ] **Step 4: Demo seeder**

```php
<?php // database/seeders/AnalyticsDemoSeeder.php
namespace Database\Seeders;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * 90 days of deterministic marketing-site analytics so the admin page has a
 * story: ~15–40 views/day, a quarter of visitors try the demo, 40 waitlist
 * leads, 8 of which registered (as real, property-less owner users).
 * Idempotent: wipes and regenerates its own rows each run.
 */
class AnalyticsDemoSeeder extends Seeder
{
    private const NS = '00000000-0000-4000-8000-00000000a000';
    private const PATHS = ['/', '/', '/', '/coming-soon', '/demo', '/demo', '/auth/register'];
    private const REFERRERS = [null, null, null, 'google.com', 'facebook.com', 'lowyat.net', 'instagram.com'];

    public function run(): void
    {
        mt_srand(2026);
        AnalyticsEvent::query()->delete();
        Lead::query()->delete();
        User::where('email', 'like', 'lead%@example.com')->forceDelete();

        $leadsMade = 0; $converted = 0; $vidSeq = 0;
        for ($day = 89; $day >= 0; $day--) {
            $date = now()->subDays($day)->startOfDay();
            $visitors = mt_rand(6, 16);
            for ($v = 0; $v < $visitors; $v++) {
                $vid = Uuid::uuid5(self::NS, 'v' . (++$vidSeq))->toString();
                $ref = self::REFERRERS[mt_rand(0, count(self::REFERRERS) - 1)];
                $t = $date->copy()->addMinutes(mt_rand(480, 1380));
                $views = mt_rand(1, 4);
                for ($i = 0; $i < $views; $i++) {
                    $this->event($vid, 'page_view', $t->copy()->addMinutes($i * 2), ['path' => self::PATHS[mt_rand(0, count(self::PATHS) - 1)], 'referrer' => $i === 0 ? $ref : null]);
                }
                if (mt_rand(1, 100) <= 25) {
                    $this->event($vid, 'demo_enter', $t->copy()->addMinutes(10), ['props' => ['role' => mt_rand(0, 1) ? 'owner' : 'tenant']]);
                    if (mt_rand(1, 100) <= 20) { $this->event($vid, 'demo_feedback_click', $t->copy()->addMinutes(15)); }
                }
                if ($leadsMade < 40 && mt_rand(1, 100) <= 5) {
                    $n = ++$leadsMade;
                    $email = sprintf('lead%02d@example.com', $n);
                    $this->event($vid, 'waitlist_signup', $t->copy()->addMinutes(20), ['props' => ['email' => $email]]);
                    $lead = Lead::create(['email' => $email, 'visitor_id' => $vid, 'source' => 'waitlist', 'first_seen_at' => $t, 'last_seen_at' => $t]);
                    if ($converted < 8 && $n % 5 === 0) {
                        $converted++;
                        $owner = User::create(['name' => "Lead {$n}", 'email' => $email, 'role' => 'owner', 'password' => Hash::make('password'), 'plan_tier' => 'free']);
                        $owner->forceFill(['created_at' => $t->copy()->addDays(2)])->saveQuietly();
                        $this->event($vid, 'register', $t->copy()->addDays(2), ['path' => '/auth/register', 'props' => ['email' => $email, 'userId' => $owner->id]]);
                        $lead->update(['converted_user_id' => $owner->id, 'last_seen_at' => $t->copy()->addDays(2)]);
                    }
                }
            }
        }
    }

    private function event(string $vid, string $name, $at, array $extra = []): void
    {
        AnalyticsEvent::create(array_merge([
            'visitor_id' => $vid, 'event' => $name, 'path' => null, 'referrer' => null, 'utm' => null, 'props' => null,
            'ip_hash' => hash('sha256', $vid), 'user_agent' => 'seed', 'created_at' => $at,
        ], $extra));
    }
}
```
`DemoSeeder::run()` — add `$this->call(AnalyticsDemoSeeder::class);` as the last line of the transaction. (40 leads: the 5 % rate over ~1 000 visitors produces ≥ 40 before the cap; the `$leadsMade < 40` guard makes the count exact.)

- [ ] **Step 5: Gate** full suite green; `docker exec roofly-backend php artisan migrate --seed` on the dev DB.

---

## Part B — Frontend

### Task 8: Tracking — types, `useEnv.trackingEnabled`, `useTrack`, plugin, five call sites

**Files:** create `app/types/analytics.ts`, `app/composables/useTrack.ts`, `app/demo/track.ts`, `app/services/api/track.ts`, `app/plugins/track.client.ts`; modify `nuxt.config.ts`, `app/composables/useEnv.ts`, `app/types/admin.ts`, `app/demo/auth.ts`, `EmailCapture.vue`, `pages/demo/index.vue`, `DemoLoginShortcuts.vue`, `FloatingFeedback.vue`, `pages/auth/register.vue`, `app/services/api/auth.ts` (+ `visitorId` on register), `app/services/contracts/auth.ts`.

**Produces:**
```ts
// types/analytics.ts
export const TRACK_EVENTS = ["page_view","demo_enter","demo_feedback_click","waitlist_signup","register"] as const;
export type TrackEvent = (typeof TRACK_EVENTS)[number];
export interface TrackPayload { visitorId: string; event: TrackEvent; path?: string; referrer?: string; utm?: { source?: string; medium?: string; campaign?: string }; props?: Record<string, string>; at: string }
export interface TrackAdapter { send(payload: TrackPayload): void }
// composables/useTrack.ts
export const useTrack = () => ({ track(event: TrackEvent, props?: Record<string,string>): void; visitorId(): string })
export const TRACKED_PREFIXES = ["/", "/coming-soon", "/demo", "/auth"];   // exact "/" or startsWith for the rest
export const isTrackedPath = (path: string) => boolean   // false for /owner, /tenant, /admin, /suspended
```
`useEnv()` gains `trackingEnabled = !useMock && config.public.tracking !== false`. `ADMIN_PERMISSIONS` gains `"analytics.view"` after `"tenants.view"`; `DEMO_OPS_PRESET` gains it; `AuditAction` gains `"analytics.exported"`; `AUDIT_ACTIONS` too. `RegisterPayload` gains `visitorId?: string`.

- [ ] **Step 1: nuxt.config + useEnv** — `runtimeConfig.public.tracking: process.env.NUXT_PUBLIC_TRACKING !== "false"`; in `useEnv` return `trackingEnabled: !(isDemo || config.public.useMock) && config.public.tracking !== false,`.

- [ ] **Step 2: types + permission/audit additions** (exact code above for `types/analytics.ts`; one-line inserts in `types/admin.ts` and `demo/auth.ts`).

- [ ] **Step 3: Adapters + composable + plugin**

```ts
// app/demo/track.ts
import type { TrackAdapter } from "~/types/analytics";
/** Demo never tracks — demo-roofly must generate zero rows. */
export const demoTrack: TrackAdapter = { send() {} };
```
```ts
// app/services/api/track.ts
import type { TrackAdapter, TrackPayload } from "~/types/analytics";
export const apiTrack: TrackAdapter = {
  send(payload: TrackPayload) {
    const url = `${useRuntimeConfig().public.apiBase}/track`;
    const body = JSON.stringify(payload);
    try {
      if (typeof navigator !== "undefined" && navigator.sendBeacon) {
        navigator.sendBeacon(url, new Blob([body], { type: "application/json" }));
        return;
      }
      void $fetch(url, { method: "POST", body: payload, keepalive: true }).catch(() => {});
    } catch {
      // analytics must never break a page
    }
  },
};
```
```ts
// app/composables/useTrack.ts
import type { TrackEvent, TrackPayload } from "~/types/analytics";
import { demoTrack } from "~/demo/track";
import { apiTrack } from "~/services/api/track";

const VID_KEY = "roofly_vid";
const UTM_KEY = "roofly_utm";
export const TRACKED_PREFIXES = ["/coming-soon", "/demo", "/auth"];
export const isTrackedPath = (path: string) => path === "/" || TRACKED_PREFIXES.some((p) => path === p || path.startsWith(`${p}/`));

const read = (k: string) => { try { return localStorage.getItem(k); } catch { return null; } };
const write = (k: string, v: string) => { try { localStorage.setItem(k, v); } catch { /* private mode */ } };

export const useTrack = () => {
  const env = useEnv();
  const adapter = env.useMock ? demoTrack : apiTrack;

  const visitorId = (): string => {
    let id = read(VID_KEY);
    if (!id) { id = crypto.randomUUID(); write(VID_KEY, id); }
    return id;
  };

  /** First-touch UTM: captured once from the landing URL, reused on every event. */
  const utm = (): TrackPayload["utm"] | undefined => {
    const stored = read(UTM_KEY);
    if (stored) return JSON.parse(stored);
    const q = new URLSearchParams(window.location.search);
    const u = { source: q.get("utm_source") ?? undefined, medium: q.get("utm_medium") ?? undefined, campaign: q.get("utm_campaign") ?? undefined };
    if (!u.source && !u.medium && !u.campaign) return undefined;
    write(UTM_KEY, JSON.stringify(u));
    return u;
  };

  const track = (event: TrackEvent, props?: Record<string, string>) => {
    if (!import.meta.client || !env.trackingEnabled) return;
    const path = window.location.pathname;
    if (!isTrackedPath(path)) return;
    const ref = document.referrer ? new URL(document.referrer).hostname : undefined;
    adapter.send({ visitorId: visitorId(), event, path, referrer: ref && ref !== window.location.hostname ? ref : undefined, utm: utm(), props, at: new Date().toISOString() });
  };

  return { track, visitorId };
};
```
```ts
// app/plugins/track.client.ts
import { isTrackedPath } from "~/composables/useTrack";
/** page_view on every client-side navigation to a public/marketing path (spec § 3). */
export default defineNuxtPlugin((nuxtApp) => {
  const router = useRouter();
  const { track } = useTrack();
  router.afterEach((to) => {
    if (isTrackedPath(to.path)) nuxtApp.runWithContext(() => track("page_view"));
  });
});
```
Note: `router.afterEach` also fires for the initial client navigation in Nuxt 4, so the first page view is captured without an extra `onMounted`.

- [ ] **Step 4: Call sites**
- `EmailCapture.vue` inside the `if (res.ok && body.success)` branch: `useTrack().track("waitlist_signup", { email: email.value.trim() });`
- `pages/demo/index.vue`: `onMounted(() => useTrack().track("demo_enter", { role: "landing" }));` and in the role-enter function before `auth.login`: `useTrack().track("demo_enter", { role });`
- `DemoLoginShortcuts.vue` `enter()`: `useTrack().track("demo_enter", { role });` first line.
- `FloatingFeedback.vue`: `@click="useTrack().track('demo_feedback_click')"` on the anchor (keep `href`/target).
- `pages/auth/register.vue`: pass `visitorId: useTrack().visitorId()` in the `auth.register({...})` payload; after it resolves and before `navigateTo`: `useTrack().track("register", { email: <form email>, userId: auth.user?.id ?? "" });`
- `contracts/auth.ts` `RegisterPayload` `+ visitorId?: string`; `api/auth.ts` already spreads the payload so nothing else changes; `demo/auth.ts` `register()` ignores it.

- [ ] **Step 5: Gate** typecheck (5 known), greps incl. `grep -rn "useTrack\|track(" frontend/app/pages/owner frontend/app/pages/tenant frontend/app/pages/admin` → empty; `grep -rn "useApi" frontend/app/demo/` → empty.

---

### Task 9: Admin analytics contract, demo data + adapter, API adapter, selector

**Files:** create `app/services/contracts/admin/analytics.ts`, `app/demo/data/analytics.ts`, `app/demo/services/admin/analytics.ts`, `app/services/api/admin/analytics.ts`, `app/services/useAdminAnalytics.ts`; modify `app/types/analytics.ts` (admin-side types).

**Produces (types appended to `types/analytics.ts`):**
```ts
export type LeadSource = "waitlist" | "demo" | "register";
export interface AdminLead { id: string; email: string; source: LeadSource; firstSeenAt: string; lastSeenAt: string; pageViews: number; demoEntered: boolean; convertedUserId: string | null; convertedOwnerName: string | null }
export interface LeadEvent { id: string; event: TrackEvent; path: string | null; props: Record<string, unknown>; createdAt: string }
export interface AdminLeadDetail extends AdminLead { events: LeadEvent[] }
export interface AnalyticsRange { from?: string; to?: string }
export interface AnalyticsOverview { range: { from: string; to: string; days: number }; tiles: { views: number; visitors: number; newVisitors: number; demoEntries: number; leads: number; registrations: number; conversionPct: number }; series: { days: string[]; views: number[]; visitors: number[]; leads: number[]; registrations: number[] }; funnel: { visitors: number; demo: number; leads: number; registered: number }; topPages: { path: string; views: number }[]; referrers: { referrer: string; visitors: number }[] }
export interface LeadListQuery { q?: string; source?: LeadSource; converted?: boolean; page?: number; perPage?: number }
```
Contract:
```ts
export interface AdminAnalyticsService {
  overview(range: AnalyticsRange): Promise<AnalyticsOverview>;
  leads(query: LeadListQuery): Promise<Paginated<AdminLead>>;
  lead(id: string): Promise<AdminLeadDetail | null>;
  exportCsv(query: LeadListQuery): Promise<string>;
}
```
Selector `useAdminAnalytics()`; API adapter uses `cleanQuery` from `services/api/admin/query.ts`, `lead()` returns `null` on 404 like owners/tenants; `exportCsv` uses `responseType: "text"`.

- [ ] **Step 1: Demo data** — `demo/data/analytics.ts` builds the same story as `AnalyticsDemoSeeder` with a seeded PRNG (mulberry32, seed 2026): export `analyticsEventsMock: { visitorId, event, path, referrer, props, createdAt }[]` (~90 days), `leadsMock: AdminLead[]` (40, 8 converted — the converted ones link to `convertedUserId: "o-lead-N"` and `convertedOwnerName: "Lead N"`; they do **not** need rows in `adminOwnersMock`, the detail link shows "Not found" if clicked — acceptable for demo), plus a `computeOverview(range): AnalyticsOverview` helper that does the same arithmetic as the backend over the mock events (distinct visitors, newVisitors by first-ever event, daily buckets, funnel, top 10 pages/referrers with `"direct"`).

```ts
// mulberry32
const rng = (seed: number) => () => { let t = (seed += 0x6d2b79f5); t = Math.imul(t ^ (t >>> 15), t | 1); t ^= t + Math.imul(t ^ (t >>> 7), t | 61); return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
```

- [ ] **Step 2: Demo adapter** — `overview` → `computeOverview`; `leads` → filter `leadsMock` (q on email, source, converted) sorted by `lastSeenAt` desc, `paginate()` from `demo/services/admin/paginate.ts`; `lead` → lead + last 20 `analyticsEventsMock` for its `visitorId` mapped to `LeadEvent`; `exportCsv` → `buildCsv` with the same 7 headers as the backend.

- [ ] **Step 3: API adapter + selector** (mirrors `services/api/admin/owners.ts` + `useAdminOwners.ts`).

- [ ] **Step 4: Gate** typecheck + greps.

---

### Task 10: Components, sidebar, strings

**Files:** create `components/admin/{SourcePill,FunnelStrip,EventList,LeadDrawer}.vue`; modify `components/admin/SidebarNav.vue`, `i18n/locales/en.json` (`admin.nav.analytics`, `admin.analytics.*`, `admin.settings.admins.keys.analytics.view`, `admin.audit.actions.analytics.exported`).

**Produces:**
- `<SourcePill :source>` — waitlist → `draft`, demo → `maintenance`, register → `active`.
- `<FunnelStrip :steps="[{key,label,count}]">` — 4 cards in a row (`grid-cols-2 lg:grid-cols-4`), each shows count and `% of previous` (first shows 100 %); card-row on mobile.
- `<EventList :events="LeadEvent[]">` — card-row list: pill with the event label (`admin.analytics.events.<event>`), time, path, and a compact `props` line (email/role/userId).
- `<LeadDrawer v-model:open :lead-id>` — `Modal size="lg"`; loads `useAdminAnalytics().lead(id)` on open; header (email, source pill, first/last seen, converted owner link), then `EventList`.
- Sidebar item `{ to: "/admin/analytics", label: t("admin.nav.analytics"), icon: ChartBar, needs: "analytics.view" }` between Tenants and Audit.

Strings (`en.json`, nested as needed):
```json
"analytics": {
  "title": "Analytics", "subtitle": "Who looks at roofly.my, who tries the demo, who leaves an email, who signs up.",
  "range": { "label": "Range", "d7": "Last 7 days", "d30": "Last 30 days", "d90": "Last 90 days", "custom": "Custom", "from": "From", "to": "To" },
  "tiles": { "views": "Page views", "visitors": "Visitors", "visitorsHelp": "{n} new", "demo": "Demo entries", "leads": "Leads", "registrations": "Registrations", "conversion": "Conversion", "conversionHelp": "Registrations ÷ visitors" },
  "charts": { "views": "Page views per day", "registrations": "Registrations per day" },
  "funnel": { "title": "Funnel", "visitors": "Visitors", "demo": "Tried the demo", "leads": "Left an email", "registered": "Registered" },
  "topPages": "Top pages", "referrers": "Top referrers", "direct": "Direct / unknown",
  "leads": { "title": "Leads", "searchPlaceholder": "Search email", "columns": { "email": "Email", "source": "Source", "firstSeen": "First seen", "lastSeen": "Last seen", "views": "Views", "demo": "Demo", "converted": "Converted" }, "filters": { "converted": "Converted only" }, "empty": "No leads yet", "emptyHelp": "Leads appear when someone joins the waitlist or registers." },
  "sources": { "waitlist": "Waitlist", "demo": "Demo", "register": "Registered" },
  "events": { "page_view": "Page view", "demo_enter": "Entered demo", "demo_feedback_click": "Clicked feedback", "waitlist_signup": "Joined waitlist", "register": "Registered" },
  "drawer": { "title": "Lead", "recent": "Recent activity", "noEvents": "No events recorded for this lead." }
}
```
plus `"nav": { …, "analytics": "Analytics" }`, `"keys": { …, "analytics": { "view": "View analytics" } }`, `"actions": { …, "analytics": { "exported": "Leads exported" } }`.

- [ ] **Gate** typecheck (5 known).

---

### Task 11: `/admin/analytics` page

**Files:** create `pages/admin/analytics.vue`.

Structure (reuse the list-page patterns from `pages/admin/owners/index.vue` — route-synced state, reset-page-or-load watchers, keyboard rows, `<button>` cards, `NoAccess`):
1. `definePageMeta({ layout: "admin" })`; `NoAccess v-if="!can('analytics.view')"`.
2. Range state: `preset` (`d7|d30|d90|custom`, default `d30`) + `from`/`to` (used when custom), kept in `route.query`; `range` computed → `{from,to}` (presets compute dates client-side so the API gets explicit dates).
3. `overview` ref loaded via `useAdminAnalytics().overview(range)`; `loading`.
4. Six `StatTile`s; two `MiniAreaChart`s fed `{ key: day, label: dd MMM, amount }` with `:format="count"`; `FunnelStrip`; two `Card` lists for top pages / referrers (referrer `direct` → `t("admin.analytics.direct")`).
5. Leads: `q` (debounced 300 ms), `source` select, `converted` checkbox, `page`; `DataTableShell` with columns email · `SourcePill` · first seen · last seen · views · demo (✓ / —) · converted (`NuxtLink` to `/admin/owners/${convertedUserId}` showing the name, or "—"). Row click / Enter opens `LeadDrawer`. Export button → `downloadCsvText("roofly-leads-<date>.csv", csv)` with a danger toast on failure.
6. Mobile: tiles `grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6`, funnel `grid-cols-2 lg:grid-cols-4`, lists stack under `lg`.

- [ ] **Gate** typecheck (5 known); `grep -rn "formatRM\|MoneyDisplay\|useMoney" frontend/app/pages/admin frontend/app/components/admin` → empty.

---

### Task 12: Docs, env, final sweep

- [ ] `.env.example` + `docker-compose.yml` frontend env: `NUXT_PUBLIC_TRACKING=${NUXT_PUBLIC_TRACKING:-true}` with a one-line comment.
- [ ] `docs/backend/API-SPEC.md`: add `POST /track` (Public) and the four `/admin/analytics/*` routes (Admin → Analytics) with request/response shapes; add `AdminLeadResource` / `LeadEventResource` to the appendix; permission list 14 keys, preset 8. `docs/frontend/API-MAP.md`: add the analytics contract row and the `/admin/analytics` page; add a "Tracking" subsection (events, call sites, `trackingEnabled`). `.claude/CLAUDE.md`: Admin shell paragraph mentions Analytics + tracking rule; credentials unchanged. `docs/frontend/UI-STANDARDS.md` § 11.16 "Funnel strip" (4-up desktop, 2-up mobile, step % under count).
- [ ] Final sweep: backend suite green; typecheck 5 known; all greps (incl. the no-tracking-in-shells grep) empty; `route:list --path=admin` shows 25 routes (21 + 4) and `route:list --path=track` shows 1 with `throttle:track`.
- [ ] Hand-off for the browser walk: API mode — visit `/coming-soon` and `/demo`, join the waitlist, register an owner with that email, then `/admin/analytics` as `admin@roofly.my` shows the funnel and the lead marked converted; export CSV; ops admin (`ops@roofly.my`) sees Analytics (preset). Demo mode — seeded 90-day data, DevTools Network shows **zero** `/api/track` calls. Local opt-out — `NUXT_PUBLIC_TRACKING=false` → no beacons.

---

## Self-review notes

**Spec coverage:** § 3 events/call sites → T8; § 4 tables/recorder/prune/throttle → T1, T3, T4, T7; § 5 endpoints + resources → T5, T6; § 6 page/components/sidebar → T10, T11; § 7/§ 8 structure → file map; § 9 tests → each backend task + T12 greps; § 10 open points: preset is additive (T2, reseed picks it up; UAT super-admin grants once), server-side register link is source of truth (T4), range cap 366 (T5).
**Known simplifications:** overview bucketing pulls range rows into PHP (fine at this scale, same style as the dashboard); converted demo leads become real property-less owner users (also exercises `no_property_7d`); the demo `lead()` detail's converted-owner link may 404 in demo mode (those owners aren't in `adminOwnersMock`) — acceptable, noted in T9.
**Type consistency:** `AdminLead` keys ↔ `AdminLeadResource`; `AnalyticsOverview` ↔ `overview()` payload; `TRACK_EVENTS` ↔ `AnalyticsEvent::EVENTS`; `analytics.view` / `analytics.exported` present in backend constants, frontend unions, i18n; CSV header identical in backend export and demo `exportCsv`.
