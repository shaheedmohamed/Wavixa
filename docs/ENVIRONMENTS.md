# Environment Configuration Guide

This document describes how to configure Wavixa for different environments (Development, Staging, Production).

## Environment Files Overview

Each service has environment configuration files:

```
frontend/
├── .env.example       # Template
├── .env.development   # Development config
└── .env.production    # Production config

backend/
├── .env.example       # Template
├── .env               # Development config
└── .env.production    # Production config

services/
├── .env.example       # Template
├── .env.development   # Development config
└── .env.production    # Production config
```

## Frontend Environment Configuration

### .env.development

```env
# API Configuration
VITE_API_BASE_URL=http://localhost:8000/api

# Application Settings
VITE_APP_NAME=Wavixa (Dev)
VITE_APP_VERSION=1.0.0

# Optional: Development tools
VITE_DEBUG=true
```

### .env.production

```env
# API Configuration
VITE_API_BASE_URL=https://api.wavixa.com/api

# Application Settings
VITE_APP_NAME=Wavixa
VITE_APP_VERSION=1.0.0

# Production settings
VITE_DEBUG=false
```

### Frontend Environment Variables

| Variable | Development | Production | Description |
|----------|-------------|-----------|-------------|
| VITE_API_BASE_URL | http://localhost:8000/api | https://api.wavixa.com/api | Backend API endpoint |
| VITE_APP_NAME | Wavixa (Dev) | Wavixa | Application name |
| VITE_APP_VERSION | 1.0.0 | 1.0.0 | App version |
| VITE_DEBUG | true | false | Debug mode |

## Backend Environment Configuration

### .env (Development)

```env
# Application
APP_NAME=Wavixa
APP_ENV=local
APP_KEY=base64:YOUR_KEY_HERE
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=wavixa
# DB_USERNAME=root
# DB_PASSWORD=

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Cache
CACHE_STORE=database

# Queue
QUEUE_CONNECTION=database

# Mail
MAIL_MAILER=log
MAIL_FROM_ADDRESS="dev@wavixa.local"

# WhatsApp Service
WHATSAPP_SERVICE_URL=http://localhost:3000
WHATSAPP_SERVICE_API_KEY=dev-secret-key

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:8000

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

### .env.production

```env
# Application
APP_NAME=Wavixa
APP_ENV=production
APP_KEY=base64:YOUR_PRODUCTION_KEY_HERE
APP_DEBUG=false
APP_URL=https://wavixa.com

# Database
DB_CONNECTION=mysql
DB_HOST=db.wavixa.com
DB_PORT=3306
DB_DATABASE=wavixa_prod
DB_USERNAME=wavixa_user
DB_PASSWORD=STRONG_PASSWORD_HERE

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE=true
SESSION_HTTP_ONLY=true

# Cache
CACHE_STORE=redis
REDIS_HOST=redis.wavixa.com
REDIS_PORT=6379
REDIS_PASSWORD=REDIS_PASSWORD_HERE

# Queue
QUEUE_CONNECTION=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=YOUR_USERNAME
MAIL_PASSWORD=YOUR_PASSWORD
MAIL_FROM_ADDRESS="noreply@wavixa.com"
MAIL_FROM_NAME="Wavixa"

# WhatsApp Service
WHATSAPP_SERVICE_URL=https://whatsapp.wavixa.com
WHATSAPP_SERVICE_API_KEY=PRODUCTION_API_KEY_HERE

# CORS
CORS_ALLOWED_ORIGINS=https://wavixa.com,https://app.wavixa.com,https://api.wavixa.com

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=warning

# Security
SANCTUM_STATEFUL_DOMAINS=wavixa.com,app.wavixa.com
```

### Backend Environment Variables

| Variable | Development | Production | Description |
|----------|-------------|-----------|-------------|
| APP_ENV | local | production | Environment type |
| APP_DEBUG | true | false | Debug mode |
| DB_CONNECTION | sqlite | mysql | Database type |
| CACHE_STORE | database | redis | Cache driver |
| QUEUE_CONNECTION | database | redis | Queue driver |
| MAIL_MAILER | log | smtp | Mail driver |
| LOG_LEVEL | debug | warning | Log level |
| SESSION_SECURE | false | true | HTTPS only |

## WhatsApp Service Environment Configuration

### .env.development

```env
# Server
NODE_ENV=development
PORT=3000
HOST=localhost

# API
API_BASE_URL=http://localhost:3000/api

# Laravel Backend
LARAVEL_API_URL=http://localhost:8000/api
LARAVEL_API_KEY=dev-secret-key

# WhatsApp
WHATSAPP_SESSION_DIR=./sessions
WHATSAPP_WEBHOOK_URL=http://localhost:8000/api/webhooks/whatsapp

# Logging
LOG_LEVEL=debug

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:8000,http://localhost:3000
```

### .env.production

```env
# Server
NODE_ENV=production
PORT=3000
HOST=0.0.0.0

# API
API_BASE_URL=https://whatsapp.wavixa.com/api

# Laravel Backend
LARAVEL_API_URL=https://api.wavixa.com/api
LARAVEL_API_KEY=PRODUCTION_API_KEY_HERE

# WhatsApp
WHATSAPP_SESSION_DIR=/var/lib/wavixa/sessions
WHATSAPP_WEBHOOK_URL=https://api.wavixa.com/api/webhooks/whatsapp

# Logging
LOG_LEVEL=info

# CORS
CORS_ALLOWED_ORIGINS=https://wavixa.com,https://api.wavixa.com,https://whatsapp.wavixa.com
```

### WhatsApp Service Environment Variables

| Variable | Development | Production | Description |
|----------|-------------|-----------|-------------|
| NODE_ENV | development | production | Environment type |
| PORT | 3000 | 3000 | Server port |
| HOST | localhost | 0.0.0.0 | Server host |
| LOG_LEVEL | debug | info | Log level |
| WHATSAPP_SESSION_DIR | ./sessions | /var/lib/wavixa/sessions | Session storage path |

## Environment-Specific Configurations

### Development Environment

**Characteristics**:
- Local development on single machine
- SQLite database
- Debug mode enabled
- Verbose logging
- CORS allows localhost
- Mock/test data

**Setup**:
```bash
# Frontend
cd frontend
cp .env.example .env.development
npm run dev

# Backend
cd backend
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# WhatsApp Service
cd services
cp .env.example .env.development
npm run dev
```

### Staging Environment

**Characteristics**:
- Separate staging server
- MySQL database
- Debug mode disabled
- Moderate logging
- CORS allows staging domains
- Real data (sanitized)

**Setup**:
```bash
# Configure .env files for staging
# Deploy to staging server
# Run migrations
# Seed with staging data
```

### Production Environment

**Characteristics**:
- Production server(s)
- MySQL database with replication
- Debug mode disabled
- Minimal logging
- CORS restricted to production domains
- Real data
- SSL/TLS enabled
- Load balancing
- Auto-scaling

**Setup**:
```bash
# Configure .env.production files
# Deploy via CI/CD pipeline
# Run migrations
# Enable monitoring and alerts
# Setup backups
```

## Switching Environments

### Frontend

```bash
# Development
npm run dev

# Production build
npm run build
npm run preview
```

### Backend

```bash
# Development
php artisan serve

# Production (via web server like Nginx)
# Ensure .env.production is used
```

### WhatsApp Service

```bash
# Development
npm run dev

# Production
NODE_ENV=production npm start
```

## Sensitive Data Management

### Never Commit to Git

These should NEVER be committed:
- `.env` files with real credentials
- API keys
- Database passwords
- JWT secrets
- Private keys

### Safe Practices

1. **Use .env.example**
   - Keep template with placeholder values
   - Document all required variables

2. **Use Environment Variables**
   - Store secrets in environment
   - Use CI/CD secrets management
   - Use cloud provider secret managers

3. **Rotate Credentials**
   - Change API keys regularly
   - Rotate database passwords
   - Update JWT secrets

4. **Access Control**
   - Limit who can access .env files
   - Use role-based access
   - Audit access logs

## Database Configuration

### Development (SQLite)

```env
DB_CONNECTION=sqlite
# Database file: database/database.sqlite
```

### Production (MySQL)

```env
DB_CONNECTION=mysql
DB_HOST=db.wavixa.com
DB_PORT=3306
DB_DATABASE=wavixa_prod
DB_USERNAME=wavixa_user
DB_PASSWORD=STRONG_PASSWORD
```

### Database Migrations

```bash
# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Fresh migration (development only)
php artisan migrate:fresh

# Seed database
php artisan db:seed
```

## Caching Configuration

### Development

```env
CACHE_STORE=database
```

### Production

```env
CACHE_STORE=redis
REDIS_HOST=redis.wavixa.com
REDIS_PORT=6379
REDIS_PASSWORD=PASSWORD
```

## Queue Configuration

### Development

```env
QUEUE_CONNECTION=database
```

### Production

```env
QUEUE_CONNECTION=redis
REDIS_HOST=redis.wavixa.com
REDIS_PORT=6379
```

## Logging Configuration

### Development

```env
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

### Production

```env
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

## Email Configuration

### Development

```env
MAIL_MAILER=log
# Emails logged to storage/logs/
```

### Production

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=username
MAIL_PASSWORD=password
```

## Verification Checklist

Before deploying to production:

- [ ] All environment variables configured
- [ ] Database migrations run successfully
- [ ] API keys generated and stored securely
- [ ] CORS origins configured correctly
- [ ] SSL/TLS certificates installed
- [ ] Logging configured appropriately
- [ ] Backups configured
- [ ] Monitoring and alerts enabled
- [ ] Security headers configured
- [ ] Rate limiting enabled
- [ ] CSRF protection enabled
- [ ] Secrets not committed to git

---

**Last Updated**: July 3, 2026
