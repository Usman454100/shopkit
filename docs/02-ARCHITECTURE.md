# Architecture

## 1. Stack

| Layer | Technology | Notes |
|---|---|---|
| Customer app | React Native (Expo or bare RN — see Open Questions) | Single shared app, all stores |
| Store/Org admin | React (Vite) web app | Built to static assets, served from Laravel's `public/admin` |
| Super Admin console | React (Vite) web app | Same pattern, `public/superadmin`, separate auth guard |
| Backend/API | Laravel 11 | REST API, `/api/*` |
| Multi-tenancy | `stancl/tenancy` package | Subdomain-based tenant identification |
| Database | MySQL (cPanel-compatible) | Per isolation tier — see §3 |
| Push notifications | Firebase Cloud Messaging (FCM) | Triggered server-side on order/status events |
| Hosting (initial) | Shared cPanel hosting | Migrate to VPS/cloud when load requires (see §5) |
| Payments | JazzCash, Easypaisa APIs; COD; manual bank transfer | See 04-FEATURES-BY-PHASE.md |

## 2. High-level system diagram

```
[Customer RN App] ---\
                       \
[Store Admin (React)] --+--> [Laravel API] --> [MySQL: shared DB (row-scoped) | dedicated DB per Enterprise tenant]
                       /            |
[Super Admin (React)] /             +--> [FCM] --> push notifications
                                     +--> [JazzCash/Easypaisa APIs]
```

## 3. Multi-tenancy & isolation strategy

**Tenant identification:** subdomain-based. Each store gets `{store-slug}.shopkit.app` (or equivalent domain). `stancl/tenancy` resolves the tenant from the subdomain on every request and swaps DB connection/context automatically.

**Isolation tiers (hybrid model):**

- **Basic / Pro tier (default):** single shared MySQL database. Every tenant-scoped table carries a `store_id` (and `organization_id` where relevant). Laravel **global scopes** (via `stancl/tenancy`'s single-database mode, or custom `BelongsToStore` trait + global scope) enforce filtering automatically on every query — no manual `where('store_id', ...)` scattered through controllers. This is a hard rule: any query on a tenant-scoped model that bypasses the global scope is a bug, not a shortcut.
- **Enterprise tier:** dedicated database per tenant (`stancl/tenancy`'s multi-database mode). Store admin/owner requests this tier explicitly (paid upgrade); Super Admin provisions a new DB + runs tenant migrations.
- **Organization-level exception:** an organization owner with multiple stores should generally keep all their stores in the **same isolation tier** (preferably shared-DB) to keep the cross-store consolidated dashboard a simple query rather than a cross-database aggregation job. Enforce this as a business rule at store-creation time (see 08-OPEN-QUESTIONS.md).

**Cross-tenant safety checklist (required before any tenant-scoped model ships):**
- [ ] Model uses the shared `BelongsToStore` trait / global scope
- [ ] No raw DB queries bypass the scope
- [ ] Feature has an automated test that asserts Store A cannot see Store B's data
- [ ] API responses never leak another tenant's IDs (e.g. sequential IDs guessable across tenants — consider UUIDs for tenant-scoped resources)

## 4. Deployment on cPanel (single-package deployment)

1. Build React admin (store) and React Super Admin apps: `npm run build` → static assets.
2. Copy build output into Laravel's `public/admin/` and `public/superadmin/` respectively.
3. Laravel serves these as static SPA routes; a catch-all route serves `index.html` for client-side routing, while `/api/*` is reserved for the actual API.
4. Deploy the whole Laravel app (with vendor/, built assets, `.env` configured) as a single zip/upload to cPanel.
5. Cron: use cPanel's cron tab to call `php artisan schedule:run` every minute (Laravel's scheduler drives any periodic jobs — subscription renewal checks, expiry alerts, etc., since there's no persistent queue worker on shared hosting).
6. React Native app is built/distributed separately (Expo EAS Build or bare RN build pipeline) — not part of the cPanel deployment at all; it only talks to the API over HTTPS.

## 5. Scaling path (when to leave cPanel)

cPanel shared hosting is acceptable for pilot/early-stage load. Move to a VPS/cloud provider (DigitalOcean, Hetzner, AWS) when any of these thresholds are hit:
- Concurrent tenant count causes noticeable response slowdowns
- Need for real persistent queue workers (Laravel Horizon / `queue:work` as a daemon) instead of cron-driven jobs
- Need for WebSocket-based real-time features (FCM push covers most needs without this, but live order-tracking maps etc. would need it)
- Enterprise tenants requesting dedicated database instances at a scale cPanel's MySQL limits can't support

This migration should require **no application rewrite** if tenancy and job-scheduling are built without cPanel-specific shortcuts — only infrastructure and deployment-script changes.

## 6. Authentication & authorization

- Laravel Sanctum for API token auth (mobile app + web admin).
- Roles: `super_admin`, `org_owner`, `store_admin`, `store_staff`, `customer`. Use a permissions package (e.g. `spatie/laravel-permission`) scoped per-store where relevant so `store_staff` permissions can be restricted (e.g. view orders but not payroll).

## 7. Third-party integrations summary

| Integration | Purpose | Requires |
|---|---|---|
| JazzCash | Payment | Merchant business account, NTN, settlement bank account, API credentials + sandbox |
| Easypaisa | Payment | Same as above, separate merchant application |
| Firebase Cloud Messaging | Push notifications | Firebase project, server key |
| SMS gateway (optional, TBD) | OTP/order alerts | See Open Questions |
