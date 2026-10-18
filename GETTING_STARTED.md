# Wavixa - Getting Started Guide

Welcome to Wavixa! This guide will help you get up and running quickly.

## 🚀 Quick Start (5 minutes)

### Prerequisites Check

Verify you have the required software:

```bash
# Check Node.js
node --version  # Should be 18.0.0 or higher

# Check PHP
php --version   # Should be 8.2 or higher

# Check Composer
composer --version
```

### Start All Services

#### Option 1: Run in Separate Terminals (Recommended for Development)

**Terminal 1 - Frontend:**
```bash
cd frontend
npm install
npm run dev
# Opens at http://localhost:5173
```

**Terminal 2 - Backend:**
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
# Runs at http://localhost:8000
```

**Terminal 3 - WhatsApp Service:**
```bash
cd services
npm install
cp .env.example .env.development
npm run dev
# Runs at http://localhost:3000
```

#### Option 2: Run All at Once (Requires npm-run-all)

```bash
npm install -g npm-run-all
npm-run-all --parallel "npm run dev --prefix frontend" "php artisan serve --prefix backend" "npm run dev --prefix services"
```

### Access the Application

- **Frontend**: http://localhost:5173
- **Backend API**: http://localhost:8000/api
- **WhatsApp Service**: http://localhost:3000/api

## 📁 Project Structure

```
Wavixa/
├── frontend/          # Vue.js Frontend
├── backend/           # Laravel API
├── services/          # Node.js WhatsApp Service
├── docs/              # Documentation
└── README.md          # Main documentation
```

## 📚 Documentation

### For Setup & Configuration
- **[SETUP.md](./docs/SETUP.md)** - Detailed setup instructions
- **[ENVIRONMENTS.md](./docs/ENVIRONMENTS.md)** - Environment configuration

### For Architecture & Development
- **[ARCHITECTURE.md](./docs/ARCHITECTURE.md)** - System architecture
- **[CONTRIBUTING.md](./docs/CONTRIBUTING.md)** - Contribution guidelines

### For Deployment
- **[DEPLOYMENT.md](./docs/DEPLOYMENT.md)** - Production deployment guide

### Main Documentation
- **[README.md](./README.md)** - Complete project overview

## 🔧 Common Tasks

### Frontend Development

```bash
cd frontend

# Install dependencies
npm install

# Start development server
npm run dev

# Build for production
npm run build

# Run tests
npm run test

# Lint code
npm run lint
```

### Backend Development

```bash
cd backend

# Install dependencies
composer install

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
php artisan serve

# Run tests
php artisan test

# Create a new model
php artisan make:model ModelName -m

# Create a new controller
php artisan make:controller ControllerName
```

### WhatsApp Service Development

```bash
cd services

# Install dependencies
npm install

# Start development server
npm run dev

# Run tests
npm run test

# Lint code
npm run lint
```

## 🔐 Environment Setup

Each service needs environment configuration:

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

### WhatsApp Service (.env.development)
```env
NODE_ENV=development
PORT=3000
HOST=localhost

LARAVEL_API_URL=http://localhost:8000/api
LARAVEL_API_KEY=dev-secret-key

WHATSAPP_SESSION_DIR=./sessions

CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:8000,http://localhost:3000
```

## 🧪 Testing

### Run All Tests

```bash
# Frontend
cd frontend && npm run test

# Backend
cd backend && php artisan test

# Services
cd services && npm run test
```

## 🐛 Troubleshooting

### Port Already in Use

```bash
# Frontend (change port)
npm run dev -- --port 5174

# Backend (change port)
php artisan serve --port 8001

# Services (change port in .env)
# Change PORT=3000 to PORT=3001
```

### Database Issues

```bash
# Reset database (development only)
cd backend
php artisan migrate:fresh
php artisan db:seed
```

### Module Not Found

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

### CORS Errors

Check that CORS_ALLOWED_ORIGINS in .env files includes your frontend URL.

## 📖 Learning Resources

### Frontend
- [Vue.js Documentation](https://vuejs.org/)
- [Vite Documentation](https://vitejs.dev/)
- [Axios Documentation](https://axios-http.com/)

### Backend
- [Laravel Documentation](https://laravel.com/docs)
- [Laravel API Documentation](https://laravel.com/api/)

### Services
- [Express.js Documentation](https://expressjs.com/)
- [Node.js Documentation](https://nodejs.org/docs/)

## 🤝 Contributing

We welcome contributions! Please read [CONTRIBUTING.md](./docs/CONTRIBUTING.md) for guidelines.

## 📞 Support

- **Documentation**: Check the `docs/` folder
- **Issues**: Report bugs on GitHub
- **Email**: support@wavixa.com

## 🎯 Next Steps

1. **Explore the Code**: Familiarize yourself with the project structure
2. **Read Architecture**: Understand how services communicate
3. **Setup Development**: Follow the setup guide for your OS
4. **Run Tests**: Ensure everything is working
5. **Start Contributing**: Pick an issue or feature to work on

## 📋 Checklist for New Developers

- [ ] Cloned the repository
- [ ] Installed all prerequisites
- [ ] Set up environment files
- [ ] Started all three services
- [ ] Accessed the frontend at localhost:5173
- [ ] Tested API endpoints
- [ ] Read the architecture documentation
- [ ] Reviewed the contributing guidelines
- [ ] Set up your IDE/editor
- [ ] Joined the team communication channels

## 🎓 Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    Frontend (Vue.js)                    │
│              http://localhost:5173                      │
└──────────────────────┬──────────────────────────────────┘
                       │ REST API
                       ▼
┌─────────────────────────────────────────────────────────┐
│              Backend (Laravel)                          │
│         http://localhost:8000/api                       │
└──────────────────────┬──────────────────────────────────┘
                       │ REST API
                       ▼
┌─────────────────────────────────────────────────────────┐
│      WhatsApp Service (Node.js)                         │
│         http://localhost:3000/api                       │
└─────────────────────────────────────────────────────────┘
```

## 🔄 Development Workflow

1. **Create Feature Branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make Changes**
   - Write code
   - Write tests
   - Update documentation

3. **Test Locally**
   ```bash
   npm run test      # Frontend
   php artisan test  # Backend
   ```

4. **Commit Changes**
   ```bash
   git commit -m "feat: add new feature"
   ```

5. **Push and Create PR**
   ```bash
   git push origin feature/your-feature-name
   ```

## 📝 Important Files

- **README.md** - Project overview
- **docs/SETUP.md** - Detailed setup guide
- **docs/ARCHITECTURE.md** - System architecture
- **docs/CONTRIBUTING.md** - Contribution guidelines
- **docs/DEPLOYMENT.md** - Production deployment
- **docs/ENVIRONMENTS.md** - Environment configuration

## 🎉 You're Ready!

You now have Wavixa running locally. Start exploring the codebase and contributing!

For detailed information, refer to the documentation in the `docs/` folder.

---

**Last Updated**: July 3, 2026
**Version**: 1.0.0
