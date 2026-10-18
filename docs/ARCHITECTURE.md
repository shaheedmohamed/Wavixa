# Wavixa Architecture Documentation

## Overview

Wavixa is a professional SaaS platform built with a modern microservices architecture. The system is divided into three main services that communicate via REST APIs.

## System Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                         Client Layer                             │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │           Vue.js Frontend Application                      │ │
│  │  • User Interface                                          │ │
│  │  • Client-side State Management                           │ │
│  │  • API Service Layer                                      │ │
│  └────────────────────────────────────────────────────────────┘ │
└──────────────────────────┬───────────────────────────────────────┘
                           │ HTTP/REST
                           ▼
┌──────────────────────────────────────────────────────────────────┐
│                      API Gateway Layer                           │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │         Laravel Backend (API Server)                       │ │
│  │  • Authentication & Authorization                         │ │
│  │  • Request Routing & Validation                           │ │
│  │  • Business Logic                                         │ │
│  │  • Database Management                                    │ │
│  │  • Service Orchestration                                  │ │
│  │  • Webhook Management                                     │ │
│  └────────────────────────────────────────────────────────────┘ │
└──────────────────────────┬───────────────────────────────────────┘
                           │ HTTP/REST
                           ▼
┌──────────────────────────────────────────────────────────────────┐
│                    Microservices Layer                           │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │      Node.js WhatsApp Integration Service                 │ │
│  │  • WhatsApp Session Management                            │ │
│  │  • Message Processing                                     │ │
│  │  • QR Code Generation                                     │ │
│  │  • Webhook Notifications                                  │ │
│  │  • Session Persistence                                    │ │
│  └────────────────────────────────────────────────────────────┘ │
└──────────────────────────┬───────────────────────────────────────┘
                           │ WhatsApp Protocol
                           ▼
                      WhatsApp API
```

## Service Components

### 1. Frontend (Vue.js)

**Purpose**: User-facing web application

**Key Features**:
- Responsive UI components
- Client-side routing
- State management
- API integration
- Authentication handling
- Real-time updates (WebSocket ready)

**Technology Stack**:
- Vue.js 3
- Vite (Build tool)
- Axios (HTTP client)
- Pinia/Vuex (State management)

**Port**: 5173 (Development)

**Responsibilities**:
- Render user interface
- Handle user interactions
- Manage client-side state
- Communicate with backend API
- Display real-time data

### 2. Backend (Laravel)

**Purpose**: Central API server and business logic orchestrator

**Key Features**:
- RESTful API endpoints
- User authentication & authorization
- Database management
- Service orchestration
- Webhook handling
- Request validation
- Error handling
- Logging & monitoring

**Technology Stack**:
- Laravel 12
- PHP 8.2+
- MySQL/SQLite
- Composer

**Port**: 8000 (Development)

**Modules**:

#### Auth Module
- User registration
- User login/logout
- Token management
- Password reset
- User profile management

#### Dashboard Module
- User statistics
- Activity logs
- Quick actions
- Analytics data

#### Billing Module
- Subscription plans
- Payment processing
- Invoice management
- Usage tracking

#### API Keys Module
- API key generation
- Key management
- Rate limiting
- Usage statistics

#### WhatsApp Module
- Session management
- Message logging
- Webhook handling
- Integration with Node.js service

**Responsibilities**:
- Authenticate users
- Validate requests
- Manage database
- Orchestrate services
- Handle webhooks
- Log activities
- Manage billing

### 3. WhatsApp Service (Node.js)

**Purpose**: WhatsApp integration and message handling

**Key Features**:
- WhatsApp session management
- Message sending/receiving
- QR code generation
- Session persistence
- Webhook notifications
- Health monitoring

**Technology Stack**:
- Node.js 18+
- Express.js
- Baileys (WhatsApp library)
- Axios (HTTP client)

**Port**: 3000 (Development)

**Modules**:

#### WhatsApp Module
- Session creation
- Session management
- Message operations
- Status tracking
- QR code handling

#### Auth Module
- API key validation
- Token management
- Request authentication

**Responsibilities**:
- Manage WhatsApp sessions
- Send/receive messages
- Generate QR codes
- Persist sessions
- Send webhooks to backend
- Monitor health

## Data Flow

### User Authentication Flow

```
1. User submits login credentials (Frontend)
   ↓
2. Frontend sends POST /auth/login (Backend)
   ↓
3. Backend validates credentials
   ↓
4. Backend generates JWT token
   ↓
5. Backend returns token to Frontend
   ↓
6. Frontend stores token in localStorage
   ↓
7. Frontend includes token in subsequent requests
```

### WhatsApp Message Flow

```
1. User requests to send message (Frontend)
   ↓
2. Frontend sends POST /whatsapp/messages (Backend)
   ↓
3. Backend validates request
   ↓
4. Backend creates message record in database
   ↓
5. Backend forwards request to WhatsApp Service
   ↓
6. WhatsApp Service sends message via Baileys
   ↓
7. WhatsApp Service sends webhook to Backend
   ↓
8. Backend updates message status in database
   ↓
9. Frontend receives real-time update
```

### Session Creation Flow

```
1. User initiates WhatsApp session (Frontend)
   ↓
2. Frontend sends POST /whatsapp/sessions (Backend)
   ↓
3. Backend validates user
   ↓
4. Backend creates session record
   ↓
5. Backend forwards to WhatsApp Service
   ↓
6. WhatsApp Service generates QR code
   ↓
7. WhatsApp Service returns QR to Backend
   ↓
8. Backend returns QR to Frontend
   ↓
9. User scans QR code
   ↓
10. WhatsApp Service sends webhook (session connected)
   ↓
11. Backend updates session status
   ↓
12. Frontend receives update
```

## Communication Protocols

### Frontend ↔ Backend
- **Protocol**: HTTP/REST
- **Format**: JSON
- **Authentication**: Bearer Token (JWT)
- **Base URL**: http://localhost:8000/api

### Backend ↔ WhatsApp Service
- **Protocol**: HTTP/REST
- **Format**: JSON
- **Authentication**: Bearer Token (API Key)
- **Base URL**: http://localhost:3000/api

### WhatsApp Service → Backend (Webhooks)
- **Protocol**: HTTP/REST
- **Format**: JSON
- **Authentication**: Bearer Token (API Key)
- **Endpoint**: http://localhost:8000/api/webhooks/whatsapp

## Database Schema

### Core Tables

#### users
```sql
- id (Primary Key)
- name
- email (Unique)
- password (Hashed)
- phone_number
- subscription_plan
- api_key
- created_at
- updated_at
```

#### whatsapp_sessions
```sql
- id (Primary Key)
- user_id (Foreign Key)
- phone_number
- session_id
- status (pending, connected, disconnected)
- qr_code
- metadata (JSON)
- created_at
- updated_at
```

#### whatsapp_messages
```sql
- id (Primary Key)
- session_id (Foreign Key)
- phone_number
- message_text
- message_type (text, image, document)
- direction (incoming, outgoing)
- status (pending, sent, delivered, read, failed)
- metadata (JSON)
- created_at
- updated_at
```

#### api_keys
```sql
- id (Primary Key)
- user_id (Foreign Key)
- key (Unique)
- name
- last_used_at
- created_at
- updated_at
```

#### subscriptions
```sql
- id (Primary Key)
- user_id (Foreign Key)
- plan_id
- status (active, cancelled, expired)
- started_at
- expires_at
- created_at
- updated_at
```

## Security Architecture

### Authentication
- JWT tokens for API authentication
- API keys for service-to-service communication
- Password hashing using bcrypt
- Token expiration and refresh mechanism

### Authorization
- Role-based access control (RBAC)
- Resource-level permissions
- API key scoping
- Rate limiting per user/API key

### Data Protection
- HTTPS/TLS for all communications
- Environment variables for secrets
- Database encryption at rest
- Secure session storage

### CORS Policy
- Whitelist allowed origins
- Restrict HTTP methods
- Validate credentials
- Custom header support

## Scalability Considerations

### Horizontal Scaling
- **Frontend**: Serve via CDN
- **Backend**: Load balancer with multiple instances
- **WhatsApp Service**: Multiple instances for session distribution

### Vertical Scaling
- Increase server resources
- Optimize database queries
- Implement caching strategies
- Use connection pooling

### Caching Strategy
- Redis for session caching
- Database query caching
- API response caching
- Client-side caching

### Database Optimization
- Indexing on frequently queried columns
- Query optimization
- Connection pooling
- Read replicas for scaling

## Monitoring & Logging

### Application Monitoring
- Health check endpoints
- Performance metrics
- Error tracking
- Request logging

### Logging Strategy
- Centralized logging
- Log levels (debug, info, warning, error)
- Structured logging (JSON format)
- Log retention policies

### Alerting
- Service health alerts
- Error rate monitoring
- Performance degradation alerts
- Security event alerts

## Deployment Architecture

### Development Environment
- Local development servers
- SQLite database
- Mock WhatsApp service (optional)

### Staging Environment
- Docker containers
- MySQL database
- Real WhatsApp integration
- SSL certificates

### Production Environment
- Kubernetes clusters
- Managed database services
- CDN for static assets
- Load balancers
- Auto-scaling policies

## API Versioning

- Current version: v1
- URL pattern: `/api/v1/...`
- Backward compatibility maintained
- Deprecation notices provided

## Error Handling

### Error Response Format
```json
{
  "success": false,
  "message": "Error description",
  "error_code": "ERROR_CODE",
  "details": {}
}
```

### HTTP Status Codes
- 200: OK
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 429: Too Many Requests
- 500: Internal Server Error

## Future Enhancements

1. **Microservices Expansion**
   - Billing service
   - Analytics service
   - Notification service

2. **Advanced Features**
   - Real-time messaging (WebSocket)
   - Media handling
   - Message templates
   - Automation workflows

3. **Infrastructure**
   - Kubernetes deployment
   - Service mesh (Istio)
   - Event streaming (Kafka)
   - Distributed tracing

4. **Performance**
   - GraphQL API
   - API caching layer
   - Database sharding
   - Message queue system

---

**Last Updated**: July 3, 2026
**Version**: 1.0.0
