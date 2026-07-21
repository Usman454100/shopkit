# UX Flows & Screen Inventory

## 1. Customer App (React Native)

### Onboarding
1. Splash → Welcome screen (explain: "Order from your local shop")
2. Join store: [Scan QR] | [Enter invite link] | [Search stores by name/location]
3. Register/Login (phone number + OTP, or email/password — see Open Questions)
4. Land on store home (store name/logo shown, "Powered by ShopKit" footer)

### Core loop
5. Store Home → category list / featured products
6. Product listing → product detail (variant picker if `has_variants`, weight selector if `pricing_type=weight_based`, expiry/freshness note if `is_perishable`)
7. Add to cart → Cart screen (edit quantities, remove items)
8. Checkout → delivery address → payment method selection (COD always available; JazzCash/Easypaisa/bank transfer shown only if store enabled them) → place order
9. Order confirmation screen
10. Order tracking (status timeline: pending → confirmed → preparing → out for delivery → delivered)
11. Order history list → order detail (reorder button)
12. Wishlist (Phase 2)
13. Profile: saved addresses, switch store, logout

### Empty/edge states to design explicitly
- Store temporarily unavailable (subscription lapsed)
- Empty cart
- Out-of-stock product (disable add-to-cart, show "Notify me" optionally — Phase 2+)
- Expired/near-expiry perishable item (should not be orderable if past expiry)

## 2. Store Admin (React web)

### Navigation shell
Left sidebar: Dashboard, Orders, Products, Inventory, Customers, (Phase 3: Purchases, Payables/Receivables, Employees/Payroll, Promotions, Reports), Settings

### Key screens
- **Dashboard**: today's orders count, revenue, low-stock alerts, expiring-soon items
- **Orders**: list with status filter, order detail with status-change action + customer info
- **Products**: list + add/edit form (conditional fields: pricing type toggle reveals weight-unit field; "has variants" toggle reveals variant builder; "is perishable" toggle reveals expiry/batch fields)
- **Inventory**: stock table, manual adjustment, reorder-level setting
- **Customers**: list of customers who've ordered, order history per customer
- **Settings**: store profile, logo upload, payment methods enabled, staff accounts (Phase 2)

### Org Owner additions (Phase 2, multi-store)
- **Store switcher** in top nav
- **Consolidated dashboard**: cross-store totals + per-store breakdown table, drill-down link into each store's full admin

## 3. Super Admin console (React web)

- **Store approval queue**: pending requests, view details, approve/reject (with reason)
- **Store list**: all stores, status, subscription plan, isolation tier
- **Subscription management**: assign/change plan, mark payment received (Phase 1 manual billing)
- **Platform metrics**: total stores, active subscriptions, revenue (manual-tracked in Phase 1)

## 4. Design system notes
- Design tokens (spacing, type scale, color roles) should be defined once and shared across the two React web apps (Store Admin + Super Admin) and, where feasible, mirrored in the RN app's theme — avoid three independent, drifting style systems.
- "Powered by ShopKit — thedevsolutions.com" footer: fixed small text/link, consistent placement across customer app footer/about screen and admin panel footer. Not removable by store admins.
