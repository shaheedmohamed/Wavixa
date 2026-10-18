# Wavixa - Professional SaaS Platform

A comprehensive WhatsApp integration SaaS platform with a modular microservices architecture.

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend (Vue.js)                        │
│              http://localhost:5173                          │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              Laravel API Backend                            │
│         http://localhost:8000/api                           │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ • Authentication & Authorization                     │  │
│  │ • Dashboard Management                               │  │
│  │ • Billing & Subscriptions                            │  │
│  │ • API Keys Management                                │  │
│  │ • WhatsApp Session Management                        │  │
│  │ • Message Logging & Analytics                        │  │
│  └──────────────────────────────────────────────────────┘  │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│         Node.js WhatsApp Service                            │
│         http://localhost:3000/api                           │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ • WhatsApp Session Management (Baileys)              │  │
│  │ • Message Sending & Receiving                        │  │
│  │ • QR Code Generation                                 │  │
│  │ • Webhook Integration                                │  │
│  │ • Session Persistence                                │  │
│  └──────────────────────────────────────────────────────┘  │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
                  WhatsApp API
```

## 📁 Project Structure

```
Wavixa/
├── frontend/                    # Vue.js Frontend Application
│   ├── src/
│   │   ├── components/         # Vue Components
│   │   │   ├── auth/          # Authentication Components
│   │   │   ├── dashboard/     # Dashboard Components
│   │   │   └── common/        # Shared Components
│   │   ├── pages/             # Page Components
│   │   ├── services/          # API Services
│   │   │   ├── api.js        # Base API Client
│   │   │   └── auth.js       # Auth Service
│   │   ├── stores/            # State Management (Pinia/Vuex)
│   │   ├── utils/             # Utility Functions
│   │   └── assets/            # Static Assets
│   ├── .env.example           # Environment Variables Template
│   ├── .env.development       # Development Environment
│   ├── .env.production        # Production Environment
│   ├── package.json           # Dependencies
│   └── vite.config.js         # Vite Configuration
│
├── backend/                     # Laravel API Backend
│   ├── app/
│   │   ├── Modules/           # Modular Structure
│   │   │   ├── Auth/          # Authentication Module
│   │   │   ├── Dashboard/     # Dashboard Module
│   │   │   ├── Billing/       # Billing Module
│   │   │   ├── ApiKeys/       # API Keys Module
│   │   │   └── WhatsApp/      # WhatsApp Integration Module
│   │   │       ├── Controllers/
│   │   │       ├── Models/
│   │   │       ├── Services/
│   │   │       └── Requests/
│   │   └── Http/
│   │       └── Middleware/    # Custom Middleware
│   ├── routes/
│   │   ├── api.php            # API Routes
│   │   └── web.php            # Web Routes
│   ├── database/
│   │   ├── migrations/        # Database Migrations
│   │   └── seeders/           # Database Seeders
│   ├── .env.example           # Environment Variables Template
│   ├── .env.development       # Development Environment
│   ├── .env.production        # Production Environment
│   ├── composer.json          # PHP Dependencies
│   └── artisan                # Laravel CLI
│
├── services/                    # Node.js WhatsApp Service
│   ├── src/
│   │   ├── modules/
│   │   │   ├── whatsapp/      # WhatsApp Module
│   │   │   │   ├── controllers/
│   │   │   │   ├── services/
│   │   │   │   └── models/
│   │   │   └── auth/          # Authentication Module
│   │   ├── routes/            # API Routes
│   │   ├── middleware/        # Express Middleware
│   │   ├── services/          # Business Logic
│   │   │   └── LaravelClient.js
│   │   ├── config/            # Configuration Files
│   │   ├── utils/             # Utility Functions
│   │   └── index.js           # Entry Point
│   ├── .env.example           # Environment Variables Template
│   ├── .env.development       # Development Environment
│   ├── .env.production        # Production Environment
│   ├── package.json           # Node Dependencies
│   └── nodemon.json           # Nodemon Configuration
│
├── docs/                        # Documentation
│   ├── ARCHITECTURE.md         # Architecture Documentation
│   ├── API.md                  # API Documentation
│   ├── SETUP.md                # Setup Guide
│   ├── DEPLOYMENT.md           # Deployment Guide
│   └── CONTRIBUTING.md         # Contributing Guidelines
│
├── .github/
│   └── workflows/              # GitHub Actions CI/CD
│
├── .gitignore                  # Git Ignore Rules
├── README.md                   # This File
└── docker-compose.yml          # Docker Compose (Optional)
```

## 🚀 Quick Start

### Prerequisites

- **Node.js** >= 18.0.0
- **PHP** >= 8.2
- **Composer** >= 2.0
- **npm** or **yarn**

### Installation

#### 1. Frontend Setup

```bash
cd frontend
npm install
cp .env.example .env.development
npm run dev
```

The frontend will be available at `http://localhost:5173`

#### 2. Backend Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

The backend API will be available at `http://localhost:8000/api`

#### 3. WhatsApp Service Setup

```bash
cd services
npm install
cp .env.example .env.development
npm run dev
```

The WhatsApp service will be available at `http://localhost:3000/api`

## 🔧 Configuration

### Environment Variables

Each service has its own environment configuration:

- **Frontend**: `.env.development`, `.env.production`
- **Backend**: `.env` (development), `.env.production`
- **Services**: `.env.development`, `.env.production`

Copy `.env.example` to the appropriate environment file and update the values.

### API Communication

The services communicate via REST APIs:

- **Frontend → Backend**: All requests go through the Laravel API
- **Backend → WhatsApp Service**: Laravel communicates with Node.js service for WhatsApp operations
- **WhatsApp Service → Backend**: Webhook notifications sent back to Laravel

## 📚 API Documentation

### Frontend API Service

The frontend uses a centralized API service (`src/services/api.js`) for all HTTP requests.

```javascript
import api from '@/services/api';

// GET request
const data = await api.get('/endpoint');

// POST request
const response = await api.post('/endpoint', { data });

// PUT request
const updated = await api.put('/endpoint', { data });

// DELETE request
await api.delete('/endpoint');
```

### Backend API Endpoints

Base URL: `http://localhost:8000/api`

#### Authentication
- `POST /auth/register` - Register new user
- `POST /auth/login` - Login user
- `POST /auth/logout` - Logout user
- `GET /auth/me` - Get current user
- `POST /auth/refresh` - Refresh token

#### Dashboard
- `GET /dashboard` - Get dashboard data
- `GET /dashboard/stats` - Get statistics

#### Billing
- `GET /billing/plans` - Get subscription plans
- `POST /billing/subscribe` - Subscribe to plan
- `GET /billing/invoices` - Get invoices

#### API Keys
- `GET /api-keys` - List API keys
- `POST /api-keys` - Create API key
- `DELETE /api-keys/:id` - Delete API key

#### WhatsApp
- `POST /whatsapp/sessions` - Create WhatsApp session
- `GET /whatsapp/sessions/:id` - Get session status
- `POST /whatsapp/messages` - Send message
- `GET /whatsapp/messages/:id` - Get message status

### WhatsApp Service API

Base URL: `http://localhost:3000/api`

#### Health
- `GET /health` - Service health check

#### Authentication
- `POST /auth/validate` - Validate API key
- `POST /auth/refresh` - Refresh token

#### WhatsApp
- `POST /whatsapp/sessions` - Create session
- `GET /whatsapp/sessions/:sessionId` - Get session status
- `POST /whatsapp/messages` - Send message
- `GET /whatsapp/messages/:messageId` - Get message status
- `DELETE /whatsapp/sessions/:sessionId` - Disconnect session

## 🔐 Security

- All API endpoints require authentication
- API keys are stored securely in the database
- CORS is configured to allow only trusted origins
- Environment variables are used for sensitive data
- HTTPS is enforced in production

## 📦 Deployment

### Docker Deployment (Optional)

A `docker-compose.yml` file can be created for containerized deployment.

### Manual Deployment

See `docs/DEPLOYMENT.md` for detailed deployment instructions.

## 🧪 Testing

### Frontend Tests
```bash
cd frontend
npm run test
```

### Backend Tests
```bash
cd backend
php artisan test
```

### Service Tests
```bash
cd services
npm run test
```

## 📝 Development Guidelines

- Follow the modular architecture pattern
- Keep services loosely coupled
- Use environment variables for configuration
- Document API endpoints
- Write tests for critical functionality
- Use meaningful commit messages

## 🤝 Contributing

See `docs/CONTRIBUTING.md` for contribution guidelines.

## 📄 License

This project is licensed under the MIT License.

## 📞 Support

For support, visit: https://wavixa.com/support

---

**Last Updated**: July 3, 2026
**Version**: 1.0.0
