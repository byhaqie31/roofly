# Admin analytics — views, leads, demo, registrations — design

**Date:** 2026-08-23
**Scope:** First-party, lightweight analytics for roofly.my: capture page views, demo engagement, waitlist signups and owner registrations from the public/marketing surfaces into our own database, and show them on a new Admin Portal page (`/admin/analytics`) with a funnel, trend charts, and an actionable leads list. Both adapters (demo + API), backend routes + tests.
**Out of scope:** sessions / time-on-page, heatmaps, cohorts, real-time, geo, A/B tests, any third-party analytics vendor, tracking inside the owner / tenant / admin shells.
**Depends on:** the admin back office foundation (`2026-08-23-admin-backoffice-foundation-design.md`) — permissions, `AuditLogger`, admin layout, `DataTableShell`, `MiniAreaChart` `format` prop; and the demo/API adapter split (`2026-08-23-demo-adapter-split-design.md`).

---

## 1. Why

Before launch the team needs to know: how many people look at roofly.my, how many try the demo, how many leave an email, how many become owners — and **who** the leads are so they can be followed up. Today the only capture is the waitlist form posting to Web3Forms (an external inbox); nothing records views, demo entries or the funnel between them.

## 2. Locked decisions

| Decision | Choice |
|---|---|
| Lead definition | Funnel: visitor → tried demo → left email (waitlist) → registered as owner. Tenants are not leads (owners invite them); they appear as a registered count only. |
| Capture | **First-party, minimal.** A `POST /api/track` beacon from the Nuxt app into our MySQL. No cookies, no vendor script. Visitor identity = random UUID in `localStorage["roofly_vid"]`. |
| What is tracked | Public + marketing surfaces only (`/`, `/coming-soon`, `/demo/**`, `/auth/*`). **Never** inside `/owner/**`, `/tenant/**`, `/admin/**`. |
| Event set | Fixed whitelist of five (§ 3). Backend rejects anything else. |
| Waitlist | Keeps posting to Web3Forms unchanged **and** records a `waitlist_signup` event → lead row. |
| Users axis | Unique visitors (new vs returning) + a **leads list** (email, source, first/last seen, page views, demo ✓, converted owner). A lead's last 20 events are viewable in a drawer; no full per-person analytics. |
| Privacy | Only PII stored is the email a person typed. IP is stored as a salted SHA-256 hash; user agent truncated to 255. Raw events pruned after 13 months (scheduled); leads kept. |
| Permission | New key `analytics.view` (14th), **in the Operations preset**. Read-only screen; the only audited action is the CSV export (`analytics.exported`). |
| Money | None. Counts and percentages only. |
| Demo | `demo/data/analytics.ts` generates ~90 days of deterministic data; the admin page works demo-first like every other surface. `features.admin` stays off in demo, so demo-roofly never shows it. |

## 3. Events (frontend → `POST /api/track`)

`useTrack()` composable — `track(name: TrackEvent, props?: Record<string, string>)`. Uses `navigator.sendBeacon` when available, else `$fetch` with `keepalive`. Fire-and-forget; failures are swallowed (analytics must never break a page). Tracking runs in uat and production. It is a no-op in demo (`useEnv().useMock`) and can be switched off locally with `NUXT_PUBLIC_TRACKING=false` (new env, default on; exposed as `useEnv().trackingEnabled`).

| Event | Fired from | Props |
|---|---|---|
| `page_view` | `plugins/track.client.ts` on `router.afterEach` for tracked paths | `path`, `referrer` (document.referrer host, first hit only), `utm_source/medium/campaign` (first touch, persisted in `localStorage["roofly_utm"]`) |
| `demo_enter` | `pages/demo/index.vue` on mount; `DemoLoginShortcuts.vue` on click | `role` (`owner` / `tenant` / `landing`) |
| `demo_feedback_click` | `FloatingFeedback.vue` on click — **reserved**: the widget only renders on the demo subdomain, which is frontend-only (no API), so this event is not captured today; it stays whitelisted for when the widget appears on a tracked surface | — |
| `waitlist_signup` | `components/marketing/EmailCapture.vue` after a successful Web3Forms post | `email` |
| `register` | `pages/auth/register.vue` after `auth.register()` resolves | `email`, `userId` |

Payload: `{ visitorId, event, path, referrer?, utm?, props?, at }` (`at` is accepted for forward compatibility and ignored; the server timestamp is authoritative).

## 4. Data model (backend)

```
analytics_events
  id            uuid pk
  visitor_id    uuid            index
  event         string(40)      index (event, created_at)
  path          string(255) null
  referrer      string(255) null
  utm           json null        {source, medium, campaign}
  props         json null        (email/userId/role …, ≤ 2 KB)
  ip_hash       char(64) null    sha256(ip + APP_KEY)
  user_agent    string(255) null
  created_at    timestamp        index

leads
  id                 uuid pk
  email              string(255) unique
  visitor_id         uuid null index
  source             enum('waitlist','demo','register')   — first thing that identified them
  first_seen_at      timestamp
  last_seen_at       timestamp
  converted_user_id  uuid null fk users (nullOnDelete)
  created_at / updated_at
```

Write path (`TrackController@store` → `AnalyticsRecorder` service):
1. Validate event ∈ whitelist, `visitorId` is a uuid, `props` ≤ 2 KB, email (if present) is a valid email.
2. Insert the event row.
3. If the event carries `email`: upsert `leads` by email — set `visitor_id` if null, `first_seen_at` on create, `last_seen_at` always, `source` on create (`waitlist` for waitlist_signup, `register` for register). `converted_user_id` is **never** set from the client payload — only `linkRegistration()` (server-side, on the real registration) converts a lead.
4. If a `demo_enter` arrives for a `visitor_id` that already has a lead row, bump `last_seen_at` (demo ✓ is derived at read time from events).

Backfill: `RegisterController` also calls `AnalyticsRecorder::linkRegistration($user, $visitorId?)` so a lead who registers with a different browser is still marked converted by email.

Throttle: `throttle:track` = 120 requests/min per IP. Oversized or unknown events → 422; the client ignores the response.

Prune: `analytics:prune` artisan command scheduled daily (`routes/console.php`), deletes `analytics_events` older than 13 months in 1 000-row chunks.

## 5. Admin API (`/api/admin/analytics/*`, `auth:sanctum` + `role:admin` + `can:analytics.view`)

```
GET  admin/analytics/overview?from=YYYY-MM-DD&to=YYYY-MM-DD      (default: last 30 days inclusive)
GET  admin/analytics/leads?q=&source=&converted=1&page=&perPage=   {data: AdminLead[], meta}
GET  admin/analytics/leads/{lead}                                  AdminLead + events[] (last 20)
GET  admin/analytics/leads/export.csv?q=&source=&converted=1       (audited: analytics.exported)
POST track                                                         (guest, throttle:track)
```

`overview` payload:
```
{
  range: { from, to, days },
  tiles: { views, visitors, newVisitors, demoEntries, leads, registrations, conversionPct },
  series: { days: [YYYY-MM-DD…], views: [], visitors: [], leads: [], registrations: [] },
  funnel: { visitors, demo, leads, registered },          // counts of distinct visitors/leads in range
  topPages: [{ path, views }]  (top 10),
  referrers: [{ referrer, visitors }] (top 10, "direct" for null)
}
```
`conversionPct` = registrations ÷ visitors × 100 (0 when no visitors). `visitors` = distinct `visitor_id`; `newVisitors` = visitors whose first-ever event is inside the range.

`AdminLeadResource` keys (pinned by test): `id, email, source, firstSeenAt, lastSeenAt, pageViews, demoEntered, convertedUserId, convertedOwnerName`. `LeadEventResource`: `id, event, path, props, createdAt` (props with `email` redacted to the lead's own email only; `ip_hash` / `user_agent` never emitted).

## 6. Admin page (`/admin/analytics`)

Sidebar: "Analytics" between Tenants and Audit, gated `analytics.view` (NoAccess card otherwise).

Layout (top → bottom), all counts, no money:
1. Header: title + date-range `<Select>` (7 / 30 / 90 days / custom) with two date `<Input>`s when custom; range is kept in the route query.
2. Six `StatTile`s: Views · Visitors (help: "{new} new") · Demo entries · Leads · Registrations · Conversion %.
3. Two `MiniAreaChart`s (`format` = count; the component is single-series): "Views" (daily `series.views`) and "Registrations" (daily `series.registrations`). Visitors and leads per day are shown as the tile help text ("{n} today / {avg} avg"), not as extra lines.
4. Funnel strip `FunnelStrip.vue`: four steps with count and step-to-step %, card-row on mobile.
5. Two lists side by side (stacked under `lg`): Top pages, Top referrers.
6. Leads table via `DataTableShell` (same list-page patterns as owners/tenants: route-synced filters, reset-page-or-load watchers, keyboard rows, `<button>` cards): email · source pill · first seen · last seen · page views · demo ✓ · converted → link to `/admin/owners/[id]` or "—". Search (email), filters source / converted, CSV export (`audit.view` not required — `analytics.view` suffices; export is audited).
7. `LeadDrawer.vue` (Modal, size lg): lead header + last 20 events as an `AuditTable`-style list (reuses the card-row pattern; new small `EventList.vue` rather than overloading `AuditTable`).

English-only like the rest of the admin shell. Tokens/components per UI-STANDARDS; new mobile note § 11.16 "Funnel strip".

## 7. Frontend structure

```
types/analytics.ts                 TrackEvent, TrackPayload, AdminLead, LeadEvent, AnalyticsOverview, LeadListQuery
composables/useTrack.ts            track(); visitor id + first-touch UTM in localStorage
plugins/track.client.ts            page_view on route change for tracked paths (skips owner/tenant/admin)
services/contracts/admin/analytics.ts   AdminAnalyticsService { overview(range), leads(query), lead(id), exportCsv(query) }
services/api/admin/analytics.ts    apiAdminAnalytics (+ apiTrack in services/api/track.ts — used by useTrack in API mode)
demo/services/admin/analytics.ts   demoAdminAnalytics over demo/data/analytics.ts (seeded PRNG, 90 days)
demo/track.ts                      demoTrack: no-op (demo never tracks)
services/useAdminAnalytics.ts      selector
components/admin/{FunnelStrip,LeadDrawer,EventList,SourcePill}.vue
pages/admin/analytics.vue
```
`useTrack()` picks `demoTrack` vs `apiTrack` via `useEnv().useMock` like every selector; in demo it is a no-op so demo-roofly generates no rows.

## 8. Backend structure

```
database/migrations/…_create_analytics_events_table.php, …_create_leads_table.php
app/Models/{AnalyticsEvent,Lead}.php
app/Support/AdminPermissions.php           + ANALYTICS_VIEW ('analytics.view', preset: true)
app/Services/AnalyticsRecorder.php         record(payload, ip, ua); linkRegistration(user, visitorId?)
app/Http/Controllers/Api/TrackController.php
app/Http/Controllers/Api/Admin/AnalyticsController.php   overview, leads, lead, export
app/Http/Requests/TrackRequest.php, Admin/AnalyticsRangeRequest.php
app/Http/Resources/Admin/{AdminLeadResource,LeadEventResource}.php
app/Console/Commands/PruneAnalyticsEvents.php + routes/console.php schedule
database/seeders/AnalyticsDemoSeeder.php   (called by DemoSeeder; ~90 days, deterministic)
tests/Feature/Analytics/{TrackTest,AnalyticsRecorderTest,AdminAnalyticsTest,AdminLeadResourceTest,PruneTest}.php
```

## 9. Testing

Backend: track accepts whitelisted events and rejects unknown / oversized / bad uuid (422); throttle header present; waitlist event creates a lead, register converts it (also via `linkRegistration` by email with a different visitor); overview math on a fixed fixture (views, distinct visitors, new visitors, funnel, conversionPct, series length = days); leads filters + pagination; resource key sets pinned; export requires `analytics.view` and is audited; prune deletes only > 13 months. Permission: ops preset includes `analytics.view`; an admin without it gets 403.

Frontend: typecheck (5 known errors, 0 new); import-direction greps; no money helpers under admin; `grep -rn "useTrack\|track(" frontend/app/pages/{owner,tenant,admin}` must be empty (no tracking in product shells). Browser walk by the owner: visit `/coming-soon` + `/demo` in API mode, sign up to the waitlist, register an owner, then see the funnel/leads in `/admin/analytics`; demo mode shows seeded data and sends zero `/api/track` requests.

## 10. Open points (decide during the plan)

- Exact Operations-preset change is additive (`analytics.view`): existing ops admins seeded by `DemoSeeder` get it on reseed; on UAT the super-admin grants it once (or we add a one-off seeder step).
- `register` event is fired client-side *and* linked server-side; the server link is the source of truth — the client event is only for the timeline.
- Custom date range capped at 366 days to keep the overview query cheap.
