# CYBER EXAM

**Professional cybersecurity certification platform.** Candidates register, pay a
fixed fee, sit a 100-question timed exam and receive an automatically scored,
verifiable digital certificate.

CYBER EXAM is a self-contained, independent platform built with a lightweight
PHP MVC architecture. It runs on standard PHP/MySQL hosting with **no required
Composer or Node dependencies**.

---

## Features

- **7 certification tracks** — SOC Analyst, Network Security, Ethical Hacking,
  Cloud Security, Digital Forensics, GRC / Governance, Cybersecurity Architecture.
- **700 original questions** (100 per track), scenario-based, across Beginner /
  Intermediate / Advanced difficulty.
- **Exam engine** — 100 questions per attempt, randomised question **and** answer
  order, 120-minute **server-controlled** timer with automatic submission.
- **Automated scoring** — 1 point per question, 70% pass threshold, instant
  results with a per-domain breakdown.
- **Payments** — €10 per exam, confirmed **only** via a signed provider webhook
  (Stripe-ready), with a pluggable gateway interface (PayPal / Mobile Money can be
  added). A local **sandbox** gateway reproduces the full signed-webhook flow for
  development and tests.
- **Certificates** — vector PDF generated on pass, with a graphic signature,
  official seal, QR code and a unique, HMAC-signed certificate ID.
- **Public verification** — `/verify.php` and `/certificate.php` check every
  certificate against the database and its integrity signature; revocation
  supported.
- **Back-office** — dashboards, candidate/exam/payment/certificate management,
  full question and certification CRUD, configurable price/duration, and
  role-based access control (super-admin / admin / support).
- **Security** — PDO prepared statements, CSRF tokens, output escaping, session
  hardening, brute-force throttling and account lockout, security headers +
  Content Security Policy, audit logging, and tamper-evident certificates.
- **Modern UI** — responsive, animated interface (deep black / electric blue /
  cyan), self-hosted fonts, no external runtime CDNs.

---

## Requirements

- PHP **8.2+** with PDO MySQL, GD, mbstring, cURL, OpenSSL (all standard).
- MySQL **8+** (or MariaDB 10.6+), InnoDB.
- Apache with `mod_rewrite` and `mod_headers` (an `.htaccess` is provided), or
  Nginx with equivalent rules. Also runs on PHP's built-in server for development.

No Composer packages are required. `zlib` (usually built in) is used to compress
generated PDFs; the writer works without it too.

---

## Installation

```bash
# 1. Configure the environment
cp .env.example .env
php tools/generate-key.php        # copy the output into APP_KEY in .env
#   then set DB_*, APP_URL, PAYMENT_PROVIDER, MAIL_* in .env

# 2. Create the database schema
mysql -u <user> -p <database> < database/schema.sql

# 3. Seed the 7 certifications and 700 questions (idempotent)
php seed.php

# 4. Create the first administrator (password prompted, never echoed)
php tools/create-admin.php "Your Name" admin@your-domain.com super_admin
#   (alternatively set ADMIN_SETUP_TOKEN in .env and use /admin/setup.php once)
```

Point your web server's document root at the project directory. The provided
`.htaccess` files block direct access to `config/`, `controllers/`, `models/`,
`views/`, `includes/`, `services/`, `storage/`, `database/`, `tools/`, `tests/`,
`vendor/`, `certificates/`, `uploads/` and to `.env`, `seed.php` and other
sensitive files.

Ensure `storage/` and `certificates/` are writable by the web server.

### Local development

```bash
# .env: APP_ENV=local, APP_DEBUG=true, PAYMENT_PROVIDER=sandbox, MAIL_DRIVER=log
php -S localhost:8000 tools/dev-router.php
```

The `sandbox` payment provider is automatically disabled when `APP_ENV=production`.
With `MAIL_DRIVER=log`, emails are written to `storage/mail/` instead of being sent.

---

## Payments & webhooks

A payment is **never** treated as complete based on a browser redirect. The flow is:

1. The candidate starts checkout → a `pending` payment is created and they are
   redirected to the provider's hosted checkout.
2. The provider calls the webhook endpoint (`/api/webhooks/stripe.php`) with a
   **signed** event. The signature and the amount/currency are verified
   server-side before the payment is marked `paid`.
3. Only a `paid` payment (not yet consumed by an attempt) unlocks an exam.

Configure the Stripe webhook to point at `https://your-domain/api/webhooks/stripe.php`
and set `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` in `.env`.

To add another provider, implement `App\Services\Payment\PaymentGateway` and
register it in `PaymentService::gateway()`.

---

## Exam integrity

- Questions are drawn and both question and answer order are randomised **per
  attempt** using a CSPRNG.
- The timer is authoritative on the server (`expires_at`); the client only
  displays it. When time runs out the attempt is auto-submitted and scored.
- The client never receives the correct answers or explanations. Scoring happens
  entirely server-side against the database answer key.
- Certificates carry an HMAC-SHA256 signature over their data; editing a PDF or a
  URL cannot produce a valid verification.

---

## Certificate verification

Every certificate has an ID of the form `CEX-<TRACK>-<YEAR>-<8 chars>`
(e.g. `CEX-SOC-2026-7KQ2M9XA`). Anyone can verify it at:

- `/verify.php?id=<CERTIFICATE_ID>` — verification form and result, or
- `/certificate.php?id=<CERTIFICATE_ID>` — public certificate page.

The QR code on each certificate points to the verification URL. Verification
reads the live database record and validates its integrity signature.

---

## Project structure

```
/admin            Back-office entry points
/api              JSON endpoints and payment webhooks
/assets           css / js / fonts / images (public)
/config           Application configuration (not web-accessible)
/controllers      Request handlers (MVC controllers)
/models           Data access via PDO (MVC models)
/services         Business logic (exam, scoring, payments, certificate, mail, PDF, QR)
/views            Templates (MVC views)
/includes         Framework core (bootstrap, routing helpers, security)
/database         schema.sql and question/certification seeds
/certificates     Cached generated PDFs (not web-accessible)
/storage          logs, queued mail, cache (not web-accessible)
/tools            CLI utilities (key/admin generation, dev router)
/tests            Unit and end-to-end test suites
index.php login.php register.php dashboard.php checkout.php exam.php
result.php verify.php certificate.php ...   Public entry points
```

Application code follows a light MVC split: controllers orchestrate, models handle
SQL through PDO, services hold business logic, and views render HTML with all
dynamic output escaped.

---

## Testing

A seeded database and a `.env` with `APP_ENV=local` / `PAYMENT_PROVIDER=sandbox`
are required.

```bash
tests/run.sh
```

This lints every PHP file, boots a disposable server and runs:

- **`tests/unit_test.php`** — scoring boundaries (69% NOT PASSED, 70% and 71%
  PASSED), webhook signature verification, QR generation, certificate
  signing/PDF, password hashing and question-bank integrity.
- **`tests/http_flow_test.php`** — full fail path: registration, email
  verification gate, login throttling, CSRF enforcement, payment-by-webhook,
  exam start/answer/submit, authorization and reflected-XSS checks.
- **`tests/pass_flow_test.php`** — full pass path: 100% score, automatic
  certificate issuance, PDF download, public verification and revocation.

---

## Configuration

Exam duration, price, currency, passing score, questions per exam, email
verification requirement and registration status are configurable from
**Admin → Settings**, and can be applied to all certifications at once. Per-track
overrides are available on each certification.

---

## Security notes

- Keep `APP_DEBUG=false` and `APP_ENV=production` in production.
- Serve the site over HTTPS and set `FORCE_HTTPS=true` (behind a proxy, list it in
  `TRUSTED_PROXIES`).
- Remove `ADMIN_SETUP_TOKEN` from `.env` after creating the first administrator.
- Back up the database and `certificates/` directory regularly.
- Rotate `APP_KEY` only with care: it signs certificate integrity and verification
  hashes.
