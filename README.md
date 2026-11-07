# Devs Arun — v2 (portfolio + CRM + admin)

PHP 8.1+ · MySQL 5.7+/MariaDB 10.4+ · Tailwind CSS · vanilla JS. Vendor libraries are bundled in `/vendor` (PHPMailer 6.12 for SMTP, official Razorpay PHP SDK 2.9.3) — no Composer needed on the server. To update them: `composer update`.

## What's inside

**Public site** (clean URLs, every page is a folder): `/`, `/services/`, `/services/{slug}/`, `/work/`, `/pricing/`, `/aeo-geo/`, `/about/`, `/contact/`, `/start-project/`, `/track/`, `/checkout/`, `/pay/{token}/`, `/receipt/`, `/brochure/{slug}/`, `/privacy/`, `/terms/`, `/refund-policy/`, `/sitemap.xml`.

- Every service is a detailed product page: **What is it?** explainer, **How it helps your business**, **3 buying packages** (each with its own Enquire button), deliverables, technology, process, scope, FAQ, inline enquiry form and a **downloadable PDF brochure** in the AEO/GEO deck style.
- **Enquire buttons everywhere** (cards, hero, packages, pricing, brochure library, mobile action bar) open one enquiry modal → lead with package of interest, reference ID, emails.
- 4-step **project brief** form (service → details + file → budget → contact) → reference ID, confirmation email, admin alert.
- **AI assistant** trained on live services/prices + your own knowledge text. Works offline (rules engine) or with Gemini / OpenAI / Groq. Captures name/email/phone into the CRM.
- **Razorpay**: book selected services online, or pay negotiated quotes via secure links. Signature-verified + webhook.
- Client **tracking page** with status timeline and public updates.
- First-party analytics (no IPs stored), UTM / referrer attribution on every lead.

**Admin** (`/admin/`): dashboard & funnel, leads CRM (status, priority, value, follow-ups, notes, client-visible updates, email, quotes, CSV export, attachments), AI chat transcripts, payments, quotes/payment links, email center (SMTP, templates, segment emails, log), analytics, services/portfolio/testimonials editors, settings (SMTP, Razorpay, AI, branding — upload your own logo/favicon), team roles (owner/manager/viewer), audit log.

**Security**: PDO prepared statements, CSRF on every form/API, bcrypt passwords, login lockout, session hardening, rate limits, honeypots, CSP & security headers, secrets encrypted (AES-256-GCM), uploads type-checked and stored outside the web root, brand images re-encoded, private folders blocked by `.htaccess`.

## Upgrades

Schema/content upgrades run automatically on the first request after deploying new code (`app/upgrade.php`, tracked by the `schema_version` setting). Existing content you edited is never overwritten.

## Deploy (Hostinger / cPanel)

1. Upload everything to `public_html`, **including `/vendor`** (keep all `.htaccess` files — they block `app/`, `vendor/`, `storage/`, `database/`).
2. Create an empty MySQL database + user.
3. Open `https://yourdomain.com/install/` and fill the form once. It creates tables, seeds all services & projects, your owner account and `app/config.php`, then locks itself.
4. Log in at `/admin/` → **Settings**: add SMTP, Razorpay keys + webhook secret (webhook URL is shown there), and an AI key (optional).
5. Turn on HTTPS and uncomment the HTTPS redirect in `.htaccess`.

Sub-folder installs: set `RewriteBase` in `.htaccess`. Nginx: route `/services/{slug}/`, `/brochure/{slug}/`, `/pay/{token}/`, `/sitemap.xml` the same way `dev-router.php` does and deny `app/ database/ storage/`.

## Local development

```bash
npm install && npm run build:css      # rebuild CSS after changing classes
php -S localhost:8000 dev-router.php  # mirrors .htaccess routing
```
