# 02_PERMISSIONS_STANDARD_AND_RECONCILIATION.md
This file is the standard that prevents RBAC drift.

---

## Canonical Permission Standard
CRUD:
- `module.view`
- `module.create`
- `module.update`
- `module.delete`

Admin:
- `module.manage`

Specials (examples):
- `clients.import`, `clients.export`
- `reports.export`
- `attendance.approve`
- `tasks.review`
- `roles.assign`
- `users.assign-roles`

Legacy:
- `module.edit` is legacy and must be treated as backward-compatible only.

---

## Role Defaults (direction)
- Owner: full access (view/create/update/delete/manage + specials)
- Manager: broad access, limited billing
- Employee: operational access (no settings update/billing manage)
- Client: limited portal access (no internal modules)

---

## Reconciliation Command
A command exists to safely reconcile canonical permissions without deleting legacy:
- `php artisan permissions:reconcile`
- `php artisan permissions:reconcile --assign`
- `php artisan permissions:reconcile --assign --dry-run`

Rules:
- Never delete permissions automatically.
- Prefer adding canonical perms and optionally assigning them to roles that have legacy equivalents.