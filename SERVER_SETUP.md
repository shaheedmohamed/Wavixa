# Wavixa — New Server Setup

Full deployment reference for this app. Clone this repo to `/var/www/Wavixa`.
The CRM lives in a **separate repo** (`shaheedmohamed/WavixaCrm` → `/var/www/WavixaCRM`)
and the Academy in `shaheedmohamed/WavixaAcademy` → `/var/www/WavixaAcademy`.
Deploy all three — the `wavixa.net` nginx vhost below routes into the CRM paths.

## Production topology

| Piece | Source dir | Runs as | Port / path |
|---|---|---|---|
| Main Laravel API | `backend/` | php8.5-fpm via nginx | `wavixa.net/api/*` |
| Main SPA | `frontend/` build → `backend/public/` | static | `wavixa.net/*` |
| WhatsApp engine (live) | `whatsapp-engine/` | `wavixa-engine.service` | `127.0.0.1:3005` |
| whatsapp-web.js service | `whatsapp-service/` | `wavixa-whatsapp.service` | `127.0.0.1:3002` |
| Call bridge | `whatsapp-callbridge/` | `wavixa-callbridge.service` | `127.0.0.1:3999` |
| Telegram service | `telegram-service/` | `wavixa-telegram.service` | `127.0.0.1:3003` |
| Queue worker | `backend/` | `wavixa-queue.service` | — |
| Reverb WS | `backend/` | `wavixa-reverb.service` | `127.0.0.1:8090` |
| `services/` | legacy, **no systemd unit** — optional | — | (3000 conflicts w/ other apps) |

Domains: `wavixa.net`, `www.wavixa.net` (this repo) — `academy.wavixa.net` (Academy repo).

## 1. System packages (Ubuntu 24.04)

```bash
apt update && apt install -y nginx mysql-server redis-server ffmpeg \
  certbot python3-certbot-nginx git unzip curl

# PHP 8.5 via ondrej PPA
add-apt-repository -y ppa:ondrej/php && apt update
apt install -y php8.5-fpm php8.5-cli php8.5-mysql php8.5-redis php8.5-curl \
  php8.5-gd php8.5-mbstring php8.5-xml php8.5-zip php8.5-bcmath php8.5-intl

# Node 24
curl -fsSL https://deb.nodesource.com/setup_24.x | bash - && apt install -y nodejs

# Composer 2
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Chromium deps for Puppeteer (whatsapp-service + callbridge)
apt install -y libnss3 libatk1.0-0t64 libatk-bridge2.0-0t64 libcups2t64 \
  libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 libxrandr2 \
  libgbm1 libasound2t64 libpango-1.0-0 libcairo2 libxfixes3
```

## 2. Deploy code

```bash
cd /var/www && git clone git@github.com:shaheedmohamed/Wavixa.git Wavixa
```

## 3. MySQL

```sql
CREATE DATABASE wavixa_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE wavixa_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- CRM repo
CREATE USER 'wavixa_user'@'127.0.0.1' IDENTIFIED BY 'wavixa_pass_2026';
CREATE USER 'wavixa_user'@'localhost' IDENTIFIED BY 'wavixa_pass_2026';
GRANT ALL ON wavixa_db.* TO 'wavixa_user'@'127.0.0.1';
GRANT ALL ON wavixa_crm.* TO 'wavixa_user'@'127.0.0.1';
GRANT ALL ON wavixa_db.* TO 'wavixa_user'@'localhost';
GRANT ALL ON wavixa_crm.* TO 'wavixa_user'@'localhost';
FLUSH PRIVILEGES;
```

## 4. Laravel backend

```bash
cd /var/www/Wavixa/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env            # .env.example contains the REAL production values
php artisan migrate --force
php artisan db:seed --class=PlanSeeder --force
php artisan storage:link
php artisan config:cache
chown -R www-data:www-data storage bootstrap/cache public
```

If restoring the production data (a full dump ships in the repo root):

```bash
zcat /var/www/Wavixa/database_dump.sql.gz | mysql -u wavixa_user -p wavixa_db
```

(Run the import INSTEAD of `migrate --force` for a data-preserving deploy.)

## 5. Frontend

```bash
cd /var/www/Wavixa/frontend
npm ci
cp .env.example .env.production   # real values already inside
npm run build
cp -r dist/* /var/www/Wavixa/backend/public/
chown -R www-data:www-data /var/www/Wavixa/backend/public
```

## 6. Node services

```bash
cd /var/www/Wavixa/whatsapp-engine   && npm ci && npm run build   # produces dist/
cd /var/www/Wavixa/whatsapp-service  && npm ci && cp .env.example .env
cd /var/www/Wavixa/telegram-service  && npm ci && cp .env.example .env
# callbridge has no package.json — it uses whatsapp-service's node_modules
ln -sfn /var/www/Wavixa/whatsapp-service/node_modules /var/www/Wavixa/whatsapp-callbridge/node_modules
mkdir -p /var/www/Wavixa/whatsapp-callbridge/out
```

Puppeteer Chrome for whatsapp-service (as www-data):

```bash
cd /var/www/Wavixa/whatsapp-service
sudo -u www-data npx puppeteer browsers install chrome \
  --path /var/www/Wavixa/whatsapp-service/.puppeteer_cache
```

Call-bridge secret files (NOT in git — recreate with these exact values):

```bash
cd /var/www/Wavixa/whatsapp-callbridge
echo -n 'cb4ac337790bd7d77df1c685ffa06afbc1591bce7bf36cfb3b1bfd42807a49cf' > .bridge-secret
echo -n 'wavixa-local-api-key-2026' > .service-key
echo -n 'edc6383e1547a1f219dc4dbb3fdb50276fb4d6bf1682c81bcfc61caf779747c4' > .wssecret
```

## 7. systemd units

Write these to `/etc/systemd/system/`, then `systemctl daemon-reload`.

### wavixa-engine.service

```ini
[Unit]
Description=Wavixa WhatsApp Independent Session Engine
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/Wavixa/whatsapp-engine
Environment=NODE_ENV=production
Environment=ENGINE_PORT=3005
Environment=ENGINE_HOST=127.0.0.1
Environment=ENGINE_WORKER_ID=worker-1
Environment=ENGINE_DEBUG_STANZAS=0
Environment=LOG_LEVEL=debug
Environment=ENGINE_INTERNAL_SECRET=wavixa-local-api-key-2026
Environment=WHATSAPP_SERVICE_API_KEY=wavixa-local-api-key-2026
Environment=LARAVEL_API_URL=https://wavixa.net/api
# Separated CRM backend (own DB wavixa_crm) gets a copy of every webhook.
Environment=LARAVEL_API_URLS_EXTRA=http://127.0.0.1:8088/api
ExecStart=/usr/bin/node dist/index.js
Restart=always
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
```

### wavixa-whatsapp.service

```ini
[Unit]
Description=Wavixa WhatsApp Service
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/Wavixa/whatsapp-service
Environment=NODE_ENV=production
Environment=WHATSAPP_SERVICE_API_KEY=wavixa-local-api-key-2026
ExecStart=/usr/bin/node index.js
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### wavixa-callbridge.service

```ini
[Unit]
Description=Wavixa WhatsApp Call Bridge (Puppeteer WhatsApp Web voice calls)
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/var/www/Wavixa/whatsapp-callbridge
Environment=NODE_PATH=/var/www/Wavixa/whatsapp-service/node_modules
ExecStart=/usr/bin/node bridge.js
Restart=always
RestartSec=5
StandardOutput=append:/var/www/Wavixa/whatsapp-callbridge/out/bridge.log
StandardError=append:/var/www/Wavixa/whatsapp-callbridge/out/bridge.log

[Install]
WantedBy=multi-user.target
```

### wavixa-queue.service

```ini
[Unit]
Description=Wavixa Laravel Queue Worker
After=network.target mysql.service
Wants=mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/Wavixa/backend
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=60
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### wavixa-reverb.service

```ini
[Unit]
Description=Wavixa Laravel Reverb WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/Wavixa/backend
ExecStart=/usr/bin/php /var/www/Wavixa/backend/artisan reverb:start --host=127.0.0.1 --port=8090
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

### wavixa-telegram.service

```ini
[Unit]
Description=Wavixa Telegram MTProto Service
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/Wavixa/telegram-service
Environment=NODE_ENV=production
ExecStart=/usr/bin/node index.js
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```bash
systemctl enable --now wavixa-engine wavixa-whatsapp wavixa-callbridge \
  wavixa-queue wavixa-reverb wavixa-telegram
```

## 8. Crontab (root)

```cron
* * * * * cd /var/www/Wavixa/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

## 9. Nginx

`/etc/nginx/sites-available/wavixa.net` (+ symlink into `sites-enabled/`).
This single vhost also routes the CRM app (`/api/crm*`, `/crm/*`) — deploy
the WavixaCrm repo to `/var/www/WavixaCRM` first, or those routes 502.

```nginx
server {
    server_name wavixa.net www.wavixa.net;

    root /var/www/Wavixa/backend/public;
    index index.html;

    client_max_body_size 50M;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ ^/(r|l)/ {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    # === Separated CRM app (/var/www/WavixaCRM) — own Laravel + own DB ===
    location = /api/crm {
        include fastcgi.conf;
        fastcgi_param SCRIPT_FILENAME /var/www/WavixaCRM/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/WavixaCRM/backend/public;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }
    location ^~ /api/crm/ {
        include fastcgi.conf;
        fastcgi_param SCRIPT_FILENAME /var/www/WavixaCRM/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/WavixaCRM/backend/public;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }

    # CRM SPA — its own build, assets namespaced under /crm-static/.
    location = /crm {
        return 301 /crm/login;
    }
    location ^~ /crm/ {
        alias /var/www/WavixaCRM/frontend/dist/;
        try_files $uri $uri/ /crm/index.html;
    }
    location ^~ /crm-static/ {
        alias /var/www/WavixaCRM/frontend/dist/;
        try_files $uri =404;
    }

    # CRM media: CRM backend's public dir first, then main app's dir
    # (engine MediaManager writes inbound media there).
    location ^~ /storage/crm_media/ {
        root /var/www/WavixaCRM/backend/public;
        try_files $uri @crm_media_main;
    }
    location @crm_media_main {
        root /var/www/Wavixa/backend/public;
    }

    location ~ ^/api(/|$) {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    # Reverb WebSocket (Pusher protocol) — /app/ WS proxied;
    # /apps/ publish API stays internal on 127.0.0.1:8090.
    location /reverb/app/ {
        rewrite ^/reverb/(.*)$ /$1 break;
        proxy_pass http://127.0.0.1:8090;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
        proxy_no_cache 1;
        proxy_cache_bypass 1;
        add_header Cache-Control "no-store" always;
    }

    # WhatsApp call bridge audio WebSocket (CRM voice calls)
    location = /call-ws {
        rewrite ^/call-ws$ /ws break;
        proxy_pass http://127.0.0.1:3999;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
        proxy_no_cache 1;
        proxy_cache_bypass 1;
        add_header Cache-Control "no-store" always;
    }

    location /engine-api/ {
        rewrite ^/engine-api/(.*)$ /$1 break;
        proxy_pass http://127.0.0.1:3005;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_no_cache 1;
        proxy_cache_bypass 1;
        add_header Cache-Control "no-store" always;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    listen 80;
    listen [::]:80;
}
```

SSL via certbot (after DNS points here):

```bash
certbot --nginx -d wavixa.net -d www.wavixa.net
```

## 10. Internal CRM vhost

`/etc/nginx/sites-available/wavixa-crm-internal` — the engine posts a copy of
every webhook here (see `LARAVEL_API_URLS_EXTRA`):

```nginx
server {
    listen 127.0.0.1:8088;
    server_name _;

    root /var/www/WavixaCRM/backend/public;
    index index.php;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

```bash
nginx -t && systemctl reload nginx
```

## 11. Things NOT in git — copy manually or redo

- **WhatsApp sessions**: `whatsapp-service/.wwebjs_auth/`,
  `whatsapp-callbridge/.auth/`, `whatsapp-engine/data/creds/`.
  Either rsync them from the old server (preserves linked devices) or
  re-pair via QR/pairing code from the admin dashboard.
- **Uploaded media**: `backend/public/storage/` (~3.4 GB `crm_media`) —
  rsync from old server or accept empty.
- **Telegram sessions**: `telegram-service/.telegram_auth/`.

## 12. External config to remember

- Meta app dashboard webhook: `https://wavixa.net/api/meta-test/webhook`,
  verify token `wavixa-meta-test-verify-token-2026`, field `messages`.
- If the domain ever changes, update `APP_URL`, `APP_FRONTEND_URL`,
  `CORS_ALLOWED_ORIGINS`, `META_TEST_REDIRECT_URI`, `PAYPAL_RETURN_URL`,
  `PAYPAL_CANCEL_URL`, `LARAVEL_API_URL` in every service `.env` / unit,
  plus nginx `server_name` + certbot.

## 13. Verify

```bash
systemctl status wavixa-engine wavixa-whatsapp wavixa-queue wavixa-reverb \
  wavixa-callbridge wavixa-telegram php8.5-fpm nginx mysql redis-server
curl -s https://wavixa.net/api/health || curl -s https://wavixa.net/api/
curl -s http://127.0.0.1:3005/health 2>/dev/null | head
```
