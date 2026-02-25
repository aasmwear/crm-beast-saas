# 01_CURSOR_WORKFLOW_PLAYBOOK.md
This file defines how we use Cursor with minimal prompts and maximum correctness.

---

## Which Cursor agent to use
Use **Composer 1.5** for all PR work (multi-file changes, RBAC, tenancy, migrations).
Use Auto only for tiny one-file edits.

---

## The PR Loop (every time)
1) **Pre-check** (must)
   - search existing code (routes/controllers/pages/tests)
   - state what exists and what is missing
2) **Plan**
   - list PR-sized steps (max 3–8 files typically)
3) **Implement**
4) **Verify**
   - `./vendor/bin/sail artisan test`
   - `./vendor/bin/sail npm run build`
5) **Update docs**
   - `docs/PROJECT_STATUS.md` (what changed + QA steps)
   - `docs/PERMISSIONS.md` (if permissions/policies/controllers changed)

---

## Prompt Template (copy/paste)
Use this exact structure for all Cursor prompts:

1) RULE: Before changing anything, search and confirm what already exists. Do not duplicate.
2) Context (tenancy, RBAC, prod build only)
3) Goals (what “done” means)
4) Tasks (ordered)
5) Deliverables (files changed, QA, tests/build)

---

## “Always Check Exists First” Rule (mandatory)
Before implementing ANY feature:
- list the relevant route(s)
- list the controller(s)
- list the Vue page(s)
- list policy/gates/permission strings
- list tests that already cover it
If something exists: improve it; do not rebuild.

---

## Minimal Prompt Strategy
- One prompt = one PR.
- Do not stack multiple modules in one prompt.
- Fix correctness first (tenancy/RBAC/DB/tests) before UI polish.