# Wavixa Setup Guide

Complete setup instructions for the Wavixa SaaS platform.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Frontend Setup](#frontend-setup)
3. [Backend Setup](#backend-setup)
4. [WhatsApp Service Setup](#whatsapp-service-setup)
5. [Running All Services](#running-all-services)
6. [Verification](#verification)
7. [Troubleshooting](#troubleshooting)

## Prerequisites

### System Requirements

- **Operating System**: Windows, macOS, or Linux
- **RAM**: Minimum 4GB (8GB recommended)
- **Disk Space**: Minimum 5GB

### Required Software

#### Node.js & npm
- Download from: https://nodejs.org/ (LTS version recommended)
- Verify installation:
  ```bash
  node --version
  npm --version
  ```

#### PHP & Composer
- Download PHP from: https://www.php.net/downloads
- Download Composer from: https://getcomposer.org/download/
- Verify installation:
  ```bash
  php --version
  composer --version
  ```

#### Git (Optional but recommended)
- Download from: https://git-scm.com/

## Frontend Setup

### Step 1: Navigate to Frontend Directory

```bash
cd frontend
```

### Step 2: Install Dependencies

```bash
npm install
```

This will install all required npm packages listed in `package.json`.

### Step 3: Configure Environment

Copy the example environment file:

```bash
cp .env.example .env.development
```

Update `.env.development` with your settings:

```env
VITE_API_BASE_URL=http://localhost:8000/api
VITE_APP_NAME=Wavixa (Dev)
VITE_APP_VERSION=1.0.0
```

### Step 4: Start Development Server

```bash
npm run dev
```

The frontend will be available at: **http://localhost:5173**

### Frontend Project Structure

```
frontend/
├── src/
│   ├── components/
│   │   ├── auth/          # Login, Register, Password Reset
│   │   ├── dashboard/     # Dashboard components
│   │   └── common/        # Header, Footer, Navigation
│   ├── pages/             # Page-level components
│   ├── services/
│   │   ├── api.js        # Base API client
│   │   └── auth.js       # Authentication service
│   ├── stores/            # State management
│   ├── utils/             # Helper functions
│   └── assets/            # Images, styles, fonts
├── public/                # Static files
├── package.json
├── vite.config.js
└── .env.development
```

## Backend Setup

### Step 1: Navigate to Backend Directory

```bash
cd backend
```

### Step 2: Install Dependencies

```bash
composer install
```

This will install all required PHP packages.

### Step 3: Configure Environment

Copy the example environment file:

```bash
cp .env.example .env
```

Update `.env` with your settings:

```env
APP_NAME=Wavixa
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# Or use MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=wavixa
# DB_USERNAME=root
# DB_PASSWORD=

WHATSAPP_SERVICE_URL=http://localhost:3000
WHATSAPP_SERVICE_API_KEY=your-secret-api-key

CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:8000
```

### Step 4: Generate Application Key

```bash
php artisan key:generate
```

### Step 5: Create Database

For SQLite (default):
```bash
touch database/database.sqlite
```

For MySQL, create the database manually:
```sql
CREATE DATABASE wavixa;
```

### Step 6: Run Migrations

```bash
php artisan migrate
```

This will create all necessary database tables.

### Step 7: Seed Database (Optional)

```bash
php artisan db:seed
```

This will populate the database with sample data.

### Step 8: Start Development Server

```bash
php artisan serve
```

The backend API will be available at: **http://localhost:8000/api**

### Backend Project Structure

```
backend/
├── app/
│   ├── Modules/
│   │   ├── Auth/
│   │   │   ├── Controllers/
│   │   │   ├── Models/
│   │   │   ├── Requests/
│   │   │   └── Services/
│   │   ├── Dashboard/
│   │   ├── Billing/
│   │   ├── ApiKeys/
│   │   └── WhatsApp/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   └── Models/
├── routes/
│   ├── api.php
│   └── web.php
├── database/
│   ├── migrations/
│   └── seeders/
├── config/
├── storage/
├── tests/
├── .env
├── composer.json
└── artisan
```

## WhatsApp Service Setup

### Step 1: Navigate to Services Directory

```bash
cd services
```

### Step 2: Install Dependencies

```bash
npm install
```

This will install all required npm packages.

### Step 3: Configure Environment

Copy the example environment file:

```bash
cp .env.example .env.development
```

Update `.env.development` with your settings:

```env
NODE_ENV=development
PORT=3000
HOST=localhost

LARAVEL_API_URL=http://localhost:8000/api
LARAVEL_API_KEY=your-secret-api-key

WHATSAPP_SESSION_DIR=./sessions
WHATSAPP_WEBHOOK_URL=http://localhost:8000/api/webhooks/whatsapp

LOG_LEVEL=debug

CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:8000,http://localhost:3000
```

### Step 4: Create Sessions Directory

```bash
mkdir sessions
```

### Step 5: Start Development Server

```bash
npm run dev
```

The WhatsApp service will be available at: **http://localhost:3000/api**

### Service Project Structure

```
services/
├── src/
│   ├── modules/
│   │   ├── whatsapp/
│   │   │   ├── controllers/
│   │   │   ├── services/
│   │   │   └── models/
│   │   └── auth/
│   ├── routes/
│   │   ├── whatsapp.js
│   │   ├── auth.js
│   │   └── health.js
│   ├── middleware/
│   ├── services/
│   │   └── LaravelClient.js
│   ├── config/
│   ├── utils/
│   └── index.js
├── sessions/           # WhatsApp session files
├── package.json
└── .env.development
```

## Running All Services

### Option 1: Separate Terminal Windows

Open three terminal windows and run each service:

**Terminal 1 - Frontend:**
```bash
cd frontend
npm run dev
```

**Terminal 2 - Backend:**
```bash
cd backend
php artisan serve
```

**Terminal 3 - WhatsApp Service:**
```bash
cd services
npm run dev
```

### Option 2: Using npm-run-all (Recommended)

From the root directory:

```bash
npm install -g npm-run-all
npm-run-all --parallel "npm run dev --prefix frontend" "php artisan serve --prefix backend" "npm run dev --prefix services"
```

### Option 3: Using Docker Compose (Optional)

If you have Docker installed:

```bash
docker-compose up
```

## Verification

### Check Frontend

1. Open browser: http://localhost:5173
2. You should see the Wavixa login page

### Check Backend

1. Open browser or use curl: http://localhost:8000/api/health
2. You should see a JSON response

### Check WhatsApp Service

1. Open browser or use curl: http://localhost:3000/api/health
2. You should see a JSON response

### Test API Communication

```bash
# From backend to WhatsApp service
curl http://localhost:3000/api/health

# From frontend to backend
curl http://localhost:8000/api/health
```

## Troubleshooting

### Frontend Issues

**Problem**: Port 5173 already in use
```bash
# Use a different port
npm run dev -- --port 5174
```

**Problem**: Module not found errors
```bash
# Clear node_modules and reinstall
rm -rf node_modules package-lock.json
npm install
```

### Backend Issues

**Problem**: "Class not found" errors
```bash
# Regenerate autoloader
composer dump-autoload
```

**Problem**: Database connection error
```bash
# Check .env file settings
# Ensure database exists
# Run migrations again
php artisan migrate:fresh
```

**Problem**: Port 8000 already in use
```bash
# Use a different port
php artisan serve --port=8001
```

### WhatsApp Service Issues

**Problem**: Cannot connect to Laravel backend
```bash
# Check LARAVEL_API_URL in .env
# Ensure Laravel service is running
# Check firewall settings
```

**Problem**: Sessions directory permission error
```bash
# Create sessions directory with proper permissions
mkdir -p sessions
chmod 755 sessions
```

### General Issues

**Problem**: CORS errors
```bash
# Check CORS_ALLOWED_ORIGINS in .env files
# Ensure all services are running on correct ports
# Clear browser cache
```

**Problem**: API key authentication fails
```bash
# Verify LARAVEL_API_KEY matches in both .env files
# Check Authorization header format
```

## Next Steps

1. Review the [Architecture Documentation](./ARCHITECTURE.md)
2. Read the [API Documentation](./API.md)
3. Check the [Contributing Guidelines](./CONTRIBUTING.md)
4. Start developing features!

---

**Last Updated**: July 3, 2026
