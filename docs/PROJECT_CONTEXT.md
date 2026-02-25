# CRM Beast — Project Context

**Single source of truth for Cursor prompts and onboarding.**

---

## Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12, PHP 8.2+ |
| Database | PostgreSQL 15+ (Sail) |
| Frontend | Inertia.js 2.0, Vue 3 (Composition API, `<script setup>`, TypeScript) |
| Styling | Tailwind CSS |
| Auth | Laravel Breeze |
| RBAC | Spatie Laravel-Permission (Teams) |
| Billing | Cashier (Stripe) |

---

## Multi-Tenancy

- **Route scope:** All tenant routes under `/org/{organization:slug}/...`
- **Middleware:** `ResolveTenant` — resolves org from route param, sets Spatie team context
- **Rules:**
  - Every org-scoped query MUST filter by `organization_id` (or equivalent)
  - No cross-tenant leakage
  - `ResolveTenant` sets `app(PermissionRegistrar::class)->setPermissionsTeamId($org->id)` when user is authenticated
  - Injects `scoped.organization` into container

---

## Spatie Teams

- Permissions and roles are **team-scoped** via `team_id`
- `team_id` = `organization_id` for tenant roles
- `team_id` = `null` for global roles (e.g. Super Admin)
- User model MUST use `HasRoles` trait
- Permission checks require tenant context to be set first

---

## Production Build (No Dev Server)

- **Do NOT use** `npm run dev` or Vite HMR
- Use **production builds** only:
  - `npm ci` or `npm install` once
  - `npm run build` whenever frontend changes
- Output: `public/build/` + `public/build/manifest.json`
- Laravel renders via `@vite(...)` and manifest

---

## Ports (Sail)

- **APP:** 8080
- **Vite (if ever used):** 5199

---

## Repo Identity

- **Project:** CRM Beast ("The Beast")
- **Workspace:** crm-beast-saas (canonical)
