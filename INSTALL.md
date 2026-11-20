# Quote form backend: installation

The two-step quote form (`index.html` → `additional-details.html` → `thank-you.html`) posts to `insert.php`.
Step 1 saves a new lead and emails the team and the customer. Step 2 updates the **same** lead (found through the
visitor's PHP session) and sends the detailed enquiry email and the customer's final confirmation.

## What's in the repo

| Path | Purpose |
| --- | --- |
| `config.sample.php` | Template for the single configuration file. Copy to `config.php`. |
| `insert.php` | Form endpoint for both steps (JSON in and out). |
| `form-token.php` | Gives the form its CSRF token (the HTML pages are static and cached). |
| `places-key.php` | Gives step 2 the Google Maps browser key from `config.php`. |
| `assets/forms.js` | Front-end helper: fetches the token, posts the form, reads the JSON reply. |
| `assets/places.js` | Google address and suburb suggestions for the step 2 suburb boxes. |
| `app/src/` | PHP classes: validation, database, security, PHPMailer, email templates. |
| `database/schema.sql` | Creates the `leads` table. |
| `assets/email/logo.png` | Logo used in the emails (email apps don't show SVG). |
| `composer.json` | Installs PHPMailer and the class autoloader. |

## Requirements

- PHP 8.1 or newer with `pdo_mysql`, `mbstring` and `openssl`
- MySQL 5.7+ or MariaDB 10.3+
- Composer
- An SMTP account (e.g. Google Workspace, Microsoft 365, Amazon SES, Mailgun, SendGrid, or your host's mailbox)

## 1. Create the database

In CloudPanel: **Sites → cleaners4u.com.au → Databases → Add Database**. Note the database name, user and password.

Import the table (over SSH, from the site folder):

```bash
cd ~/htdocs/cleaners4u.com.au
mysql -h 127.0.0.1 -u DB_USER -p DB_NAME < database/schema.sql
```

(Or open phpMyAdmin from CloudPanel and import `database/schema.sql`.)

## 2. Install PHPMailer

```bash
cd ~/htdocs/cleaners4u.com.au
composer install --no-dev --optimize-autoloader
```

## 3. Create config.php

```bash
cp config.sample.php config.php
chmod 640 config.php
nano config.php
```

Fill in:

- `db`: host (`127.0.0.1`), name, user, password
- `smtp`: host, port, username, password, encryption (`tls` for 587, `ssl` for 465), `from_email`, `from_name`
- `admin_email`: where lead emails go (several addresses separated by commas is fine)
- `site`: name, URL, phone, logo URL (already set for cleaners4u.com.au)
- `log_dir`: keep it outside the public folder (default `/home/cleaners4u/logs/forms`)

`config.php` is git-ignored, and deploys never overwrite it.

**Address suggestions (optional):** the step 2 "Suburb" and "Moving to" boxes show Google address and suburb
suggestions (with the "powered by Google" footer) when `google.maps_api_key` is set. To get a key:

1. In [Google Cloud Console](https://console.cloud.google.com/) create or pick a project with billing turned on.
2. Enable **Maps JavaScript API** and **Places API (New)**.
3. Create an API key under **APIs & Services → Credentials**. Restrict it to **Websites** `https://cleaners4u.com.au/*`
   and `https://www.cleaners4u.com.au/*`, and to those two APIs.
4. Put the key in `config.php` → `google` → `maps_api_key`.

Without a key (or if Google can't be reached) the boxes fall back to the built-in Melbourne suburb list.

**Email deliverability:** `from_email` must be an address your SMTP account is allowed to send as, and the domain
should have SPF, DKIM and DMARC records for that provider. Otherwise emails may land in spam or be rejected.

## 4. Deploy updates

The server keeps the git data outside the public folder. To pull the latest code from GitHub and install packages:

```bash
~/deploy.sh
```

## 5. Test

1. Open https://cleaners4u.com.au/, fill in step 1 and continue. You should land on step 2 with your reference (`C4U-…`).
2. Complete step 2. You should land on the thank-you page with the same reference.
3. Check the admin inbox (two emails) and the customer inbox (two emails).
4. Check the row in the database:
   ```sql
   SELECT lead_ref, status, name, clean_type, step1_admin_mailed_at, step2_customer_mailed_at, last_mail_error
   FROM leads ORDER BY id DESC LIMIT 5;
   ```
5. If something fails, read the log: `tail -n 50 /home/cleaners4u/logs/forms/forms-$(date +%Y-%m).log`

## How it works

**Step 1 (`request`)**
1. Checks the request came from the site's own JavaScript (`X-Requested-With`, Origin/Referer host) with a valid CSRF token.
2. Rejects bots (honeypot field `extra_field`, form filled in under 2 seconds).
3. Validates name, Australian phone number, email and privacy consent (same rules as the browser).
4. Duplicate check: the same email + phone again from the same browser session, or from the same IP within 30 minutes,
   reuses the existing lead instead of creating a new one (and doesn't send emails again).
5. Rate limit: at most 10 new leads per IP per hour.
6. Saves the lead with a unique reference such as `C4U-261120-7K3QX`, IP address, page URL, GCLID / GBRAID / ad source /
   campaign ID, device ID, user agent and submission time, and ties it to a new session id.
7. Replies to the browser straight away, then sends the admin notification and the customer thank-you email.

**Step 2 (`detail`)**
1. Same request and CSRF checks.
2. Validates every answer against the form's options (clean type, date, suburb, property type, bedrooms, bathrooms,
   add-ons and their follow-up counts, notes, move, utilities and consent).
3. Loads the lead from the session (the row's `session_hash` must match this browser) and locks it.
4. Updates that same row and marks it `complete`. Submitting again doesn't create a duplicate or resend emails.
5. Sends the detailed enquiry email to the team (flagged **COMBINED BOOKING** when a move was added) and the final
   confirmation to the customer.

**Security:** PDO prepared statements only (emulation off), CSRF token per session, hardened session cookie
(`HttpOnly`, `Secure`, `SameSite=Lax`, strict mode, idle timeout, new session id when a lead is created), every value
escaped in the emails, JSON responses marked no-store, errors logged and never shown to visitors.

**Email failures** never block the visitor. The outcome of each email is recorded on the lead (`*_mailed_at` columns or
`last_mail_error`) and in the log file.

## Optional: hide back-end files from the web

The PHP files return nothing when opened directly, but you can also block these paths in the site's nginx
settings (CloudPanel → Sites → cleaners4u.com.au → Vhost), inside the `server { … }` block for port 8080:

```nginx
location ~ ^/(app|database|vendor)/ { deny all; return 404; }
location ~ ^/(composer\.(json|lock)|config(\.sample)?\.php|INSTALL\.md|README\.md)$ { deny all; return 404; }
```
