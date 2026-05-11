---
description: Update demo-roofly branch to latest UAT (merge, no rebase, no PR back)
---

Update the `demo-roofly` branch to the latest `origin/UAT`.

**Context — branch flow for this repo:**
- Feature branches → PR into `UAT`
- `UAT` → release PR into `main`
- `demo-roofly` is a **downstream deploy branch** that consumes `UAT`. It never PRs back into UAT or main.

**What to do:**

1. Confirm we're in the roofly repo, then check out `demo-roofly` and make sure it's clean and up to date with its remote:
   ```bash
   git checkout demo-roofly
   git pull --ff-only
   ```
   If `git pull --ff-only` fails because local has diverged from `origin/demo-roofly`, stop and report — do not force anything.

2. Fetch latest UAT and check what's about to be merged:
   ```bash
   git fetch origin UAT
   git log --oneline demo-roofly..origin/UAT
   ```
   If the log is empty, report "demo-roofly already up to date with UAT" and stop.

3. Merge UAT into demo-roofly with an explicit merge commit (matches the existing `Merge origin/UAT into demo-roofly` pattern in history):
   ```bash
   git merge --no-ff origin/UAT -m "Merge origin/UAT into demo-roofly"
   ```
   If there are conflicts, stop and surface them — do not auto-resolve.

4. Push:
   ```bash
   git push
   ```

5. Report: the list of UAT commits merged in, and the new `demo-roofly` HEAD sha.

**Do not:**
- Rebase `demo-roofly` onto UAT (history has merge commits — keep that pattern)
- Open a PR from `demo-roofly` to UAT or main
- Force-push
- Touch the `UAT` branch
