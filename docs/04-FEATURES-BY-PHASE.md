# Features by Phase

## Phase 1 — MVP (Basic tier)

**Platform/Super Admin**
- Store registration request form (public)
- Super Admin review/approve/reject queue
- Store + tenant creation on approval (subdomain provisioning, shared-DB tenant record)

**Store Admin (React web)**
- Login (Sanctum)
- Product catalog CRUD (full flexible schema: fixed/weight-based pricing, variants, perishable/expiry)
- Inventory: stock levels, manual adjustment, low-stock indicator
- Expired-goods flag: scheduled job marks/hides products past `expiry_date`, surfaces alert on dashboard
- Orders: view incoming orders, update status (pending → confirmed → preparing → out_for_delivery → delivered/cancelled)
- Basic dashboard: today's orders, revenue-to-date, low-stock count

**Customer App (React Native)**
- Onboarding: join store via QR scan / invite link / manual store list search
- Browse catalog (respecting variant/weight-based/perishable display logic)
- Cart, checkout, COD payment only
- Order status tracking
- Order history

**Billing**
- Subscription record created on org approval (manual/Basic plan assignment; full plan-switching UI can wait for Phase 2 if needed — flag in Open Questions)

## Phase 2 — Pro tier

- **Organization hierarchy**: org owner login, store switcher, consolidated cross-store dashboard (sales/inventory/order summary across all owned stores)
- **Payments**: JazzCash + Easypaisa integration (merchant onboarding should start in parallel with Phase 1 dev, not after)
- **Bank transfer** payment option (manual proof upload + admin confirmation)
- **Push notifications** via FCM: new order alert to store admin, order-status-change alert to customer
- **Wishlist** (customer app)
- **Reviews** (product and/or store level)
- Store admin: staff accounts with restricted permissions (store_staff role, e.g. can manage orders but not settings)

## Phase 3 — Enterprise tier

- **Purchases** module: record supplier purchases, purchase items, purchase returns
- **Sales returns**
- **Payables** (money store owes suppliers) and **Receivables** (money owed to store, e.g. credit sales)
- **Employees & Payroll**: employee records, monthly payroll runs, payroll items (base + deductions + net pay)
- **Other expenses** tracking
- **Promotions/Deals**: percentage/flat discounts, bundle deals, scoped to all/category/specific products
- **Reports**: profit & loss, sales summary, inventory valuation, payroll summary — exportable (CSV/PDF)
- **Dedicated hosting/isolation tier**: separate database provisioning workflow for Enterprise tenants (Super Admin triggers migration to dedicated DB)

## Cross-cutting (applies across all phases)

- Tenant isolation enforcement (see 02-ARCHITECTURE.md §3) — required from Phase 1, not added later
- "Powered by ShopKit" branding footer on all customer/admin surfaces
- Audit trail on sensitive actions (order status changes, payroll processing, store approval/rejection) — minimum: who/when, even if a full audit UI comes later
