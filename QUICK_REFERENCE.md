# Wavixa Quick Reference Guide

## 🚀 Start All Services (Development)

### Terminal 1 - Frontend
```bash
cd frontend
npm install
npm run dev
```
**Access**: http://localhost:5173

### Terminal 2 - Backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```
**Access**: http://localhost:8000/api

### Terminal 3 - WhatsApp Service
```bash
cd services
npm install
cp .env.example .env.development
npm run dev
```
**Access**: http://localhost:3000/api

## 📁 Project Locations

| Service | Path | Port | Tech |
|---------|------|------|------|
| Frontend | `C:\Wavixa\frontend` | 5173 | Vue.js |
| Backend | `C:\Wavixa\backend` | 8000 | Laravel |
| Services | `C:\Wavixa\services` | 3000 | Node.js |

## 🔧 Common Commands

### Frontend
```bash
cd frontend

npm install          # Install dependencies
npm run dev         # Start dev server
npm run build       # Build for production
npm run test        # Run tests
npm run lint        # Lint code
```

### Backend
```bash
cd backend

composer install                    # Install dependencies
php artisan key:generate           # Generate app key
php artisan migrate                # Run migrations
php artisan serve                  # Start dev server
php artisan test                   # Run tests
php artisan make:model ModelName   # Create model
php artisan make:controller Name   # Create controller
```

### Services
```bash
cd services

npm install         # Install dependencies
npm run dev        # Start dev server
npm run test       # Run tests
npm run lint       # Lint code
```

## 📚 Documentation Map

| Document | Purpose |
|----------|---------|
| README.md | Project overview |
| GETTING_STARTED.md | Quick start guide |
| PROJECT_SUMMARY.md | Completion summary |
| docs/SETUP.md | Detailed setup |
| docs/ARCHITECTURE.md | System design |
| docs/ENVIRONMENTS.md | Configuration |
| docs/DEPLOYMENT.md | Production deploy |
| docs/CONTRIBUTING.md | Contribution rules |

## 🔐 Environment Files

### Frontend (.env.development)
```env
VITE_API_BASE_URL=http://localhost:8000/api
VITE_APP_NAME=Wavixa (Dev)
VITE_APP_VERSION=1.0.0
```

### Backend (.env)
```env
APP_NAME=Wavixa
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite

WHATSAPP_SERVICE_URL=http://localhost:3000
WHATSAPP_SERVICE_API_KEY=dev-secret-key

CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:8000
```

### Services (.env.development)
```env
NODE_ENV=development
PORT=3000
HOST=localhost

LARAVEL_API_URL=http://localhost:8000/api
LARAVEL_API_KEY=dev-secret-key

WHATSAPP_SESSION_DIR=./sessions

CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:8000,http://localhost:3000
```

## 🐛 Troubleshooting

### Port Already in Use
```bash
# Frontend (change port)
npm run dev -- --port 5174

# Backend (change port)
php artisan serve --port 8001

# Services (edit .env)
# Change PORT=3000 to PORT=3001
```

### Clear Cache/Dependencies
```bash
# Frontend
cd frontend
rm -rf node_modules package-lock.json
npm install

# Backend
cd backend
composer dump-autoload

# Services
cd services
rm -rf node_modules package-lock.json
npm install
```

### Database Issues
```bash
cd backend
php artisan migrate:fresh  # Reset database
php artisan db:seed        # Seed with data
```

## 📊 API Endpoints

### Health Check
```bash
# Backend
curl http://localhost:8000/api/health

# Services
curl http://localhost:3000/api/health
```

### Authentication (Backend)
```bash
# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'

# Get Current User
curl http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### WhatsApp (Services)
```bash
# Create Session
curl -X POST http://localhost:3000/api/whatsapp/sessions \
  -H "Content-Type: application/json" \
  -d '{"userId":"1","phoneNumber":"+1234567890"}'

# Send Message
curl -X POST http://localhost:3000/api/whatsapp/messages \
  -H "Content-Type: application/json" \
  -d '{"sessionId":"session_1","phoneNumber":"+1234567890","message":"Hello"}'
```

## 🎯 Development Workflow

### Create Feature Branch
```bash
git checkout -b feature/your-feature-name
```

### Make Changes
- Write code
- Write tests
- Update docs

### Test Locally
```bash
npm run test      # Frontend
php artisan test  # Backend
```

### Commit Changes
```bash
git commit -m "feat: add new feature"
```

### Push and Create PR
```bash
git push origin feature/your-feature-name
```

## 📦 Project Structure

```
Wavixa/
├── frontend/              # Vue.js App
│   ├── src/
│   │   ├── components/   # Vue Components
│   │   ├── services/     # API Services
│   │   └── pages/        # Page Components
│   └── package.json
│
├── backend/               # Laravel API
│   ├── app/Modules/      # Feature Modules
│   ├── routes/           # API Routes
│   └── composer.json
│
├── services/              # Node.js Service
│   ├── src/
│   │   ├── routes/       # API Routes
│   │   └── services/     # Business Logic
│   └── package.json
│
└── docs/                  # Documentation
    ├── SETUP.md
    ├── ARCHITECTURE.md
    ├── ENVIRONMENTS.md
    ├── DEPLOYMENT.md
    └── CONTRIBUTING.md
```

## 🔗 Service Communication

```
Frontend (5173)
    ↓ HTTP/REST
Backend (8000)
    ↓ HTTP/REST
Services (3000)
    ↓ WhatsApp Protocol
WhatsApp API
```

## 🎓 Key Files to Review

### Understanding the System
1. `README.md` - Overview
2. `docs/ARCHITECTURE.md` - System design
3. `docs/SETUP.md` - Configuration

### Development
1. `frontend/src/services/api.js` - API client
2. `backend/app/Modules/WhatsApp/Services/WhatsAppServiceClient.php` - Service client
3. `services/src/services/LaravelClient.js` - Backend client

### Deployment
1. `docs/ENVIRONMENTS.md` - Configuration
2. `docs/DEPLOYMENT.md` - Production setup

## 🚨 Important Notes

- ✓ Never commit `.env` files with real credentials
- ✓ Always use `.env.example` as template
- ✓ Keep API keys in environment variables
- ✓ Test locally before pushing
- ✓ Write tests for new features
- ✓ Update documentation

## 📞 Getting Help

1. **Check Documentation**: Look in `docs/` folder
2. **Search Issues**: Check GitHub issues
3. **Read Code**: Examples in existing code
4. **Ask Questions**: Create discussion or issue

## ✅ Pre-Development Checklist

- [ ] All services running
- [ ] Can access frontend at localhost:5173
- [ ] Can access backend API at localhost:8000/api
- [ ] Can access services at localhost:3000/api
- [ ] Read GETTING_STARTED.md
- [ ] Read ARCHITECTURE.md
- [ ] Read CONTRIBUTING.md
- [ ] Set up your IDE
- [ ] Created feature branch

## 🎉 You're Ready!

Everything is set up. Start developing!

---

**Quick Reference v1.0.0**
**Last Updated**: July 3, 2026
