# Product Requirements Document (PRD)

## 1. Vision
Enable any small local retail business — grocery, vegetable/produce, shoe store, or similar — to run its own online ordering system without building custom software, by subscribing to a shared, multi-tenant platform. Customers order through one shared app; each store experiences it as "their own" system.

## 2. Problem being solved
Local shopkeepers currently take orders by phone/WhatsApp with no structured catalog, inventory tracking, or order history. They cannot afford custom app development. ShopKit gives them a subscription-priced, ready-made storefront + back-office.

## 3. Personas

| Persona | Description | Primary surface |
|---|---|---|
| **Super Admin** | ShopKit platform operator (you / Dev Solutions team) | Super Admin web console |
| **Organization Owner** | A shopkeeper who owns 1+ stores, possibly across regions | React web admin (org-level view) |
| **Store Admin/Staff** | Manages day-to-day for one store (orders, inventory) | React web admin (store-scoped) |
| **Customer** | Local resident ordering from a nearby store | React Native app |

## 4. Non-goals (explicitly out of scope)
- This is **not** a Daraz-style multi-vendor marketplace where customers browse across stores in one catalog. Each customer session is scoped to one joined store at a time.
- No public store discovery marketplace in Phase 1–3 (stores are joined via QR/invite link, not browsed publicly). Revisit only if requested later.
- No delivery-rider logistics platform — delivery is store's own arrangement (status tracked, not dispatched/optimized by ShopKit).

## 5. Core product principles
1. **One shared customer app, many isolated stores.** No per-store native app builds or app-store listings.
2. **Flexible catalog schema from day one.** Every store — regardless of vertical — uses the same product model, capable of expressing weight-based pricing, size/color variants, and perishable/expiry tracking. Store admins simply leave irrelevant fields unset.
3. **Tenant isolation is non-negotiable.** No cross-tenant data leakage under any load condition.
4. **Subscription is the only revenue model.** No commission-per-order logic required in billing (may be revisited later, but not in scope now).
5. **Branding stays with ShopKit.** Stores get logo/name/colors within their own space, not standalone app identity.

## 6. Subscription tiers (maps to phased feature rollout — see 05-SUBSCRIPTION-BILLING.md)

| Tier | Includes |
|---|---|
| **Basic** | Store registration, product catalog, orders, COD, single-store admin dashboard, inventory |
| **Pro** | Everything in Basic + multi-store org hierarchy, JazzCash/Easypaisa, push notifications, wishlist, reviews |
| **Enterprise** | Everything in Pro + full accounting (purchases, payables/receivables, payroll), promotions/deals, advanced reports, dedicated hosting/database isolation |

## 7. High-level user journeys

### 7.1 Store onboarding (Super Admin approval flow)
1. Prospective shop owner submits a registration request (business name, category, contact, address) via a public request form.
2. Super Admin reviews the request in the Super Admin console.
3. Super Admin approves → store + tenant record created, owner receives credentials/invite link, subscription trial or plan selection begins.
4. Super Admin can reject with a reason (owner notified).

### 7.2 Customer ordering journey
1. Customer downloads the ShopKit app once.
2. Joins a store via QR code scan, shared link, or manual store search/list.
3. Browses that store's catalog only (session scoped to one store at a time; switching stores is supported but is an explicit action, not blended browsing).
4. Adds to cart → places order → selects payment method (COD / bank transfer / JazzCash / Easypaisa depending on what the store enabled) → tracks order status → views order history, wishlist.

### 7.3 Store admin journey
1. Logs into React web admin panel (subdomain-scoped, e.g. `storea.shopkit.app/admin`).
2. Manages catalog, inventory, incoming orders, customers.
3. (Enterprise) manages purchases, payables/receivables, payroll, promotions, views reports.

### 7.4 Organization owner (multi-store) journey
1. Logs in once at the organization level.
2. Sees a store switcher and/or a consolidated cross-store dashboard (sales, inventory alerts, payroll summary).
3. Drills into any individual store's full admin view.

## 8. Success criteria for Phase 1 (MVP)
- A pilot store owner can register, get approved, list products (using at least one of: fixed price, weight-based, variant-based, perishable/expiry), and receive/manage a real order from a real customer through the app, paid via COD, without developer intervention.
