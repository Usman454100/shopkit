# Deployment Runbook — cPanel (Milestone 5)

Reference: `docs/02-ARCHITECTURE.md` §4, `docs/07-ROADMAP.md` Milestone 5.

This covers everything needed to deploy ShopKit to a cPanel host. Steps under
"Build machine" run locally or in CI, never on the cPanel server itself —
cPanel only needs PHP + MySQL, no Node.js at runtime.

## 1. Build machine: assets

```
bash deploy/build-assets.sh
```

Builds `admin-web/` and `superadmin-web/`, copies their `dist/` output into
`backend/public/admin/` and `backend/public/superadmin/`. Both apps are
currently minimal placeholder shells (a login page hitting the real API) —
enough to prove this pipeline end-to-end; real admin/console features are a
separate, later effort.

## 2. Build machine: vendor + Laravel caches

```
cd backend
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`route:cache` requires zero closure-based routes — this was a real blocker
until `routes/tenant.php`'s placeholder `/` route (a closure) was removed in
this milestone. If you add a new route later and `route:cache` starts
failing, check for closures first.

## 3. Package for upload

Upload the whole app tree: `app/`, `bootstrap/`, `config/`, `database/`,
`public/` (including the built `admin/`/`superadmin/` assets from step 1),
`resources/`, `routes/`, `vendor/` (from step 2), `composer.json`,
`composer.lock`, and a configured `.env` (see §4). Do **not** upload
`node_modules/`, `admin-web/`, `superadmin-web/`, or `tests/`.

## 4. Production `.env` checklist

| Key | Value | Why |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | Never leak stack traces publicly |
| `APP_KEY` | freshly generated (`php artisan key:generate --force`), kept secret | |
| `APP_URL` / `APP_TENANT_DOMAIN` | the real domain | **Blocked on Open Question #5** — not chosen yet |
| `DB_*` | cPanel MySQL database credentials | |
| `QUEUE_CONNECTION` | `sync` | No persistent worker on shared hosting; nothing in the app queues jobs anyway (see Milestone 5 decision) |
| `MAIL_MAILER` | a real provider, not `log` | Store owners can't read `storage/logs/laravel.log` — flagged as a **pre-launch** item, not a pipeline blocker |
| Sanctum config | no changes needed | We use Bearer tokens exclusively, never cookie/SPA auth, so none of Sanctum's stateful-domain config applies |

## 5. First-deploy data steps

```
php artisan migrate --force
php artisan db:seed --class=SubscriptionPlanSeeder
php artisan shopkit:create-super-admin "Name" "email@example.com" "password"
```

## 6. cPanel hosting setup (manual, host-plan-dependent)

These need your actual cPanel/hosting account — documented here since they
can't be scripted the way the rest of this has been.

- **Document root**: point it directly at `backend/public/` (recommended —
  cleaner and keeps `vendor/`/`.env`/`app/` outside the web root). Some
  budget shared-hosting plans only allow `public_html/` as the document root
  with no override; if that's what you have, the fallback is moving
  `public/`'s contents into `public_html/` and adjusting the `require`
  paths in `public_html/index.php` accordingly — messier and not
  recommended unless there's no other option.
- **Wildcard DNS + vhost routing**: our tenancy model needs
  `*.yourdomain.com` → the same server/document root for every store
  subdomain to resolve. Whether your specific cPanel plan supports a
  wildcard subdomain (some do out of the box, some need WHM/root access,
  some don't support it on shared plans at all) has to be verified against
  the actual account — genuinely can't be confirmed without it.
- **Wildcard SSL**: same dependency — needs a cert covering
  `*.yourdomain.com`, via cPanel AutoSSL (if it supports wildcard + DNS
  validation on your plan) or a purchased wildcard certificate.
- **Cron** (drives the scheduler — no persistent workers on shared hosting):
  ```
  * * * * * php /home/USER/path/to/app/artisan schedule:run >> /dev/null 2>&1
  ```
  Currently drives one job: `products:flag-expired` (daily).

## 7. Known limitation found during verification

Verified locally against **real Apache** (XAMPP), not `artisan serve` —
`artisan serve` has its own router and doesn't process `.htaccess` at all,
so it can't validate any of this.

**The bare directory paths `/admin` and `/superadmin` (no further path
segment) bypass Laravel entirely**, including the `PreventAccessFromCentralDomains`
tenancy check. This is Apache's own `mod_dir` behavior: `public/.htaccess`'s
rewrite-to-`index.php` rule only fires when the request does **not** match a
real file or directory (`!-f`, `!-d`). Since `backend/public/admin/` and
`backend/public/superadmin/` physically exist as directories (containing the
built `index.html`), Apache's directory-index handling serves them directly
for the exact bare path — before Laravel's router, and therefore before any
tenancy middleware, ever runs.

Confirmed scope precisely:
- Bare `/admin/` on the **central** domain incorrectly serves the store-admin
  shell (should be blocked — it's meant to be per-store-subdomain only).
- Any **deeper** path (`/admin/dashboard`, `/admin/anything`) does **not**
  hit this shortcut and correctly goes through Laravel — verified 404 on the
  central domain, 200 on a real tenant subdomain.

**This is not a security hole.** The static shell is unauthenticated HTML/JS
with no embedded secrets or data; the actual authorization boundary is the
API layer (`store.sync`, `store.member`, `auth:sanctum`, `EnsureStoreIsActive`),
which is independent of which shell happened to load and was extensively
verified in Milestones 2-4. Loading the wrong SPA shell at the bare path is
cosmetically wrong, not a data-exposure risk.

**Not fixed yet, because the honest fix has a real trade-off**: blocking this
at the Apache level means hardcoding the actual central domain string into
`.htaccess` (or the vhost config), duplicating a value that's still unresolved
(Open Question #5) and would need to stay in sync with `config('tenancy.central_domains')`
by hand. Revisit this once the real domain is chosen — add an explicit
`RewriteCond %{HTTP_HOST}` guard in `public/.htaccess` (or the vhost) for the
bare `/admin` and `/superadmin` paths at that point, rather than duplicating
a placeholder domain now.
