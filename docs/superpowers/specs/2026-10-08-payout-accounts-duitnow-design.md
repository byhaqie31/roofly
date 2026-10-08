# Payout accounts + manual DuitNow rent payments — design

**Date:** 2026-10-08 · **Status:** approved in chat, implementing on `fix/footer-layout` · **Builds on:** [2026-10-07-invoice-generation-tenant-invite-design.md](2026-10-07-invoice-generation-tenant-invite-design.md), [2026-10-07-agreement-review-design.md](2026-10-07-agreement-review-design.md)

## 1. Goal

Roofly has no payment gateway yet, but the tenant "Pay now" pretends to be FPX and flips the invoice to `paid` instantly, and the owner's only payout data is a read-only `bankAccountLast4` that nothing can edit. Rent really moves by **manual DuitNow / bank transfer** today. This change:

1. lets owners **manage payout accounts** in Settings (several, one default);
2. lets each **agreement point at a payout account** (default unless overridden);
3. replaces the fake FPX pay with **tenant claims "I've paid" → owner confirms or rejects**;
4. **prepares the gateway** (FPX / card) as a visible "Coming soon" path behind one flag, without building any gateway code.

## 2. Decisions (owner, 2026-10-08)

| Question | Decision |
|---|---|
| Tenant flow | **Claim + owner confirms.** Tenant transfers in their banking app, taps "I've paid" with a reference; owner confirms (→ paid) or rejects (→ unchanged, tenant can re-claim). |
| Proof | **Reference + date paid (+ optional note) now**; receipt upload is a Phase-4 placeholder (no file uploads exist yet). |
| Account structure | **Many accounts, one default, per-agreement override.** `agreements.payout_account_id = null` means "owner's default". |
| Gateway | **Prepared, "Coming soon".** `features.onlinePayments` (off everywhere incl. demo). The simulated FPX modal survives behind it as the shell the real gateway slots into. |
| Emails | Keep both: `PaymentClaimSubmitted` → owner, `PaymentClaimRejected` → tenant. |
| Old column | `users.bank_account_last4` is **dropped**; the read-only Profile block goes away. |

## 3. Data model

### 3.1 `payout_accounts` (new)

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `owner_id` | uuid FK users, cascade | |
| `label` | string 60 | e.g. "Personal Maybank" |
| `bank` | string 40 | `MalaysianBank` slug (§ 3.4) |
| `account_holder_name` | string 120 | |
| `account_number` | string 30, nullable | digits only (normalised server-side: strip spaces/dashes) |
| `duitnow_id_type` | string 20, nullable | `phone` \| `mykad` \| `brn` \| `passport` |
| `duitnow_id` | string 40, nullable | required iff `duitnow_id_type` set |
| `is_default` | bool | exactly one `true` per owner who has ≥1 account |
| timestamps | | |

Rules: at least one of `account_number` / `duitnow_id` (422 otherwise). The first account an owner creates is default regardless of input. `POST …/default` flips the rest off in one transaction. Deleting the default promotes the **oldest remaining** account. Deleting an account referenced by agreements nulls their `payout_account_id` (FK `nullOnDelete`) — they fall back to the default; the UI warns with `agreementCount` first.

Forward-looking only (not built): `gateway_ref`, `verified_at` when a gateway needs the account as its settlement target.

### 3.2 `agreements.payout_account_id` (new, nullable FK `nullOnDelete`)

Not a term column — changing it never drops a sent/accepted agreement back to draft, and it's editable at any status. Store/Update requests accept `payoutAccountId: uuid|null`; the controller 422s (`payoutAccountId` field error) if it isn't one of the caller's accounts. **Resolved account** = the agreement's account ?? the owner's default ?? `null`.

### 3.3 `payments` (columns added)

| Column | Notes |
|---|---|
| `payout_account_id` | nullable FK `nullOnDelete` — the resolved account at claim time |
| `note` | nullable string 500 — tenant's note |
| `rejection_reason` | nullable string 300 |
| `confirmed_at` | nullable timestamp |

A **claim** is a `Payment { method: transfer, status: pending, reference, paid_at = claimed date, amount = invoice total due at claim time }`. Confirm → `successful` + `confirmed_at` (+ optional corrected `paid_at`), invoice → `paid`. Reject → `failed` + `rejection_reason`, invoice untouched. **Invoice statuses are unchanged** — "awaiting confirmation" is derived from "has a pending payment". At most **one pending payment per invoice** (409 `claim_pending` otherwise).

### 3.4 Banks

`MalaysianBank` = `maybank | cimb | public_bank | rhb | hong_leong | ambank | bank_islam | bank_rakyat | bsn | affin | alliance | ocbc | uob | hsbc | standard_chartered | muamalat | agrobank | gxbank | aeon_bank | boost_bank | other`. Labels are proper nouns, not translated: `frontend/app/config/banks.ts`; the backend validates the slug list (`App\Enums\MalaysianBank`).

## 4. API

All owner routes sit in the existing `role:owner` + `not-suspended` group; tenant routes under `role:tenant` `/me`.

| Method | Path | Body → response |
|---|---|---|
| GET | `/payout-accounts` | → `PayoutAccount[]` (default first, then oldest) |
| POST | `/payout-accounts` | `{label, bank, accountHolderName, accountNumber?, duitnowIdType?, duitnowId?, isDefault?}` → `201 PayoutAccount` |
| PATCH | `/payout-accounts/{id}` | same fields, all `sometimes` → `PayoutAccount` (403 other owner) |
| POST | `/payout-accounts/{id}/default` | → `PayoutAccount[]` |
| DELETE | `/payout-accounts/{id}` | → `204` |
| POST | `/me/invoices/{id}/claim` | `{reference (req, ≤100), paidAt (req, date, ≤ today), note? (≤500)}` → `201 {payment, invoice}`. 403 not the tenant's; 422 invoice not `pending`/`overdue`; 409 `{code: "claim_pending"}`; 422 `{code: "no_payout_account"}` when nothing resolves. Emails the owner `PaymentClaimSubmitted` when their `payment_received` notification event is on (default on). |
| POST | `/payments/{id}/confirm` | `{paidAt?}` → `{payment, invoice}`; 403 other owner; 409 unless `pending` |
| POST | `/payments/{id}/reject` | `{reason (req, ≤300)}` → `{payment, invoice}`; 403 / 409 as above; emails the tenant `PaymentClaimRejected` |
| POST | `/me/invoices/{id}/pay` | **existing simulated gateway** — now `403 {code: "online_payments_unavailable"}` unless `config('app.online_payments')` (env `ONLINE_PAYMENTS`, default `false`), so nobody can self-mark paid on UAT/prod. |

Resource shapes:

```ts
PayoutAccount { id, label, bank, accountHolderName, accountNumber: string|null,
  duitnowIdType: "phone"|"mykad"|"brn"|"passport"|null, duitnowId: string|null,
  isDefault: boolean, agreementCount: number /* owner endpoints only; 0 elsewhere */, createdAt }
Payment += { payoutAccountId: string|null, note: string|null, rejectionReason: string|null, confirmedAt: string|null }
Agreement += { payoutAccountId: string|null }
AgreementWithRefs += { payoutAccount: PayoutAccount|null }   // resolved
InvoiceWithRefs  += { payoutAccount: PayoutAccount|null }   // resolved for the invoice's agreement
OwnerProfile -= bankAccountLast4
AttentionKind += "payment_claim"   // title = invoice number, meta = tenant name, link = /owner/payments?status=awaiting; listed first
```

Privacy: owners see their own accounts in full. A tenant sees full details only of the account resolved for **their own** agreement/invoices (they must type them into their bank). **Admin never sees payout accounts** — no admin Resource changes; `AdminResourcesTest` stays green.

## 5. Overdue + late fees

`InvoiceGenerator::markOverdue` skips an invoice that has a **pending** payment whose `date(paid_at) <= due_date` — a tenant who paid on time isn't fined while the owner hasn't looked. Rejected → next roll flips it overdue with the fee. A claim dated after `due_date` doesn't block the flip. No waiver button (out of scope).

## 6. Frontend

**Contracts**
- New `PayoutAccountsService` (`list`, `create`, `update`, `setDefault`, `remove`) + `services/usePayoutAccounts.ts`; demo adapter over `demo/data/payoutAccounts.ts` (Aminah: "Personal Maybank" default with DuitNow phone, "Aminah Properties CIMB" business with SSM BRN); API adapter → § 4.
- `InvoicesService` += `claimTransferForTenant(invoiceId, {reference, paidAt, note?})`, `confirmClaim(paymentId, {paidAt?})`, `rejectClaim(paymentId, reason)`. `payForTenant` stays as the gateway method.
- `utils/paymentClaim.ts` (pure, Vitest): `pendingClaim(payments)`, `latestRejectedClaim(payments)` (only if no later pending/successful), `maskAccountNumber(n)` (`•••• 4521`).

**Flag** — `useEnv().features.onlinePayments` ← `NUXT_PUBLIC_FEATURE_ONLINE_PAYMENTS` (default off; demo follows the flag too, so it reads "Coming soon" everywhere today).

**Owner**
- Settings → new **Payouts** tab (between Profile and Preferences; `?tab=payouts` deep-link). `SettingsPayoutsPanel`: account cards (label, bank, holder, masked number, DuitNow ID, Default pill) with Edit / Set as default / Delete (delete confirms, mentions `agreementCount`); Add opens `PayoutAccountFormModal` (vee-validate + Zod `schemas/payoutAccount.ts`). Below: **Online payments — Coming soon** card. The Profile tab's read-only payout block is removed.
- Agreement detail Overview: **Payout** row — resolved account + "(your default)" hint, **Change** → select ("Use my default" + accounts). Create page: same select, default "Use my default". No account yet → link to Settings → Payouts.
- Payments page: rows with a pending claim show an **Awaiting confirmation** pill (tone `pending`) instead of the stored status pill and a **Review** action; a new **Awaiting** filter chip (`?status=awaiting` deep-link). `InvoiceViewModal` shows a claim panel (reference, date paid, note, account, receipt placeholder) with **Confirm** (editable date) and **Reject** (required reason).
- Dashboard: `payment_claim` attention kind (tone `pending`), demo mirror kept in lock-step.
- Getting-started checklist: new `add_payout` step (rental-only, before `create_agreement`) → `/owner/settings?tab=payouts`; `ChecklistInput.payoutAccounts`.

**Tenant**
- `PayInvoiceModal` becomes a method picker: **DuitNow transfer** (account details with Copy buttons → "I've paid" form: reference, date paid, note, receipt placeholder) and **Online banking / card** (Coming soon pill, disabled; with the flag on it runs today's simulated FPX flow). No resolved account → "Your landlord hasn't added payment details yet".
- Invoice cards + home hero: pending claim → "Awaiting landlord confirmation" instead of Pay now; rejected claim → reason + Pay now again.
- `/tenant/agreement`: read-only **Pay rent to** card (also during review).

## 7. Tests

Backend: `PayoutAccountTest` (CRUD, first-is-default, single default, set-default, delete promotes, delete nulls agreements, ≥1 of number/DuitNow ID, 403 cross-owner), `PaymentClaimTest` (claim happy path + emails respecting the pref, 403/409/422 cases, confirm/reject + emails, cross-owner 403, gateway pay 403 when flag off), `InvoiceGeneratorTest` additions (overdue skip / reject-then-flip / late claim), agreement payout validation + resolution in `/me/agreement` and `/me/invoices`, `bank_account_last4` gone from the account resource, admin key pins untouched. Frontend: Vitest for `paymentClaim.ts` + the checklist step; typecheck + dev-server log.

## 8. Out of scope

Gateway integration (Billplz/Curlec, webhooks, settlement) · receipt upload (Phase 4) · DuitNow QR image (Phase 4) · late-fee waiver · partial payments · WhatsApp notifications · bank-account verification.
