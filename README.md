# Corvane — Shipping & Tracking Platform

Bilingual (English / French) shipping and parcel-tracking platform: public tracking, instant quotes,
a five-step booking wizard, manual payments with proof validation, and a Filament back-office.
Built to the *Cahier des charges techniques* (6 Oct 2026). "Corvane" is a placeholder brand.

## Stack

| Layer | Choice |
| --- | --- |
| Backend | PHP 8.4, Laravel 13 (modular monolith) |
| Public site and account | Blade, Alpine.js **CSP build**, Tailwind CSS v4 |
| Back-office | Filament 5 (Livewire 4) |
| 3D globe | three.js, static SVG map fallback |
| Database | PostgreSQL 16 |
| Cache, queues, rate limits, sessions | Redis |
| Files | Private disk / S3 bucket, signed 5-minute URLs, ClamAV |
| PDFs | dompdf (label, invoice, commercial invoice, receipt) |

> The specification names Laravel 11; it is out of security support, so the current release (13) is used.
> The public site uses Alpine's CSP build instead of Livewire so it runs under a strict nonce-based
> Content-Security-Policy with no `unsafe-eval`.

## Local development

Requirements: PHP 8.4 (intl, pdo_pgsql, redis, imagick, gd), Composer, Node 24, Docker.

```bash
# PostgreSQL, Redis and Mailpit for development
docker run -d --name corvane-pg -e POSTGRES_USER=corvane -e POSTGRES_PASSWORD=corvane_dev_pw \
  -e POSTGRES_DB=corvane -p 127.0.0.1:55432:5432 postgres:16-alpine
docker run -d --name corvane-redis -p 127.0.0.1:56379:6379 redis:7.4-alpine
docker run -d --name corvane-mailpit -p 127.0.0.1:58025:8025 -p 127.0.0.1:51025:1025 axllent/mailpit

composer install && npm ci
cp .env.example .env            # then set APP_ENV=local, APP_DEBUG=true, DB/REDIS/MAIL ports above
php artisan key:generate
echo "FIELD_ENCRYPTION_KEY=base64:$(openssl rand -base64 32)" >> .env
php artisan migrate --seed       # demo data is seeded only in local, testing and staging
php artisan storage:link
npm run build

php artisan serve                # http://127.0.0.1:8000
php artisan queue:work           # proof scanning, PDFs, emails
php artisan schedule:work        # expiry, reminders, overdue alerts, retention
```

Mail sent in development is visible in Mailpit at http://127.0.0.1:58025.

### Demo accounts (local and staging only)

Password for all: `Corvane-Demo-2026!`

| Account | Role | TOTP secret |
| --- | --- | --- |
| customer@corvane.test | Customer | none |
| admin@corvane.test | Admin | `JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP` |
| verifier@corvane.test / verifier2@corvane.test | Payment verifier | `KRSXG5CTMVRXEZLU…` / `MFRGGZDFMZTWQ2LK…` |
| support@corvane.test | Support agent | `ONSWG4TFORXXEZLT…` |

Staff sign in at `/login` (password, then authenticator code) and land in the back-office at `/admin`.
Demo tracking numbers: `CV-AIR-100016`, `CV-SEA-100016`, `CV-RD-100016`.

## Tests

```bash
php artisan test          # 87 tests on PostgreSQL (database corvane_test)
vendor/bin/pint           # code style
```

The suite covers the payment acceptance scenarios P1–P13 (spec 13.2) plus two-person approval,
conflict-of-interest, partial payments, gift cards, idempotency, tracking privacy, CAPTCHA and rate
limits, lockout, enumeration resistance, TOTP replay, IDOR checks, role matrix, file access, CSP,
CSRF, append-only audit log and French translation completeness.

## Where things live

| Concern | Location |
| --- | --- |
| Payment flow (select, proof, review, release, expiry, refunds) | `app/Services/Payments` |
| Quote engine and rate cards | `app/Services/Pricing` |
| Tracking, carrier detection, aggregator adapter | `app/Services/Tracking` |
| Booking, events, subscriptions | `app/Services/Shipping` |
| Uploads, scanning, signed URLs | `app/Services/Files`, `app/Http/Controllers/Web/FileController.php` |
| Security headers, CSP, idempotency, admin gate | `app/Http/Middleware` |
| Rate limits | `app/Providers/AppServiceProvider.php` |
| Roles and permissions (data, not code) | `app/Support/Permissions.php`, `app/Policies` |
| Back-office | `app/Filament` |
| Platform settings defaults | `config/platform.php` (overridable in Admin → Settings) |
| Translations | `lang/fr.json`, `lang/{en,fr}/*.php` (French URLs such as `/fr/suivi`) |

## Security summary

- Payment account details are encrypted (AES-256-GCM, separate key) and returned **only** by
  `POST /api/v1/orders/{id}/payment-method` to the order owner; never in pages, bundles or public APIs.
- Label, tracking number and invoice are released only after verifier approval, in one transaction;
  orders above the threshold need two different approvers; verifiers cannot approve their own orders.
- Proof uploads: content sniffing, size/count/pixel limits, malware scan, metadata stripping, SHA-256
  duplicate detection, private storage, 5-minute signed URLs that are also ownership-checked.
- Strict nonce CSP on the public site, HSTS, frame denial, `nosniff`; Markdown rendered with raw HTML
  escaped; CSRF on all state changes; per-endpoint rate limits plus a global limiter and nginx limits.
- Lockout after 5 failures (15 minutes), CAPTCHA after repeated failures, enumeration-safe
  register/login/reset, TOTP with replay protection and recovery codes, 2FA mandatory for staff.
- Append-only audit log (database trigger) with secrets redacted; changes to payment details are
  audited, emailed to all admins and can be delayed before going live.

The back-office CSP allows `unsafe-inline`/`unsafe-eval` because Filament requires it; compensating
controls are escaped output, 2FA-only staff access and an optional IP allowlist (`ADMIN_IP_ALLOWLIST`).

## Deployment

`Dockerfile` builds one image used by php-fpm, the queue worker and the scheduler;
`docker-compose.yml` adds nginx (`docker/nginx/default.conf`, edge rate limits), PostgreSQL, Redis and
ClamAV. CI (`.github/workflows/ci.yml`) runs Pint, the test suite on PostgreSQL + Redis, and
`composer audit` / `npm audit`.

Before go-live: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`,
`CAPTCHA_DRIVER=turnstile`, `MALWARE_SCANNER=clamav`, `PRIVATE_DISK=private_s3`, a CDN/WAF in front,
real keys from a secrets manager, and the open questions in spec section 16.1 answered (brand, carrier
agreements, payment-account terms, gift cards, rates, legal texts reviewed by a lawyer).
