# PostgreSQL partitioning strategy (large tenants)

**Status:** Decision and implementation guide — **not implemented**  
**Audience:** Engineering leads, SRE/DBA, future implementers  
**Scope:** CRM Beast on **PostgreSQL** (Sail/production). No schema changes are described as executed here; this document defines **when** and **how** partitioning may be introduced later.

---

## 1. Preconditions (current state)

The codebase and operations already support a future cutover:

| Area | Relevance to partitioning |
|------|---------------------------|
| **Tenant scoping** | Hot paths filter by `organization_id` (or imply it via validated `project_id`). Cross-tenant application queries are exceptions (platform dashboards, lifecycle jobs) and must stay explicit. |
| **Indexes** | PostgreSQL-oriented composite indexes align with partition keys (see `2026_04_11_000001_partition_readiness_indexes` and scale-pass migrations). Partition pruning benefits from the same predicate shapes. |
| **Lifecycle** | `config/lifecycle.php`: `activities` (warm → archive), `stripe_webhook_events` (cold prune), `tasks` (soft-delete hard purge after grace). Partitioning does **not** replace retention; it complements large hot sets. |
| **Read models** | `org_daily_metrics`, webhook summaries/rollups, snapshot read mode reduce full-table aggregation on the largest tenants. |

Partitioning is justified when **per-table** growth or **per-org** concentration breaks SLOs **after** indexes, snapshots, and lifecycle are already in use.

---

## 2. Partition candidates (priority order)

### 2.1 First tier — highest value

| Table | Why partition first | Notes |
|-------|---------------------|--------|
| **`stripe_webhook_events`** | Append-heavy, high insert rate, time-bounded operational value; platform and org-scoped reads often filter `organization_id` and/or `created_at` / `status`. Cold prune already reduces tail; largest pain is **hot tail size** and **ingestion index churn** on a single heap. | `organization_id` is **nullable** (unscoped events). Strategy must treat NULL as a dedicated partition or a separate non-partitioned holding pattern (see §3). |
| **`activities`** | Steady insert volume from product usage; large tenants dominate row count; queries are overwhelmingly `organization_id` + time ordering; warm rows move to `activities_archive`. Partitioning the **hot** table bounds vacuum and index size per slice. | Archive command and app reads must target correct child partitions or use a **parent** unified view during transition. |

### 2.2 Second tier

| Table | Why slightly later | Notes |
|-------|---------------------|--------|
| **`tasks`** | Large cardinality and JSONB (`assignees`); heavy index set (GIN + composites). Partitioning helps very large **single-tenant** shards; cross-project reporting within an org stays partition-aligned. | Soft deletes keep rows until purge — partition row counts include trashed rows unless partitioned with a predicate or separate handling. |

### 2.3 Lower priority for physical partitioning

- **`audit_logs`**, **`comments`**, **`notifications`** — archive/prune patterns differ; evaluate after tier 1–2 unless metrics show them as larger than `activities` for your fleet.
- **Reference and narrow tables** — not candidates.

---

## 3. Partition strategy (key design per table)

Design principle: **match the dominant filter in tenant-scoped queries** so PostgreSQL can **prune** partitions. Use **RANGE** or **LIST** on PostgreSQL 11+; avoid unnecessary sub-partitioning until a single org exceeds a second threshold.

### 3.1 `stripe_webhook_events`

| Option | Recommendation |
|--------|------------------|
| **Primary partition key** | **RANGE (`created_at`)** by **month** (or week if insert rate is extreme). Rationale: ingestion is time-ordered; retention is age-based (`lifecycle` uses `created_at`); prune jobs naturally drop **old** partitions in bulk once policy allows (after legal/ops sign-off — today prune is row-based; moving to detach-drop is a **policy change**, not automatic in current app). |
| **Secondary / composite** | Optional **LIST (`organization_id`)** only if a small set of “mega” orgs is proven to dominate and you need **per-org** detach. Hybrid **RANGE (time) → SUBPARTITION LIST (org)** is powerful but operationally heavy; default to **time RANGE only** first. |
| **NULL `organization_id`** | Use a dedicated partition (e.g. `p_webhooks_unscoped`) or **LIST** default; never mix NULL and scoped rows in a way that breaks unique (`stripe_event_id`) and FK expectations. |

**Query pattern alignment:** Org-scoped failure windows (`organization_id` + `created_at` + `status`) benefit from **time** pruning on range partitions **plus** existing partial/composite indexes **within** each partition.

### 3.2 `activities`

| Option | Recommendation |
|--------|----------------|
| **Primary partition key** | **LIST (`organization_id`)** for a **known set of large tenants**, **or** **RANGE (`created_at`)** monthly for all tenants if org list is unstable. **Preferred v1:** **RANGE (`created_at`)** monthly — aligns with `WarmTableArchiver` (cutoff on `created_at`), minimizes cross-org partition count explosion, and matches dashboard “recent activity” time windows. |
| **Mega-tenant variant** | If one org exceeds an inner threshold (§4), **attach** a dedicated subpartition or move that org to a dedicated table behind a **union view** (compatibility pattern). |

**Query pattern alignment:** `WHERE organization_id = ? ORDER BY created_at DESC LIMIT n` — with **RANGE (created_at)**, include `created_at` in the predicate (or constrain date range) so the planner can prune; `organization_id` remains a selective filter **inside** each partition (existing composite `(organization_id, subject_type, subject_id)` and `(organization_id, created_at)` remain valid **on each child**).

### 3.3 `tasks`

| Option | Recommendation |
|--------|------------------|
| **Primary partition key** | **LIST (`organization_id`)** for orgs above threshold, **or** **RANGE (`created_at`)** quarterly if inserts are spread and board/list queries always include recency. **Preferred v1:** **LIST (`organization_id`)** only for **tier `large` / `enterprise`** tenants that exceed row thresholds — keeps most of the fleet on a single default partition and limits operational partition count. |
| **Soft deletes** | Partitions include trashed rows until hard purge; ensure purge jobs **scope** `organization_id` (already in lifecycle config) and consider **partition-local** batch delete to avoid locking the whole parent. |

**Query pattern alignment:** `Task` queries already use `organization_id` first; LIST by org maximizes pruning for the largest tenants.

### 3.4 Summary matrix

| Table | Recommended v1 key | Rationale |
|-------|-------------------|-----------|
| `stripe_webhook_events` | RANGE (`created_at`) | Ingestion + retention + prune windows are time-centric; nullable org handled explicitly. |
| `activities` | RANGE (`created_at`) | Archive + “recent feed” are time-centric; bounded partition count. |
| `tasks` | LIST (`organization_id`) for hot orgs only | Board/index already org-first; isolates mega-tenant heaps. |

**Hybrid (time then org):** Reserve for a second phase if a **single** time partition still holds too many rows for one org.

---

## 4. Threshold triggers (when to start a partitioning project)

Treat these as **gates**: partitioning is a **major** project; open it when **multiple** signals persist after tuning indexes, snapshots, and lifecycle.

### 4.1 Quantitative — per table (indicative; tune to your SLOs)

| Signal | Suggested “investigate” band | Suggested “partition project” band |
|--------|------------------------------|-----------------------------------|
| **Total table row count** | `stripe_webhook_events` > ~50M; `activities` (hot) > ~30M; `tasks` > ~40M | Sustained growth above band + planner regressions |
| **Rows per organization (p99 or top N)** | Single org > ~5M tasks or > ~10M activities | Single org drives >25% of table bloat or p95 query time |
| **Table + index on-disk size** | Parent table + indexes > ~150–300 GB (single instance) | Autovacuum/I/O sustained pressure; backup/restore SLA at risk |
| **Query latency (tenant-scoped p95)** | Board/list/activity feed p95 > 500 ms after code + index optimization | p95 > 1 s for common pages with correct predicates |
| **Write volume** | Sustained > ~2–5k inserts/sec on `stripe_webhook_events` | Insert-induced lock/index hot spots on parent |
| **Vacuum / bloat** | `n_dead_tup` chronically high; autovacuum cannot keep up | User-visible slowdown + `pg_stat_user_tables` shows pathological bloat on one table |

### 4.2 Qualitative gates

- **Runbook readiness:** Ability to **attach/detach** partitions in staging with zero app code change (parent name stable).
- **Tier signal:** `organizations.tier` in `large` / `enterprise` **and** that org meets per-org row thresholds (tasks LIST candidate).
- **Reporting:** Product/analytics agree on time-range defaults so RANGE pruning is effective.

---

## 5. Operational model (create, attach, maintain, prune)

All steps assume **PostgreSQL native partitioning** (declarative partitioning, PG 11+). Exact DDL is intentionally omitted here; implementers follow PostgreSQL docs and internal runbooks.

### 5.1 Creation and attach

1. **Create parent** (if starting greenfield) or **convert** existing table to partitioned parent — conversion is high-risk and usually done via **copy-swap** or logical replication; not detailed here.
2. **Create child partitions** ahead of time:
   - **RANGE (time):** next N months of empty partitions (cron or manual).
   - **LIST (org):** one partition per designated `organization_id` plus **DEFAULT** for everyone else.
3. **Attach** partitions to parent; enforce **CHECK** constraints matching partition bounds (PostgreSQL manages this for declarative partitions).
4. **Validate:** Row counts, constraints, indexes **per child** (re-create indexes on children to match current “partition-ready” set).

### 5.2 Maintenance

- **Indexes:** Create on **each** partition (or on parent where PG version supports unified indexing — prefer explicit per-partition indexes for clarity in v1).
- **Statistics:** After bulk load, **ANALYZE** per partition; monitor **partition-wise joins** in `EXPLAIN`.
- **Monitoring:** Track partition row counts, dead tuples, attach failures, and query plans that fail to prune (add alerts on “Seq Scan” on parent where children exist).

### 5.3 Prune and lifecycle alignment

| Current behavior | Future partitioned behavior (conceptual) |
|------------------|------------------------------------------|
| Row `DELETE` for old `stripe_webhook_events` | **Optional:** **DETACH** oldest time partition + drop table after retention approval (faster bulk reclaim); legal/ops must approve if any row could be needed for dispute. |
| `activities` archive | Archive job selects by `created_at` cutoff — **prune partitions** that are fully older than cutoff **after** rows are moved to `activities_archive`, or archive **per partition** to reduce lock scope. |
| `tasks` soft-delete purge | Keep row-level purge; optionally run **per LIST partition** for mega-org. |

**Rule:** Partitioning changes **how** space is reclaimed; it does not change **whether** retention policies are correct.

### 5.4 Cross-tenant and platform jobs

- **Platform dashboards** that aggregate across orgs must use SQL that either:
  - scans **all** partitions (acceptable for rare jobs), or
  - continues to use **read models** (`webhook_event_summaries`, rollups, `org_daily_metrics`) to avoid full raw scans.
- **`--organization=`** on lifecycle commands should map to **LIST partition** boundaries when tasks are LIST-partitioned by org.

---

## 6. Risks and mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| **Foreign keys** | PostgreSQL partitioning historically constrained FKs **to** partition root vs **from** children; rules evolved by version. | Design phase: inventory **all** FKs referencing `tasks`, `activities`, `stripe_webhook_events`. Prefer **application-enforced** references or FKs on children consistent with PG version in use. |
| **Unique constraints** | Global uniqueness must include partition key where required (e.g. `stripe_event_id` already global). | Ensure unique indexes include partition key columns if the planner requires it for partitioned unique indexes. |
| **Cross-tenant queries** | Accidental full scan of all partitions. | Code review + `EXPLAIN` gates in CI for known heavy queries; keep summaries/rollups for platform. |
| **Reporting / BI** | Ad-hoc SQL without `created_at` / `organization_id` predicates. | Document required filters; use read replicas or columnar export for warehouse. |
| **Operational complexity** | Wrong default partition, missed pre-creation of next month’s partition, insert failures. | Automation to create future partitions; alerts; DEFAULT partition monitoring for overflow. |
| **Rollback** | Detach is easier than merge-back. | Rollout plan (§7) uses feature flags and dual-write or read-from-parent-only until stable. |

---

## 7. Rollout plan (safe introduction, backward compatibility)

### Phase 0 — Decision (this document)

- Confirm PostgreSQL version, FK inventory, and largest tables from `pg_total_relation_size` + `pg_stat_user_tables`.
- Align with legal on **detach-and-drop** vs row delete for webhooks.

### Phase 1 — Staging only

- Create **partitioned clone** of one table (e.g. `stripe_webhook_events_next`) fed by **logical replication** or batch copy — **no** production cutover.
- Replay representative read/write mix; verify plans prune partitions.

### Phase 2 — Read compatibility

- Introduce **database view** or **connection routing** only if needed (application still uses single logical table name). Prefer **rename swap**: `stripe_webhook_events` → `_legacy`, `stripe_webhook_events_part` → `stripe_webhook_events` during maintenance window (example pattern — exact steps are implementation-specific).

### Phase 3 — Production cutover (maintenance window)

- Freeze writes briefly **or** use replication lag window.
- Swap table names / attach as parent; re-run migrations for indexes on children if not inherited.
- Smoke: webhook ingest, activity feed, task board.

### Phase 4 — Steady state

- Automated partition **creation** (calendar) and monitoring.
- Quarterly review of thresholds (§4) and tier-based LIST expansion for `tasks`.

**Backward compatibility:** Application code continues to reference **one** logical table name (`tasks`, `activities`, `stripe_webhook_events`); only the physical storage layout changes. Any raw SQL in reports must be audited for partition key predicates.

---

## 8. What not to do (without a new decision record)

- Do not partition **without** resolving FK and unique constraint design for your exact PostgreSQL major version.
- Do not partition **small** tables — partition metadata overhead dominates.
- Do not replace **lifecycle** or **snapshots** with partitioning alone.

---

## 9. References (in-repo)

- `docs/ARCHITECTURE_GUARDRAILS.md` — “Partition readiness”
- `docs/DATA_LIFECYCLE.md` — retention and archive semantics
- `config/lifecycle.php` — `activities`, `stripe_webhook_events`, `tasks` policies
- `database/migrations/2026_04_11_000001_partition_readiness_indexes.php` — index alignment (implementation detail; strategy above is independent)

---

**Document owner:** Engineering  
**Next action:** When §4 thresholds are met, open an implementation RFC referencing this doc and PostgreSQL release notes for your production major version.
