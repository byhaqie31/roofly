# Google Sign-in, Owner Onboarding & Getting-started Checklist — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Owners can sign in with Google, answer one "what do you manage?" screen, tag each property as rental / own stay / investment, and see a data-driven getting-started checklist on the dashboard that deep-links to what they still need to fill in.

**Architecture:** Google Identity Services issues an ID token in the browser; `POST /auth/google` verifies it server-side against Google's `tokeninfo` endpoint (no new Composer packages), finds-or-creates the owner, and starts the same Sanctum session `/auth/login` does. Onboarding answers live on `users` (`purposes`, `onboarded_at`, `checklist_dismissed_at`); the per-property `purpose` column drives exclusion of non-rental properties from occupancy, attention, and income. The checklist is a pure function over already-available lists — nothing stored per step.

**Tech Stack:** Nuxt 4 / Vue 3 / Pinia / vee-validate + Zod / reka-ui / Tailwind v3 / `@nuxtjs/i18n`; Laravel 13 + Sanctum; PHPUnit 12 (sqlite in-memory); Vitest (added in Task 12).

**Spec:** `docs/superpowers/specs/2026-08-23-google-login-owner-onboarding-design.md`

## Global Constraints

- **NEVER run `git commit` or `git push`.** Baihaqie reviews and commits. Tasks end at a verification step, not a commit.
- Work on the current branch `feature/admin-backoffice` (user instruction). PRs target `UAT`, never `main`.
- Money is integer sen. No new money fields here, but keep the rule.
- Demo/API adapter parity: `app/demo/**` never imports `useApi`; `services/api/**` never imports `~/demo`; pages/components import only `services/useX`. No `if (useMock)` inside methods.
- Per-env behaviour via `useEnv()` derived fields — one new field `features.googleLogin`.
- Sentence case in all strings, en + ms for owner-facing keys. Never a literal `@` in a translation value.
- Two font weights (400 / 600). Tailwind opacity modifiers on hex tokens silently no-op — use solid tokens.
- Reka UI `<TabsRoot v-model>` (not `v-model:value`).
- `AdminResourcesTest` must stay green: do **not** add `purpose` or onboarding fields to any `Admin/*Resource`.
- Backend tests: `docker exec roofly-backend php artisan test --filter=<Name>`. Frontend typecheck: `docker exec roofly-frontend npm run typecheck` (5 known pre-existing errors in `InvoiceViewModal.vue`, `payments.vue`, `Icon.vue`, `EmptyState.vue` are tolerated — the count must not grow).
- Migration filenames follow the repo's hand-sequenced convention: `2026_08_25_00000N_*.php`.
- Columns that hold enum-ish values are `string` columns (not DB enums) so sqlite ALTERs cleanly — match `users.status`.

---

## File map

**Backend (create)**
- `database/migrations/2026_08_25_000001_add_google_and_onboarding_to_users_table.php`
- `database/migrations/2026_08_25_000002_add_purpose_to_properties_table.php`
- `app/Enums/PropertyPurpose.php`
- `app/Support/GoogleIdToken.php` — verifies a GIS ID token, injectable/fakeable
- `app/Http/Controllers/Api/Auth/GoogleLoginController.php`, `app/Http/Controllers/Api/Auth/PasswordResetController.php`, `app/Notifications/ResetPassword.php`
- `app/Http/Requests/CompleteOnboardingRequest.php`
- `tests/Feature/GoogleLoginTest.php`, `tests/Unit/GoogleIdTokenTest.php`, `tests/Feature/AccountOnboardingTest.php`, `tests/Feature/PropertyPurposeTest.php`, `tests/Feature/PasswordResetTest.php`

**Backend (modify)**
- `app/Models/User.php`, `app/Models/Property.php`, `app/Http/Resources/AuthUserResource.php`, `app/Http/Resources/PropertyResource.php`, `app/Http/Requests/StorePropertyRequest.php`, `app/Http/Requests/UpdatePropertyRequest.php`, `app/Http/Controllers/Api/Auth/LoginController.php`, `app/Http/Controllers/Api/Owner/AccountController.php`, `app/Http/Controllers/Api/Owner/DashboardController.php`, `app/Services/AuditLogger.php`, `app/Providers/AppServiceProvider.php`, `config/services.php`, `routes/api.php`, `database/factories/PropertyFactory.php`, `database/seeders/DemoSeeder.php`, `.env.example`, `tests/Feature/AuthContractTest.php`, `tests/Feature/DashboardContractTest.php`

**Frontend (create)**
- `app/components/auth/GoogleSignInButton.vue`
- `app/pages/owner/onboarding.vue`, `app/pages/auth/forgot-password.vue`, `app/pages/auth/reset-password.vue`
- `app/components/owner/OwnerPurposePicker.vue` — shared by onboarding + settings
- `app/utils/onboardingChecklist.ts` (pure) + `app/utils/onboardingChecklist.test.ts`
- `app/composables/useOnboardingChecklist.ts`
- `app/components/owner/GettingStartedCard.vue`
- `app/components/owner/SettingsSetPasswordForm.vue`
- `vitest.config.ts`

**Frontend (modify)**
- `app/types/auth.ts`, `app/types/property.ts`, `app/schemas/property.ts`, `app/services/contracts/auth.ts`, `app/services/contracts/ownerSettings.ts`, `app/services/api/auth.ts`, `app/services/api/ownerSettings.ts`, `app/demo/auth.ts`, `app/demo/services/ownerSettings.ts`, `app/demo/data/properties.ts`, `app/demo/services/dashboard.ts`, `app/stores/auth.ts`, `app/middleware/auth.global.ts`, `app/composables/useEnv.ts`, `app/composables/useReports.ts`, `app/pages/auth/login.vue`, `app/pages/auth/register.vue`, `app/pages/owner/index.vue`, `app/pages/owner/reports.vue`, `app/pages/owner/properties/index.vue`, `app/pages/owner/properties/[id].vue`, `app/pages/owner/tenants/index.vue`, `app/components/owner/AddPropertyModal.vue`, `app/components/owner/PropertyDetailsForm.vue`, `app/components/owner/PropertyCard.vue`, `app/components/owner/SettingsPreferencesForm.vue`, `app/components/owner/SettingsProfileForm.vue`, `app/components/auth/DemoLoginShortcuts.vue`, `nuxt.config.ts`, `package.json`, `i18n/locales/en.json`, `i18n/locales/ms.json`

**Docs**: `docs/backend/API-SPEC.md`, `docs/frontend/API-MAP.md`, `docs/frontend/MOCK-POC.md`, `docs/frontend/UI-STANDARDS.md`, `.claude/CLAUDE.md`, root `.env.example`.

---

### Task 1: Users migration, model, `AuthUserResource`

**Files:**
- Create: `backend/database/migrations/2026_08_25_000001_add_google_and_onboarding_to_users_table.php`
- Modify: `backend/app/Models/User.php`, `backend/app/Http/Resources/AuthUserResource.php`, `backend/tests/Feature/AuthContractTest.php`

**Interfaces:**
- Produces: `users.google_id`, `users.avatar_url`, `users.purposes` (json), `users.onboarded_at`, `users.checklist_dismissed_at`; `AuthUserResource` keys `[id,name,email,phone,role,permissions,isSuperAdmin,hasPassword,avatarUrl,onboardedAt,purposes,checklistDismissedAt]`; `User::hasPassword(): bool`.

- [ ] **Step 1: Update the contract test to the new key set (failing)**

In `backend/tests/Feature/AuthContractTest.php` replace both `assertSame([...keys...])` lines with:

```php
$this->assertSame(AuthContractTest::AUTH_USER_KEYS, array_keys($res->json()));
```
and
```php
$this->assertSame(AuthContractTest::AUTH_USER_KEYS, array_keys($res->json('user')));
```
and add inside the class:

```php
public const AUTH_USER_KEYS = [
    'id', 'name', 'email', 'phone', 'role', 'permissions', 'isSuperAdmin',
    'hasPassword', 'avatarUrl', 'onboardedAt', 'purposes', 'checklistDismissedAt',
];

public function test_me_exposes_onboarding_fields_for_owner(): void
{
    Sanctum::actingAs(User::factory()->owner()->create([
        'purposes' => ['rental', 'own_stay'], 'onboarded_at' => now(),
    ]));
    $res = $this->getJson('/api/auth/me')->assertOk();
    $this->assertTrue($res->json('hasPassword'));
    $this->assertSame(['rental', 'own_stay'], $res->json('purposes'));
    $this->assertNotNull($res->json('onboardedAt'));
    $this->assertNull($res->json('checklistDismissedAt'));
}

public function test_me_returns_empty_purposes_for_tenant(): void
{
    Sanctum::actingAs(User::factory()->tenant()->create());
    $res = $this->getJson('/api/auth/me')->assertOk();
    $this->assertSame([], $res->json('purposes'));
    $this->assertNull($res->json('onboardedAt'));
}
```

- [ ] **Step 2: Run — expect FAIL** (`no such column: purposes` / key mismatch)

`docker exec roofly-backend php artisan test --filter=AuthContractTest`

- [ ] **Step 3: Migration**

`backend/database/migrations/2026_08_25_000001_add_google_and_onboarding_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar_url', 500)->nullable()->after('google_id');
            // Owner onboarding (spec 2026-08-23 google-login-owner-onboarding § 4).
            $table->json('purposes')->nullable()->after('notification_preferences');
            $table->timestamp('onboarded_at')->nullable()->after('purposes');
            $table->timestamp('checklist_dismissed_at')->nullable()->after('onboarded_at');
        });

        // Existing owners are never ambushed by the onboarding screen.
        DB::table('users')->where('role', 'owner')->whereNull('onboarded_at')
            ->update(['onboarded_at' => DB::raw('created_at'), 'purposes' => json_encode(['rental'])]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'avatar_url', 'purposes', 'onboarded_at', 'checklist_dismissed_at']);
        });
    }
};
```

(`password` is already nullable in the base migration — no change needed.)

- [ ] **Step 4: Model**

In `backend/app/Models/User.php` add to `$fillable` after `'notification_preferences',`:

```php
        'google_id',
        'avatar_url',
        'purposes',
        'onboarded_at',
        'checklist_dismissed_at',
```

Add to `casts()`:

```php
            'purposes'                 => 'array',
            'onboarded_at'             => 'datetime',
            'checklist_dismissed_at'   => 'datetime',
```

Add helper under `// ── Helpers`:

```php
    public function hasPassword(): bool
    {
        return $this->password !== null;
    }
```

- [ ] **Step 5: Resource**

Replace the return array in `backend/app/Http/Resources/AuthUserResource.php`:

```php
        $isOwner = $this->role === UserRole::OWNER;

        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'email'                => $this->email,
            'phone'                => $this->phone,
            'role'                 => $this->role?->value,
            'permissions'          => $permissions,
            'isSuperAdmin'         => (bool) $this->is_super_admin,
            'hasPassword'          => $this->password !== null,
            'avatarUrl'            => $this->avatar_url,
            'onboardedAt'          => $isOwner ? $this->onboarded_at?->toISOString() : null,
            'purposes'             => $isOwner ? ($this->purposes ?? []) : [],
            'checklistDismissedAt' => $isOwner ? $this->checklist_dismissed_at?->toISOString() : null,
        ];
```
Add `use App\Enums\UserRole;` at the top.

- [ ] **Step 6: Run — expect PASS**

`docker exec roofly-backend php artisan test --filter=AuthContractTest`

- [ ] **Step 7: Full suite sanity** — `docker exec roofly-backend php artisan test` (all green; `AdminResourcesTest` unaffected since admin resources don't use `AuthUserResource`).

---

### Task 2: `GoogleIdToken` verifier

**Files:**
- Create: `backend/app/Support/GoogleIdToken.php`, `backend/tests/Unit/GoogleIdTokenTest.php`
- Modify: `backend/config/services.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/.env.example`

**Interfaces:**
- Produces: `App\Support\GoogleIdToken::verify(string $credential): ?array` returning `['sub','email','name','picture']` or `null`. Container-bound with the configured client id so controllers type-hint it and tests `$this->mock(GoogleIdToken::class)`.

- [ ] **Step 1: Failing unit test**

`backend/tests/Unit/GoogleIdTokenTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\GoogleIdToken;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleIdTokenTest extends TestCase
{
    private function payload(array $over = []): array
    {
        return array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => 'client-123',
            'sub' => '10769150350006150715113082367',
            'email' => 'Owner@Example.com',
            'email_verified' => 'true',
            'exp' => (string) (time() + 600),
            'name' => 'Owner Person',
            'picture' => 'https://lh3.googleusercontent.com/a/pic',
        ], $over);
    }

    public function test_valid_token_returns_normalised_profile(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload())]);
        $out = (new GoogleIdToken('client-123'))->verify('tok');
        $this->assertSame([
            'sub' => '10769150350006150715113082367',
            'email' => 'owner@example.com',
            'name' => 'Owner Person',
            'picture' => 'https://lh3.googleusercontent.com/a/pic',
        ], $out);
    }

    public function test_rejects_wrong_audience(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload(['aud' => 'other']))]);
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));
    }

    public function test_rejects_unverified_email(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload(['email_verified' => 'false']))]);
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));
    }

    public function test_rejects_expired_and_bad_issuer_and_google_error(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload(['exp' => (string) (time() - 5)]))]);
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));

        Http::fake(['oauth2.googleapis.com/*' => Http::response($this->payload(['iss' => 'evil.example']))]);
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));

        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_token'], 400)]);
        $this->assertNull((new GoogleIdToken('client-123'))->verify('tok'));
    }
}
```

- [ ] **Step 2: Run — expect FAIL** (class not found)

`docker exec roofly-backend php artisan test --filter=GoogleIdTokenTest`

- [ ] **Step 3: Implement**

`backend/app/Support/GoogleIdToken.php`:

```php
<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Verifies a Google Identity Services ID token (the credential the GIS
 * button hands the SPA) via Google's tokeninfo endpoint. Zero dependencies;
 * bound in AppServiceProvider with the configured client id so it can be
 * swapped with a mock in tests.
 */
class GoogleIdToken
{
    private const ENDPOINT = 'https://oauth2.googleapis.com/tokeninfo';
    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function __construct(private readonly string $clientId) {}

    /** @return array{sub:string,email:string,name:string,picture:?string}|null */
    public function verify(string $credential): ?array
    {
        if ($this->clientId === '') {
            return null;
        }

        $res = Http::timeout(5)->get(self::ENDPOINT, ['id_token' => $credential]);
        if (! $res->ok()) {
            return null;
        }
        $p = $res->json();

        if (($p['aud'] ?? null) !== $this->clientId) return null;
        if (! in_array($p['iss'] ?? '', self::ISSUERS, true)) return null;
        if ((int) ($p['exp'] ?? 0) < time()) return null;
        if (($p['email_verified'] ?? 'false') !== 'true') return null;
        if (empty($p['sub']) || empty($p['email'])) return null;

        $email = strtolower($p['email']);

        return [
            'sub'     => (string) $p['sub'],
            'email'   => $email,
            'name'    => (string) ($p['name'] ?? $email),
            'picture' => isset($p['picture']) ? (string) $p['picture'] : null,
        ];
    }
}
```

`backend/config/services.php` — add before the closing `];`:

```php
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
    ],
```

`backend/app/Providers/AppServiceProvider.php` — in `register()` (create the method if only `boot()` exists):

```php
        $this->app->bind(\App\Support\GoogleIdToken::class, fn () =>
            new \App\Support\GoogleIdToken((string) config('services.google.client_id'))
        );
```

`backend/.env.example` — add after the `# Sanctum` block:

```
# Google sign-in (owners only). Same OAuth web client id as NUXT_PUBLIC_GOOGLE_CLIENT_ID.
GOOGLE_CLIENT_ID=
```

- [ ] **Step 4: Run — expect PASS** `docker exec roofly-backend php artisan test --filter=GoogleIdTokenTest`

---

### Task 3: `POST /auth/google` + password-login guard + audit constants

**Files:**
- Create: `backend/app/Http/Controllers/Api/Auth/GoogleLoginController.php`, `backend/tests/Feature/GoogleLoginTest.php`
- Modify: `backend/routes/api.php`, `backend/app/Http/Controllers/Api/Auth/LoginController.php`, `backend/app/Services/AuditLogger.php`

**Interfaces:**
- Consumes: `GoogleIdToken::verify` (Task 2); `AuthUserResource` (Task 1).
- Produces: `POST /api/auth/google {credential}` → `200 {user, token}` (same envelope as `/auth/login`), `401 {message}` invalid token, `403 {message, code: 'not_owner'}`; `POST /auth/login` → `422 errors.email` when the account has no password. `AuditLogger::AUTH_GOOGLE_LOGIN = 'auth.google_login'`, `AUTH_GOOGLE_REGISTER = 'auth.google_register'`.

- [ ] **Step 1: Failing feature test**

`backend/tests/Feature/GoogleLoginTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GoogleIdToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(?array $profile): void
    {
        $this->mock(GoogleIdToken::class, fn ($m) => $m->shouldReceive('verify')->andReturn($profile));
    }

    private function profile(string $email = 'new@example.com'): array
    {
        return ['sub' => 'g-123', 'email' => $email, 'name' => 'Google Owner', 'picture' => 'https://img/pic'];
    }

    public function test_creates_owner_and_logs_in(): void
    {
        $this->fakeGoogle($this->profile());
        $res = $this->postJson('/api/auth/google', ['credential' => 'tok'])->assertCreated();
        $this->assertSame(AuthContractTest::AUTH_USER_KEYS, array_keys($res->json('user')));
        $this->assertSame('owner', $res->json('user.role'));
        $this->assertFalse($res->json('user.hasPassword'));
        $this->assertNull($res->json('user.onboardedAt'));
        $this->assertNotEmpty($res->json('token'));
        $u = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('g-123', $u->google_id);
        $this->assertNotNull($u->email_verified_at);
        $this->assertSame('auth.google_register', Activity::latest('id')->first()->event);
    }

    public function test_links_existing_password_owner(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'old@example.com', 'onboarded_at' => now()]);
        $this->fakeGoogle($this->profile('old@example.com'));
        $res = $this->postJson('/api/auth/google', ['credential' => 'tok'])->assertOk();
        $this->assertSame($owner->id, $res->json('user.id'));
        $this->assertTrue($res->json('user.hasPassword'));
        $this->assertSame('g-123', $owner->fresh()->google_id);
        $this->assertSame(1, User::where('email', 'old@example.com')->count());
        $this->assertSame('auth.google_login', Activity::latest('id')->first()->event);
    }

    public function test_tenant_email_is_forbidden(): void
    {
        User::factory()->tenant()->create(['email' => 't@example.com']);
        $this->fakeGoogle($this->profile('t@example.com'));
        $this->postJson('/api/auth/google', ['credential' => 'tok'])
            ->assertForbidden()->assertJsonPath('code', 'not_owner');
    }

    public function test_invalid_token_is_unauthorized(): void
    {
        $this->fakeGoogle(null);
        $this->postJson('/api/auth/google', ['credential' => 'tok'])->assertUnauthorized();
        $this->assertSame(0, User::count());
    }

    public function test_credential_is_required(): void
    {
        $this->postJson('/api/auth/google', [])->assertStatus(422);
    }

    public function test_password_login_on_google_only_account_is_422(): void
    {
        User::factory()->owner()->create(['email' => 'g@example.com', 'password' => null, 'google_id' => 'g-1']);
        $this->postJson('/api/auth/login', ['email' => 'g@example.com', 'password' => 'whatever'])
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
    }
}
```

- [ ] **Step 2: Run — expect FAIL** (404 route) `docker exec roofly-backend php artisan test --filter=GoogleLoginTest`

- [ ] **Step 3: Audit constants**

In `backend/app/Services/AuditLogger.php` add after `ANALYTICS_EXPORTED`:

```php
    public const AUTH_GOOGLE_LOGIN         = 'auth.google_login';
    public const AUTH_GOOGLE_REGISTER      = 'auth.google_register';
    public const ACCOUNT_ONBOARDED         = 'account.onboarded';
    public const ACCOUNT_CHECKLIST_DISMISSED = 'account.checklist_dismissed';
    public const ACCOUNT_CHECKLIST_RESTORED  = 'account.checklist_restored';
    public const ACCOUNT_PASSWORD_SET      = 'account.password_set';
```
and append them to the `ACTIONS` array:
```php
        self::AUTH_GOOGLE_LOGIN, self::AUTH_GOOGLE_REGISTER, self::ACCOUNT_ONBOARDED,
        self::ACCOUNT_CHECKLIST_DISMISSED, self::ACCOUNT_CHECKLIST_RESTORED, self::ACCOUNT_PASSWORD_SET,
```

- [ ] **Step 4: Controller**

`backend/app/Http/Controllers/Api/Auth/GoogleLoginController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\GoogleIdToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Owner-only Google sign-in (spec 2026-08-23 § 3.3). The SPA posts the GIS
 * ID token; we verify it, auto-link by verified email, and start the same
 * session + token pair `/auth/login` does.
 */
class GoogleLoginController extends Controller
{
    public function store(Request $request, GoogleIdToken $google, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['credential' => 'required|string']);

        $profile = $google->verify($data['credential']);
        if ($profile === null) {
            return response()->json(['message' => 'Google sign-in failed.'], 401);
        }

        $user = User::where('email', $profile['email'])->first();
        $created = false;

        if ($user !== null && $user->role !== UserRole::OWNER) {
            return response()->json([
                'message' => 'This email belongs to a tenant account.',
                'code'    => 'not_owner',
            ], 403);
        }

        if ($user === null) {
            $user = User::create([
                'name'              => $profile['name'],
                'email'             => $profile['email'],
                'role'              => UserRole::OWNER,
                'password'          => null,
                'google_id'         => $profile['sub'],
                'avatar_url'        => $profile['picture'],
                'email_verified_at' => now(),
            ]);
            $created = true;
        } else {
            $user->forceFill([
                'google_id'         => $user->google_id ?? $profile['sub'],
                'avatar_url'        => $user->avatar_url ?? $profile['picture'],
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        if ($user->first_login_at === null) {
            $user->forceFill(['first_login_at' => now()])->saveQuietly();
        }

        $audit->record($created ? AuditLogger::AUTH_GOOGLE_REGISTER : AuditLogger::AUTH_GOOGLE_LOGIN, $user);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => (new AuthUserResource($user->fresh()))->resolve(),
            'token' => $token,
        ], $created ? 201 : 200);
    }
}
```

- [ ] **Step 5: Route** — in `backend/routes/api.php`, inside `Route::prefix('auth')->group(...)` after the `login` line:

```php
    Route::post('google',        [\App\Http\Controllers\Api\Auth\GoogleLoginController::class, 'store'])->middleware('throttle:10,1');
```

- [ ] **Step 6: Password-login guard** — in `LoginController::store`, right after `$credentials = $request->validate([...]);`:

```php
        $existing = User::where('email', $credentials['email'])->first();
        if ($existing !== null && $existing->isOwner() && ! $existing->hasPassword()) {
            return response()->json([
                'message' => 'This account signs in with Google.',
                'errors'  => ['email' => ['This account signs in with Google.']],
            ], 422);
        }
```
Add `use App\Models\User;`.

- [ ] **Step 7: Run — expect PASS** `docker exec roofly-backend php artisan test --filter=GoogleLoginTest`, then `--filter=AuthContractTest`, then the `Admin/AdminAuditTest` (the ACTIONS list is validated there) — all green.

---

### Task 4: Account onboarding / checklist / set-password endpoints

**Files:**
- Create: `backend/app/Http/Requests/CompleteOnboardingRequest.php`, `backend/tests/Feature/AccountOnboardingTest.php`
- Modify: `backend/app/Http/Controllers/Api/Owner/AccountController.php`, `backend/routes/api.php`

**Interfaces:**
- Produces: `PATCH /api/account/onboarding {purposes: string[]}` → `AuthUserResource`; `PATCH /api/account/checklist {dismissed: bool}` → `AuthUserResource`; `POST /api/account/password {password, password_confirmation}` → `AuthUserResource` (422 if a password already exists).

- [ ] **Step 1: Failing test**

`backend/tests/Feature/AccountOnboardingTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_onboarding_sets_purposes_and_timestamp(): void
    {
        $u = User::factory()->owner()->create(['onboarded_at' => null, 'purposes' => null]);
        Sanctum::actingAs($u);
        $res = $this->patchJson('/api/account/onboarding', ['purposes' => ['rental', 'investment']])->assertOk();
        $this->assertSame(['rental', 'investment'], $res->json('purposes'));
        $this->assertNotNull($res->json('onboardedAt'));
        $this->assertSame(AuthContractTest::AUTH_USER_KEYS, array_keys($res->json()));
    }

    public function test_onboarding_is_idempotent_and_validates(): void
    {
        $u = User::factory()->owner()->create(['onboarded_at' => now()->subDay(), 'purposes' => ['rental']]);
        Sanctum::actingAs($u);
        $first = $u->onboarded_at;
        $this->patchJson('/api/account/onboarding', ['purposes' => ['own_stay']])->assertOk();
        $this->assertTrue($first->equalTo($u->fresh()->onboarded_at));
        $this->assertSame(['own_stay'], $u->fresh()->purposes);

        $this->patchJson('/api/account/onboarding', ['purposes' => []])->assertStatus(422);
        $this->patchJson('/api/account/onboarding', ['purposes' => ['hotel']])->assertStatus(422);
    }

    public function test_checklist_dismiss_and_restore(): void
    {
        Sanctum::actingAs($u = User::factory()->owner()->create());
        $this->patchJson('/api/account/checklist', ['dismissed' => true])->assertOk();
        $this->assertNotNull($u->fresh()->checklist_dismissed_at);
        $res = $this->patchJson('/api/account/checklist', ['dismissed' => false])->assertOk();
        $this->assertNull($res->json('checklistDismissedAt'));
    }

    public function test_set_password_only_when_missing(): void
    {
        Sanctum::actingAs($u = User::factory()->owner()->create(['password' => null, 'google_id' => 'g']));
        $res = $this->postJson('/api/account/password', ['password' => 'secret123', 'password_confirmation' => 'secret123'])->assertOk();
        $this->assertTrue($res->json('hasPassword'));
        $this->assertTrue(Hash::check('secret123', $u->fresh()->password));

        $this->postJson('/api/account/password', ['password' => 'another12', 'password_confirmation' => 'another12'])->assertStatus(422);
    }

    public function test_tenant_cannot_hit_owner_account_routes(): void
    {
        Sanctum::actingAs(User::factory()->tenant()->create());
        $this->patchJson('/api/account/onboarding', ['purposes' => ['rental']])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run — expect FAIL** `docker exec roofly-backend php artisan test --filter=AccountOnboardingTest`

- [ ] **Step 3: FormRequest**

`backend/app/Http/Requests/CompleteOnboardingRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteOnboardingRequest extends FormRequest
{
    public const PURPOSES = ['rental', 'own_stay', 'investment'];

    public function authorize(): bool
    {
        return true; // role:owner middleware gates the route
    }

    public function rules(): array
    {
        return [
            'purposes'   => 'required|array|min:1',
            'purposes.*' => 'in:' . implode(',', self::PURPOSES),
        ];
    }
}
```

- [ ] **Step 4: Controller methods** — append to `AccountController`:

```php
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
```
Add imports: `use App\Http\Requests\CompleteOnboardingRequest; use App\Http\Resources\AuthUserResource; use App\Services\AuditLogger;`.

- [ ] **Step 5: Routes** — under `// Account / settings` in `routes/api.php`:

```php
        Route::patch('account/onboarding',             [\App\Http\Controllers\Api\Owner\AccountController::class, 'completeOnboarding']);
        Route::patch('account/checklist',              [\App\Http\Controllers\Api\Owner\AccountController::class, 'updateChecklist']);
        Route::post('account/password',                [\App\Http\Controllers\Api\Owner\AccountController::class, 'setPassword']);
```

- [ ] **Step 6: Run — expect PASS** `docker exec roofly-backend php artisan test --filter=AccountOnboardingTest`

---

### Task 5: Property `purpose` (backend)

**Files:**
- Create: `backend/database/migrations/2026_08_25_000002_add_purpose_to_properties_table.php`, `backend/app/Enums/PropertyPurpose.php`, `backend/tests/Feature/PropertyPurposeTest.php`
- Modify: `backend/app/Models/Property.php`, `backend/app/Http/Resources/PropertyResource.php`, `backend/app/Http/Requests/StorePropertyRequest.php`, `backend/app/Http/Requests/UpdatePropertyRequest.php`, `backend/database/factories/PropertyFactory.php`, `backend/database/seeders/DemoSeeder.php`

**Interfaces:**
- Produces: `properties.purpose` string column default `rental`; `PropertyResource.purpose`; `App\Enums\PropertyPurpose {RENTAL, OWN_STAY, INVESTMENT}`; `Property::scopeRental()`.

- [ ] **Step 1: Failing test**

`backend/tests/Feature/PropertyPurposeTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyPurposeTest extends TestCase
{
    use RefreshDatabase;

    private array $tier1 = [
        'name' => 'Home', 'type' => 'landed', 'address' => '1 Jalan Satu',
        'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40000',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->owner()->create());
    }

    public function test_store_accepts_purpose_and_defaults_to_rental(): void
    {
        $res = $this->postJson('/api/properties', $this->tier1 + ['purpose' => 'own_stay'])->assertCreated();
        $this->assertSame('own_stay', $res->json('purpose'));

        $res = $this->postJson('/api/properties', ['name' => 'Two'] + $this->tier1)->assertCreated();
        $this->assertSame('rental', $res->json('purpose'));
    }

    public function test_store_rejects_unknown_purpose(): void
    {
        $this->postJson('/api/properties', $this->tier1 + ['purpose' => 'hotel'])->assertStatus(422);
    }

    public function test_update_changes_purpose(): void
    {
        $p = Property::factory()->create(['owner_id' => auth()->id()]);
        $res = $this->patchJson("/api/properties/{$p->id}", ['purpose' => 'investment'])->assertOk();
        $this->assertSame('investment', $res->json('purpose'));
        $this->assertSame('investment', $p->fresh()->purpose->value);
    }

    public function test_rental_scope(): void
    {
        Property::factory()->create(['owner_id' => auth()->id(), 'purpose' => 'own_stay']);
        Property::factory()->create(['owner_id' => auth()->id()]);
        $this->assertSame(1, Property::rental()->count());
    }
}
```

- [ ] **Step 2: Run — expect FAIL** `docker exec roofly-backend php artisan test --filter=PropertyPurposeTest`

- [ ] **Step 3: Enum + migration + model**

`backend/app/Enums/PropertyPurpose.php`:

```php
<?php

namespace App\Enums;

enum PropertyPurpose: string
{
    case RENTAL = 'rental';
    case OWN_STAY = 'own_stay';
    case INVESTMENT = 'investment';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
```

`backend/database/migrations/2026_08_25_000002_add_purpose_to_properties_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // rental | own_stay | investment — string (not DB enum) so sqlite ALTERs cleanly.
            $table->string('purpose', 20)->default('rental')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
```

`backend/app/Models/Property.php`: add `'purpose',` to `$fillable` after `'type',`; add `'purpose' => PropertyPurpose::class,` to `casts()`; add `use App\Enums\PropertyPurpose;`; add a scope:

```php
    public function scopeRental($query)
    {
        return $query->where('purpose', PropertyPurpose::RENTAL->value);
    }
```

- [ ] **Step 4: Requests, resource, factory**

`StorePropertyRequest::rules()` — add after `'type'`:
```php
            'purpose'  => ['sometimes', Rule::in(\App\Enums\PropertyPurpose::values())],
```
`UpdatePropertyRequest::rules()` — add after `'type'`:
```php
            'purpose'       => ['sometimes', Rule::in(\App\Enums\PropertyPurpose::values())],
```
`PropertyResource` — add after `'type'`:
```php
            'purpose'       => $this->purpose?->value ?? 'rental',
```
`PropertyFactory::definition()` — add `'purpose'  => 'rental',`.

`DemoSeeder::seedProperties()` — add `'purpose' => 'rental',` to each of the five `Property::updateOrCreate` attribute arrays (default covers it, but explicit keeps the seeder honest with the frontend mock). No own-stay property in the DB seed — `DemoSeederTest` pins counts; the own-stay demo property lives in the frontend mock only (Task 10).

- [ ] **Step 5: Run — expect PASS** `docker exec roofly-backend php artisan test --filter="PropertyPurposeTest|PropertyContractTest|AdminResourcesTest|DemoSeederTest"`

---

### Task 6: Dashboard excludes non-rental properties (backend)

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Owner/DashboardController.php`, `backend/tests/Feature/DashboardContractTest.php`

**Interfaces:**
- Consumes: `Property::rental()` (Task 5).
- Produces: `isEmpty` still counts *all* properties; every other stat/feed uses rental-property ids only.

- [ ] **Step 1: Failing test** — append to `DashboardContractTest` (reuse its existing owner/actingAs setup; if the class has none, create `$this->owner` in `setUp()` exactly as `PropertyContractTest` does):

```php
    public function test_non_rental_properties_are_excluded_from_occupancy_but_not_is_empty(): void
    {
        $home = \App\Models\Property::factory()->create(['owner_id' => $this->owner->id, 'purpose' => 'own_stay']);
        \App\Models\Unit::factory()->create(['property_id' => $home->id, 'status' => 'vacant']);

        $res = $this->getJson('/api/dashboard')->assertOk();
        $this->assertFalse($res->json('isEmpty'));
        $this->assertSame(0, $res->json('stats.unitCount'));

        $rental = \App\Models\Property::factory()->create(['owner_id' => $this->owner->id]);
        \App\Models\Unit::factory()->create(['property_id' => $rental->id, 'status' => 'occupied']);
        $res = $this->getJson('/api/dashboard')->assertOk();
        $this->assertSame(1, $res->json('stats.unitCount'));
        $this->assertSame(100, $res->json('stats.occupancyPct'));
    }
```
(Check `UnitFactory` exists under `database/factories`; it does — `UnitContractTest` uses it. If `status` isn't fillable via factory state, pass `'status' => \App\Enums\UnitStatus::OCCUPIED`.)

- [ ] **Step 2: Run — expect FAIL** `docker exec roofly-backend php artisan test --filter=DashboardContractTest`

- [ ] **Step 3: Implement** — in `DashboardController::index` replace

```php
        $propertyIds = Property::where('owner_id', $ownerId)->pluck('id');
        $isEmpty = $propertyIds->isEmpty();
```
with
```php
        // isEmpty looks at every property (an own-stay home is still a property);
        // everything else is rental-only (spec 2026-08-23 § 5.3).
        $isEmpty = ! Property::where('owner_id', $ownerId)->exists();
        $propertyIds = Property::where('owner_id', $ownerId)->rental()->pluck('id');
```

- [ ] **Step 4: Run — expect PASS** `docker exec roofly-backend php artisan test --filter=DashboardContractTest`, then the full suite `docker exec roofly-backend php artisan test`.

---

### Task 7: Frontend types, contracts, both adapters

**Files:**
- Modify: `frontend/app/types/auth.ts`, `frontend/app/types/property.ts`, `frontend/app/schemas/property.ts`, `frontend/app/services/contracts/auth.ts`, `frontend/app/services/contracts/ownerSettings.ts`, `frontend/app/services/api/auth.ts`, `frontend/app/services/api/ownerSettings.ts`, `frontend/app/demo/auth.ts`, `frontend/app/demo/services/ownerSettings.ts`, `frontend/app/demo/data/properties.ts`, `frontend/app/stores/auth.ts`

**Interfaces:**
- Produces: `OwnerPurpose`, `PropertyPurpose` types; `AuthUser.{hasPassword, avatarUrl, onboardedAt, purposes, checklistDismissedAt}`; `Property.purpose`; `AuthAdapter.loginWithGoogle(credential)`; `OwnerSettingsService.{completeOnboarding, setChecklistDismissed, setPassword}`; `useAuthStore().loginWithGoogle(credential)` and `useAuthStore().setUser(user)`; `demoSession.update(patch)` helper in `demo/auth.ts`.

- [ ] **Step 1: Types**

`types/auth.ts` — replace the interface:

```ts
export type OwnerPurpose = "rental" | "own_stay" | "investment";
export const OWNER_PURPOSES: readonly OwnerPurpose[] = ["rental", "own_stay", "investment"] as const;

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
  /** Admin only — `[]` for owners and tenants. Super-admins get the full list. */
  permissions: AdminPermission[];
  isSuperAdmin: boolean;
  /** False for Google-only accounts — Settings → Profile offers "Set a password". */
  hasPassword: boolean;
  avatarUrl: string | null;
  /** Owners only. `null` ⇒ the route guard sends them to /owner/onboarding. */
  onboardedAt: string | null;
  /** Owners only — `[]` until onboarded. */
  purposes: OwnerPurpose[];
  checklistDismissedAt: string | null;
}
```

`types/property.ts` — after `PropertyType` add:
```ts
export type PropertyPurpose = "rental" | "own_stay" | "investment";
```
In `Property` after `type: PropertyType;` add `purpose: PropertyPurpose;`. In `PropertyInput` add `| "purpose"` to the `Pick` list.

`schemas/property.ts` — add `const propertyPurposeSchema = z.enum(["rental", "own_stay", "investment"]);` after `propertyTypeSchema`, add `purpose: propertyPurposeSchema,` to `propertyInputSchema` and to `propertyDetailsFormSchema` (after `type`).

`demo/data/properties.ts` — add `purpose: "rental",` directly after `type:` on all five existing properties.

- [ ] **Step 2: Contracts**

`services/contracts/auth.ts` — add to `AuthAdapter` after `register`:
```ts
  /** Owner-only Google sign-in (GIS ID token). Creates or links by verified email. */
  loginWithGoogle(credential: string): Promise<AuthUser>;
```

`services/contracts/ownerSettings.ts`:
```ts
import type { AuthUser, OwnerPurpose } from "~/types/auth";
// …existing imports
export interface OwnerSettingsService {
  // …existing
  /** Onboarding answer; idempotent — re-calling updates purposes only. Returns the refreshed AuthUser. */
  completeOnboarding(input: { purposes: OwnerPurpose[] }): Promise<AuthUser>;
  setChecklistDismissed(dismissed: boolean): Promise<AuthUser>;
  /** Only for accounts with `hasPassword === false`. */
  setPassword(password: string): Promise<AuthUser>;
}
```

- [ ] **Step 3: API adapters**

`services/api/auth.ts` — add after `register`:
```ts
  async loginWithGoogle(credential) {
    const { request } = useApi();
    await request("/../sanctum/csrf-cookie");
    const res = await request<{ user: AuthUser }>("/auth/google", {
      method: "POST",
      body: { credential },
    });
    return res.user;
  },
```

`services/api/ownerSettings.ts` — add:
```ts
  completeOnboarding: (input) =>
    useApi().request<AuthUser>("/account/onboarding", { method: "PATCH", body: input }),

  setChecklistDismissed: (dismissed) =>
    useApi().request<AuthUser>("/account/checklist", { method: "PATCH", body: { dismissed } }),

  setPassword: (password) =>
    useApi().request<AuthUser>("/account/password", {
      method: "POST",
      body: { password, password_confirmation: password },
    }),
```
with `import type { AuthUser } from "~/types/auth";`.

- [ ] **Step 4: Demo adapters**

`demo/auth.ts`:
- Add a shared owner-field block and extend every user literal. Define near the top:
```ts
const OWNER_DEFAULTS = {
  hasPassword: true,
  avatarUrl: null,
  // The stock demo owner is "seasoned": onboarded long ago, checklist dismissed
  // so the curated dashboard/tour is untouched. The Google-demo path resets both.
  onboardedAt: "2026-01-12T09:00:00Z",
  purposes: ["rental"] as OwnerPurpose[],
  checklistDismissedAt: "2026-01-12T09:05:00Z",
};
const NON_OWNER_DEFAULTS = {
  hasPassword: true,
  avatarUrl: null,
  onboardedAt: null,
  purposes: [] as OwnerPurpose[],
  checklistDismissedAt: null,
};
```
  (`import type { AuthUser, OwnerPurpose } from "~/types/auth";`). Spread `...OWNER_DEFAULTS` into the owner literals in `customerUserFor` and `register`, and `...NON_OWNER_DEFAULTS` into the tenant and both admin literals.
- Add the session helper and the Google method:
```ts
/** Lets demo services mutate the persisted demo user (onboarding, checklist). */
export const demoSession = {
  current: (): AuthUser | null => restore(),
  update(patch: Partial<AuthUser>): AuthUser {
    const next = { ...(restore() as AuthUser), ...patch };
    persist(next);
    return next;
  },
};
```
  and inside `demoAuth`:
```ts
  async loginWithGoogle() {
    await delay();
    const user: AuthUser = {
      id: DEMO_OWNER_ID,
      name: "Cik Aminah",
      email: "aminah.google@roofly.my",
      phone: null,
      role: "owner",
      permissions: [],
      isSuperAdmin: false,
      ...OWNER_DEFAULTS,
      hasPassword: false,
      avatarUrl: null,
      onboardedAt: null,
      purposes: [],
      checklistDismissedAt: null,
    };
    persist(user);
    return user;
  },
```

`demo/services/ownerSettings.ts` — `import { demoSession } from "~/demo/auth";` and add:
```ts
  async completeOnboarding({ purposes }) {
    const current = demoSession.current();
    return demoSession.update({
      purposes: [...new Set(purposes)],
      onboardedAt: current?.onboardedAt ?? new Date().toISOString(),
    });
  },

  async setChecklistDismissed(dismissed) {
    return demoSession.update({
      checklistDismissedAt: dismissed ? new Date().toISOString() : null,
    });
  },

  async setPassword() {
    return demoSession.update({ hasPassword: true });
  },
```

- [ ] **Step 5: Store** — in `stores/auth.ts` actions add:
```ts
    async loginWithGoogle(credential: string) {
      this.loading = true;
      try {
        this.user = await adapter().loginWithGoogle(credential);
      } finally {
        this.loading = false;
      }
    },

    /** Replace the session user after an account mutation (onboarding, checklist, password). */
    setUser(user: AuthUser) {
      this.user = user;
    },
```

- [ ] **Step 6: Typecheck** — `docker exec roofly-frontend npm run typecheck`. Expect exactly the 5 known errors. TypeScript will flag any `AuthUser` literal you forgot to extend (e.g. in `demo/auth.ts`) — fix those.

---

### Task 8: Env flag + `GoogleSignInButton` + login/register wiring

**Files:**
- Create: `frontend/app/components/auth/GoogleSignInButton.vue`
- Modify: `frontend/nuxt.config.ts`, `frontend/app/composables/useEnv.ts`, `frontend/app/pages/auth/login.vue`, `frontend/app/pages/auth/register.vue`, `frontend/app/components/auth/DemoLoginShortcuts.vue`, `frontend/i18n/locales/en.json`, `frontend/i18n/locales/ms.json`, root `.env.example`

**Interfaces:**
- Consumes: `useAuthStore().loginWithGoogle` (Task 7).
- Produces: `useEnv().features.googleLogin`, `useEnv().googleClientId`; `<GoogleSignInButton @credential="…" @unavailable="…" />`.

- [ ] **Step 1: Runtime config + env flag**

`nuxt.config.ts` `runtimeConfig.public` — add:
```ts
      // Google sign-in (owners only, spec 2026-08-23). Empty ⇒ button hidden.
      googleClientId: process.env.NUXT_PUBLIC_GOOGLE_CLIENT_ID ?? "",
```
`composables/useEnv.ts` — inside the returned object:
```ts
    googleClientId: config.public.googleClientId as string,
```
and in `features`:
```ts
      // Google sign-in needs a client id and is never shown in demo (demo has
      // its own "Continue with Google (demo)" shortcut instead).
      googleLogin: !isDemo && Boolean(config.public.googleClientId),
```
Root `.env.example` — after the `NUXT_PUBLIC_TRACKING` block:
```
# Google sign-in for owners (OAuth web client id). Empty hides the button.
# Demo never shows it. Must match backend GOOGLE_CLIENT_ID.
NUXT_PUBLIC_GOOGLE_CLIENT_ID=
```

- [ ] **Step 2: Button component**

`components/auth/GoogleSignInButton.vue`:

```vue
<script setup lang="ts">
import { onMounted, ref, watch } from "vue";

/**
 * Renders Google Identity Services' own button and emits the ID token.
 * Loads the GIS script lazily; if it can't load (blocked, offline) emits
 * `unavailable` so the page can show a muted fallback line.
 */
const emit = defineEmits<{ credential: [token: string]; unavailable: [] }>();

const { googleClientId } = useEnv();
const { locale } = useI18n();
const { isDark } = useTheme();
const host = ref<HTMLDivElement | null>(null);
const failed = ref(false);

type GoogleId = {
  initialize: (o: { client_id: string; callback: (r: { credential: string }) => void; ux_mode: "popup" }) => void;
  renderButton: (el: HTMLElement, o: Record<string, string | number>) => void;
};
const gis = (): GoogleId | null =>
  (window as unknown as { google?: { accounts?: { id?: GoogleId } } }).google?.accounts?.id ?? null;

const loadScript = () =>
  new Promise<void>((resolve, reject) => {
    if (gis()) return resolve();
    const existing = document.querySelector<HTMLScriptElement>('script[src^="https://accounts.google.com/gsi/client"]');
    if (existing) {
      existing.addEventListener("load", () => resolve());
      existing.addEventListener("error", () => reject(new Error("gis")));
      return;
    }
    const s = document.createElement("script");
    s.src = "https://accounts.google.com/gsi/client";
    s.async = true;
    s.defer = true;
    s.onload = () => resolve();
    s.onerror = () => reject(new Error("gis"));
    document.head.appendChild(s);
  });

const render = () => {
  const id = gis();
  if (!id || !host.value) return;
  host.value.innerHTML = "";
  id.renderButton(host.value, {
    type: "standard",
    theme: isDark.value ? "filled_black" : "outline",
    size: "large",
    text: "continue_with",
    shape: "rectangular",
    width: host.value.clientWidth || 320,
    locale: locale.value,
  });
};

onMounted(async () => {
  try {
    await loadScript();
    gis()!.initialize({
      client_id: googleClientId,
      ux_mode: "popup",
      callback: (r) => emit("credential", r.credential),
    });
    render();
  } catch {
    failed.value = true;
    emit("unavailable");
  }
});

watch([locale, isDark], () => render());
</script>

<template>
  <div class="w-full">
    <div v-if="!failed" ref="host" class="flex w-full justify-center" />
    <p v-else class="text-center text-caption text-ink-muted">
      {{ $t("auth.google.unavailable") }}
    </p>
  </div>
</template>
```
Check `useTheme()` exposes `isDark` (grep `composables/useTheme.ts`); if it only exposes `theme`/`resolved`, derive `const isDark = computed(() => resolved.value === "dark")` from whichever ref it returns.

- [ ] **Step 3: Login page** — in `pages/auth/login.vue` script add:

```ts
import GoogleSignInButton from "~/components/auth/GoogleSignInButton.vue";
const { features } = useEnv();
const googleError = ref<string | null>(null);

const onGoogle = async (credential: string) => {
  googleError.value = null;
  try {
    await auth.loginWithGoogle(credential);
    await navigateTo("/owner");
  } catch (err) {
    const code = (err as { data?: { code?: string } })?.data?.code;
    googleError.value = code === "not_owner" ? t("auth.google.notOwner") : t("auth.google.failed");
  }
};
```
Template — insert between `</header>` and `<form`:
```vue
    <div v-if="features.googleLogin" class="mb-6 space-y-3">
      <GoogleSignInButton @credential="onGoogle" />
      <p v-if="googleError" class="text-center text-caption text-accent" role="alert">{{ googleError }}</p>
      <div class="flex items-center gap-3 text-micro uppercase tracking-wider text-ink-faint">
        <span class="h-px flex-1 bg-line-passive" />
        {{ t("auth.google.or") }}
        <span class="h-px flex-1 bg-line-passive" />
      </div>
    </div>
```
The owner lands on `/owner`; the guard (Task 9) forwards un-onboarded owners to `/owner/onboarding`.

- [ ] **Step 4: Register page** — same script block (without the `auth.isTenant` branch — Google is owners only) and the same template block above the form. Keep `track("register", …)` for the password path only; for Google add after a successful `loginWithGoogle`:
```ts
    track("register", { email: auth.user?.email ?? "", userId: auth.user?.id ?? "" });
```
(Only when `auth.user?.onboardedAt === null` — a brand-new account — so re-logins via Google don't count as registrations.)

- [ ] **Step 5: Demo shortcut** — in `DemoLoginShortcuts.vue` extend `loadingRole` type to `"owner" | "tenant" | "google" | null` and add:
```ts
const enterGoogle = async () => {
  track("demo_enter", { role: "owner_google" });
  loadingRole.value = "google";
  await auth.loginWithGoogle("demo");
  await navigateTo("/owner");
  loadingRole.value = null;
};
```
Template — after the tenant button inside the grid add a third full-width button:
```vue
      <Button
        variant="ghost"
        size="sm"
        class="col-span-2"
        :loading="loadingRole === 'google'"
        :disabled="loadingRole !== null"
        @click="enterGoogle"
      >
        <Icon name="Sparkles" :size="16" />
        {{ t("demo.shortcuts.continueWithGoogle") }}
      </Button>
```
(`import Icon from "~/components/ui/Icon.vue";` — or a lucide import matching the file's existing style.)

- [ ] **Step 6: i18n** — `en.json` under `auth` add:
```json
    "google": {
      "or": "or",
      "unavailable": "Google sign-in is unavailable right now — use your email below.",
      "failed": "Google sign-in failed. Please try again.",
      "notOwner": "This email is a tenant account. Ask your landlord for your invite link."
    },
```
under `demo.shortcuts`: `"continueWithGoogle": "Continue with Google (demo)"`.
`ms.json` `auth.google`:
```json
    "google": {
      "or": "atau",
      "unavailable": "Log masuk Google tidak tersedia buat masa ini — gunakan e-mel anda di bawah.",
      "failed": "Log masuk Google gagal. Sila cuba lagi.",
      "notOwner": "E-mel ini ialah akaun penyewa. Minta pautan jemputan daripada tuan rumah anda."
    },
```
and `"continueWithGoogle": "Teruskan dengan Google (demo)"`.

- [ ] **Step 7: Verify** — `docker exec roofly-frontend npm run typecheck` (5 known errors only). Then `docker logs --tail 50 roofly-frontend` for Vite SFC errors (typecheck is blind to some template errors — see memory note). Manual check is Baihaqie's: with `NUXT_PUBLIC_GOOGLE_CLIENT_ID` unset the login page is unchanged; in demo the third shortcut appears.

---

### Task 9: Onboarding page + route guard

**Files:**
- Create: `frontend/app/pages/owner/onboarding.vue`, `frontend/app/components/owner/OwnerPurposePicker.vue`
- Modify: `frontend/app/middleware/auth.global.ts`, `frontend/i18n/locales/en.json`, `frontend/i18n/locales/ms.json`

**Interfaces:**
- Consumes: `useOwnerSettings().completeOnboarding`, `useAuthStore().setUser` (Task 7).
- Produces: `<OwnerPurposePicker v-model="purposes" />` (multi-select, `OwnerPurpose[]`), reused in Task 14.

- [ ] **Step 1: Guard** — in `middleware/auth.global.ts` append after the `inWrongShell` block:

```ts
  // Owner onboarding (spec 2026-08-23 § 4.1): un-onboarded owners see the
  // one-screen onboarding before anything else in /owner; onboarded owners
  // can't revisit it (Settings → Preferences edits the answer).
  if (isOwnerArea && auth.isOwner) {
    const needsOnboarding = auth.user?.onboardedAt === null;
    const onOnboarding = to.path === "/owner/onboarding";
    if (needsOnboarding && !onOnboarding) return navigateTo("/owner/onboarding");
    if (!needsOnboarding && onOnboarding) return navigateTo("/owner");
  }
```

- [ ] **Step 2: Picker component**

`components/owner/OwnerPurposePicker.vue`:

```vue
<script setup lang="ts">
import { OWNER_PURPOSES, type OwnerPurpose } from "~/types/auth";
import Icon from "~/components/ui/Icon.vue";

const model = defineModel<OwnerPurpose[]>({ required: true });
const { t } = useI18n();

const icons: Record<OwnerPurpose, string> = {
  rental: "KeyRound",
  own_stay: "Home",
  investment: "TrendingUp",
};

const toggle = (p: OwnerPurpose) => {
  model.value = model.value.includes(p)
    ? model.value.filter((x) => x !== p)
    : [...model.value, p];
};
</script>

<template>
  <div class="grid grid-cols-1 gap-3" role="group">
    <button
      v-for="p in OWNER_PURPOSES"
      :key="p"
      type="button"
      :aria-pressed="model.includes(p)"
      class="flex items-start gap-4 rounded-lg border p-4 text-left outline-none transition focus-visible:shadow-focus"
      :class="model.includes(p)
        ? 'border-ink bg-surface-raised'
        : 'border-line-passive bg-surface-page hover:border-line-interactive'"
      @click="toggle(p)"
    >
      <span
        class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-md"
        :class="model.includes(p) ? 'bg-ink text-surface-page' : 'bg-line-passive text-ink-muted'"
      >
        <Icon :name="icons[p]" :size="18" />
      </span>
      <span class="min-w-0">
        <span class="block text-body font-semibold text-ink">{{ t(`owner.purposes.${p}.title`) }}</span>
        <span class="mt-0.5 block text-caption text-ink-muted">{{ t(`owner.purposes.${p}.help`) }}</span>
      </span>
    </button>
  </div>
</template>
```
`Icon.vue` takes a lucide name string; confirm `KeyRound`, `Home`, `TrendingUp` are accepted by its `name` prop type (it's a lucide key union or string — check the file; if it's a union, add any missing names the way existing ones are added).

- [ ] **Step 3: Page**

`pages/owner/onboarding.vue`:

```vue
<script setup lang="ts">
import { ref } from "vue";
import Button from "~/components/ui/Button.vue";
import OwnerPurposePicker from "~/components/owner/OwnerPurposePicker.vue";
import { useToast } from "~/composables/useToast";
import type { OwnerPurpose } from "~/types/auth";

definePageMeta({ layout: "auth" });

const { t } = useI18n();
useHead({ title: () => t("owner.onboarding.title") });
const { show } = useToast();
const auth = useAuthStore();

const purposes = ref<OwnerPurpose[]>([]);
const submitting = ref(false);

const finish = async (chosen: OwnerPurpose[]) => {
  submitting.value = true;
  try {
    const user = await useOwnerSettings().completeOnboarding({ purposes: chosen });
    auth.setUser(user);
    await navigateTo("/owner");
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("owner.onboarding.title") }}
      </h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("owner.onboarding.subtitle") }}</p>
    </header>

    <OwnerPurposePicker v-model="purposes" />

    <div class="mt-6 space-y-3">
      <Button
        variant="primary"
        size="lg"
        block
        :disabled="purposes.length === 0"
        :loading="submitting"
        @click="finish(purposes)"
      >
        {{ t("owner.onboarding.continue") }}
      </Button>
      <button
        type="button"
        class="block w-full text-center text-caption text-ink-muted underline underline-offset-4 hover:text-ink"
        :disabled="submitting"
        @click="finish(['rental'])"
      >
        {{ t("owner.onboarding.skip") }}
      </button>
    </div>
  </div>
</template>
```

- [ ] **Step 4: i18n** — `en.json` under `owner` add:

```json
    "purposes": {
      "rental": { "title": "Rental", "help": "Units with tenants, rent collection, agreements." },
      "own_stay": { "title": "Own stay", "help": "The home you live in — ownership, loan, bills." },
      "investment": { "title": "Investment", "help": "Held for value, not rented right now." }
    },
    "onboarding": {
      "title": "What will you manage in Roofly?",
      "subtitle": "Pick everything that applies. You can change this later in Settings.",
      "continue": "Continue",
      "skip": "Skip for now"
    },
```
`ms.json`:
```json
    "purposes": {
      "rental": { "title": "Sewaan", "help": "Unit dengan penyewa, kutipan sewa, perjanjian." },
      "own_stay": { "title": "Kediaman sendiri", "help": "Rumah yang anda diami — pemilikan, pinjaman, bil." },
      "investment": { "title": "Pelaburan", "help": "Disimpan untuk nilai, tidak disewakan buat masa ini." }
    },
    "onboarding": {
      "title": "Apa yang anda akan urus di Roofly?",
      "subtitle": "Pilih semua yang berkenaan. Anda boleh ubah kemudian di Tetapan.",
      "continue": "Teruskan",
      "skip": "Langkau buat masa ini"
    },
```

- [ ] **Step 5: Verify** — typecheck + dev-server log clean. Flow to hand Baihaqie: demo → "Continue with Google (demo)" → onboarding → pick → dashboard; refresh `/owner/onboarding` afterwards → bounced to `/owner`.

---

### Task 10: Property purpose UI + demo own-stay property

**Files:**
- Modify: `frontend/app/components/owner/AddPropertyModal.vue`, `frontend/app/components/owner/PropertyDetailsForm.vue`, `frontend/app/components/owner/PropertyCard.vue`, `frontend/app/demo/data/properties.ts`, `frontend/app/demo/data/units.ts`, `frontend/i18n/locales/en.json`, `frontend/i18n/locales/ms.json`

**Interfaces:**
- Consumes: `Property.purpose`, `propertyInputSchema.purpose` (Task 7), `auth.user.purposes`.

- [ ] **Step 1: Add modal** — in `AddPropertyModal.vue`:

```ts
import type { PropertyPurpose } from "~/types/property";
const auth = useAuthStore();
const ownerPurposes = computed<PropertyPurpose[]>(() =>
  auth.user?.purposes.length ? auth.user.purposes : ["rental"],
);
// Hidden when the owner manages only one kind of property — the value is implied.
const showPurpose = computed(() => ownerPurposes.value.length > 1);
const purposeOptions = computed(() =>
  ownerPurposes.value.map((p) => ({ value: p, label: t(`owner.purposes.${p}.title`) })),
);
```
`initialValues` gets `purpose: ownerPurposes.value[0]!,` — note `initialValues` must now be a `computed`/function since it depends on auth; simplest: turn it into `const initialValues = (): PropertyInput => ({ …, purpose: ownerPurposes.value[0]! })` and use `initialValues()` in both `useForm` and `resetForm`. Add `const [purpose] = defineField("purpose");`. Template — insert after the `type` `<Select>`:
```vue
      <Select
        v-if="showPurpose"
        v-model="purpose"
        :label="t('owner.properties.addModal.fields.purpose')"
        :options="purposeOptions"
        :error="errors.purpose"
      />
```

- [ ] **Step 2: Details form** — `PropertyDetailsForm.vue`: add `purpose: props.property.purpose,` to `initialValues`, `const [purpose] = defineField("purpose");`, `purpose: values.purpose,` in the `update` call, and
```ts
const purposeOptions = computed(() => [
  { value: "rental", label: t("owner.purposes.rental.title") },
  { value: "own_stay", label: t("owner.purposes.own_stay.title") },
  { value: "investment", label: t("owner.purposes.investment.title") },
]);
```
Template — inside the Identity section's two-column grid, after the `type` `<Select>`, add:
```vue
        <Select
          v-model="purpose"
          :options="purposeOptions"
          :label="t('owner.properties.addModal.fields.purpose')"
          :error="errors.purpose"
        />
```
(the grid becomes three items; change its class to `sm:grid-cols-3` or leave it wrapping — leave it.)

- [ ] **Step 3: Card pill** — `PropertyCard.vue`, inside the top `div` after the type pill:
```vue
        <Pill v-if="property.purpose !== 'rental'" tone="neutral">
          {{ t(`owner.purposes.${property.purpose}.title`) }}
        </Pill>
```

- [ ] **Step 4: Demo seed** — append to `propertiesMock` in `demo/data/properties.ts`:

```ts
  {
    id: "66666666-6666-6666-6666-666666666666",
    ownerId: "co-bangsar-aminah",
    name: "Bangsar family home",
    type: "landed",
    purpose: "own_stay",
    address: "Jalan Maarof, No. 18",
    city: "Kuala Lumpur",
    state: "W.P. Kuala Lumpur",
    postcode: "59000",
    yearBuilt: 1996,
    builtUpSqft: 2400,
    landSqft: 3200,
    bedrooms: 4,
    bathrooms: 3,
    parkingLots: 2,
    furnishing: "fully",
    ownership: {
      titleType: "freehold",
      purchaseDate: "2012-06-01",
      purchasePrice: 120_000_000,        // RM 1,200,000
      currentMarketValue: 210_000_000,   // RM 2,100,000
      lastValuedAt: "2025-11-01",
      valuationSource: "agent",
      mortgage: {
        bank: "CIMB",
        loanAmount: 96_000_000,           // RM 960,000
        outstandingBalance: 38_400_000,   // RM 384,000
        monthlyInstalment: 480_000,       // RM 4,800
        tenureYears: 30,
        maturityDate: "2042-06-30",
        interestRatePct: 4.1,
      },
    },
    utilities: {
      quitRentAnnual: 18_000,             // RM 180
      assessmentRateAnnual: 96_000,       // RM 960
      tnbAccountNo: "5551234567",
      waterAccountNo: "4449876543",
    },
    coOwners: [
      { id: "co-bangsar-aminah", name: "Cik Aminah", sharePct: 100, isPrimary: true },
    ],
    createdAt: "2026-02-02T09:00:00Z",
  },
```
No unit for it in `demo/data/units.ts` (an own-stay home has none by default). Check `demo/data/owner.ts` / `admin.ts` for anything that counts `propertiesMock.length` (e.g. admin owner summary "5 properties") and adjust expectations if a literal count is hard-coded.

- [ ] **Step 5: i18n** — `en.json` `owner.properties.addModal.fields` add `"purpose": "This property is for"`; `ms.json` `"purpose": "Hartanah ini untuk"`.

- [ ] **Step 6: Verify** — typecheck; dev log clean. Demo: properties list shows the new landed home with an "Own stay" pill; the demo owner (single purpose `rental`) does **not** see the purpose field in Add property, but after Google-demo onboarding with two purposes, it appears.

---

### Task 11: Exclude non-rental from dashboard + reports (frontend)

**Files:**
- Modify: `frontend/app/demo/services/dashboard.ts`, `frontend/app/composables/useReports.ts`, `frontend/app/pages/owner/reports.vue`, `frontend/i18n/locales/en.json`, `frontend/i18n/locales/ms.json`

**Interfaces:**
- Produces: `useReports().perProperty` = rental rows only; `useReports().notForRent: PropertyReportRow[]`.

- [ ] **Step 1: Demo dashboard** — in `demo/services/dashboard.ts` `buildFromDemoData`, replace the first lines with:

```ts
  const isEmpty = propertiesMock.length === 0;
  // Mirror DashboardController: only rental properties feed the stats/feed.
  const rentalIds = new Set(propertiesMock.filter((p) => p.purpose === "rental").map((p) => p.id));
  const rentalUnits = unitsMock.filter((u) => rentalIds.has(u.propertyId));
  const rentalUnitIds = new Set(rentalUnits.map((u) => u.id));
  const rentalAgreements = agreementsMock.filter((a) => rentalUnitIds.has(a.unitId));
  const rentalAgreementIds = new Set(rentalAgreements.map((a) => a.id));
  const rentalInvoices = invoicesMock.filter((i) => rentalAgreementIds.has(i.agreementId));
  const rentalInvoiceIds = new Set(rentalInvoices.map((i) => i.id));

  const unitCount = rentalUnits.length;
  const occupiedCount = rentalUnits.filter((u) => u.status === "occupied").length;
```
then use `rentalInvoices` instead of `invoicesMock`, `rentalAgreements` instead of `agreementsMock` for `expiringAgreements`, and filter `paymentsMock` with `.filter((p) => rentalInvoiceIds.has(p.invoiceId))` before `successful`. Tenants/tickets lists stay as-is (the demo owner's tenants all belong to rental units; tickets: add `.filter((t) => rentalUnitIds.has(t.unitId))` if `Ticket` has `unitId` — it does per `TicketCreateModal`; check the type).

- [ ] **Step 2: Reports composable** — in `useReports.ts`:

```ts
  const rentalProperties = computed(() => properties.value.filter((p) => p.purpose === "rental"));
  const rentalUnitIds = computed(() => new Set(
    units.value.filter((u) => rentalProperties.value.some((p) => p.id === u.propertyId)).map((u) => u.id),
  ));
  const rentalInvoiceIds = computed(() => {
    const agIds = new Set(agreements.value.filter((a) => rentalUnitIds.value.has(a.unitId)).map((a) => a.id));
    return new Set(invoices.value.filter((i) => agIds.has(i.agreementId)).map((i) => i.id));
  });
```
Change `successfulInYear` to start from `payments.value.filter((p) => rentalInvoiceIds.value.has(p.invoiceId))`, and `totalOutstanding` from `invoices.value.filter((i) => rentalInvoiceIds.value.has(i.id))`. Extract the per-property row builder into `const rowFor = (prop: Property): PropertyReportRow => { …existing body… }` and define:
```ts
  const perProperty = computed<PropertyReportRow[]>(() => rentalProperties.value.map(rowFor));
  const notForRent = computed<PropertyReportRow[]>(() =>
    properties.value.filter((p) => p.purpose !== "rental").map(rowFor),
  );
```
Return `notForRent` alongside the rest.

- [ ] **Step 3: Reports page** — after the per-property `<section>` add a compact section (desktop + mobile share one list — it's ownership data only):

```vue
      <section v-if="reports.notForRent.value.length > 0" class="mt-6">
        <Card padding="loose">
          <header class="mb-4">
            <h2 class="text-card-title font-semibold text-ink">
              {{ t("owner.reports.notForRent.title") }}
            </h2>
            <p class="mt-1 text-caption text-ink-muted">
              {{ t("owner.reports.notForRent.help") }}
            </p>
          </header>
          <ul class="divide-y divide-line-passive">
            <li
              v-for="row in reports.notForRent.value"
              :key="row.property.id"
              class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
              <NuxtLink :to="`/owner/properties/${row.property.id}`" class="min-w-0">
                <p class="truncate text-body font-medium text-ink">{{ row.property.name }}</p>
                <p class="truncate text-caption text-ink-muted">
                  {{ t(`owner.purposes.${row.property.purpose}.title`) }} · {{ row.property.city }}
                </p>
              </NuxtLink>
              <div class="text-left sm:text-right">
                <p class="text-micro text-ink-faint">{{ t("owner.reports.perProperty.cols.netGain") }}</p>
                <p class="text-body font-semibold text-ink">
                  <MoneyDisplay v-if="row.gains" :cents="row.gains.net" />
                  <span v-else class="text-ink-faint">—</span>
                </p>
              </div>
            </li>
          </ul>
        </Card>
      </section>
```
Also: the CSV export (`onDownloadCsv`) keeps exporting `perProperty` (rental) — add the not-for-rent rows after them with empty income/outstanding cells so the export is complete:
```ts
  const rows = [...reports.perProperty.value, ...reports.notForRent.value].map((row) => [ …unchanged… ]);
```

- [ ] **Step 4: i18n** — `en.json` `owner.reports` add:
```json
      "notForRent": {
        "title": "Not for rent",
        "help": "Own-stay and investment properties — capital position only, no rental income."
      },
```
`ms.json`:
```json
      "notForRent": {
        "title": "Bukan untuk disewa",
        "help": "Hartanah kediaman sendiri dan pelaburan — kedudukan modal sahaja, tiada pendapatan sewa."
      },
```

- [ ] **Step 5: Verify** — typecheck; demo dashboard occupancy unchanged (the new home has no units); Reports shows the "Not for rent" list with Bangsar.

---

### Task 12: Vitest + pure `buildChecklist`

**Files:**
- Create: `frontend/vitest.config.ts`, `frontend/app/utils/onboardingChecklist.ts`, `frontend/app/utils/onboardingChecklist.test.ts`
- Modify: `frontend/package.json`

**Interfaces:**
- Produces:
```ts
export type ChecklistKey = "add_property" | "fill_ownership" | "fill_utilities" | "add_unit" | "invite_tenant" | "create_agreement";
export interface ChecklistStep { key: ChecklistKey; done: boolean; enabled: boolean; to: string; propertyId?: string }
export interface ChecklistInput { purposes: OwnerPurpose[]; properties: Property[]; units: Unit[]; tenants: Tenant[]; agreements: Agreement[] }
export const buildChecklist: (input: ChecklistInput) => ChecklistStep[];
```

- [ ] **Step 1: Install Vitest (inside the container)**

`docker exec roofly-frontend npm install -D vitest` — then add to `package.json` scripts: `"test": "vitest run"`.

`frontend/vitest.config.ts`:
```ts
import { defineConfig } from "vitest/config";
import { fileURLToPath } from "node:url";

// Unit tests for pure modules only (no Nuxt auto-imports). `~` mirrors Nuxt's alias.
export default defineConfig({
  resolve: { alias: { "~": fileURLToPath(new URL("./app", import.meta.url)) } },
  test: { include: ["app/**/*.test.ts"], environment: "node" },
});
```

- [ ] **Step 2: Failing test**

`frontend/app/utils/onboardingChecklist.test.ts`:

```ts
import { describe, expect, it } from "vitest";
import { buildChecklist, type ChecklistInput } from "~/utils/onboardingChecklist";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";
import type { Agreement } from "~/types/agreement";

const property = (over: Partial<Property> = {}): Property => ({
  id: "p1", ownerId: "o1", name: "Home", type: "room", purpose: "rental",
  address: "1 St", city: "KL", state: "W.P. Kuala Lumpur", postcode: "50000",
  coOwners: [{ id: "c1", name: "Me", sharePct: 100, isPrimary: true }],
  createdAt: "2026-01-01T00:00:00Z",
  ...over,
});
// `room` needs only titleType for ownership and nothing for utilities — keeps fixtures small.
const completeRoom = (over: Partial<Property> = {}) =>
  property({ bedrooms: 1, bathrooms: 1, ownership: { titleType: "freehold" }, ...over });

const unit = (propertyId: string): Unit => ({ id: `u-${propertyId}`, propertyId, label: "A", status: "vacant", createdAt: "" });
const tenant: Tenant = { id: "t1", name: "T", email: "t@x.my", phone: "", status: "invited", invitedAt: "", createdAt: "" };
const agreement = (status: Agreement["status"]): Agreement => ({
  id: "a1", unitId: "u-p1", tenantId: "t1", startDate: "2026-01-01", endDate: "2026-12-31",
  rentAmount: 1, depositAmount: 1, lateFee: 0, rentDueDay: 1, status, createdAt: "",
});

const base: ChecklistInput = { purposes: ["rental"], properties: [], units: [], tenants: [], agreements: [] };
const keys = (s: ReturnType<typeof buildChecklist>) => s.map((x) => x.key);

describe("buildChecklist", () => {
  it("rental owner gets all six steps, only add_property enabled when empty", () => {
    const steps = buildChecklist(base);
    expect(keys(steps)).toEqual(["add_property", "fill_ownership", "fill_utilities", "add_unit", "invite_tenant", "create_agreement"]);
    expect(steps[0]).toMatchObject({ done: false, enabled: true, to: "/owner/properties?add=1" });
    expect(steps.slice(1).every((s) => !s.enabled && !s.done)).toBe(true);
  });

  it("own-stay-only owner gets the three property steps", () => {
    expect(keys(buildChecklist({ ...base, purposes: ["own_stay"] }))).toEqual(["add_property", "fill_ownership", "fill_utilities"]);
  });

  it("mixed purposes is the union in canonical order", () => {
    expect(keys(buildChecklist({ ...base, purposes: ["investment", "rental"] }))).toHaveLength(6);
  });

  it("ownership step links to the first incomplete property and is done when all complete", () => {
    const steps = buildChecklist({ ...base, properties: [completeRoom({ id: "p1" }), property({ id: "p2", bedrooms: 1, bathrooms: 1 })] });
    const own = steps.find((s) => s.key === "fill_ownership")!;
    expect(own).toMatchObject({ done: false, enabled: true, propertyId: "p2", to: "/owner/properties/p2?tab=ownership" });

    const all = buildChecklist({ ...base, properties: [completeRoom({ id: "p1" })] });
    expect(all.find((s) => s.key === "fill_ownership")!.done).toBe(true);
    expect(all.find((s) => s.key === "fill_utilities")!.done).toBe(true);
  });

  it("add_unit only looks at rental properties", () => {
    const ownStay = completeRoom({ id: "h", purpose: "own_stay" });
    const rental = completeRoom({ id: "p1" });
    const noUnits = buildChecklist({ ...base, purposes: ["rental", "own_stay"], properties: [ownStay, rental] });
    expect(noUnits.find((s) => s.key === "add_unit")).toMatchObject({ done: false, propertyId: "p1", to: "/owner/properties/p1?tab=overview" });

    const withUnit = buildChecklist({ ...base, properties: [rental], units: [unit("p1")] });
    expect(withUnit.find((s) => s.key === "add_unit")!.done).toBe(true);
  });

  it("tenant + agreement steps", () => {
    const steps = buildChecklist({ ...base, properties: [completeRoom()], units: [unit("p1")], tenants: [tenant], agreements: [agreement("draft")] });
    expect(steps.find((s) => s.key === "invite_tenant")).toMatchObject({ done: true, to: "/owner/tenants?invite=1" });
    expect(steps.find((s) => s.key === "create_agreement")).toMatchObject({ done: false, to: "/owner/agreements/new" });
    const active = buildChecklist({ ...base, properties: [completeRoom()], units: [unit("p1")], tenants: [tenant], agreements: [agreement("active")] });
    expect(active.find((s) => s.key === "create_agreement")!.done).toBe(true);
  });
});
```

- [ ] **Step 3: Run — expect FAIL** `docker exec roofly-frontend npm test` (module not found)

- [ ] **Step 4: Implement**

`frontend/app/utils/onboardingChecklist.ts`:

```ts
import type { OwnerPurpose } from "~/types/auth";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";
import type { Agreement } from "~/types/agreement";
import { tabCompletion } from "~/utils/propertyCompletion";

/**
 * Getting-started checklist (spec 2026-08-23 § 6). Pure: computed from the
 * owner's real data every time — nothing is stored per step, so it can never
 * drift from reality. Steps after `add_property` stay visible but disabled
 * until a property exists, so the owner sees the whole path.
 */
export type ChecklistKey =
  | "add_property"
  | "fill_ownership"
  | "fill_utilities"
  | "add_unit"
  | "invite_tenant"
  | "create_agreement";

export interface ChecklistStep {
  key: ChecklistKey;
  done: boolean;
  /** False while the step can't be acted on yet (no property). */
  enabled: boolean;
  to: string;
  propertyId?: string;
}

export interface ChecklistInput {
  purposes: OwnerPurpose[];
  properties: Property[];
  units: Unit[];
  tenants: Tenant[];
  agreements: Agreement[];
}

const RENTAL_ONLY: ChecklistKey[] = ["add_unit", "invite_tenant", "create_agreement"];
const ORDER: ChecklistKey[] = ["add_property", "fill_ownership", "fill_utilities", ...RENTAL_ONLY];

export const buildChecklist = (input: ChecklistInput): ChecklistStep[] => {
  const hasRental = input.purposes.includes("rental") || input.purposes.length === 0;
  const hasProperty = input.properties.length > 0;
  const rental = input.properties.filter((p) => p.purpose === "rental");

  const firstIncomplete = (tab: "ownership" | "utilities") =>
    input.properties.find((p) => tabCompletion(p, tab) < 100);

  const propertyStep = (key: ChecklistKey, tab: "ownership" | "utilities"): ChecklistStep => {
    const target = firstIncomplete(tab);
    return {
      key,
      done: hasProperty && target === undefined,
      enabled: hasProperty,
      to: target ? `/owner/properties/${target.id}?tab=${tab}` : "/owner/properties",
      propertyId: target?.id,
    };
  };

  const unitless = rental.find((p) => !input.units.some((u) => u.propertyId === p.id));
  const steps: Record<ChecklistKey, ChecklistStep> = {
    add_property: { key: "add_property", done: hasProperty, enabled: true, to: "/owner/properties?add=1" },
    fill_ownership: propertyStep("fill_ownership", "ownership"),
    fill_utilities: propertyStep("fill_utilities", "utilities"),
    add_unit: {
      key: "add_unit",
      done: rental.length > 0 && unitless === undefined,
      enabled: rental.length > 0,
      to: unitless ? `/owner/properties/${unitless.id}?tab=overview` : "/owner/properties",
      propertyId: unitless?.id,
    },
    invite_tenant: { key: "invite_tenant", done: input.tenants.length > 0, enabled: hasProperty, to: "/owner/tenants?invite=1" },
    create_agreement: {
      key: "create_agreement",
      done: input.agreements.some((a) => a.status === "active"),
      enabled: hasProperty,
      to: "/owner/agreements/new",
    },
  };

  return ORDER.filter((k) => hasRental || !RENTAL_ONLY.includes(k)).map((k) => steps[k]);
};
```

- [ ] **Step 5: Run — expect PASS** `docker exec roofly-frontend npm test`. Also `npm run typecheck` — the test file is under `app/`, so `vue-tsc` sees it; if it complains about `vitest` globals, the explicit `import { describe… } from "vitest"` covers it.

---

### Task 13: `GettingStartedCard` + dashboard + deep-link query params

**Files:**
- Create: `frontend/app/composables/useOnboardingChecklist.ts`, `frontend/app/components/owner/GettingStartedCard.vue`
- Modify: `frontend/app/pages/owner/index.vue`, `frontend/app/pages/owner/properties/index.vue`, `frontend/app/pages/owner/properties/[id].vue`, `frontend/app/pages/owner/tenants/index.vue`, `frontend/i18n/locales/en.json`, `frontend/i18n/locales/ms.json`

**Interfaces:**
- Consumes: `buildChecklist` (Task 12), `useOwnerSettings().setChecklistDismissed`, `useAuthStore().setUser`.
- Produces: `?add=1` on `/owner/properties` opens the add modal; `?invite=1` on `/owner/tenants` opens invite; `?tab=<key>` on `/owner/properties/:id` selects the tab. All three are cleared from the URL on consumption.

- [ ] **Step 1: Composable**

`composables/useOnboardingChecklist.ts`:

```ts
import { computed, ref } from "vue";
import { buildChecklist, type ChecklistStep } from "~/utils/onboardingChecklist";

/**
 * Loads the four lists the checklist needs and exposes the computed steps.
 * Skips the network entirely when the card wouldn't render (dismissed).
 */
export const useOnboardingChecklist = () => {
  const auth = useAuthStore();
  const loading = ref(false);
  const steps = ref<ChecklistStep[]>([]);

  const dismissed = computed(() => auth.user?.checklistDismissedAt !== null && auth.user?.checklistDismissedAt !== undefined);
  const allDone = computed(() => steps.value.length > 0 && steps.value.every((s) => s.done));
  const visible = computed(() => !dismissed.value && !allDone.value && steps.value.length > 0);
  const doneCount = computed(() => steps.value.filter((s) => s.done).length);

  const load = async () => {
    if (dismissed.value || !auth.isOwner) return;
    loading.value = true;
    try {
      const [properties, units, tenants, agreements] = await Promise.all([
        useProperties().getProperties(),
        useUnits().getUnits(),
        useTenants().getTenants(),
        useAgreements().getAgreements(),
      ]);
      steps.value = buildChecklist({
        purposes: auth.user?.purposes ?? [],
        properties, units, tenants, agreements,
      });
    } finally {
      loading.value = false;
    }
  };

  const dismiss = async () => {
    const user = await useOwnerSettings().setChecklistDismissed(true);
    auth.setUser(user);
  };

  return { load, loading, steps, visible, doneCount, dismiss };
};
```

- [ ] **Step 2: Card**

`components/owner/GettingStartedCard.vue`:

```vue
<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import Icon from "~/components/ui/Icon.vue";
import { useToast } from "~/composables/useToast";
import type { ChecklistStep } from "~/utils/onboardingChecklist";

const props = defineProps<{ steps: ChecklistStep[]; doneCount: number }>();
const emit = defineEmits<{ dismiss: [] }>();
const { t } = useI18n();
const { show } = useToast();

const onDismiss = () => {
  emit("dismiss");
  show(t("owner.checklist.dismissedToast"), "default");
};
</script>

<template>
  <section data-tour="checklist" class="mb-6 sm:mb-8">
    <Card padding="loose">
      <header class="mb-4 flex items-start justify-between gap-3">
        <div>
          <h2 class="text-card-title font-semibold text-ink">{{ t("owner.checklist.title") }}</h2>
          <p class="mt-1 text-caption text-ink-muted">
            {{ t("owner.checklist.progress", { done: doneCount, total: steps.length }) }}
          </p>
        </div>
        <button
          type="button"
          class="rounded-sm p-1 text-ink-faint outline-none transition hover:text-ink focus-visible:shadow-focus"
          :aria-label="t('owner.checklist.dismiss')"
          @click="onDismiss"
        >
          <Icon name="X" :size="16" />
        </button>
      </header>

      <ol class="divide-y divide-line-passive">
        <li v-for="(step, i) in steps" :key="step.key">
          <component
            :is="step.enabled && !step.done ? 'NuxtLink' : 'div'"
            :to="step.enabled && !step.done ? step.to : undefined"
            class="group flex items-start gap-3 rounded-sm py-3 outline-none transition"
            :class="step.enabled && !step.done ? 'hover:bg-surface-hover focus-visible:shadow-focus' : ''"
            :aria-disabled="!step.enabled"
          >
            <span
              class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-pill text-micro font-semibold"
              :class="step.done
                ? 'bg-status-paid-soft text-status-paid'
                : step.enabled ? 'bg-ink text-surface-page' : 'bg-line-passive text-ink-faint'"
            >
              <Icon v-if="step.done" name="Check" :size="12" />
              <template v-else>{{ i + 1 }}</template>
            </span>
            <div class="min-w-0 flex-1">
              <p
                class="text-body font-medium"
                :class="step.done ? 'text-ink-muted line-through' : step.enabled ? 'text-ink' : 'text-ink-faint'"
              >
                {{ t(`owner.checklist.steps.${step.key}.title`) }}
              </p>
              <p v-if="!step.done" class="text-caption" :class="step.enabled ? 'text-ink-muted' : 'text-ink-faint'">
                {{ t(`owner.checklist.steps.${step.key}.hint`) }}
              </p>
            </div>
            <Icon
              v-if="step.enabled && !step.done"
              name="ArrowRight"
              :size="14"
              class="mt-1 shrink-0 text-ink-faint transition group-hover:text-ink-muted"
            />
          </component>
        </li>
      </ol>
    </Card>
  </section>
</template>
```
Confirm `Icon.vue` accepts `X`, `Check`, `ArrowRight` (ArrowRight is already used on the dashboard). `<component :is="'NuxtLink'">` resolves because `NuxtLink` is globally registered.

- [ ] **Step 3: Dashboard** — `pages/owner/index.vue`:
```ts
import GettingStartedCard from "~/components/owner/GettingStartedCard.vue";
const checklist = useOnboardingChecklist();
```
In `onMounted`, change to `await Promise.all([dashboard.getDashboard(), checklist.load()]);`. Template — the card must render in **both** the empty and non-empty branches (an owner with zero properties needs it most). Insert directly after `</header>`, before the loading card:
```vue
    <GettingStartedCard
      v-if="checklist.visible.value"
      :steps="checklist.steps.value"
      :done-count="checklist.doneCount.value"
      @dismiss="checklist.dismiss"
    />
```
Keep the existing empty-state card; change its CTA link from `/owner/properties` to `/owner/properties?add=1`.

- [ ] **Step 4: Deep links**

`pages/owner/properties/index.vue` — add:
```ts
const route = useRoute();
const router = useRouter();
onMounted(async () => {
  if (route.query.add === "1") {
    showAddModal.value = true;
    router.replace({ query: {} });
  }
  // …existing load
});
```
(`import { useRouter } from "vue-router";` is how `[id].vue` does it — match.)

`pages/owner/tenants/index.vue` — same pattern with `route.query.invite === "1"` → `showModal.value = true`.

`pages/owner/properties/[id].vue` — replace `const activeTab = ref<string>("overview");` with:
```ts
const TAB_KEYS = ["overview", "details", "ownership", "utilities", "documents"];
const initialTab = typeof route.query.tab === "string" && TAB_KEYS.includes(route.query.tab) ? route.query.tab : "overview";
const activeTab = ref<string>(initialTab);
if (route.query.tab !== undefined) router.replace({ query: {} });
```
(`route` and `router` are already defined in that file; guard `documents` against `documentsEnabled` — if the flag is off and `tab=documents` is requested, fall back to `overview`.)

- [ ] **Step 5: i18n** — `en.json` under `owner`:
```json
    "checklist": {
      "title": "Getting started",
      "progress": "{done} of {total} done",
      "dismiss": "Hide checklist",
      "dismissedToast": "Hidden. Bring it back from Settings → Preferences.",
      "steps": {
        "add_property": { "title": "Add your first property", "hint": "Name, address and type — takes a minute." },
        "fill_ownership": { "title": "Fill in ownership details", "hint": "Title, purchase price, valuation and loan." },
        "fill_utilities": { "title": "Fill in utilities and fees", "hint": "Maintenance fee, quit rent, TNB and water accounts." },
        "add_unit": { "title": "Add a unit", "hint": "Tenants and agreements attach to units." },
        "invite_tenant": { "title": "Invite your first tenant", "hint": "Name, email and phone — they get a link." },
        "create_agreement": { "title": "Create an agreement", "hint": "Link a tenant to a unit with rent and dates." }
      }
    },
```
`ms.json`:
```json
    "checklist": {
      "title": "Mula di sini",
      "progress": "{done} daripada {total} selesai",
      "dismiss": "Sembunyikan senarai semak",
      "dismissedToast": "Disembunyikan. Paparkan semula dari Tetapan → Keutamaan.",
      "steps": {
        "add_property": { "title": "Tambah hartanah pertama anda", "hint": "Nama, alamat dan jenis — seminit sahaja." },
        "fill_ownership": { "title": "Isi butiran pemilikan", "hint": "Hakmilik, harga belian, penilaian dan pinjaman." },
        "fill_utilities": { "title": "Isi utiliti dan yuran", "hint": "Yuran penyelenggaraan, cukai tanah, akaun TNB dan air." },
        "add_unit": { "title": "Tambah unit", "hint": "Penyewa dan perjanjian dikaitkan dengan unit." },
        "invite_tenant": { "title": "Jemput penyewa pertama anda", "hint": "Nama, e-mel dan telefon — mereka akan terima pautan." },
        "create_agreement": { "title": "Cipta perjanjian", "hint": "Kaitkan penyewa dengan unit, sewa dan tarikh." }
      }
    },
```
(`→` is fine in i18n values; `@` is not.)

- [ ] **Step 6: Verify** — typecheck + `npm test` + dev log. Demo Google flow: onboarding → dashboard shows "Getting started 3 of 6" (demo data has properties/units/tenants; ownership incomplete on some); click a row → lands on the right tab/modal with a clean URL; × hides it.

---

### Task 14: Settings — purposes, checklist toggle, set password

**Files:**
- Create: `frontend/app/components/owner/SettingsSetPasswordForm.vue`
- Modify: `frontend/app/components/owner/SettingsPreferencesForm.vue`, `frontend/app/components/owner/SettingsProfileForm.vue`, `frontend/i18n/locales/en.json`, `frontend/i18n/locales/ms.json`

**Interfaces:**
- Consumes: `OwnerPurposePicker` (Task 9), `useOwnerSettings().{completeOnboarding,setChecklistDismissed,setPassword}`, `useAuthStore().setUser`.

- [ ] **Step 1: Preferences form** — in `SettingsPreferencesForm.vue` add:
```ts
import OwnerPurposePicker from "~/components/owner/OwnerPurposePicker.vue";
import type { OwnerPurpose } from "~/types/auth";
const auth = useAuthStore();
const purposes = ref<OwnerPurpose[]>([...(auth.user?.purposes ?? ["rental"])]);
const showChecklist = ref(auth.user?.checklistDismissedAt === null);
```
In `onSubmit`, after `updatePreferences` resolves:
```ts
    if (purposes.value.length > 0) {
      auth.setUser(await useOwnerSettings().completeOnboarding({ purposes: purposes.value }));
    }
    const wantsDismissed = !showChecklist.value;
    if (wantsDismissed !== (auth.user?.checklistDismissedAt !== null)) {
      auth.setUser(await useOwnerSettings().setChecklistDismissed(wantsDismissed));
    }
```
Template — add two sections before the save button, following the existing `border-t` section style:
```vue
    <section class="space-y-4 border-t border-line-passive pt-6">
      <header>
        <h2 class="text-card-title font-semibold text-ink">{{ t("owner.settings.preferences.purposesTitle") }}</h2>
        <p class="mt-1 text-caption text-ink-muted">{{ t("owner.settings.preferences.purposesHelp") }}</p>
      </header>
      <OwnerPurposePicker v-model="purposes" />
      <p v-if="purposes.length === 0" class="text-caption text-accent">{{ t("owner.settings.preferences.purposesMin") }}</p>
    </section>

    <section class="space-y-4 border-t border-line-passive pt-6">
      <label class="flex cursor-pointer items-start gap-3">
        <input v-model="showChecklist" type="checkbox" class="mt-1 h-4 w-4 accent-ink" />
        <span>
          <span class="block text-body text-ink">{{ t("owner.settings.preferences.checklistToggle") }}</span>
          <span class="block text-caption text-ink-muted">{{ t("owner.settings.preferences.checklistHelp") }}</span>
        </span>
      </label>
    </section>
```
Disable the save button when `purposes.length === 0`.

- [ ] **Step 2: Set-password form**

`components/owner/SettingsSetPasswordForm.vue`:
```vue
<script setup lang="ts">
import { ref } from "vue";
import Input from "~/components/ui/Input.vue";
import Button from "~/components/ui/Button.vue";
import { useToast } from "~/composables/useToast";

const { t } = useI18n();
const { show } = useToast();
const { toFieldErrors } = useApiError();
const auth = useAuthStore();
const password = ref("");
const confirm = ref("");
const error = ref<string | null>(null);
const submitting = ref(false);

const onSubmit = async () => {
  error.value = null;
  if (password.value.length < 8) return (error.value = t("auth.admin.passwordTooShort"));
  if (password.value !== confirm.value) return (error.value = t("auth.admin.passwordMismatch"));
  submitting.value = true;
  try {
    auth.setUser(await useOwnerSettings().setPassword(password.value));
    show(t("owner.settings.profile.passwordSetToast"), "success");
  } catch (err) {
    const f = toFieldErrors(err);
    error.value = f ? Object.values(f)[0]! : t("common.genericError");
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <form class="space-y-4" @submit.prevent="onSubmit">
    <Input v-model="password" type="password" autocomplete="new-password" :label="t('auth.admin.newPassword')" />
    <Input v-model="confirm" type="password" autocomplete="new-password" :label="t('auth.admin.confirmPassword')" />
    <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
    <div class="flex justify-end">
      <Button type="submit" variant="primary" :loading="submitting">{{ t("owner.settings.profile.setPassword") }}</Button>
    </div>
  </form>
</template>
```
(Reusing `auth.admin.newPassword` etc. is deliberate — the strings are generic; but those keys are **en-only** per the admin convention, so add owner-facing copies: `owner.settings.profile.newPassword` / `confirmPassword` / `passwordTooShort` / `passwordMismatch` in both locales and use those instead. Do that — don't reuse admin keys.)

- [ ] **Step 3: Profile form** — in `SettingsProfileForm.vue` add `import SettingsSetPasswordForm from "~/components/owner/SettingsSetPasswordForm.vue"; const auth = useAuthStore();` and, in the template after the identity section (find the `<section` for `payout` and insert **before** it):
```vue
    <section v-if="!auth.user?.hasPassword" class="space-y-4 border-t border-line-passive pt-6">
      <header>
        <h2 class="text-card-title font-semibold text-ink">{{ t("owner.settings.profile.passwordTitle") }}</h2>
        <p class="mt-1 text-caption text-ink-muted">{{ t("owner.settings.profile.passwordHelp") }}</p>
      </header>
      <SettingsSetPasswordForm />
    </section>
```
If the profile form is one `<form>` wrapping everything, nested forms are invalid HTML — place the section **outside** the outer `<form>` (after its closing tag) inside the root element.

- [ ] **Step 4: i18n** — `en.json` `owner.settings.preferences` add:
```json
      "purposesTitle": "What you manage",
      "purposesHelp": "Drives the getting-started checklist and the default for new properties.",
      "purposesMin": "Pick at least one.",
      "checklistToggle": "Show the getting-started checklist on the dashboard",
      "checklistHelp": "It hides itself once every step is done.",
```
`owner.settings.profile` add:
```json
      "passwordTitle": "Set a password",
      "passwordHelp": "You signed up with Google. Add a password to also log in with email.",
      "setPassword": "Set password",
      "newPassword": "New password",
      "confirmPassword": "Confirm password",
      "passwordTooShort": "Use at least 8 characters.",
      "passwordMismatch": "Passwords do not match.",
      "passwordSetToast": "Password set. You can now log in with email too."
```
`ms.json` preferences:
```json
      "purposesTitle": "Apa yang anda urus",
      "purposesHelp": "Menentukan senarai semak permulaan dan tetapan lalai hartanah baharu.",
      "purposesMin": "Pilih sekurang-kurangnya satu.",
      "checklistToggle": "Papar senarai semak permulaan di halaman utama",
      "checklistHelp": "Ia akan tersembunyi sendiri apabila semua langkah selesai.",
```
profile:
```json
      "passwordTitle": "Tetapkan kata laluan",
      "passwordHelp": "Anda mendaftar dengan Google. Tambah kata laluan untuk log masuk dengan e-mel juga.",
      "setPassword": "Tetapkan kata laluan",
      "newPassword": "Kata laluan baharu",
      "confirmPassword": "Sahkan kata laluan",
      "passwordTooShort": "Gunakan sekurang-kurangnya 8 aksara.",
      "passwordMismatch": "Kata laluan tidak sepadan.",
      "passwordSetToast": "Kata laluan ditetapkan. Anda kini boleh log masuk dengan e-mel juga."
```

- [ ] **Step 5: Verify** — typecheck + dev log. Demo Google user → Settings → Profile shows "Set a password"; Preferences shows purposes + checklist toggle; toggling restores the card on the dashboard.

---

### Task 15: Docs + env + CLAUDE.md

**Files:**
- Modify: `docs/backend/API-SPEC.md`, `docs/frontend/API-MAP.md`, `docs/frontend/MOCK-POC.md`, `docs/frontend/UI-STANDARDS.md`, `.claude/CLAUDE.md`

- [ ] **Step 1: API-SPEC.md** — add under the Auth module: `POST /auth/google` (guest, `throttle:10,1`, request `{credential}`, responses 200/201 `{user, token}`, 401, 403 `code: not_owner`, audit `auth.google_login` / `auth.google_register`), and the 422 `email` rule on `/auth/login` for password-less owners. Under Owner → Account: `PATCH /account/onboarding`, `PATCH /account/checklist`, `POST /account/password` with rules, response `AuthUserResource`, audit actions. Under Properties: `purpose` field (`sometimes|in:rental,own_stay,investment`, default `rental`) on store/update and in the Resource. Under Dashboard: "rental properties only; `isEmpty` counts all".

- [ ] **Step 2: API-MAP.md** — add rows: Login/Register page → `auth.loginWithGoogle` → `POST /auth/google` (demo: `demoAuth.loginWithGoogle`); Onboarding page → `ownerSettings.completeOnboarding` → `PATCH /account/onboarding`; Dashboard → `useOnboardingChecklist` (4 list reads, no new endpoint) + `ownerSettings.setChecklistDismissed` → `PATCH /account/checklist`; Settings → Profile → `ownerSettings.setPassword` → `POST /account/password`; Settings → Preferences → onboarding + checklist methods.

- [ ] **Step 3: MOCK-POC.md** — Properties "Schema impact": `properties.purpose` string default `rental`; non-rental excluded from occupancy/attention/income. Settings section: `users.purposes`, `onboarded_at`, `checklist_dismissed_at`, `google_id`, `avatar_url`; onboarding screen + checklist (computed, not stored). Keep each to 3–5 lines.

- [ ] **Step 4: UI-STANDARDS.md § 11** — add "Checklist card: numbered circle / check / muted disabled rows, title + one-line hint, whole row is the link; hides when complete or dismissed; same layout on mobile." Also § for the auth pages: "Social button above the email form with an `or` divider; never shown in demo."

- [ ] **Step 5: CLAUDE.md** — in "Current state" add one paragraph: Google sign-in (owners only, `features.googleLogin`, `NUXT_PUBLIC_GOOGLE_CLIENT_ID` + backend `GOOGLE_CLIENT_ID`), onboarding screen at `/owner/onboarding` (guard in `auth.global.ts`), `Property.purpose`, and the computed getting-started checklist (`utils/onboardingChecklist.ts`, Vitest via `docker exec roofly-frontend npm test`). In "How to run" add the `npm test` line. In the file tree add `utils/onboardingChecklist.ts` and `composables/useOnboardingChecklist.ts`.

- [ ] **Step 6: Final verification (evidence, not assertion)**
  - `docker exec roofly-backend php artisan test` → all green, paste the summary line.
  - `docker exec roofly-frontend npm test` → green.
  - `docker exec roofly-frontend npm run typecheck` → exactly the 5 known errors.
  - `docker logs --tail 80 roofly-frontend` → no Vite/SFC errors.
  - `git status` → list the changed files for Baihaqie's review. **Do not commit.**
  - Write a short browser-test handoff for Baihaqie: (1) demo login page shows "Continue with Google (demo)", (2) onboarding appears once, (3) Add property hides/shows purpose by owner purposes, (4) Bangsar home shows in Reports "Not for rent" and not in occupancy, (5) checklist deep-links land on the right tab/modal with a clean URL, (6) Settings → Profile "Set a password" for the Google-demo user, (7) with a real `NUXT_PUBLIC_GOOGLE_CLIENT_ID` on UAT the real button renders and `POST /api/auth/google` returns `{user, token}`.

---

### Task 16: Forgot / reset password (backend)

**Files:**
- Create: `backend/app/Notifications/ResetPassword.php`, `backend/app/Http/Controllers/Api/Auth/PasswordResetController.php`, `backend/tests/Feature/PasswordResetTest.php`
- Modify: `backend/routes/api.php`

**Interfaces:**
- Produces: `POST /api/auth/forgot-password {email}` → always `200 {message}`; `POST /api/auth/reset-password {token,email,password,password_confirmation}` → `200 {user, token}` or `422 errors.email`. Reset link = `config('app.frontend_url') . '/auth/reset-password?token=…&email=…'`.

- [ ] **Step 1: Failing test**

`backend/tests/Feature/PasswordResetTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_always_200_and_only_notifies_existing_non_admins(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create(['email' => 'o@example.com']);
        User::factory()->superAdmin()->create(['email' => 'admin@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();
        $this->postJson('/api/auth/forgot-password', ['email' => 'admin@example.com'])->assertOk();
        $this->postJson('/api/auth/forgot-password', ['email' => 'o@example.com'])->assertOk();

        Notification::assertSentTo($owner, ResetPassword::class, function (ResetPassword $n) use ($owner) {
            $url = $n->url($owner);
            return str_contains($url, '/auth/reset-password?token=') && str_contains($url, 'email=o%40example.com');
        });
        Notification::assertCount(1);
    }

    public function test_reset_sets_password_and_logs_in(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'o@example.com']);
        $token = Password::createToken($owner);

        $res = $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'o@example.com',
            'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertOk();

        $this->assertSame(['user', 'token'], array_keys($res->json()));
        $this->assertSame($owner->id, $res->json('user.id'));
        $this->assertTrue(Hash::check('newsecret1', $owner->fresh()->password));
    }

    public function test_reset_gives_google_only_account_a_password(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'g@example.com', 'password' => null, 'google_id' => 'g-1']);
        $token = Password::createToken($owner);
        $res = $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'g@example.com',
            'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertOk();
        $this->assertTrue($res->json('user.hasPassword'));
    }

    public function test_reset_with_bad_token_is_422(): void
    {
        User::factory()->owner()->create(['email' => 'o@example.com']);
        $this->postJson('/api/auth/reset-password', [
            'token' => 'nope', 'email' => 'o@example.com',
            'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_reset_validates_password_rules(): void
    {
        $this->postJson('/api/auth/reset-password', ['token' => 't', 'email' => 'x@y.my', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
    }
}
```

- [ ] **Step 2: Run — expect FAIL** `docker exec roofly-backend php artisan test --filter=PasswordResetTest`

- [ ] **Step 3: Notification**

`backend/app/Notifications/ResetPassword.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Customer (owner/tenant) password reset — link lands on the Nuxt app, not the API host. */
class ResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(object $notifiable): string
    {
        return rtrim(config('app.frontend_url'), '/')
            . '/auth/reset-password?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new MailMessage)
            ->subject('Reset your Roofly password')
            ->line("Hi {$notifiable->name}, we received a request to reset your password.")
            ->line('Kami menerima permintaan untuk menetapkan semula kata laluan anda.')
            ->action('Reset password', $this->url($notifiable))
            ->line("This link expires in {$minutes} minutes. / Pautan ini tamat tempoh dalam {$minutes} minit.")
            ->line('If you did not request this, you can ignore this email. / Jika anda tidak memintanya, abaikan e-mel ini.');
    }
}
```

Override the default notification on the model — in `app/Models/User.php` add:

```php
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPassword($token));
    }
```

- [ ] **Step 4: Controller**

`backend/app/Http/Controllers/Api/Auth/PasswordResetController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Customer forgot/reset password (spec 2026-08-23 § 3.4). The forgot endpoint
 * never reveals whether an email exists; admins are skipped because they
 * onboard through the invite flow.
 */
class PasswordResetController extends Controller
{
    private const GENERIC = 'If that email exists, we have sent a reset link.';

    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email']);

        $user = User::where('email', $data['email'])->first();
        if ($user !== null && ! $user->isAdmin()) {
            Password::sendResetLink(['email' => $data['email']]);
        }

        return response()->json(['message' => self::GENERIC]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            if ($user->isAdmin()) {
                return; // admins never reset through the customer flow
            }
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        $user = User::where('email', $data['email'])->first();
        if ($status !== Password::PASSWORD_RESET || $user === null || $user->isAdmin()) {
            return response()->json([
                'message' => 'This reset link is invalid or has expired.',
                'errors'  => ['email' => ['This reset link is invalid or has expired.']],
            ], 422);
        }

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => (new AuthUserResource($user->fresh()))->resolve(),
            'token' => $token,
        ]);
    }
}
```
(`password` has the `hashed` cast, so assigning the plain string hashes it.)

- [ ] **Step 5: Routes** — in the `auth` prefix group:
```php
    Route::post('forgot-password', [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
    Route::post('reset-password',  [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
```

- [ ] **Step 6: Run — expect PASS** `docker exec roofly-backend php artisan test --filter=PasswordResetTest`, then the full suite. Confirm `MAIL_*` for UAT is the Mailtrap sandbox (root `.env.example` already documents it) and that the queue-worker container runs — the notification is queued like `AdminInvite`.

---

### Task 17: Forgot / reset password (frontend)

**Files:**
- Create: `frontend/app/pages/auth/forgot-password.vue`, `frontend/app/pages/auth/reset-password.vue`
- Modify: `frontend/app/services/contracts/auth.ts`, `frontend/app/services/api/auth.ts`, `frontend/app/demo/auth.ts`, `frontend/app/stores/auth.ts`, `frontend/app/pages/auth/login.vue`, `frontend/i18n/locales/en.json`, `frontend/i18n/locales/ms.json`

**Interfaces:**
- Consumes: `POST /auth/forgot-password`, `POST /auth/reset-password` (Task 16).
- Produces: `AuthAdapter.forgotPassword(email): Promise<void>`, `AuthAdapter.resetPassword(input: {token; email; password}): Promise<AuthUser>`; store actions of the same names.

- [ ] **Step 1: Contract + adapters + store**

`services/contracts/auth.ts` — add:
```ts
  /** Always resolves — the API never reveals whether the email exists. */
  forgotPassword(email: string): Promise<void>;
  /** Sets the password from an emailed token and logs the user in. */
  resetPassword(input: { token: string; email: string; password: string }): Promise<AuthUser>;
```
`services/api/auth.ts`:
```ts
  async forgotPassword(email) {
    const { request } = useApi();
    await request("/../sanctum/csrf-cookie");
    await request("/auth/forgot-password", { method: "POST", body: { email } });
  },

  async resetPassword({ token, email, password }) {
    const { request } = useApi();
    await request("/../sanctum/csrf-cookie");
    const res = await request<{ user: AuthUser }>("/auth/reset-password", {
      method: "POST",
      body: { token, email, password, password_confirmation: password },
    });
    return res.user;
  },
```
`demo/auth.ts` inside `demoAuth`:
```ts
  async forgotPassword() {
    await delay();
  },

  async resetPassword({ email }) {
    await delay();
    const user = customerUserFor(email);
    persist(user);
    return user;
  },
```
`stores/auth.ts` actions:
```ts
    async forgotPassword(email: string) {
      this.loading = true;
      try {
        await adapter().forgotPassword(email);
      } finally {
        this.loading = false;
      }
    },

    async resetPassword(input: { token: string; email: string; password: string }) {
      this.loading = true;
      try {
        this.user = await adapter().resetPassword(input);
      } finally {
        this.loading = false;
      }
    },
```

- [ ] **Step 2: Forgot page**

`pages/auth/forgot-password.vue`:
```vue
<script setup lang="ts">
import { ref } from "vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";

definePageMeta({ layout: "auth" });
const { t } = useI18n();
useHead({ title: () => t("auth.forgot.title") });

const auth = useAuthStore();
const email = ref("");
const error = ref<string | null>(null);
const sent = ref(false);

const onSubmit = async () => {
  error.value = null;
  if (!email.value) {
    error.value = t("validation.required");
    return;
  }
  try {
    await auth.forgotPassword(email.value);
    sent.value = true;
  } catch {
    error.value = t("common.genericError");
  }
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("auth.forgot.title") }}</h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("auth.forgot.subtitle") }}</p>
    </header>

    <div v-if="sent" class="rounded-lg border border-line-passive bg-surface-raised p-6 text-center">
      <p class="text-body text-ink">{{ t("auth.forgot.sentTitle") }}</p>
      <p class="mt-2 text-caption text-ink-muted">{{ t("auth.forgot.sentHelp", { email }) }}</p>
    </div>

    <form v-else class="space-y-4" @submit.prevent="onSubmit">
      <Input v-model="email" type="email" autocomplete="email" :label="t('auth.email')" size="lg" />
      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
      <Button type="submit" variant="primary" size="lg" :loading="auth.loading" block>
        {{ t("auth.forgot.submit") }}
      </Button>
    </form>

    <p class="mt-6 text-center text-caption text-ink-muted">
      <NuxtLink to="/auth/login" class="text-ink underline underline-offset-2">{{ t("auth.forgot.backToLogin") }}</NuxtLink>
    </p>
  </div>
</template>
```

- [ ] **Step 3: Reset page**

`pages/auth/reset-password.vue`:
```vue
<script setup lang="ts">
import { computed, ref } from "vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";

definePageMeta({ layout: "auth" });
const { t } = useI18n();
useHead({ title: () => t("auth.reset.title") });

const route = useRoute();
const auth = useAuthStore();
const { toFieldErrors } = useApiError();

const token = computed(() => (typeof route.query.token === "string" ? route.query.token : ""));
const email = ref(typeof route.query.email === "string" ? route.query.email : "");
const password = ref("");
const confirm = ref("");
const error = ref<string | null>(null);
const linkInvalid = computed(() => token.value === "");

const onSubmit = async () => {
  error.value = null;
  if (!email.value || !password.value) return (error.value = t("validation.required"));
  if (password.value.length < 8) return (error.value = t("validation.minLength", { min: 8 }));
  if (password.value !== confirm.value) return (error.value = t("auth.reset.mismatch"));
  try {
    await auth.resetPassword({ token: token.value, email: email.value, password: password.value });
    await navigateTo(auth.isTenant ? "/tenant" : "/owner");
  } catch (err) {
    const f = toFieldErrors(err);
    error.value = f ? Object.values(f)[0]! : t("auth.reset.invalid");
  }
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("auth.reset.title") }}</h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("auth.reset.subtitle") }}</p>
    </header>

    <div v-if="linkInvalid" class="rounded-lg border border-line-passive bg-surface-raised p-6 text-center">
      <p class="text-body text-ink">{{ t("auth.reset.invalid") }}</p>
      <NuxtLink to="/auth/forgot-password" class="mt-3 inline-block text-caption text-ink underline underline-offset-2">
        {{ t("auth.reset.requestNew") }}
      </NuxtLink>
    </div>

    <form v-else class="space-y-4" @submit.prevent="onSubmit">
      <Input v-model="email" type="email" autocomplete="email" :label="t('auth.email')" size="lg" />
      <Input v-model="password" type="password" autocomplete="new-password" :label="t('auth.reset.newPassword')" size="lg" />
      <Input v-model="confirm" type="password" autocomplete="new-password" :label="t('auth.reset.confirmPassword')" size="lg" />
      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
      <Button type="submit" variant="primary" size="lg" :loading="auth.loading" block>
        {{ t("auth.reset.submit") }}
      </Button>
    </form>
  </div>
</template>
```

- [ ] **Step 4: Login link** — in `pages/auth/login.vue` add under the password input (inside the form, before the error line):
```vue
      <p class="text-right text-caption">
        <NuxtLink to="/auth/forgot-password" class="text-ink-muted underline underline-offset-2 hover:text-ink">
          {{ t("auth.forgotPassword") }}
        </NuxtLink>
      </p>
```
The `auth.forgotPassword` key already exists in both locales.

- [ ] **Step 5: i18n** — `en.json` `auth` add:
```json
    "forgot": {
      "title": "Forgot your password?",
      "subtitle": "Enter your email and we'll send you a reset link.",
      "submit": "Send reset link",
      "sentTitle": "Check your inbox",
      "sentHelp": "If {email} has an account, a reset link is on its way. It expires in 60 minutes.",
      "backToLogin": "Back to log in"
    },
    "reset": {
      "title": "Set a new password",
      "subtitle": "Choose a password with at least 8 characters.",
      "newPassword": "New password",
      "confirmPassword": "Confirm password",
      "mismatch": "Passwords do not match.",
      "submit": "Save password and log in",
      "invalid": "This reset link is invalid or has expired.",
      "requestNew": "Request a new link"
    },
```
`ms.json`:
```json
    "forgot": {
      "title": "Lupa kata laluan?",
      "subtitle": "Masukkan e-mel anda dan kami akan hantar pautan tetapan semula.",
      "submit": "Hantar pautan tetapan semula",
      "sentTitle": "Semak peti masuk anda",
      "sentHelp": "Jika {email} mempunyai akaun, pautan tetapan semula sedang dihantar. Ia tamat tempoh dalam 60 minit.",
      "backToLogin": "Kembali ke log masuk"
    },
    "reset": {
      "title": "Tetapkan kata laluan baharu",
      "subtitle": "Pilih kata laluan sekurang-kurangnya 8 aksara.",
      "newPassword": "Kata laluan baharu",
      "confirmPassword": "Sahkan kata laluan",
      "mismatch": "Kata laluan tidak sepadan.",
      "submit": "Simpan kata laluan dan log masuk",
      "invalid": "Pautan tetapan semula ini tidak sah atau telah tamat tempoh.",
      "requestNew": "Minta pautan baharu"
    },
```
(`{email}` interpolation is fine; no literal `@`.)

- [ ] **Step 6: Tracked paths** — `composables/useTrack.ts` treats `/auth/*` as tracked marketing paths, so page views on the two new pages are fine and need no change.

- [ ] **Step 7: Verify** — typecheck + dev log. Demo: login → "Forgot your password?" → submit → sent state; `/auth/reset-password?token=x&email=aminah@roofly.my` → set → lands on `/owner`. Add the two pages + the Mailtrap check to the Task 15 handoff list, and add the two endpoints to API-SPEC/API-MAP in Task 15.

---

## Self-review

**Spec coverage.** § 3 Google sign-in → Tasks 2, 3, 7, 8, 14 (set password). § 3.4 forgot/reset password → Tasks 16, 17. § 3.2 `AuthUser` → Task 1, 7. § 4 onboarding → Tasks 1, 4, 9, 14 (edit later). § 5 purpose + effects → Tasks 5, 6, 10, 11. § 6 checklist → Tasks 12, 13. § 7 parity → Task 7 (every contract method implemented in both adapters; TypeScript enforces). § 8 error handling → Task 3 (401/403/422), Task 8 (unavailable/failed/notOwner), Task 9 (guard loop until onboarded). § 9 testing → backend PHPUnit tasks 1–6, Vitest task 12; spec said "Pest" and "ReportTest" — repo uses PHPUnit, and the Reports page computes client-side, so the report exclusion is covered by the frontend composable (Task 11) and the backend dashboard test (Task 6). § 10 rollout → env vars in Tasks 2 and 8, demo never renders the button (Task 8 flag).

**Deviation from spec, deliberate:** `AuditLogger` lives in `app/Services` (not `app/Support`); auth validation stays inline (repo convention, no new FormRequests for auth); property `purpose` is a string column, not a DB enum (sqlite); the own-stay demo property is frontend-mock only (the DB `DemoSeeder` is count-pinned by `DemoSeederTest` and the spec only needs it visible in `demo-roofly`, which is frontend-only).

**Type consistency.** `OwnerPurpose` (auth) and `PropertyPurpose` (property) share the same three literals by design — one is the owner answer, the other the per-property tag. `setChecklistDismissed(boolean)` replaces the spec's `dismissChecklist/restoreChecklist` pair (one method, one endpoint, one toggle). `ChecklistStep.enabled` is an addition the spec implied ("render disabled"). `auth.setUser` is used by Tasks 9, 13, 14. Deep-link strings in Task 12 tests match Task 13 consumers exactly: `/owner/properties?add=1`, `/owner/tenants?invite=1`, `/owner/properties/:id?tab=ownership|utilities|overview`, `/owner/agreements/new`.
