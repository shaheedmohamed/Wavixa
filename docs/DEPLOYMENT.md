# Wavixa Deployment Guide

Complete guide for deploying Wavixa to production environments.

## Table of Contents

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Infrastructure Setup](#infrastructure-setup)
3. [Frontend Deployment](#frontend-deployment)
4. [Backend Deployment](#backend-deployment)
5. [WhatsApp Service Deployment](#whatsapp-service-deployment)
6. [Post-Deployment Verification](#post-deployment-verification)
7. [Monitoring & Maintenance](#monitoring--maintenance)

## Pre-Deployment Checklist

### Code Quality
- [ ] All tests passing
- [ ] Code linting passed
- [ ] No console errors/warnings
- [ ] Security vulnerabilities scanned
- [ ] Dependencies updated
- [ ] Code reviewed

### Configuration
- [ ] .env.production files created
- [ ] All environment variables set
- [ ] Database credentials secured
- [ ] API keys generated
- [ ] CORS origins configured
- [ ] SSL certificates obtained

### Database
- [ ] Database created
- [ ] Migrations tested
- [ ] Backups configured
- [ ] Connection pooling configured
- [ ] Indexes optimized

### Security
- [ ] Secrets not in repository
- [ ] HTTPS enabled
- [ ] CORS properly configured
- [ ] Rate limiting enabled
- [ ] Input validation implemented
- [ ] Security headers configured

### Documentation
- [ ] Deployment steps documented
- [ ] Rollback procedure documented
- [ ] Monitoring setup documented
- [ ] Team trained on deployment

## Infrastructure Setup

### Server Requirements

#### Frontend Server
- **OS**: Linux (Ubuntu 20.04+) or Windows Server
- **RAM**: 2GB minimum
- **Storage**: 10GB
- **CPU**: 2 cores
- **Bandwidth**: 100Mbps

#### Backend Server
- **OS**: Linux (Ubuntu 20.04+)
- **RAM**: 4GB minimum
- **Storage**: 50GB
- **CPU**: 4 cores
- **Bandwidth**: 100Mbps

#### WhatsApp Service Server
- **OS**: Linux (Ubuntu 20.04+)
- **RAM**: 4GB minimum
- **Storage**: 100GB (for sessions)
- **CPU**: 4 cores
- **Bandwidth**: 100Mbps

#### Database Server
- **OS**: Linux (Ubuntu 20.04+)
- **RAM**: 8GB minimum
- **Storage**: 500GB
- **CPU**: 8 cores
- **Backup**: Daily automated backups

### Network Configuration

```
┌─────────────────────────────────────────────────────────┐
│                    Internet                             │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
         ┌─────────────────────────┐
         │   Load Balancer (SSL)   │
         │  (Nginx/HAProxy)        │
         └──────────┬──────────────┘
                    │
        ┌───────────┼───────────┐
        │           │           │
        ▼           ▼           ▼
    ┌────────┐ ┌────────┐ ┌────────┐
    │Frontend│ │Backend │ │WhatsApp│
    │Server 1│ │Server 1│ │Service1│
    └────────┘ └────────┘ └────────┘
        │           │           │
        └───────────┼───────────┘
                    │
                    ▼
            ┌──────────────────┐
            │  Database Server │
            │  (MySQL Master)  │
            └──────────────────┘
                    │
                    ▼
            ┌──────────────────┐
            │  Database Slave  │
            │  (Read Replica)  │
            └──────────────────┘
```

## Frontend Deployment

### Option 1: Traditional Web Server (Nginx)

#### Step 1: Build Frontend

```bash
cd frontend
npm install
npm run build
```

This creates a `dist/` directory with optimized production files.

#### Step 2: Upload to Server

```bash
# From local machine
scp -r frontend/dist/* user@server:/var/www/wavixa/html/
```

#### Step 3: Configure Nginx

Create `/etc/nginx/sites-available/wavixa`:

```nginx
server {
    listen 80;
    server_name wavixa.com www.wavixa.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name wavixa.com www.wavixa.com;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/wavixa.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/wavixa.com/privkey.pem;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Gzip Compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript;
    
    root /var/www/wavixa/html;
    index index.html;
    
    # SPA Routing
    location / {
        try_files $uri $uri/ /index.html;
    }
    
    # Cache Static Assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # API Proxy
    location /api/ {
        proxy_pass http://backend-server:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

#### Step 4: Enable Site

```bash
sudo ln -s /etc/nginx/sites-available/wavixa /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Option 2: Docker Deployment

#### Step 1: Create Dockerfile

```dockerfile
# frontend/Dockerfile
FROM node:18-alpine as builder

WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

FROM nginx:alpine

COPY --from=builder /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
```

#### Step 2: Build and Push Image

```bash
docker build -t wavixa-frontend:latest frontend/
docker tag wavixa-frontend:latest registry.example.com/wavixa-frontend:latest
docker push registry.example.com/wavixa-frontend:latest
```

#### Step 3: Deploy with Docker Compose

```yaml
version: '3.8'
services:
  frontend:
    image: registry.example.com/wavixa-frontend:latest
    ports:
      - "80:80"
    environment:
      - VITE_API_BASE_URL=https://api.wavixa.com/api
```

## Backend Deployment

### Step 1: Prepare Server

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install dependencies
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-redis \
    php8.2-curl php8.2-gd php8.2-mbstring php8.2-xml \
    composer git nginx mysql-client
```

### Step 2: Clone Repository

```bash
cd /var/www
git clone https://github.com/wavixa/backend.git
cd backend
```

### Step 3: Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### Step 4: Configure Environment

```bash
cp .env.example .env.production
# Edit .env.production with production values
```

### Step 5: Generate Key

```bash
php artisan key:generate
```

### Step 6: Run Migrations

```bash
php artisan migrate --force
```

### Step 7: Configure Nginx

Create `/etc/nginx/sites-available/api.wavixa.com`:

```nginx
server {
    listen 443 ssl http2;
    server_name api.wavixa.com;
    
    ssl_certificate /etc/letsencrypt/live/api.wavixa.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.wavixa.com/privkey.pem;
    
    root /var/www/backend/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Deny access to .env
    location ~ /\.env {
        deny all;
    }
}
```

### Step 8: Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/backend
sudo chmod -R 755 /var/www/backend
sudo chmod -R 775 /var/www/backend/storage
sudo chmod -R 775 /var/www/backend/bootstrap/cache
```

### Step 9: Start Services

```bash
sudo systemctl start php8.2-fpm
sudo systemctl start nginx
sudo systemctl enable php8.2-fpm nginx
```

### Step 10: Setup Queue Worker (if needed)

```bash
# Create systemd service for queue worker
sudo nano /etc/systemd/system/wavixa-queue.service
```

```ini
[Unit]
Description=Wavixa Queue Worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/backend
ExecStart=/usr/bin/php artisan queue:work redis --sleep=3 --tries=3
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl start wavixa-queue
sudo systemctl enable wavixa-queue
```

## WhatsApp Service Deployment

### Step 1: Prepare Server

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install PM2
sudo npm install -g pm2
```

### Step 2: Clone Repository

```bash
cd /var/www
git clone https://github.com/wavixa/whatsapp-service.git services
cd services
```

### Step 3: Install Dependencies

```bash
npm install --production
```

### Step 4: Configure Environment

```bash
cp .env.example .env.production
# Edit .env.production with production values
```

### Step 5: Create Sessions Directory

```bash
mkdir -p /var/lib/wavixa/sessions
sudo chown -R nobody:nogroup /var/lib/wavixa/sessions
sudo chmod -R 755 /var/lib/wavixa/sessions
```

### Step 6: Start with PM2

```bash
# Start service
pm2 start src/index.js --name "wavixa-whatsapp" --env production

# Save PM2 configuration
pm2 save

# Create startup script
pm2 startup
```

### Step 7: Configure Nginx Reverse Proxy

```nginx
server {
    listen 443 ssl http2;
    server_name whatsapp.wavixa.com;
    
    ssl_certificate /etc/letsencrypt/live/whatsapp.wavixa.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/whatsapp.wavixa.com/privkey.pem;
    
    location / {
        proxy_pass http://localhost:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # WebSocket support
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

## Post-Deployment Verification

### Health Checks

```bash
# Frontend
curl -I https://wavixa.com

# Backend
curl https://api.wavixa.com/api/health

# WhatsApp Service
curl https://whatsapp.wavixa.com/api/health
```

### Functional Tests

```bash
# Test authentication
curl -X POST https://api.wavixa.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'

# Test WhatsApp service
curl https://whatsapp.wavixa.com/api/health
```

### Performance Tests

```bash
# Load testing with Apache Bench
ab -n 1000 -c 10 https://api.wavixa.com/api/health

# Load testing with wrk
wrk -t12 -c400 -d30s https://api.wavixa.com/api/health
```

## Monitoring & Maintenance

### Setup Monitoring

1. **Application Monitoring**
   - New Relic
   - DataDog
   - Sentry

2. **Infrastructure Monitoring**
   - Prometheus + Grafana
   - CloudWatch (AWS)
   - Azure Monitor (Azure)

3. **Log Aggregation**
   - ELK Stack (Elasticsearch, Logstash, Kibana)
   - Splunk
   - CloudWatch Logs

### Backup Strategy

```bash
# Database backup (daily)
mysqldump -u root -p wavixa_prod > /backups/wavixa_$(date +%Y%m%d).sql

# Upload to cloud storage
aws s3 cp /backups/wavixa_*.sql s3://wavixa-backups/

# Sessions backup
tar -czf /backups/sessions_$(date +%Y%m%d).tar.gz /var/lib/wavixa/sessions/
```

### Update Strategy

```bash
# 1. Create backup
mysqldump -u root -p wavixa_prod > backup.sql

# 2. Pull latest code
git pull origin main

# 3. Install dependencies
composer install --no-dev
npm install --production

# 4. Run migrations
php artisan migrate --force

# 5. Clear cache
php artisan cache:clear
php artisan config:clear

# 6. Restart services
sudo systemctl restart php8.2-fpm nginx
pm2 restart all
```

### Rollback Procedure

```bash
# 1. Restore from backup
mysql -u root -p wavixa_prod < backup.sql

# 2. Checkout previous version
git checkout previous-tag

# 3. Reinstall dependencies
composer install --no-dev
npm install --production

# 4. Restart services
sudo systemctl restart php8.2-fpm nginx
pm2 restart all
```

---

**Last Updated**: July 3, 2026
