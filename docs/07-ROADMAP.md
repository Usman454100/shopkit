# Roadmap / Build Order

This sequences 04-FEATURES-BY-PHASE.md into concrete milestones. Each milestone should be independently demoable.

## Milestone 0 — Foundation
- Laravel project scaffolding, `stancl/tenancy` installed and configured (subdomain identification)
- Core platform tables: organizations, stores, users, subscription_plans, subscriptions
- Global scope / `BelongsToStore` trait established and tested (cross-tenant isolation test passes before any feature work continues)
- Auth (Sanctum) with role field

## Milestone 1 — Store onboarding
- Public store registration request form
- Super Admin approval queue (basic console)
- Store approval → tenant provisioning (subdomain live, shared-DB tenant record created)

## Milestone 2 — Catalog & inventory (Store Admin)
- Product CRUD with full flexible schema (fixed/weight-based, variants, perishable/expiry)
- Inventory tracking, low-stock + expiring-soon alerts

## Milestone 3 — Customer app core loop
- RN app: join-store flow (QR/link/search), browse catalog, cart, checkout (COD only), order tracking, order history

## Milestone 4 — Store admin order management
- Orders list/detail/status-update in admin panel
- Basic dashboard

## Milestone 5 — Pilot launch (Basic tier complete)
- Deploy to cPanel per 02-ARCHITECTURE.md §4
- Onboard 2-3 real pilot stores across different verticals (grocery, vegetable, shoe) to validate the flexible schema holds up in practice
- **Checkpoint: do not proceed to Phase 2 build until pilot feedback is reviewed**

## Milestone 6 — Phase 2: Pro tier
- Organization hierarchy + store switcher + consolidated dashboard
- JazzCash/Easypaisa integration (merchant onboarding should already be underway from Milestone 0)
- Bank transfer manual flow
- FCM push notifications
- Wishlist, reviews
- Staff accounts with restricted permissions

## Milestone 7 — Phase 3: Enterprise tier
- Purchases, purchase returns, sales returns
- Payables, receivables
- Employees, payroll runs
- Expenses tracking
- Promotions/deals
- Reports (P&L, sales, inventory valuation)
- Dedicated hosting/DB provisioning workflow for Enterprise tenants

## Milestone 8 — Subscription billing automation
- Move from manual (Super Admin marks paid) to automated recurring billing where feasible via JazzCash/Easypaisa
- Feature-gating enforcement fully wired to `subscription.plan.features_json` across all endpoints

## Notes on sequencing
- Payment gateway merchant applications (JazzCash/Easypaisa) should start at Milestone 0, running in parallel with engineering — this is the slowest external dependency in the whole roadmap.
- Do not build Phase 3 (Enterprise) modules until at least one paying pilot customer has asked for them, per PRD §8 success criteria — this roadmap lists them for completeness, not as a mandate to build speculatively.
