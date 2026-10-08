# Branch Protection Guide

This is how Roofly's GitHub repo (`byhaqie31/roofly`) is structured for safe deploys.

## TL;DR — current state

Two long-lived branches, two rulesets, UAT-first workflow:

| Branch | Default? | Ruleset | Maps to |
|---|---|---|---|
| `UAT` | ✓ | `protect-UAT` | uat.roofly.com (staging deploy) |
| `main` |  | `protect-main` | roofly.com (production deploy) |

Anyone running `git clone` lands on `UAT` automatically. `main` exists only as the production deploy target — release PRs from UAT promote there.

## Flow

```
feature/<anything>  ──PR──▶  UAT  ──release PR──▶  main
                              │                      │
                              ▼                      ▼
                        uat.roofly.com          roofly.com
```

Local feature branches can be named anything (`feature/quote-builder`, `fix/login-bug`, `wip/whatever` — your call). They get squash-merged into `UAT` and auto-deleted on merge.

## Daily workflow

```bash
# Start work
git checkout UAT
git pull
git checkout -b my-feature

# ...edit, commit...
git push -u origin my-feature

# Open PR → UAT
gh pr create --base UAT --title "feat: my feature" --body "..."
```

When a UAT batch is ready to ship:

```bash
gh pr create --base main --head UAT --title "release: vX.Y" --body "..."
```

## Ruleset details

The current setup intentionally keeps **review optional** — the team is small enough that approval requirements are friction without a real safety benefit. PR-and-CI is the only enforced gate; reviews are opt-in (you can still request one when you want a second pair of eyes).

### `protect-UAT` (id `16134423`)

- **PR required** — direct push to `UAT` is blocked
- 0 approvals required (self-merge OK)
- No deletion, no force-push
- Admin bypass enabled
- Squash + rebase merges only (no merge commits)

### `protect-main` (id `16140859`)

Same as `protect-UAT` plus:
- **Linear history** required (forbids merge commits in history at all)

## Repo settings

```bash
gh api -X PATCH repos/byhaqie31/roofly \
  -F allow_squash_merge=true \
  -F allow_merge_commit=false \
  -F allow_rebase_merge=true \
  -F delete_branch_on_merge=true \
  -F allow_auto_merge=true \
  -F allow_update_branch=true
```

- Squash + rebase only (no merge commits in history)
- Feature branches auto-delete after merge
- Auto-merge available
- "Update branch" button shown on PRs that fall behind base

## How to recreate this setup from scratch

If you ever need to nuke and rebuild, here are the exact commands.

### 1. Branches

```bash
# from a fresh repo with only main:
git checkout main
git pull
git checkout -b UAT
git push -u origin UAT
gh repo edit byhaqie31/roofly --default-branch UAT
```

### 2. `protect-UAT` ruleset

```bash
gh api -X POST repos/byhaqie31/roofly/rulesets --input - <<'JSON'
{
  "name": "protect-UAT",
  "target": "branch",
  "enforcement": "active",
  "conditions": {
    "ref_name": { "include": ["refs/heads/UAT"], "exclude": [] }
  },
  "bypass_actors": [
    { "actor_id": 5, "actor_type": "RepositoryRole", "bypass_mode": "always" }
  ],
  "rules": [
    { "type": "deletion" },
    { "type": "non_fast_forward" },
    {
      "type": "pull_request",
      "parameters": {
        "required_approving_review_count": 0,
        "dismiss_stale_reviews_on_push": false,
        "require_code_owner_review": false,
        "require_last_push_approval": false,
        "required_review_thread_resolution": false,
        "allowed_merge_methods": ["squash", "rebase"]
      }
    }
  ]
}
JSON
```

### 3. `protect-main` ruleset

```bash
gh api -X POST repos/byhaqie31/roofly/rulesets --input - <<'JSON'
{
  "name": "protect-main",
  "target": "branch",
  "enforcement": "active",
  "conditions": {
    "ref_name": { "include": ["refs/heads/main"], "exclude": [] }
  },
  "bypass_actors": [
    { "actor_id": 5, "actor_type": "RepositoryRole", "bypass_mode": "always" }
  ],
  "rules": [
    { "type": "deletion" },
    { "type": "non_fast_forward" },
    { "type": "required_linear_history" },
    {
      "type": "pull_request",
      "parameters": {
        "required_approving_review_count": 0,
        "dismiss_stale_reviews_on_push": false,
        "require_code_owner_review": false,
        "require_last_push_approval": false,
        "required_review_thread_resolution": false,
        "allowed_merge_methods": ["squash", "rebase"]
      }
    }
  ]
}
JSON
```

> The actor_id `5` is the built-in **Maintain** role bypass — repo admins can override the rule when needed. To remove all bypass, set `"bypass_actors": []`.

## CI gate (`.github/workflows/ci.yml`)

Runs on every push to every branch except `demo-roofly` (it only merges already-gated UAT, and its `backend/` is an empty placeholder). A PR's required checks are satisfied by the push run on its head commit. Two jobs, both required on `protect-UAT` + `protect-main`:

| Job | What it runs |
|---|---|
| `backend` | `composer install` → `php artisan test` (sqlite in-memory, no services) |
| `frontend` | `npm ci` → `npm test` (Vitest) → `npm run build` |

Not in CI yet (each has a backlog to clear first): `pint --test` (163 files drift), `npm run typecheck` (5 known errors), `composer audit` / `npm audit`. `protect-main` also requires `only-from-uat` (from `guard-main.yml`). **No Dependabot.** The config was tried on 2026-10-07 and removed the same day: seven bot PRs in ten minutes is noise for a solo build. Dependency bumps are done deliberately, by hand, in a normal feature → UAT PR.

CI is a merge gate, not a deploy gate: `deploy.yml` fires on push, so the gate only works because direct pushes are blocked. Admin bypass (below) skips it — use it for emergencies only.

### Requiring the checks on a ruleset

The commands re-PUT each ruleset with its current rules plus the checks, so nothing else changes:

```bash
# protect-UAT — add a required_status_checks rule
gh api repos/byhaqie31/roofly/rulesets/16134423 \
  --jq '{name,target,enforcement,conditions,bypass_actors,rules: (.rules + [{type:"required_status_checks",parameters:{strict_required_status_checks_policy:true,do_not_enforce_on_create:false,required_status_checks:[{context:"backend"},{context:"frontend"}]}}])}' \
  | gh api -X PUT repos/byhaqie31/roofly/rulesets/16134423 --input -

# protect-main — append to the existing rule (keeps only-from-uat)
gh api repos/byhaqie31/roofly/rulesets/16140859 \
  --jq '{name,target,enforcement,conditions,bypass_actors,rules: [.rules[] | if .type=="required_status_checks" then .parameters.required_status_checks += [{context:"backend"},{context:"frontend"}] else . end]}' \
  | gh api -X PUT repos/byhaqie31/roofly/rulesets/16140859 --input -
```

> A check's `context` must match the workflow job name **exactly**. Renaming `jobs.backend` in `ci.yml` without updating both rulesets = the check is never satisfied and PRs can never merge.

## Verify the live state

```bash
# Branches: should show UAT and main only
gh api repos/byhaqie31/roofly/branches --jq '.[].name'

# Default branch: should be UAT
gh repo view byhaqie31/roofly --json defaultBranchRef --jq '.defaultBranchRef.name'

# Rulesets: should list protect-UAT and protect-main
gh api repos/byhaqie31/roofly/rulesets \
  --jq '.[] | {id, name, refs: .conditions.ref_name.include}'
```

## Tighten later (as the team grows)

The current setup is calibrated for a small high-trust team where review is opt-in. As the team scales, dial these back up by re-running the ruleset PUT with stricter values:

- **Require approvals** — set `required_approving_review_count: 1` (or 2) on `protect-UAT` and `protect-main` once you have enough reviewers that waiting isn't a bottleneck
- **Require codeowner review** — set `require_code_owner_review: true` and add a `.github/CODEOWNERS` file so PRs auto-route to the right people based on which paths they touch
- **Require last-push approval** — set `require_last_push_approval: true` on `protect-main` so any push after approval re-resets the review (prevents sneaking changes in post-review)
- **Require thread resolution** — set `required_review_thread_resolution: true` so unresolved reviewer comments block merge
- **Drop admin bypass** — set `"bypass_actors": []` on `protect-main` so even admins must go through the PR flow for prod

## Rollback / removal

If you need to undo a ruleset:

```bash
gh api repos/byhaqie31/roofly/rulesets                    # list
gh api -X DELETE repos/byhaqie31/roofly/rulesets/<ID>     # delete by id
```

If you need to delete a branch protected by a ruleset:

```bash
# Delete the ruleset first (or use admin bypass)
gh api -X DELETE repos/byhaqie31/roofly/rulesets/<ID>

# Then delete the branch
git push origin --delete <branch>
```
