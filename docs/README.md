# ShopKit — Multi-Tenant Retail Platform

> "ShopKit" is a placeholder product name. Replace throughout before public launch.

This folder is the complete requirements/spec package for development. Read in this order:

1. **01-PRD.md** — product vision, personas, scope, phasing
2. **02-ARCHITECTURE.md** — technical stack, multi-tenancy strategy, deployment
3. **03-DATABASE-SCHEMA.md** — entities, relationships, ERD
4. **04-FEATURES-BY-PHASE.md** — detailed feature breakdown per phase
5. **05-SUBSCRIPTION-BILLING.md** — plan tiers, billing logic, feature gating
6. **06-UX-FLOWS.md** — screen-by-screen flows for customer app, store admin, super admin
7. **07-ROADMAP.md** — build order and milestones
8. **08-OPEN-QUESTIONS.md** — unresolved decisions that need answers before/during build

## One-line summary
A subscription-based, multi-tenant SaaS platform letting local retail shops (grocery, vegetable, shoe, or similar) run their own branded ordering system — one shared React Native customer app (store selected via QR/link), a React web admin panel per store, and a Laravel backend with tenant isolation — with a full back-office (inventory, purchases, payroll, accounts, promotions) layered in over three build phases.

## Branding rule
Individual stores do not get their own app-store presence. All stores share the ShopKit customer app UI, differentiated only by store context (name/logo/catalog after joining). Every store-facing surface (app screens, admin panel, receipts, PWA-if-added-later) carries a small "Powered by ShopKit — thedevsolutions.com" footer/badge. This is non-negotiable branding, not a per-store option.

## Who this is for
This package is written to be handed directly to a development agent (e.g. Claude Code) to begin implementation. Each doc is self-contained but cross-references others — read 08-OPEN-QUESTIONS.md before writing any code that touches an unresolved item.
