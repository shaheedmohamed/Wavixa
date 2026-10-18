# Wavixa WhatsApp Engine — Deployment Guide

## 1. System Requirements
- Node.js >= 20 (v22 recommended)
- Redis >= 6.2 (for distributed locking & queue clustering)
- MySQL >= 8.0
- Memory: ~30-50 MB baseline per worker (unlike Chromium's ~200 MB per session)

---

## 2. Standalone Systemd Service Setup

Create `/etc/systemd/system/wavixa-engine.service`:

```ini
[Unit]
Description=Wavixa WhatsApp Independent Session Engine
After=network.target redis.service mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/www/wwwroot/wavixa.net/whatsapp-engine
Environment=NODE_ENV=production
Environment=ENGINE_PORT=3005
Environment=ENGINE_HOST=127.0.0.1
ExecStart=/usr/bin/node dist/index.js
Restart=always
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
```

Reload systemd and start:
```bash
sudo systemctl daemon-reload
sudo systemctl enable wavixa-engine
sudo systemctl start wavixa-engine
sudo systemctl status wavixa-engine
```

---

## 3. Reverse Proxy Configuration (Nginx)

To reverse-proxy internal traffic:

```nginx
location /internal/ {
    proxy_pass http://127.0.0.1:3005;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```

---

## 4. Docker Deployment

Run multi-container stack:
```bash
docker compose up -d --build
```
Check container logs:
```bash
docker compose logs -f whatsapp-engine
```
