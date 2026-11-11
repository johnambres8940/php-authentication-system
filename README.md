# Auth System (PHP + MySQL + MongoDB + Redis)

Flow: **register.html → login.html → profile.html** (all via jQuery AJAX, no page-reload form posts).

| Data | Store |
|---|---|
| username, email, password hash | MySQL (PDO prepared statements, `password_hash`) |
| name, age, dob, contact, bio, interests | MongoDB (`profiles` collection) |
| login sessions (token, 7-day sliding TTL) + login rate limit | Redis |

## Run locally
```bash
# 1. Requirements: PHP 8.1+ with pdo_mysql and mongodb extensions, Composer
composer install

# 2. Start MySQL, MongoDB, Redis (easiest: Docker)
docker compose up -d

# 3. Config
cp .env.example .env     # already created; edit if your credentials differ

# 4. Serve
php -S localhost:8000
# open http://localhost:8000
```
Tables are auto-created on first request (schema.sql is provided too).

## Deploy
Any host with PHP 8.1+ (Apache/Nginx + PHP-FPM) and reachable MySQL/MongoDB/Redis
(e.g. MongoDB Atlas, Upstash/Redis Cloud, any MySQL host). Upload the folder, run
`composer install --no-dev`, create `.env` with production values, serve over HTTPS.
Make sure `.env` is not web-accessible (`.htaccess` blocks it on Apache).

## Security notes
- Prepared statements everywhere; inputs validated and sanitized server-side
- Passwords hashed with `password_hash` (bcrypt); secrets in `.env`
- Random 256-bit session tokens stored in Redis, sent in `X-Auth-Token` header
- Login brute-force limit (10 failures / 15 min) tracked in Redis
- Frontend renders user data with `.text()` to prevent XSS
