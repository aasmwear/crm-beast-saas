# CRM Beast – Context Handbook (AI/Dev handoff)

## 1) High-level
- **Goal:** Multi-tenant SaaS CRM for digital marketing companies (accounts, projects/tasks, pipelines, invoicing, reporting, integrations).
- **Tenancy:** Single DB, scoped by `organizations` table. Route param key is **{organization:slug}** (alias `organization`).
- **Auth:** Laravel auth sessions + Sanctum (SPA). No external IdP (for now).

## 2) Stack & versions
- PHP 8.3, Laravel 12.x
- Node **20** (`.nvmrc`), Vite 7, Vue 3 + TypeScript
- Inertia, Ziggy (`import { route } from '@ziggy'`), Tailwind
- DB: Postgres
- Roles/Perms: spatie/laravel-permission (baseline installed)
- Billing: Cashier (installed, not configured)

## 3) Frontend decisions
- Always import `route` as: `import { route } from '@ziggy'`
- All tenant URLs must include `{ organization: <slug> }`
- Use Inertia `useForm`, `router.get/post/put/delete`, preserve state on filters

## 4) Back-end decisions
- Controllers authorize via policies, not `$this->authorize()` in routes file
- Models: strict fillables + typed PHPDoc
- Searching uses `ILIKE` & escaped `%/_` for safety
- `clients.assigned_account_manager_id` is the single source of truth (replaces any `account_manager_id`)

## 5) QA checklist (run before any feature work)
```bash
./vendor/bin/sail php artisan optimize:clear
npm run typecheck
npm run build
vendor/bin/pint --test
php -d xdebug.mode=off vendor/bin/phpstan analyse --memory-limit=1G
