# Agreement review — owner sends, tenant agrees — design

**Date:** 2026-10-07 · **Status:** approved in chat, implementing on `feature/admin-backoffice` · **Builds on:** [2026-10-07-invoice-generation-tenant-invite-design.md](2026-10-07-invoice-generation-tenant-invite-design.md)

## 1. Goal

Today an owner types an agreement and flips it to `active` alone; the tenant never sees or agrees to it. Owners want to **send** the terms, tenants want to **review and agree** (or push back), and the owner wants a clear signal to activate. Nothing is signed here — e-signatures are Phase 4 (documents) — this is the agreement-on-terms step that precedes the paperwork.

## 2. Decisions (owner, 2026-10-07)

| Question | Decision |
|---|---|
| Tenant's options | **Agree**, or **Ask for changes** with a short note. |
| On agree | Status becomes **`accepted`**; the owner activates it (one click). Rent and the first invoice start only on activation. |
| Owner edits while sent or accepted | Terms edits **drop the agreement back to `draft`**; the owner re-sends. The tenant always agreed to exactly what they saw. |
| Direct activation | Still allowed from `draft` (paper agreements that already exist) — the status select keeps `active`/`expired`/`terminated`; `pending_review` and `accepted` are reachable only through the flow. |

## 3. Status model

`draft → pending_review → accepted → active → expired | terminated`

| Transition | Who | How | Side-effects |
|---|---|---|---|
| draft → pending_review | owner | `POST /agreements/{id}/send` | `sent_at = now`, `review_note = null`, email `AgreementSent` to tenant |
| pending_review → draft | owner | `POST /agreements/{id}/withdraw` | `sent_at = null` |
| pending_review → accepted | tenant | `POST /me/agreements/{id}/accept` | `accepted_at = now`, email `AgreementReviewed(accepted)` to owner |
| pending_review → draft | tenant | `POST /me/agreements/{id}/request-changes {note}` | `review_note`, `changes_requested_at = now`, `sent_at = null`, email `AgreementReviewed(changes)` to owner |
| accepted → active | owner | existing `PUT /agreements/{id} {status: active}` (the detail page gets an **Activate** button) | existing `InvoiceGenerator::generateFor` |
| pending_review / accepted → draft | owner | any `PUT` that changes a **term** field (`unitId`, `tenantId`, dates, money, `rentDueDay`) | `sent_at = accepted_at = null`; the response carries `status: draft` and the UI toasts "Sent back to draft — re-send to the tenant" |

Guards: `send` is `409` unless `draft`; `withdraw` is `409` unless `pending_review`; `accept` / `request-changes` are `409` unless `pending_review`, `403` unless `agreement.tenant_id` is the caller; `note` is required, ≤ 500 chars. Everything else (`403` owner scoping) is the existing `authorizeOwner`.

Not changed: the daily `invoices:roll`, dashboards and occupancy still count only `active`. A `pending_review`/`accepted` agreement is not a tenancy yet.

## 4. Backend

- Migration `2026_10_07_000004_add_review_flow_to_agreements`: widen the `status` enum to the six values (`->change()`), add `sent_at`, `accepted_at`, `changes_requested_at` (nullable timestamps) and `review_note` (nullable string 500).
- `AgreementStatus` gains `PENDING_REVIEW`, `ACCEPTED`. `AgreementResource` gains `sentAt`, `acceptedAt`, `changesRequestedAt`, `reviewNote` (all nullable). Key-pin tests updated.
- `Owner\AgreementController`: `send`, `withdraw`; `update` detects term changes (`wasChanged()` on the term columns) while `pending_review`/`accepted` and resets to `draft`. `StoreAgreementRequest`/`UpdateAgreementRequest` reject `pending_review`/`accepted` as a manually set status (`422`).
- `Tenant\TenantAgreementReviewController`: `accept`, `requestChanges`.
- `TenantAgreementController@show` fallback order becomes: `active`, else `pending_review`, else `accepted`, else most recent non-draft — so a tenant with an old expired agreement still sees the new one awaiting review.
- Notifications (queued, bilingual, same style as `TenantInvite`): `AgreementSent` → tenant, link `/tenant/agreement`; `AgreementReviewed` → owner (unit → property → owner), link `/owner/agreements/{id}`, includes the note when present.

## 5. Frontend

- `types/agreement.ts`: six-value `AgreementStatus`; optional `sentAt`, `acceptedAt`, `changesRequestedAt`, `reviewNote` on `Agreement`. Contract: `send(id)`, `withdraw(id)`, `acceptForTenant(tenantId, agreementId)`, `requestChangesForTenant(tenantId, agreementId, note)`; demo implements the same transitions in memory.
- **Owner detail header** (`/owner/agreements/[id]`): actions become a button group — `draft`: **Send to tenant** (primary); `pending_review`: **Withdraw** (ghost) + "Sent on {date}" caption; `accepted`: **Activate** (primary) + "Agreed by tenant on {date}"; Delete becomes an **icon-only** ghost button (`Trash2`, `aria-label` + `title`) at the end of the group, on every status. The overview panel shows a review banner: awaiting tenant / tenant agreed / tenant asked for changes with the note.
- **Terms form**: `pending_review`/`accepted` are shown in the status select only as the current, disabled value; saving while in either state toasts the "back to draft" message from the response.
- **Pills / cards / lists**: `pending_review` uses the `pending` tone, `accepted` the `paid` tone, on both shells, EN + BM.
- **Tenant agreement page**: when `pending_review`, a review card above the terms with **Agree** (confirm modal restating rent, deposit, dates) and **Ask for changes** (modal with a required note). After either, the page reloads the agreement and shows the new state (`accepted`: "Agreed — waiting for your landlord to activate"; back to draft: "Your note was sent"). **Tenant home**: a "Your agreement is ready to review" card at the top while `pending_review`, linking to the agreement page; the "no active tenancy" empty state is kept for the rest.
- Owner dashboard "Needs attention" feed: **not** in this change (noted in § 7). The email plus the list/detail pills carry the signal for now.

## 6. Tests

Backend `AgreementReviewTest`: send (409 from non-draft, 403 other owner, email + timestamps), withdraw, tenant accept (status/timestamp/email), tenant request-changes (note required, back to draft, email with note), tenant 403 on someone else's agreement, term edit while pending/accepted → draft (and a non-term edit, e.g. nothing changed, does not), manual `status: pending_review` → 422, accepted → active via PUT still generates invoices, `/me/agreement` prefers pending over expired. Resource key pins updated. Frontend: typecheck + SSR render of both pages; no component tests (no jsdom).

## 7. Out of scope

Dashboard "Needs attention" items for accepted / changes-requested agreements · WhatsApp notifications · e-signature / PDF of the agreement (Phase 4) · tenant-initiated termination · reminders if the tenant doesn't respond.
