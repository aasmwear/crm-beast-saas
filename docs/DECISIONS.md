# CRM Beast — Architecture Decision Records

Short ADR log for key decisions.

---

## ADR-001: Production Build Only (No Dev Server)

- **Decision:** Use `npm run build` only; never `npm run dev` in Cursor.
- **Rationale:** Consistency, no HMR drift, manifest-based asset loading.
- **Consequence:** Frontend changes require rebuild before testing.

---

## ADR-002: Multi-Tenancy via /org/{slug}

- **Decision:** All tenant routes under `/org/{organization:slug}/...`.
- **Rationale:** Clear URL structure, route model binding, tenant isolation.
- **Consequence:** ResolveTenant middleware must run before permission checks.

---

## ADR-003: Spatie Teams for RBAC

- **Decision:** Spatie Laravel-Permission with Teams enabled.
- **Rationale:** Battle-tested, team-scoped roles, no custom RBAC.
- **Consequence:** `team_id` = org id; policies must use Spatie gates.

---

## ADR-004: Matrix UI for Roles & Permissions

- **Decision:** Quick Matrix + Advanced modes; catalog-driven backend.
- **Rationale:** Usability for non-technical admins; single source of truth.
- **Consequence:** Alias map needed for policy/DB vs catalog naming.

---

## ADR-005: Alias Strategy for Permissions

- **Decision:** Map `clients.update` → `clients.edit` (and similar) in catalog.
- **Rationale:** Policies use `.update`; seeder uses `.edit`; avoid duplication.
- **Consequence:** Canonicalization pending across codebase.
