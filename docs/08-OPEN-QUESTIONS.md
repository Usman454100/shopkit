# Open Questions — Resolve Before/During Development

These are real gaps, not formalities. Flagging them explicitly rather than guessing and baking in a wrong assumption.

## Product/business
1. **Actual subscription prices** for Basic/Pro/Enterprise — not yet set.
2. **Trial period?** Does a new store get a free trial before their first billing cycle?
3. **Grace period** length before a suspended (non-paying) store is locked out.
4. **Platform product name** — "ShopKit" is a placeholder; confirm real name before any branding/footer/domain work.
5. **Domain strategy** — do you own a domain for subdomain provisioning (`{store}.yourdomain.com`) yet? Needed before Milestone 1.
6. **Delivery model** — is delivery entirely the store's own arrangement (their own staff/rider), or does ShopKit ever need to show delivery-partner integration? Assumed: store's own arrangement, status-only tracking.
7. **Store discovery** — currently assumed customers only join via QR/link/manual search, no public marketplace browsing across stores. Confirm this stays true even post-Phase 3.

## Technical
8. **React Native tooling**: Expo (faster iteration, some native-module limitations) vs bare React Native (full native access, more build complexity)? Recommendation: start with Expo unless a specific native module need is already known.
9. **Customer login method**: phone+OTP (needs SMS gateway — cost + vendor decision) vs email/password? Phone+OTP is more natural for this user base but adds a dependency.
10. **Multi-store isolation-tier rule**: should the system *hard-enforce* that all of an organization's stores share one isolation tier (recommended in 02-ARCHITECTURE.md), or allow mixed tiers with more complex cross-store aggregation? Needs an explicit decision before Milestone 6.
11. **UUID vs incrementing IDs** for tenant-scoped resources exposed via API (incrementing IDs make cross-tenant enumeration attacks easier to attempt, even if scopes block the actual data access).
12. **SMS gateway vendor** (if phone+OTP is chosen) — which provider, and cost per message at scale.

## Design
13. Whether the customer app needs offline/poor-connectivity handling as a first-class concern (relevant given target users may be on inconsistent mobile data).
14. Multi-language support (Urdu + English) — in scope for Phase 1 or later?

## Process
15. Team/resourcing: is this built solo, with the existing Dev Solutions team, or does it need dedicated hires given the size of this scope alongside existing client work and other ventures (OmniChat, FairyBee, SchoolPro)?
16. Pilot store selection: which specific 2-3 shops (ideally across grocery/vegetable/shoe) will be the Milestone 5 pilots, and is there an existing relationship with any of them to ease onboarding/feedback?
