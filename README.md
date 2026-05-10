<div align="center">

<img src=".github/assets/house.svg" alt="Roofly" width="160">

# Roofly 🏡

### Rent management, simplified.

A modern property rental platform for Malaysian landlords.
Track tenants, generate agreements, collect rent online — all in one place.

[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Nuxt](https://img.shields.io/badge/Nuxt-3-00DC82?style=flat-square&logo=nuxt.js&logoColor=white)](https://nuxt.com)
[![Tailwind](https://img.shields.io/badge/Tailwind-3-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)

[**Live Demo**](https://demo.roofly.my) · [**Documentation**](#-documentation) · [**Deployment**](#-multi-environment-deployment) · [**Roadmap**](#%EF%B8%8F-roadmap)

</div>

---

## ✨ What it does

Most Malaysian landlords manage rentals through a messy mix of WhatsApp, Excel, and paper agreements. Roofly fixes that.

🏠 **For owners** → Dashboard, agreements, rent tracking, maintenance Kanban
👥 **For tenants** → Pay rent in 2 taps, view agreement, report issues
🇲🇾 **Made for Malaysia** → FPX payments, BM/EN, WhatsApp notifications

---

## 🛠️ Built with

**Laravel 11** · **Nuxt 3** · **Tailwind** · **MySQL** · **Redis** · **RabbitMQ** · **Docker** · **Billplz** · **WhatsApp Cloud API**

---

## 🚀 Quick start

```bash
git clone https://github.com/byhaqie31/roofly.git
cd roofly
cp .env.example .env
docker compose up -d --build
docker compose exec backend php artisan migrate --seed
```

Open `http://localhost:3000` and log in (mock auth in Phase 2):

- 🏠 **Owner:** any email not starting with `tenant`/`admin` (e.g. `owner@roofly.my`)
- 👥 **Tenant:** any email starting with `tenant` (e.g. `tenant@roofly.my`)
- Password is unchecked while mocks are on.

To preview the demo experience locally (mock data + landing page + feedback widget), append to `.env`:

```bash
NUXT_PUBLIC_APP_ENV=demo
NUXT_PUBLIC_USE_MOCK=true
NUXT_PUBLIC_DEMO_FEEDBACK_URL=
```

Then `/` redirects to `/demo`.

---

## 📚 Documentation

All reference docs live under [docs/](docs/). **Start here** if you (or an AI agent) just cloned the repo.

### Global ([docs/global/](docs/global/))

Docs that apply across the whole repo.

| Doc | What it is |
|---|---|
| [global/PROJECT.md](docs/global/PROJECT.md) | **Source of truth.** Architecture, scope, naming, roadmap. When PROJECT.md and other docs disagree, PROJECT.md wins. |
| [global/BRANCH-PROTECTION.md](docs/global/BRANCH-PROTECTION.md) | Branch strategy, GitHub rulesets, merge settings, how to recreate the protection config. |
| [global/CLAUDE-CODE-PROMPT.md](docs/global/CLAUDE-CODE-PROMPT.md) | The Phase 1 bootstrap prompt used to scaffold this monorepo. Historical / archival — keep for reference. |

### Frontend ([docs/frontend/](docs/frontend/))

| Doc | What it is |
|---|---|
| [frontend/UI-STANDARDS.md](docs/frontend/UI-STANDARDS.md) | **Locked-in UI rules.** Design tokens, typography, spacing, components. |
| [frontend/UI-INSPIRED.md](docs/frontend/UI-INSPIRED.md) | Reference / inspiration material from Lovable's design system. NOT the standard. |
| [frontend/MOCK-POC.md](docs/frontend/MOCK-POC.md) | Frontend-first mock plan, entity-by-entity. Per-surface types, mocks, services, schema impact. |

### Backend

*(No backend-specific docs yet. Add them under `docs/backend/` when needed.)*

### Doc conventions

- **Filenames:** `ALL-CAPS-WITH-HYPHENS.md` (e.g. `BRANCH-PROTECTION.md`). README.md is the only exception (GitHub convention).
- **Cross-links:** Relative paths. From `docs/frontend/X.md`, refer to `docs/global/Y.md` as `../global/Y.md`.
- **Authority order:** UI-STANDARDS.md > PROJECT.md > earlier prompts, on visual/interaction language. PROJECT.md wins on everything else.

### For AI agents

1. Read [docs/global/PROJECT.md](docs/global/PROJECT.md) in full first — that's the spec.
2. If touching frontend, also read [docs/frontend/UI-STANDARDS.md](docs/frontend/UI-STANDARDS.md).
3. If touching git/CI/branches, read [docs/global/BRANCH-PROTECTION.md](docs/global/BRANCH-PROTECTION.md) before pushing anything.
4. Daily workflow: `feature/<anything> → UAT → main`. Default branch is `UAT`. Don't push directly to `UAT` or `main` — open a PR.

---

## 🌐 Multi-environment deployment

Three environments run off three branches on one Hostinger VPS, separated by subdomain.

### Environment map

| | Branch | Subdomain | VPS clone path | Container | Host port | `APP_ENV` |
|---|---|---|---|---|---|---|
| **Demo** (client pitches) | `demo-roofly` | demo.roofly.my | `~/roofly-demo` | `demo-roofly` | `3001` | `demo` |
| **UAT** (staging) | `UAT` | uat.roofly.my | `~/roofly-uat` | `uat-roofly` | `3003` | `uat` |
| **Prod** (live) | `main` | roofly.my | `~/roofly` | `roofly` | `3002` | `production` |

### How a request reaches the right code

For `demo.roofly.my`:

```
Browser
  │  GET https://demo.roofly.my/
  ▼
Cloudflare DNS
  │  demo.roofly.my → 187.77.151.66 (VPS IP)
  ▼
VPS port 443 → Nginx
  │  Reads Host: demo.roofly.my, matches sites-enabled/demo.roofly.my
  │  → proxy_pass http://localhost:3001
  ▼
VPS localhost:3001 → Docker container "demo-roofly"
  │  Built from ~/roofly-demo (a git clone tracking the demo-roofly branch)
  │  Container reads ~/roofly-demo/.env → APP_ENV=demo
  ▼
Nuxt SSR (with demo behavior) → returns HTML
```

Five independent layers, each pointing to the next. To stand up a new environment, replicate the chain.

### Per-environment `.env`

Each VPS clone has its own gitignored `.env` overriding `docker-compose.yml`'s defaults (which are production-safe — never accidentally serves demo widgets).

```bash
# ~/roofly-demo/.env
COMPOSE_PROJECT_NAME=demo-roofly
FRONTEND_PORT=3001
NUXT_PUBLIC_APP_ENV=demo
NUXT_PUBLIC_USE_MOCK=true
NUXT_PUBLIC_DEMO_FEEDBACK_URL=https://forms.google.com/your-form-id

# ~/roofly-uat/.env
COMPOSE_PROJECT_NAME=uat-roofly
FRONTEND_PORT=3003
NUXT_PUBLIC_APP_ENV=uat

# ~/roofly/.env
COMPOSE_PROJECT_NAME=roofly
FRONTEND_PORT=3002
# All NUXT_PUBLIC_* unset → defaults to production
```

See [.env.example](.env.example) for the full list with comments.

### Auto-deploy

Pushes to a branch trigger [.github/workflows/deploy.yml](.github/workflows/deploy.yml), which SSHes into the VPS, pulls the matching clone, and runs `docker compose up -d --build`. Required GitHub Secrets: `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`.

---

## 🗺️ Roadmap

- [ ] **Phase 1** — Foundation, auth, base entities
- [x] **Phase 2** — Frontend mock POC (owner shell complete)
- [ ] **Phase 3** — Backend (Laravel + Sanctum) + per-entity API swap
- [ ] **Phase 4** — File storage (documents, photos, PDF reports)
- [ ] **Phase 5** — Billplz payments, invoices, late fees
- [ ] **Phase 6** — WhatsApp + email reminders
- [ ] **Phase 7** — Marketing site & beta launch

---

## 👋 About

Built by **[Qie](https://baihaqie.com)** — UI/UX-focused engineer based in Kuala Lumpur.
Part of the [Axel Nova Ventures](https://axelnova.tech) portfolio.

<div align="center">

Made with ❤️ in KL

</div>
