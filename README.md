# EventHub — Symfony Event Reservation System

A full-stack event reservation platform built with Symfony 8, featuring a modern UI, JWT API, Passkey authentication, and Docker support.

---

## Features

### User Features
- Browse all events with search (title/location) and pagination
- View event detail page with availability indicator
- Reserve a seat — overbooking prevention built in
- Flash messages for success/error feedback

### Admin Features
- Secure login (ROLE_ADMIN)
- Dashboard with stats: total events, reservations, upcoming events
- Full CRUD for events (create, edit, delete with confirmation)
- Image upload for events
- View all reservations, filter by event

### API Features
- `POST /api/login` — get JWT token (LexikJWTAuthenticationBundle)
- `POST /api/refresh` — refresh JWT token (GesdinetJWTRefreshTokenBundle)
- `GET /api/events` — public event listing with pagination
- `GET /api/events/{id}` — single event detail
- `GET /api/me` — authenticated user info (Bearer token required)
- `GET /api/admin/stats` — admin stats (ROLE_ADMIN required)
- `POST /passkey-api/register/options` — WebAuthn registration options
- `POST /passkey-api/register/verify` — store passkey credential
- `POST /passkey-api/login/options` — WebAuthn authentication options (returns JWT)
- `POST /passkey-api/login/verify` — verify passkey, return JWT
- `POST /passkey/login/options` — WebAuthn web login options (session-based)
- `POST /passkey/login/verify` — verify passkey, create Symfony session → redirect `/admin`

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Symfony 8 (PHP 8.2+) |
| Database | MySQL 8 / MariaDB |
| ORM | Doctrine ORM + Migrations |
| Templates | Twig + Bootstrap 5.3 |
| Security | Symfony Security (form login + JWT) |
| JWT | LexikJWTAuthenticationBundle v3 |
| Refresh Token | GesdinetJWTRefreshTokenBundle v2 |
| Passkeys | Custom WebAuthn flow (Web Authentication API) |
| Docker | PHP-FPM + Nginx + MySQL + phpMyAdmin |
| CI | GitHub Actions |

---

## Quick Start (XAMPP / Local)

### 1. Install dependencies
```bash
composer install --ignore-platform-req=ext-sodium
```

### 2. Configure environment
Copy `.env` and set your values:
```bash
cp .env .env.local
```
Edit `.env.local`:
```dotenv
APP_SECRET=your_32_char_random_secret_here
DATABASE_URL="mysql://root:@127.0.0.1:3306/event_project_db?serverVersion=8.0"
JWT_PASSPHRASE=your_passphrase_here
```

### 3. Generate JWT keypair
```bash
php generate_keys.php
```
This creates `config/jwt/private.pem` and `config/jwt/public.pem`.

### 4. Create database and run migrations
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Load demo data
```bash
php bin/console doctrine:fixtures:load --no-interaction
```

### 6. Start the server
```bash
symfony server:start
# or
php -S localhost:8000 -t public/
```

Visit: http://localhost:8000

---

## Demo Credentials

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| User | `user` | `user123` |

---

## Docker Setup

### Prerequisites
- Docker Desktop installed and running

### Start all services
```bash
docker compose up -d --build
```

This starts 4 services:
- `php` — PHP **8.4**-FPM Alpine (internal port 9000)
- `nginx` → http://localhost:8080
- `mysql` — MySQL 8 (host port 3307)
- `phpmyadmin` → http://localhost:8081

### Run migrations inside the container
```bash
docker exec eventhub_php php bin/console doctrine:migrations:migrate --no-interaction
docker exec eventhub_php php bin/console doctrine:fixtures:load --no-interaction
```

### Stop services
```bash
docker compose down
```

### Destroy everything including the database volume
```bash
docker compose down -v
```

### Docker DATABASE_URL
Inside the container the app connects via the `mysql` service hostname:
```
mysql://eventhub:eventhub_pass@mysql:3306/eventhub_db
```

> The single `compose.yaml` file is the only Docker Compose file in the project.
> `docker compose up` discovers it automatically — no `-f` flag needed.

---

## Route Table

| Method | URL | Auth | Description |
|---|---|---|---|
| GET | `/` | Public | Home page |
| GET | `/events` | Public | Events listing (search + pagination) |
| GET | `/events/{id}` | Public | Event detail |
| GET/POST | `/events/{id}/reserve` | Public | Reservation form |
| GET | `/admin/login` | Public | Admin login (password + passkey tabs) |
| GET | `/login` | Public | Redirects 301 → `/admin/login` |
| GET | `/logout` | Auth | Logout + session destroy |
| GET | `/admin` | ROLE_ADMIN | Dashboard |
| GET | `/admin/events` | ROLE_ADMIN | Manage events |
| GET/POST | `/admin/events/new` | ROLE_ADMIN | Create event |
| GET/POST | `/admin/events/{id}/edit` | ROLE_ADMIN | Edit event |
| POST | `/admin/events/{id}/delete` | ROLE_ADMIN | Delete event |
| GET | `/admin/reservations` | ROLE_ADMIN | View reservations (filter by event) |

---

## API Endpoints

> All examples use port `8080` (Docker). Replace with `8000` for local dev.

### POST /api/login — Get JWT Token
```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```
Response:
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refresh_token": "a1b2c3d4e5..."
}
```

### GET /api/me — Authenticated user info
```bash
curl http://localhost:8080/api/me \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```
Response:
```json
{"username": "admin", "roles": ["ROLE_ADMIN", "ROLE_USER"]}
```

### GET /api/events — Public event listing
```bash
curl "http://localhost:8080/api/events?page=1&search=tech"
```

### GET /api/admin/stats — Admin stats (ROLE_ADMIN required)
```bash
curl http://localhost:8080/api/admin/stats \
  -H "Authorization: Bearer YOUR_ADMIN_JWT_TOKEN"
```

---

## Refresh Token Usage

After login, you receive both a `token` (JWT, 1 hour TTL) and a `refresh_token` (30 days TTL).

### Refresh an expired JWT
```bash
curl -X POST http://localhost:8080/api/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"YOUR_REFRESH_TOKEN"}'
```
Response:
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refresh_token": "new_refresh_token_here"
}
```

---

## Passkey / WebAuthn

> **Note on `web-auth/webauthn-symfony-bundle`:**
> The official `web-auth/webauthn-symfony-bundle` is **not installed** because it requires the
> `ext-sodium` PHP extension, which is unavailable on PHP 8.5 in this environment.
> A **custom WebAuthn implementation** is used instead — it covers the full
> register/login flow (challenge generation, clientDataJSON verification, counter update,
> credential storage in DB) and is sufficient for the academic context of this project.
> To switch to the official bundle in production: enable `ext-sodium` in `php.ini`, then run
> `composer require web-auth/webauthn-symfony-bundle`.

### Admin login with Passkey
The Passkey UI is integrated directly into the admin login page at `/admin/login` (Passkey tab).
There is no separate `/passkey` page — everything is accessible from the admin auth flow.

### How it works
1. Go to `/admin/login` and click the **Passkey** tab
2. Enter your admin username and click **Register New Passkey** — your browser prompts for biometric/hardware key
3. The credential is stored in the `webauthn_credential` table linked to your user
4. Next time, click **Sign In with Passkey** — verify with biometric → automatic redirect to `/admin`
5. The API endpoints also return a JWT token for programmatic use

### Web session flow (browser login → /admin)
```
POST /passkey/login/options   → get challenge (session-based)
POST /passkey/login/verify    → verify assertion → create Symfony session → redirect /admin
```

### API JWT flow (for API consumers)
```bash
# Step 1: Get registration options
curl -X POST http://localhost:8080/passkey-api/register/options \
  -H "Content-Type: application/json" \
  -d '{"username":"admin"}'

# Step 2: Verify registration (browser handles the authenticator interaction)
curl -X POST http://localhost:8080/passkey-api/register/verify \
  -H "Content-Type: application/json" \
  -d '{"id":"...","clientDataJSON":"...","attestationObject":"..."}'

# Step 3: Get login options
curl -X POST http://localhost:8080/passkey-api/login/options \
  -H "Content-Type: application/json" \
  -d '{"username":"admin"}'

# Step 4: Verify login — returns JWT token
curl -X POST http://localhost:8080/passkey-api/login/verify \
  -H "Content-Type: application/json" \
  -d '{"id":"...","clientDataJSON":"...","authenticatorData":"...","signature":"..."}'
```

### Passkey endpoints summary

| Method | URL | Firewall | Description |
|---|---|---|---|
| POST | `/passkey/login/options` | main (session) | Get challenge for web login |
| POST | `/passkey/login/verify` | main (session) | Verify + create Symfony session |
| POST | `/passkey-api/register/options` | passkey (session) | Get challenge for registration |
| POST | `/passkey-api/register/verify` | passkey (session) | Store credential in DB |
| POST | `/passkey-api/login/options` | passkey (session) | Get challenge for API login |
| POST | `/passkey-api/login/verify` | passkey (session) | Verify + return JWT token |

---

## JWT Key Generation

Keys are stored in `config/jwt/` (gitignored in production).

### Using the included script
```bash
php generate_keys.php
```

### Using OpenSSL (Linux/Mac)
```bash
mkdir -p config/jwt
openssl genrsa -out config/jwt/private.pem -aes256 -passout pass:your_passphrase 2048
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:your_passphrase
```

### Using Symfony CLI (when ext-sodium available)
```bash
php bin/console lexik:jwt:generate-keypair
```

---

## Testing Everything

### 1. Symfony checks (local)
```bash
php bin/console cache:clear
php bin/console lint:yaml config/
php bin/console lint:twig templates/
php bin/console debug:router | grep -E "admin|passkey|api_|login"
```

### 2. Docker — full stack
```bash
# Build and start
docker compose up -d --build

# Run migrations + fixtures
docker exec eventhub_php php bin/console doctrine:migrations:migrate --no-interaction
docker exec eventhub_php php bin/console doctrine:fixtures:load --no-interaction

# Validate schema
docker exec eventhub_php php bin/console doctrine:schema:validate

# Clear cache
docker exec eventhub_php php bin/console cache:clear
```

### 3. API tests (Docker, port 8080)
```bash
# Get JWT token
TOKEN=$(curl -s -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")

# /api/me with token → 200
curl -s http://localhost:8080/api/me -H "Authorization: Bearer $TOKEN"

# /api/me without token → 401
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/api/me

# /api/admin/stats with admin token → 200
curl -s http://localhost:8080/api/admin/stats -H "Authorization: Bearer $TOKEN"

# /api/admin/stats with user token → 403
USER_TOKEN=$(curl -s -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"user","password":"user123"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/api/admin/stats \
  -H "Authorization: Bearer $USER_TOKEN"

# Passkey login/options → always JSON
curl -s -X POST http://localhost:8080/passkey-api/login/options \
  -H "Content-Type: application/json" \
  -d '{"username":"admin"}'

# Passkey with non-admin → 403 JSON
curl -s -X POST http://localhost:8080/passkey-api/login/options \
  -H "Content-Type: application/json" \
  -d '{"username":"user"}'
```

### 4. Browser tests
| URL | Expected |
|---|---|
| http://localhost:8080/ | Home page with hero + events |
| http://localhost:8080/events | Events list with search + pagination |
| http://localhost:8080/events?q=tech | Filtered results |
| http://localhost:8080/events/1 | Event detail |
| http://localhost:8080/events/1/reserve | Reservation form |
| http://localhost:8080/admin | Redirect → `/admin/login` |
| http://localhost:8080/admin/login | Login page (Password + Passkey tabs) |
| http://localhost:8080/login | Redirect 301 → `/admin/login` |
| http://localhost:8081 | phpMyAdmin |

---

## Screenshots

> Add screenshots here after running the application.

| Page | Screenshot |
|---|---|
| Home | `screenshots/home.png` |
| Events List | `screenshots/events.png` |
| Event Detail | `screenshots/event-detail.png` |
| Reservation Form | `screenshots/reservation.png` |
| Admin Login | `screenshots/admin-login.png` |
| Admin Dashboard | `screenshots/admin-dashboard.png` |
| Admin Events | `screenshots/admin-events.png` |
| Admin Reservations | `screenshots/admin-reservations.png` |

---

## Email Confirmation Setup

After a successful reservation, EventHub automatically sends a confirmation email to the attendee containing their name, event title, date, and location.

### How it works

1. User submits the reservation form at `/events/{id}/reserve`
2. After `$em->flush()`, `ReservationMailer::sendConfirmation()` is called
3. A `TemplatedEmail` is built from `templates/emails/reservation_confirmation.html.twig`
4. The email is dispatched via Symfony Mailer using `MAILER_DSN`
5. All steps are logged — check `var/log/dev.log` for `[ReservationMailer]` entries
6. In dev mode, if `MAILER_DSN=null://null`, a **yellow warning flash** is shown on the page

---

### Step 1 — Diagnose your current config

```bash
# Local
php bin/console app:debug-mailer

# Docker
docker exec eventhub_php php bin/console app:debug-mailer
```

Example output with `null://null`:
```
EventHub — Mailer Diagnostic
 ─────────────────────────────────────────────────────
  Symfony environment   dev
  MAILER_DSN            null://null
  Transport type        null (discard)
  Emails will be sent   ❌ NO (null transport)

 [WARNING] MAILER_DSN is null://null — all emails are silently discarded.
           To enable real email delivery:
             1. Create .env.local (never commit it)
             2. Add: MAILER_DSN=smtp://user:pass@sandbox.smtp.mailtrap.io:2525?encryption=tls
             3. Restart the container: docker compose restart php
             4. Test: php bin/console app:test-email your@email.com
```

---

### Step 2 — Configure Mailtrap (recommended for dev)

1. Create a free account at [mailtrap.io](https://mailtrap.io)
2. Go to **Email Testing → Inboxes → your inbox → SMTP Settings**
3. Select **Symfony** from the integrations dropdown — it shows the exact DSN
4. Create `.env.local` in the project root:

```dotenv
# .env.local — NEVER commit this file (it is in .gitignore)
MAILER_DSN=smtp://YOUR_USER:YOUR_PASS@sandbox.smtp.mailtrap.io:2525?encryption=tls
```

5. Restart the PHP container:
```bash
docker compose restart php
```

6. Verify the DSN is loaded:
```bash
docker exec eventhub_php php bin/console app:debug-mailer
# Expected: Transport type → Mailtrap SMTP, Emails will be sent → ✅ YES
```

7. Send a test email:
```bash
docker exec eventhub_php php bin/console app:test-email your@email.com
# Expected: [OK] Email sent successfully to your@email.com
```

8. Check your Mailtrap inbox — the email should appear within seconds.

---

### Step 3 — Configure Gmail SMTP (alternative)

1. Enable **2-Step Verification** on your Google Account
2. Go to **Google Account → Security → App Passwords**
3. Generate an App Password for "Mail"
4. Add to `.env.local`:

```dotenv
MAILER_DSN=smtp://your.email@gmail.com:APP_PASSWORD_HERE@smtp.gmail.com:587?encryption=tls
```

5. Restart and test:
```bash
docker compose restart php
docker exec eventhub_php php bin/console app:test-email your@email.com
```

---

### Step 4 — Inject MAILER_DSN in Docker without rebuilding

You can override `MAILER_DSN` at runtime without touching any file:

```bash
# Option A: pass as shell env var before docker compose
MAILER_DSN="smtp://user:pass@sandbox.smtp.mailtrap.io:2525?encryption=tls" \
  docker compose up -d

# Option B: create a .env file at the project root (Docker reads it automatically)
echo 'MAILER_DSN=smtp://user:pass@sandbox.smtp.mailtrap.io:2525?encryption=tls' >> .env.local

# Option C: restart only the php service after updating .env.local
docker compose restart php

# Verify the var is injected
docker compose exec php printenv MAILER_DSN
```

---

### Step 5 — Test the full reservation flow

```bash
# 1. Make sure MAILER_DSN is set to Mailtrap
docker exec eventhub_php php bin/console app:debug-mailer

# 2. Send a standalone test email
docker exec eventhub_php php bin/console app:test-email your@email.com

# 3. Watch logs while making a reservation in the browser
docker exec eventhub_php tail -f var/log/dev.log | grep -E "Mailer|email|reservation"
```

Expected log lines on success:
```
[info] [EventController] Sending reservation confirmation email {"reservation_id":1,"to":"alice@example.com","event":"Tech Summit 2026"}
[info] [ReservationMailer] Preparing confirmation email {"to":"alice@example.com","name":"Alice","event":"Tech Summit 2026"}
[info] [ReservationMailer] Confirmation email sent successfully {"to":"alice@example.com","reservation_id":1}
[info] [EventController] Confirmation email dispatched OK {"reservation_id":1}
```

---

### Admin: Resend confirmation email

In the admin reservations list (`/admin/reservations`), each row has a **Resend** button that re-sends the confirmation email to the attendee. The action is CSRF-protected.

---

### Security — no secrets in Git

- `.env` contains only `MAILER_DSN=null://null` (safe placeholder)
- Real credentials go in `.env.local` which is in `.gitignore`
- JWT private keys (`config/jwt/*.pem`) are also in `.gitignore`
- Never commit `.env.local` or any file containing real passwords/tokens

### GitGuardian alert remediation

If JWT keys or secrets were previously committed:
```bash
# 1. Regenerate JWT keys immediately
php generate_keys.php

# 2. Remove sensitive files from git history
git filter-repo --path config/jwt/private.pem --invert-paths
git filter-repo --path config/jwt/public.pem --invert-paths

# 3. Force-push to all branches
git push --force --all

# 4. Verify .gitignore covers the files
git check-ignore -v config/jwt/private.pem
# Expected output: .gitignore:XX:/config/jwt/*.pem
```

---

## Security Notes

### Never commit secrets to Git

This project follows the Symfony best practice of keeping secrets out of version control.

| File | Committed? | Purpose |
|---|---|---|
| `.env` | ✅ Yes | Safe defaults and placeholders only — no real passwords |
| `.env.example` | ✅ Yes | Template showing all required variables |
| `.env.local` | ❌ No (gitignored) | Your real secrets — Gmail, JWT passphrase, APP_SECRET |
| `config/jwt/*.pem` | ❌ No (gitignored) | RSA private/public keys |
| `var/` | ❌ No (gitignored) | Cache, logs, sessions |

### Setup for a new developer

```bash
# 1. Copy the example file
cp .env.example .env.local

# 2. Fill in your real values in .env.local
#    - APP_SECRET: php -r "echo bin2hex(random_bytes(16));"
#    - MAILER_DSN: your Gmail App Password or Mailtrap DSN
#    - JWT_PASSPHRASE: any strong random string

# 3. Generate JWT keys
php generate_keys.php

# 4. Never commit .env.local or config/jwt/*.pem
git check-ignore -v .env.local config/jwt/private.pem
```

### If a secret was accidentally committed

```bash
# 1. Immediately revoke the exposed secret:
#    - Gmail App Password: Google Account → Security → App Passwords → Delete
#    - JWT keys: php generate_keys.php (regenerates with new passphrase)
#    - APP_SECRET: generate new one, update .env.local

# 2. Remove from git history (requires git-filter-repo):
pip install git-filter-repo
git filter-repo --path .env --invert-paths
git filter-repo --path config/jwt/private.pem --invert-paths

# 3. Force push all branches
git push --force --all
git push --force --tags

# 4. Verify the file is now ignored
git check-ignore -v .env.local
```

---

## Running Tests

```bash
php bin/phpunit
```

---

## License

Proprietary — Academic project.
