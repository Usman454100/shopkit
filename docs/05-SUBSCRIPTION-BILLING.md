# Subscription & Billing

## 1. Tier structure

| Tier | Target phase unlocked | Suggested audience |
|---|---|---|
| **Basic** | Phase 1 features only | Single small shop, testing the waters |
| **Pro** | Phase 1 + 2 | Growing shop wanting notifications, digital payments, or multiple branches |
| **Enterprise** | Phase 1 + 2 + 3 | Established business wanting full accounting/payroll + dedicated hosting |

Exact prices: **not set — flag as open decision** (see 08-OPEN-QUESTIONS.md). Structure below assumes monthly billing; yearly discount optional.

## 2. Feature gating mechanism

- `subscription_plans.features_json` holds a flat list of feature flags, e.g.:
```json
{
  "multi_store": true,
  "payment_gateways": ["jazzcash", "easypaisa"],
  "push_notifications": true,
  "payroll": true,
  "promotions": true,
  "dedicated_hosting": true,
  "max_stores": 5,
  "max_products": null
}
```
- Backend middleware checks the organization's active `subscription` → `plan` → `features_json` before allowing access to a gated feature/endpoint. Fail closed (403) if the flag is absent, not fail open.
- Frontend (admin panel) reads the same flags to hide/disable UI for features not on the current plan, with an upsell prompt ("Upgrade to Pro to enable payroll").

## 3. Subscription lifecycle

1. Store approved by Super Admin → organization assigned to a plan (initially manual selection or default Basic; self-serve plan picker can come later).
2. Recurring billing: since payment gateway integration is Phase 2, **Phase 1 billing is manual/offline** (Super Admin marks subscription paid/unpaid) — do not build automated recurring billing against JazzCash/Easypaisa until Phase 2, when those integrations exist.
3. Grace period on missed payment before suspension — define exact days in Open Questions.
4. Suspended store: admin panel shows read-only/locked state; customer app shows store as temporarily unavailable (not deleted).
5. Plan upgrade/downgrade: changes `subscription.plan_id`; feature flags take effect immediately on next request (no code deploy needed).

## 4. What's explicitly NOT in scope now

- Per-order commission billing (subscription-only model, per your decision)
- Automated proration on mid-cycle upgrades (handle manually/simply at first)
- Self-serve online plan checkout in Phase 1 (Super Admin assigns plans manually until payment gateways exist)
