# Devs Arun — v2 (portfolio + CRM + admin)

PHP 8.1+ · MySQL 5.7+/MariaDB 10.4+ · Tailwind CSS · vanilla JS. Vendor libraries are bundled in `/vendor` (PHPMailer 6.12 for SMTP, official Razorpay PHP SDK 2.9.3) — no Composer needed on the server. To update them: `composer update`.

## What's inside

**Public site** (clean URLs, every page is a folder): `/`, `/services/`, `/services/{slug}/`, `/work/`, `/pricing/`, `/aeo-geo/`, `/about/`, `/contact/`, `/start-project/`, `/track/`, `/checkout/`, `/pay/{token}/`, `/receipt/`, `/brochure/{slug}/`, `/privacy/`, `/terms/`, `/refund-policy/`, `/sitemap.xml`.

- Every service is a detailed product page: **What is it?** explainer, **How it helps your business**, **3 buying packages** (each with its own Enquire button), deliverables, technology, process, scope, FAQ, inline enquiry form and a **downloadable PDF brochure** in the AEO/GEO deck style.
- **Enquire buttons everywhere** (cards, hero, packages, pricing, brochure library, mobile action bar) open one enquiry modal → lead with package of interest, reference ID, emails.
- 4-step **project brief** form (service → details + file → budget → contact) → reference ID, confirmation email, admin alert.
- **AI assistant** that knows the whole site: services (packages, FAQs, deliverables), store notes, portfolio, testimonials, policies and your own knowledge text, expanding only what is relevant to each question/page. Providers: **Groq** and **Cloudflare Workers AI** (REST API or your own Worker URL), plus Gemini / OpenAI / any OpenAI-compatible API, with automatic fallback between them and an offline rules engine as the last resort. Admin → Settings → AI has a "Test providers" button and shows the last provider error. Captures name/email/phone into the CRM.
- **Notes store** (`/store/`, `/store/{slug}/`): sell PDF notes in ₹ and/or $. In Admin → Store you upload a PDF; the browser reads it with PDF.js (bundled in `assets/vendor/pdfjs`), renders watermarked preview pages plus 2 blurred "locked" pages, and AI drafts the title, subtitle, description, highlights, contents, audience, FAQs, SEO text and a price suggestion. Everything is editable; products start as drafts. Product pages have a free-preview viewer, INR/USD toggle (auto-picked by timezone / Cloudflare country), sticky buy bar, Product + FAQ schema and a one-step Razorpay checkout. After payment the buyer gets a download button on the receipt and an email link (download limit configurable). The PDF itself is stored in `storage/products/` and never public. Store sections appear on the home, about and services pages, in the header, footer, sitemap and AI chat.
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
4. Log in at `/admin/` → **Settings**: add SMTP, Razorpay keys + webhook secret (webhook URL is shown there), and a Groq key and/or Cloudflare account ID + API token for AI. For USD sales, activate International payments in the Razorpay dashboard. Large PDF uploads need `upload_max_filesize` / `post_max_size` (set to 64M/70M in `.htaccess` and `.user.ini`; raise in hPanel → PHP Configuration if your host ignores them).
5. Turn on HTTPS and uncomment the HTTPS redirect in `.htaccess`.

Sub-folder installs: set `RewriteBase` in `.htaccess`. Nginx: route `/services/{slug}/`, `/brochure/{slug}/`, `/pay/{token}/`, `/sitemap.xml` the same way `dev-router.php` does and deny `app/ database/ storage/`.

## Local development

```bash
npm install && npm run build:css      # rebuild CSS after changing classes
php -S localhost:8000 dev-router.php  # mirrors .htaccess routing
```
