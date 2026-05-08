# Roofly Documentation

This directory holds all reference documentation for the Roofly project. If you (or an AI agent like Claude Code) just cloned this repo, **start here** to understand what's available.

## Index

### Global ([global/](global/))

Docs that apply across the whole repo (frontend + backend + infra).

| Doc | What it is |
|---|---|
| [global/PROJECT.md](global/PROJECT.md) | **Source of truth.** Architecture, scope, naming, roadmap. When PROJECT.md and other docs disagree, PROJECT.md wins. |
| [global/BRANCH-PROTECTION.md](global/BRANCH-PROTECTION.md) | Branch strategy, GitHub rulesets, merge settings, and how to recreate the protection config. |
| [global/CLAUDE-CODE-PROMPT.md](global/CLAUDE-CODE-PROMPT.md) | The Phase 1 bootstrap prompt used to scaffold this monorepo with Claude Code. Historical / archival — keep for reference. |

### Frontend ([frontend/](frontend/))

| Doc | What it is |
|---|---|
| [frontend/UI-STANDARDS.md](frontend/UI-STANDARDS.md) | **Locked-in UI rules.** Design tokens, typography, spacing, components. The actual standard that frontend code follows. |
| [frontend/UI-INSPIRED.md](frontend/UI-INSPIRED.md) | Reference / inspiration material drawn from Lovable's design system. NOT the standard — see UI-STANDARDS.md for that. |

### Backend

*(No backend-specific docs yet. Add them under `backend/` when needed.)*

---

## Conventions

- **Filenames:** ALL-CAPS-WITH-HYPHENS.md (e.g. `BRANCH-PROTECTION.md`, not `branch-protection.md`). `README.md` is the only exception (GitHub convention).
- **Cross-links:** Use relative paths. From `docs/frontend/X.md`, refer to `docs/global/Y.md` as `../global/Y.md`.
- **Authority order:** UI-STANDARDS.md > PROJECT.md > earlier prompts/memory, when there's a conflict on visual/interaction language. PROJECT.md wins on everything else.

## For AI agents reading this repo

If you've been pointed at this repo by a developer:

1. Read [global/PROJECT.md](global/PROJECT.md) in full first — that's the spec.
2. If you're touching frontend, also read [frontend/UI-STANDARDS.md](frontend/UI-STANDARDS.md).
3. If you're touching git/CI/branches, read [global/BRANCH-PROTECTION.md](global/BRANCH-PROTECTION.md) before pushing anything.
4. The repo's daily workflow is `feature/<anything> → UAT → main`. Default branch is `UAT`. Don't push directly to `UAT` or `main` — open a PR.
