# Roofly — Project Planning Document

> **Last updated:** May 2026
> **Status:** Pre-development — Phase 1 scaffolding next
> **Owner:** Qie ([@byhaqie31](https://github.com/byhaqie31))

This is the source of truth for everything Roofly. Architecture decisions, scope boundaries, naming choices, and roadmap. When in doubt, this doc wins over memory.

---

## 📑 Table of Contents

1. [Product Identity](#1-product-identity)
2. [Problem & Audience](#2-problem--audience)
3. [Value Proposition](#3-value-proposition)
4. [Product Pillars](#4-product-pillars)
5. [Feature Map](#5-feature-map)
6. [Critical User Flows](#6-critical-user-flows)
7. [Tech Stack](#7-tech-stack)
8. [Repository Structure](#8-repository-structure)
9. [Database Schema](#9-database-schema)
10. [Key Architecture Decisions](#10-key-architecture-decisions)
11. [Phased Roadmap](#11-phased-roadmap)
12. [Pricing Model](#12-pricing-model)
13. [Success Metrics](#13-success-metrics)
14. [Risks & Mitigations](#14-risks--mitigations)
15. [Portfolio Showcase Layer](#15-portfolio-showcase-layer)
16. [Open Decisions](#16-open-decisions)
17. [Conventions & Standards](#17-conventions--standards)

---

## 1. Product Identity

| Attribute | Value |
|---|---|
| **Name** | Roofly |
| **Domain** | roofly.my (primary) |
| **Tagline (EN)** | Rent management, simplified. |
| **Tagline (BM)** | Urus sewa rumah, lebih mudah. |
| **One-liner** | A property rental management platform that helps Malaysian landlords manage tenants, agreements, rent collection, and maintenance — all in one place. |
| **Brand personality** | Modern, calm, trustworthy. Premium feel without being intimidating. |
| **Visual direction** | Clean, minimal, slightly editorial. Off-white backgrounds, generous whitespace, warm accent (terracotta or deep teal). |
| **Repository** | `github.com/byhaqie31/roofly` (private until Phase 7) |
| **Parent ecosystem** | [Axel Nova Ventures](https://axelnova.tech) |

---

## 2. Problem & Audience

### The Problem

Malaysian landlords with 1–20 units manage their rentals through a chaotic mix of WhatsApp, Excel sheets, paper agreements, and memory. They lose track of:

- Who paid rent and who didn't
- When agreements expire
- Which units need maintenance
- How much they actually earned this year (tax season is painful)
- Where the signed agreement PDF is (probably WhatsApp)

Tenants suffer too: no clear way to pay rent, retrieve their agreement, or report issues without it feeling like a personal favor.

### Primary Users

**The Owner** — Cik Aminah, 45, owns 4 condo units in KL
- Owns 1–20 properties
- Manages alone or with one helper
- Uses WhatsApp + Maybank2u for everything
- Income RM 8k–50k/month from rentals
- Pain: *"Saya selalu lupa siapa belum bayar."*

**The Tenant** — Adi, 28, rents a studio in Mont Kiara
- Pays rent monthly via FPX or bank transfer
- Wants a clean way to settle bills and track payments
- Reports issues via WhatsApp, gets ignored or delayed
- Pain: *"Susah nak dapat resit. Owner susah nak respond."*

### Secondary Users (future)

- Property agents managing multiple owners (Phase 8+)
- Building management for service charge tracking (future)

---

## 3. Value Proposition

### For Owners
1. **Never miss rent again** — auto reminders to tenants, dashboard shows who's late at a glance
2. **Look professional instantly** — branded agreement PDFs, automatic receipts
3. **Tax season made easy** — one-click annual income report
4. **Tenants self-serve** — they pay, report issues, download their own agreement

### For Tenants
1. **Pay rent in 2 taps** — FPX or card, get receipt instantly
2. **Everything in one place** — agreement, payment history, receipts
3. **Issues get tracked** — no more chasing the owner on WhatsApp

### Competitive Positioning

| Competitor | Why Roofly wins |
|---|---|
| Speedhome / iBilik | Those are listing platforms. Roofly manages *after* the tenant moves in. |
| Excel + WhatsApp | Obviously. |
| Buildium / AppFolio | Built for Malaysian context (FPX, BM/EN, RM, local templates, WhatsApp). |
| Property agents | Owners keep their margin. RM 30–80/mo vs agent's 1 month rent + 10% commission. |

---

## 4. Product Pillars

Every feature must tie back to one of these:

| Pillar | Definition | Example |
|---|---|---|
| **Visibility** | Owner sees everything at a glance | Dashboard with occupancy, outstanding RM, expiring agreements |
| **Automation** | System does the chasing, not the human | Auto rent reminders, late fees, expiry alerts |
| **Self-service** | Tenants don't bother owners for routine stuff | Pay portal, agreement download, issue reporting |
| **Trust** | Every transaction is documented and traceable | PDF receipts, activity log, audit trail |
| **Local fit** | Built for how Malaysians actually rent | FPX, WhatsApp, BM/EN, IC validation |

---

## 5. Feature Map

### Owner App

| Module | Features |
|---|---|
| **Dashboard** | Income (this month vs last), occupancy %, outstanding rent, expiring agreements, recent activity, quick actions |
| **Properties** | List, detail, add/edit (name, address, type: condo/landed/shoplot/room) |
| **Units** | Per-property list, status (Vacant/Occupied/Maintenance), current tenant, history |
| **Tenants** | All tenants across properties, detail view, invite via magic link |
| **Agreements** | Template-based creation, PDF generation, send for review, renewal/termination flows |
| **Payments** | Invoice list, manual mark-as-paid, online status, receipts, custom reminders |
| **Maintenance** | Kanban (New → In Progress → Resolved), filters, comments, photos |
| **Reports** | Monthly income, annual tax report, occupancy timeline, per-property profitability |
| **Settings** | Profile, business name, bank details, notification prefs, agreement templates, late fee rules, WhatsApp number |

### Tenant App

| Module | Features |
|---|---|
| **Home** | Current rent status, pay button, recent notifications |
| **Agreement** | View terms, download PDF, expiry countdown, renewal request |
| **Payments** | Pay current invoice, history, download any past receipt |
| **Tickets** | Report issue (category, description, photo), track status, comment |
| **Profile** | Personal info, documents (IC copy), emergency contact |

### Shared / System

- Authentication (email/password, magic link for tenant invites)
- Notifications (in-app, email, WhatsApp)
- Multi-language (BM, EN)
- Activity log
- File uploads with virus scanning (later)

---

## 6. Critical User Flows

### Flow 1: Owner Onboarding
1. Sign up → verify email
2. Welcome screen → add first property
3. Add first unit
4. Invite first tenant (or skip)
5. Create first agreement (or skip)
6. Land on dashboard with empty-state guidance

**Goal:** Signup to first agreement in < 10 minutes.

### Flow 2: Tenant Invitation
1. Owner clicks "Invite tenant" on a unit
2. Enters tenant name + phone/email
3. System sends WhatsApp + email with magic link
4. Tenant clicks link → creates password → lands on dashboard
5. Sees "Your agreement is being prepared" or active agreement

### Flow 3: Monthly Rent Cycle
1. **Day 1:** System auto-generates invoices based on `rent_due_day`
2. **7 days before due:** Auto-reminder via WhatsApp + email
3. **3 days before due:** Second reminder
4. **Due day:** Final reminder + payment link
5. **Tenant pays via Billplz FPX** → webhook → invoice updated → receipt generated → owner notified
6. **Day after due, if unpaid:** Late fee auto-applied → overdue notification → owner sees on dashboard
7. Owner can manually mark as paid (with transaction reference) for cash/transfer payments

### Flow 4: Maintenance Ticket
1. Tenant logs in → "Report issue"
2. Selects category, priority, description, photos
3. Submits → owner gets WhatsApp + in-app notification
4. Owner views in Kanban → moves to In Progress → comments
5. Owner marks Resolved → tenant notified → can confirm or reopen

### Flow 5: Agreement Renewal
1. **60 days before expiry:** Owner notified
2. Owner clicks "Renew" → adjusts rent if needed → generates new agreement
3. Tenant gets notification → reviews → acknowledges
4. New agreement activates on old expiry date → invoices regenerate

---

## 7. Tech Stack

### Backend
- **Framework:** Laravel 11
- **Language:** PHP 8.3
- **Auth:** Laravel Sanctum (SPA token auth)
- **Permissions:** Spatie Permission
- **Files:** Spatie MediaLibrary (polymorphic, S3/R2 ready)
- **Audit:** Spatie ActivityLog
- **PDF:** Browsershot (Puppeteer-based, better than DomPDF for branded layouts)
- **Database:** MySQL 8
- **Cache:** Redis
- **Queue:** RabbitMQ
- **Testing:** Pest

### Frontend
- **Framework:** Nuxt 3
- **Language:** TypeScript
- **Styling:** Tailwind CSS
- **State:** Pinia
- **i18n:** @nuxtjs/i18n (BM + EN)
- **Forms:** VeeValidate + Zod
- **Tables:** TanStack Table
- **Charts:** ApexCharts
- **Icons:** Lucide
- **HTTP:** $fetch + Sanctum cookie auth
- **Testing:** Vitest + Playwright

### Infrastructure
- **Hosting:** Hostinger VPS (Ubuntu 24.04 LTS, KVM 2)
- **Containers:** Docker + Docker Compose
- **Web Server:** Nginx (reverse proxy)
- **SSL:** Let's Encrypt + Cloudflare
- **CDN:** Cloudflare
- **Storage:** Cloudflare R2 (S3 compatible)
- **CI/CD:** GitHub Actions (`appleboy/ssh-action@v1.0.3`)
- **Monitoring:** Sentry
- **Analytics:** Plausible

### External Integrations
- **Payments:** Billplz (FPX, cards) — fastest FPX integration in MY
- **Email:** Resend
- **Messaging:** WhatsApp Cloud API (Meta direct, free tier covers MVP)
- **API docs:** Scribe (auto-generates OpenAPI from Laravel routes)

---

## 8. Repository Structure

```
roofly/
├── README.md
├── PROJECT.md                       ← this file
├── docker-compose.yml
├── docker-compose.prod.yml
├── .github/
│   ├── assets/
│   │   ├── house.svg
│   │   └── screenshots/
│   └── workflows/
│       └── deploy.yml
├── backend/                         ← Laravel 11
│   ├── app/
│   │   ├── Models/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Services/                ← business logic (PaymentService, AgreementService)
│   │   ├── Jobs/                    ← queued jobs
│   │   ├── Notifications/           ← email + WhatsApp + DB channels
│   │   ├── Enums/                   ← UnitStatus, AgreementStatus, InvoiceStatus
│   │   └── Policies/
│   ├── database/
│   │   ├── migrations/
│   │   ├── seeders/
│   │   └── factories/
│   ├── routes/api.php
│   ├── tests/
│   ├── Dockerfile
│   └── .env.example
├── frontend/                        ← Nuxt 3
│   ├── pages/
│   │   ├── auth/
│   │   ├── owner/
│   │   └── tenant/
│   ├── components/
│   │   ├── owner/
│   │   ├── tenant/
│   │   └── ui/                      ← shared UI primitives
│   ├── composables/
│   ├── stores/                      ← Pinia stores
│   ├── i18n/
│   │   ├── en.json
│   │   └── ms.json
│   ├── middleware/
│   ├── plugins/
│   ├── tests/
│   ├── Dockerfile
│   └── .env.example
└── docs/                            ← architecture diagrams, ADRs
```

---

## 9. Database Schema

### Core Entities & Relationships

```
USERS (owner | tenant | admin)
  ├─ owns → PROPERTIES
  │    └─ contains → UNITS
  │         ├─ has → AGREEMENTS ─→ generates INVOICES ─→ settles PAYMENTS
  │         │    ├─ creates → TENANCIES (linked to tenant USERS)
  │         │    └─ stores → DOCUMENTS (polymorphic)
  │         └─ has → TICKETS
  │              └─ has → TICKET_COMMENTS
  └─ receives → NOTIFICATIONS
```

### Key Fields per Table

**users**
- id (uuid, PK), name, email (unique), phone, role (enum), password, email_verified_at, timestamps, soft deletes

**properties**
- id (uuid, PK), owner_id (FK → users), name, address, city, state, postcode, type (enum: condo/landed/shoplot/room), timestamps, soft deletes

**units**
- id (uuid, PK), property_id (FK), label, bedrooms, bathrooms, sqft, status (enum: vacant/occupied/maintenance), timestamps, soft deletes

**agreements**
- id (uuid, PK), unit_id (FK), tenant_id (FK → users), start_date, end_date, rent_amount_cents, deposit_amount_cents, late_fee_cents, rent_due_day (1–28), status (enum: draft/active/expired/terminated), timestamps, soft deletes

**tenancies**
- id (uuid, PK), agreement_id (FK), tenant_id (FK), moved_in_at, moved_out_at, timestamps

**invoices**
- id (uuid, PK), agreement_id (FK), invoice_number (unique), amount_cents, late_fee_cents, due_date, status (enum: pending/paid/overdue/cancelled), timestamps, soft deletes

**payments**
- id (uuid, PK), invoice_id (FK), amount_cents, method (enum: fpx/card/cash/transfer), billplz_bill_id (nullable), status (enum: pending/successful/failed), paid_at, timestamps

**payment_webhooks**
- id (uuid, PK), payload (json), processed_at, timestamps — *raw audit trail of all Billplz callbacks*

**documents**
- id (uuid, PK), documentable_id, documentable_type (polymorphic), filename, disk, path, category (enum: agreement/ic_copy/receipt/photo/other), timestamps, soft deletes

**tickets**
- id (uuid, PK), unit_id (FK), reporter_id (FK → users), title, description, priority (enum: low/medium/high), status (enum: new/in_progress/resolved), category (enum: plumbing/electrical/aircon/structural/other), timestamps, soft deletes

**ticket_comments**
- id (uuid, PK), ticket_id (FK), user_id (FK), body, timestamps

**notifications** (Laravel default schema)
- id (uuid, PK), type, notifiable_type, notifiable_id, data (json), read_at, timestamps

---

## 10. Key Architecture Decisions

### ADR-001: UUIDs over auto-increment IDs
- Prevents leaking business metrics (e.g., "they have 12 properties")
- Supports public-shareable links without sequential exposure
- Use Laravel's `HasUuids` trait

### ADR-002: Money in cents (integers), never decimals
- `rent_amount_cents = 150000` for RM 1,500.00
- Avoids float precision issues
- Use Laravel cast for display formatting

### ADR-003: Single users table with role enum
- One `users` table; role determines behavior (owner/tenant/admin)
- A landlord might also be a tenant elsewhere — they're a person first
- Use Spatie Permission for fine-grained per-resource permissions

### ADR-004: Properties → Units separation
- Even a single house = property with one unit
- Identical model for: 1 house, 20-unit apartment block, 5 shoplots
- Don't shortcut this

### ADR-005: Agreements vs Tenancies separated
- **Agreement** = legal document (terms, rent, dates)
- **Tenancy** = actual occupation record (moved in, moved out)
- Renewals create new agreement linked to existing tenant
- Early termination: keep agreement, close tenancy

### ADR-006: Invoices auto-generated from agreements
- On agreement activation, generate first invoice
- Cron job generates next month's invoices on day 1 of each month
- Each invoice is its own record (supports partial payments, manual adjustments)

### ADR-007: Payments separate from Invoices
- One invoice → one payment normally, but supports retries, failures, partials
- Every payment attempt is logged
- `billplz_bill_id` nullable for cash/manual payments

### ADR-008: Polymorphic documents
- Documents attach to agreements, units, tenants, tickets
- Use Spatie MediaLibrary (handles polymorphism + S3/R2 + conversions)

### ADR-009: Soft deletes everywhere
- Owners *will* accidentally delete tenants and panic
- Recovery > permanent loss

### ADR-010: Activity log from Day 1
- Spatie ActivityLog auto-tracks every model change
- Critical for trust, debugging, support tickets

### ADR-011: Multi-tenancy via owner_id scoping
- Single database, `owner_id` FK on every owned entity
- Global Eloquent scope filters by authenticated owner
- Simpler than `stancl/tenancy` and fine for this scale
- If we hit 1000+ owners, migrate to per-tenant DB later

### ADR-012: Audit raw webhook payloads
- Store every Billplz webhook as raw JSON in `payment_webhooks`
- Critical when there's a payment dispute
- Process webhook → mark `processed_at`

---

## 11. Phased Roadmap

### Phase 1 — Foundation (Week 1–2)
**Goal:** Skeleton you can demo

- [ ] Monorepo + Docker Compose setup (`backend/`, `frontend/`, MySQL, Redis, RabbitMQ)
- [ ] Laravel 11 install + base packages (Sanctum, Spatie Permission, Spatie MediaLibrary, Spatie ActivityLog)
- [ ] Nuxt 3 install + base packages (Tailwind, Pinia, i18n, VeeValidate)
- [ ] Auth flow: register, login, logout (Sanctum cookie-based)
- [ ] Role-based middleware (owner / tenant / admin guards)
- [ ] Core entities: User, Property, Unit (migrations + models + factories)
- [ ] Owner dashboard shell (sidebar, layout, dark mode toggle)
- [ ] Tenant dashboard shell
- [ ] BM/EN i18n setup with sample translations
- [ ] Seeders with realistic Malaysian sample data (Jalan names, RM amounts, IC format)
- [ ] CI: GitHub Actions runs tests on PR

### Phase 2 — Properties & Agreements (Week 2–3)
**Goal:** Owner can manage properties end-to-end

- [ ] Property CRUD with photo uploads (Spatie MediaLibrary)
- [ ] Unit CRUD nested under Property
- [ ] Tenant invitation flow (magic link via email + WhatsApp)
- [ ] Agreement builder with template variables
- [ ] PDF generation (Browsershot, Tailwind-styled HTML template)
- [ ] Polymorphic document vault (IC copies, signed agreements)
- [ ] Tenant accepts invitation → links to unit → views agreement

### Phase 3 — Payments (Week 3–4)
**Goal:** Money actually moves

- [ ] Rent schedule auto-generation on agreement activation
- [ ] Billplz integration (sandbox → production)
- [ ] Webhook handler with raw payload audit
- [ ] Auto late fee calculation (configurable per agreement)
- [ ] Receipt PDF auto-generation on payment success
- [ ] Tenant payment portal
- [ ] Manual mark-as-paid flow for cash/transfer (with transaction reference)

### Phase 4 — Notifications & Reminders (Week 4–5)
**Goal:** System works without owner doing anything

- [ ] RabbitMQ queue worker setup
- [ ] Scheduled jobs: rent reminders (7d, 3d, 1d, overdue)
- [ ] Agreement expiry alerts (60d, 30d, 7d)
- [ ] Email channel via Resend
- [ ] WhatsApp Cloud API channel
- [ ] In-app notification center (unread badge, mark as read)

### Phase 5 — Maintenance Tickets (Week 5)
**Goal:** Tenant ↔ Owner communication loop

- [ ] Ticket creation by tenant (category, priority, description, photo upload)
- [ ] Owner ticket Kanban board
- [ ] Comment thread per ticket
- [ ] Status workflow (New → In Progress → Resolved → Reopened)
- [ ] Notifications on status change

### Phase 6 — Reports & Polish (Week 6)
**Goal:** Portfolio-ready

- [ ] Owner financial dashboard (income chart, occupancy %, outstanding RM)
- [ ] PDF + Excel export (annual tax report, monthly statement)
- [ ] Onboarding flow with empty states
- [ ] Demo account auto-reset (nightly cron)
- [ ] Lighthouse score 90+ across the board

### Phase 7 — Marketing & Launch (Week 7–8)
**Goal:** Public-ready

- [ ] Public landing page (separate Nuxt static site)
- [ ] Pricing page
- [ ] Documentation site
- [ ] Loom walkthrough video
- [ ] Beta launch (5–10 owners via LinkedIn + FB property groups)
- [ ] Make GitHub repo public with portfolio README

### Future (Post-Launch)
- [ ] Mobile app (Nuxt → Capacitor or native)
- [ ] Property agent multi-org support
- [ ] Tenant credit scoring
- [ ] Stamp duty e-filing integration
- [ ] AI-powered agreement clause suggestions

---

## 12. Pricing Model

| Plan | Price | Units | Best for |
|---|---|---|---|
| **Free** | RM 0/mo | 3 | Single property owners |
| **Starter** | RM 29/mo | 5 | Small landlords |
| **Pro** | RM 79/mo | 25 | Growing portfolios |
| **Business** | RM 199/mo | Unlimited | Agents & multi-org |

**Beta strategy:** Free during beta (first 6 months) to gather feedback and case studies. Forever-free plan continues post-beta.

---

## 13. Success Metrics

### Product Health
- Active properties
- Active agreements
- Monthly Recurring Revenue (when paid plans launch)
- Payment success rate (Billplz failures)
- Avg time to first agreement (onboarding metric)
- Tenant invite acceptance rate

### Portfolio Metrics
- Lighthouse score (90+ across all categories)
- API response time p95 (< 200ms)
- Test coverage (≥ 70% backend, ≥ 60% frontend)
- Uptime (target 99.5%)
- Code quality (services, no fat controllers, clean architecture)

---

## 14. Risks & Mitigations

| Risk | Mitigation |
|---|---|
| Billplz integration takes longer than expected | Build with manual payment marking first; integrate Billplz as Phase 3 add-on |
| WhatsApp Cloud API approval delays | Email-first for notifications; WhatsApp as upgrade |
| Owners don't want to migrate from WhatsApp | Free tier + Excel import feature later |
| Scope creep killing momentum | Strict phase gates; ship Phase 1–3 before touching Phase 4 |
| Tenants don't sign up | Owner can use it solo (mark payments manually); tenant portal is bonus |
| Legal: agreement template validity | Use Stamp Office–compatible template; add disclaimer; partner with a lawyer for review later |

---

## 15. Portfolio Showcase Layer

Every phase includes "showcase polish":

- Architecture diagrams (Mermaid) in README
- API documentation via Scribe (OpenAPI)
- Test coverage badges (Pest + Vitest)
- Loom walkthrough embedded on landing page
- Case study page on baihaqie.com
- GitHub README with architecture diagram, tech stack badges, live demo link
- Postmortem-style "lessons learned" doc

---

## 16. Open Decisions

Items still TBD before/during build:

- [ ] **Brand color** — deep teal (#0F6E56), warm terracotta (#993C1D), or something else?
- [ ] **Logo direction** — stick with house illustration, or design a wordmark?
- [ ] **Domain** — roofly.my (more local trust) or roofly.app (more SaaS-feel)?
- [ ] **Agreement template** — write generic, or partner with a lawyer for vetted version?
- [ ] **Beta recruitment channel** — LinkedIn, FB property groups, Reddit r/malaysia, friends-and-family?
- [ ] **Payment provider primary** — Billplz (fastest) or Fiuu (your existing connection)?

---

## 17. Conventions & Standards

### Code style
- **PHP:** PSR-12, Laravel Pint for formatting
- **TypeScript:** Prettier + ESLint, no `any` types
- **Vue:** Composition API only, `<script setup lang="ts">`, kebab-case component names
- **Tailwind:** Avoid `@apply` except for genuine reusable patterns; use design tokens (CSS vars) for theming

### Naming
- **Database tables:** `snake_case`, plural (`properties`, `payment_webhooks`)
- **Models:** `PascalCase`, singular (`Property`, `PaymentWebhook`)
- **Routes:** `kebab-case` URLs, RESTful (`/api/properties/{id}/units`)
- **Components:** `kebab-case` files, `PascalCase` registration (`property-card.vue` → `<PropertyCard>`)
- **Composables:** `useThing.ts` (camelCase)
- **Stores:** `useThingStore.ts`
- **Enums:** `PascalCase` class, `SCREAMING_SNAKE_CASE` cases

### Git workflow
- **Main branch:** `main` (protected, requires PR)
- **Feature branches:** `feat/short-description`
- **Commits:** Conventional commits (`feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `test:`)
- **PRs:** Squash merge into main

### Testing strategy
- **Backend:** Feature tests for every API endpoint, unit tests for service layer
- **Frontend:** Component tests for critical UI, E2E for the 5 critical flows
- **Critical paths require ≥ 90% coverage:** payments, agreements, notifications

### Money handling rules
- **Always store cents** as integers
- **Format on display only** (never store formatted strings)
- **Use Laravel's `MoneyCast`** or Brick\Money package
- **Never use floats for currency math**

### Environment
- **Local:** Docker Compose
- **Staging:** subdomain on VPS (`staging.roofly.my`)
- **Production:** main domain (`roofly.my` and `app.roofly.my`)
- **Secrets:** Never commit; use `.env.example` for structure, GitHub Secrets for CI

---

## Appendix: Quick Reference

### Demo accounts (seeded)
- Owner: `owner@roofly.my` / `password`
- Tenant: `tenant@roofly.my` / `password`

### Critical commands
```bash
# Spin up local dev
docker compose up -d --build

# Migrate + seed
docker compose exec backend php artisan migrate --seed

# Run tests
docker compose exec backend php artisan test
docker compose exec frontend npm run test

# Tail queue worker logs
docker compose logs -f queue-worker

# Reset everything
docker compose down -v && docker compose up -d --build
```

### Key URLs
- Production: https://roofly.my
- Staging: https://staging.roofly.my
- API base: https://roofly.my/api
- Repo: https://github.com/byhaqie31/roofly
- Project board: TBD (GitHub Projects)

---

*This document is the source of truth. Update it as decisions evolve. When PROJECT.md and your memory disagree — PROJECT.md wins.*
