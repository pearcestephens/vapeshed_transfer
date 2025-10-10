# System Architecture
**Vape Shed Transfer Engine - Complete Technical Architecture**

Version: 2.0.0  
Last Updated: October 9, 2025  
Status: Production Ready

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [System Components](#system-components)
3. [Data Architecture](#data-architecture)
4. [Application Architecture](#application-architecture)
5. [Integration Architecture](#integration-architecture)
6. [Security Architecture](#security-architecture)
7. [Infrastructure Architecture](#infrastructure-architecture)
8. [Deployment Architecture](#deployment-architecture)
9. [Technology Stack](#technology-stack)
10. [Design Patterns](#design-patterns)
11. [Scalability](#scalability)
12. [Monitoring & Observability](#monitoring--observability)

---

## Architecture Overview

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                            CLIENT LAYER                                  │
├─────────────────────────────────────────────────────────────────────────┤
│  Web Browser    │    Mobile Browser    │    API Clients    │    Webhooks │
└────────┬────────┴──────────┬───────────┴─────────┬─────────┴─────────┬──┘
         │                   │                     │                   │
         └───────────────────┴─────────────────────┴───────────────────┘
                                      │
┌─────────────────────────────────────▼───────────────────────────────────┐
│                            CDN LAYER                                     │
│                         (Cloudflare CDN)                                 │
└─────────────────────────────────────┬───────────────────────────────────┘
                                      │
┌─────────────────────────────────────▼───────────────────────────────────┐
│                         WEB SERVER LAYER                                 │
│                    Nginx (Reverse Proxy + Load Balancer)                │
│                    - SSL/TLS Termination                                 │
│                    - Rate Limiting                                       │
│                    - Static Asset Serving                                │
│                    - Request Routing                                     │
└─────────────────────────────────────┬───────────────────────────────────┘
                                      │
         ┌────────────────────────────┼────────────────────────────┐
         │                            │                            │
┌────────▼────────┐         ┌─────────▼────────┐        ┌─────────▼────────┐
│  APPLICATION    │         │   APPLICATION    │        │   APPLICATION    │
│    SERVER 1     │         │    SERVER 2      │        │    SERVER 3      │
│  (PHP-FPM 8.2)  │         │  (PHP-FPM 8.2)   │        │  (PHP-FPM 8.2)   │
└────────┬────────┘         └─────────┬────────┘        └─────────┬────────┘
         │                            │                            │
         └────────────────────────────┼────────────────────────────┘
                                      │
         ┌────────────────────────────┼────────────────────────────┐
         │                            │                            │
┌────────▼────────┐         ┌─────────▼────────┐        ┌─────────▼────────┐
│  CACHE LAYER    │         │   DATABASE       │        │  QUEUE WORKERS   │
│  Redis Cluster  │         │   MariaDB        │        │  Background Jobs │
│  - Sessions     │         │   - Primary      │        │  - Email Queue   │
│  - App Cache    │         │   - Replicas     │        │  - Vend Sync     │
│  - Rate Limits  │         │   - Backups      │        │  - PDF Gen       │
└─────────────────┘         └──────────────────┘        └──────────────────┘
                                      │
         ┌────────────────────────────┼────────────────────────────┐
         │                            │                            │
┌────────▼────────┐         ┌─────────▼────────┐        ┌─────────▼────────┐
│  FILE STORAGE   │         │   INTEGRATIONS   │        │   MONITORING     │
│  - Uploads      │         │   - Vend API     │        │   - Logs         │
│  - Backups      │         │   - SendGrid     │        │   - Metrics      │
│  - Logs         │         │   - Twilio       │        │   - Alerts       │
└─────────────────┘         └──────────────────┘        └──────────────────┘
```

### Architecture Principles

**1. Separation of Concerns**
```
- Presentation Layer (Views)
- Application Layer (Controllers)
- Business Logic Layer (Services)
- Data Access Layer (Models)
- Infrastructure Layer (Database, Cache, Queue)
```

**2. Modularity**
```
- Independent modules
- Clear interfaces
- Minimal coupling
- Maximum cohesion
```

**3. Scalability**
```
- Horizontal scaling (add more servers)
- Vertical scaling (upgrade resources)
- Database replication
- Cache layers
- Queue workers
```

**4. Security**
```
- Defense in depth
- Least privilege
- Input validation
- Output encoding
- Encryption (at rest & in transit)
```

**5. Reliability**
```
- Fault tolerance
- Graceful degradation
- Circuit breakers
- Health checks
- Automated recovery
```

### System Context

**Actors:**
- Store Managers (create, manage transfers)
- Warehouse Staff (pick, pack transfers)
- Administrators (system configuration)
- System (automated processes)

**External Systems:**
- Vend POS (product, store, inventory data)
- SendGrid (email notifications)
- Twilio (SMS notifications)
- Cloudflare (CDN, DDoS protection)

**Data Flows:**
```
User → Web Browser → Nginx → PHP-FPM → Application Logic → Database
                                     ↓
                              Cache (Redis)
                                     ↓
                              Queue (Background Jobs)
                                     ↓
                              External APIs (Vend, Email)
```

---

## System Components

### Frontend Components

**Web Interface:**
```
components/
├── dashboard/
│   ├── DashboardController.js      # Main dashboard logic
│   ├── MetricsWidget.js            # Real-time metrics display
│   ├── ActivityFeed.js             # Recent activity stream
│   └── QuickActions.js             # Quick action buttons
├── transfers/
│   ├── TransferList.js             # Transfer listing with filters
│   ├── TransferForm.js             # Create/edit transfer form
│   ├── TransferDetails.js          # Transfer detail view
│   └── StatusTracker.js            # Visual status tracking
├── inventory/
│   ├── StockLevels.js              # Inventory overview
│   ├── ProductSearch.js            # Product search/selection
│   └── AlertsList.js               # Low stock alerts
├── analytics/
│   ├── ReportBuilder.js            # Custom report builder
│   ├── ChartRenderer.js            # Chart visualization
│   └── DataExporter.js             # Export to CSV/PDF
├── settings/
│   ├── UserProfile.js              # User profile management
│   ├── NotificationSettings.js     # Notification preferences
│   └── SystemConfig.js             # System configuration
└── shared/
    ├── Navbar.js                   # Navigation bar
    ├── Sidebar.js                  # Sidebar navigation
    ├── Modal.js                    # Modal dialogs
    ├── Toast.js                    # Toast notifications
    ├── DataTable.js                # Reusable data table
    └── FormValidation.js           # Form validation helpers
```

**CSS Architecture:**
```
assets/css/
├── base/
│   ├── reset.css                   # CSS reset
│   ├── typography.css              # Typography styles
│   └── variables.css               # CSS custom properties
├── components/
│   ├── buttons.css                 # Button styles
│   ├── forms.css                   # Form styles
│   ├── modals.css                  # Modal styles
│   ├── tables.css                  # Table styles
│   └── cards.css                   # Card styles
├── layouts/
│   ├── header.css                  # Header layout
│   ├── sidebar.css                 # Sidebar layout
│   └── footer.css                  # Footer layout
├── pages/
│   ├── dashboard.css               # Dashboard-specific styles
│   ├── transfers.css               # Transfer pages styles
│   └── settings.css                # Settings pages styles
└── utilities/
    ├── spacing.css                 # Margin/padding utilities
    ├── colors.css                  # Color utilities
    └── responsive.css              # Responsive utilities
```

### Backend Components

**MVC Architecture:**
```
app/
├── Controllers/                    # Request handlers
│   ├── BaseController.php          # Base controller with common methods
│   ├── DashboardController.php     # Dashboard endpoints
│   ├── TransferController.php      # Transfer CRUD operations
│   ├── InventoryController.php     # Inventory management
│   ├── AnalyticsController.php     # Analytics & reporting
│   ├── SettingsController.php      # Configuration management
│   └── Api/                        # API controllers
│       ├── AuthController.php      # Authentication API
│       ├── TransferApiController.php
│       └── WebhookController.php
├── Models/                         # Data models
│   ├── Transfer.php                # Transfer model
│   ├── TransferItem.php            # Transfer item model
│   ├── Product.php                 # Product model
│   ├── Store.php                   # Store model
│   └── User.php                    # User model
├── Services/                       # Business logic
│   ├── TransferService.php         # Transfer business logic
│   ├── InventoryService.php        # Inventory calculations
│   ├── VendSyncService.php         # Vend integration
│   ├── NotificationService.php     # Notification handling
│   └── Analytics/
│       ├── TransferAnalytics.php   # Transfer analytics
│       ├── PredictiveAnalytics.php # ML predictions
│       └── PerformanceMetrics.php  # Performance tracking
├── Middleware/                     # Request/response filters
│   ├── AuthenticationMiddleware.php
│   ├── AuthorizationMiddleware.php
│   ├── RateLimitMiddleware.php
│   ├── CSRFMiddleware.php
│   └── LoggingMiddleware.php
├── Core/                           # Core framework
│   ├── Application.php             # Application bootstrap
│   ├── Router.php                  # Request routing
│   ├── Database.php                # Database connection
│   ├── Cache.php                   # Cache abstraction
│   ├── Queue.php                   # Job queue
│   └── Logger.php                  # Logging system
└── Infrastructure/                 # Infrastructure services
    ├── Email/
    │   ├── EmailService.php        # Email sending
    │   └── Templates/              # Email templates
    ├── SMS/
    │   └── SMSService.php          # SMS sending
    └── Storage/
        └── FileStorage.php         # File storage
```

### Database Components

**Schema Organization:**
```sql
-- Core Tables
transfers              # Main transfer records
transfer_items         # Transfer line items
products               # Product catalog
stores                 # Store/outlet information
users                  # User accounts

-- Inventory Management
inventory_snapshots    # Historical inventory levels
stock_movements        # Stock movement tracking
reorder_points         # Automatic reorder triggers

-- Analytics
transfer_analytics     # Aggregated transfer metrics
prediction_cache       # ML prediction results
performance_metrics    # System performance data

-- System
system_config          # Key-value configuration
audit_logs             # Security audit trail
notifications          # Notification queue
queue_jobs             # Background job queue
failed_jobs            # Failed job tracking

-- Security
user_sessions          # Active user sessions
login_attempts         # Login attempt tracking
password_history       # Password change history
mfa_tokens             # MFA authentication tokens
```

---

## Data Architecture

### Entity Relationship Diagram

```
┌──────────────────────┐
│       USERS          │
│ ─────────────────────│
│ PK: user_id          │
│     username         │
│     email            │
│     password_hash    │
│ FK: role_id          │
└──────────┬───────────┘
           │
           │ 1:N (created_by)
           │
┌──────────▼───────────────┐         ┌─────────────────────┐
│      TRANSFERS           │         │      STORES         │
│ ─────────────────────────│         │ ────────────────────│
│ PK: transfer_id          │ N:1     │ PK: store_id        │
│ FK: from_store_id        ├────────►│     name            │
│ FK: to_store_id          ├────────►│     address         │
│     reference            │         │ FK: vend_outlet_id  │
│     status               │         └─────────────────────┘
│     created_at           │
│ FK: created_by           │
└──────────┬───────────────┘
           │
           │ 1:N
           │
┌──────────▼───────────────┐         ┌─────────────────────┐
│    TRANSFER_ITEMS        │         │     PRODUCTS        │
│ ─────────────────────────│         │ ────────────────────│
│ PK: item_id              │ N:1     │ PK: product_id      │
│ FK: transfer_id          │         │     name            │
│ FK: product_id           ├────────►│     sku             │
│     quantity             │         │     barcode         │
│     unit_price           │         │     category        │
└──────────────────────────┘         │ FK: vend_product_id │
                                     └─────────────────────┘
```

### Data Flow Diagram

**Transfer Creation Flow:**
```
┌──────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────┐
│   User   │────►│  Controller  │────►│   Service    │────►│ Database │
└──────────┘     └──────────────┘     └──────────────┘     └──────────┘
                        │                     │                   │
                        │                     │                   │
                        ▼                     ▼                   ▼
                 ┌──────────────┐     ┌──────────────┐     ┌──────────┐
                 │ Validation   │     │  Business    │     │  Vend    │
                 │              │     │    Logic     │     │  Sync    │
                 └──────────────┘     └──────────────┘     └──────────┘
                        │                     │                   │
                        │                     │                   │
                        ▼                     ▼                   ▼
                 ┌──────────────┐     ┌──────────────┐     ┌──────────┐
                 │   Response   │◄────│ Notification │◄────│  Queue   │
                 └──────────────┘     └──────────────┘     └──────────┘
```

### Data Storage

**Relational Data (MariaDB):**
- Transactional data (transfers, items)
- User accounts and permissions
- Configuration
- Audit logs

**Cache Data (Redis):**
- Session data
- Application cache
- Rate limiting counters
- Real-time data

**File Storage:**
- User uploads (CSV imports)
- Generated reports (PDF)
- Backup files
- Log files

### Data Lifecycle

**Hot Data (Active):**
```
Location: Database + Cache
Retention: Current + last 30 days
Access: Frequent (multiple times/day)
Performance: Optimized indexes, query cache
```

**Warm Data (Recent):**
```
Location: Database
Retention: 31-365 days
Access: Occasional (weekly)
Performance: Standard indexes
```

**Cold Data (Historical):**
```
Location: Archive database
Retention: > 365 days
Access: Rare (monthly reports)
Performance: Compressed, partitioned
```

---

## Application Architecture

### Layered Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER                        │
│  - HTML/CSS/JavaScript                                       │
│  - REST API endpoints                                        │
│  - WebSocket connections                                     │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                    APPLICATION LAYER                         │
│  - Controllers (request handling)                            │
│  - Middleware (auth, validation, rate limiting)              │
│  - View rendering                                            │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                   BUSINESS LOGIC LAYER                       │
│  - Services (business rules)                                 │
│  - Domain models                                             │
│  - Workflows                                                 │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                   DATA ACCESS LAYER                          │
│  - Models (ORM)                                              │
│  - Repositories                                              │
│  - Query builders                                            │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                  INFRASTRUCTURE LAYER                        │
│  - Database connections                                      │
│  - Cache connections                                         │
│  - External API clients                                      │
│  - File storage                                              │
└─────────────────────────────────────────────────────────────┘
```

### Request Lifecycle

**1. Request Reception:**
```
HTTP Request → Nginx → PHP-FPM → index.php
```

**2. Routing:**
```php
// routes/web.php
Router::get('/transfers/{id}', 'TransferController@show');

// Core/Router.php resolves route to controller
$router->resolve($request);
```

**3. Middleware Pipeline:**
```php
// Middleware stack
$middlewares = [
    AuthenticationMiddleware::class,
    AuthorizationMiddleware::class,
    CSRFMiddleware::class,
    RateLimitMiddleware::class,
    LoggingMiddleware::class,
];

// Process request through middleware
$response = $middlewarePipeline->handle($request, $controller);
```

**4. Controller Action:**
```php
// app/Controllers/TransferController.php
public function show(int $id)
{
    // Authorize
    if (!$this->auth->can('transfers.view')) {
        return $this->forbidden();
    }
    
    // Get data via service
    $transfer = $this->transferService->getById($id);
    
    // Return response
    return $this->view('transfers/show', ['transfer' => $transfer]);
}
```

**5. Service Layer:**
```php
// app/Services/TransferService.php
public function getById(int $id)
{
    // Check cache first
    $cacheKey = "transfer:$id";
    if ($cached = Cache::get($cacheKey)) {
        return $cached;
    }
    
    // Query database
    $transfer = Transfer::with(['items', 'fromStore', 'toStore'])->find($id);
    
    // Cache result
    Cache::put($cacheKey, $transfer, 600);
    
    return $transfer;
}
```

**6. Data Access:**
```php
// app/Models/Transfer.php
class Transfer extends Model
{
    public function items()
    {
        return $this->hasMany(TransferItem::class, 'transfer_id');
    }
    
    public function fromStore()
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }
}
```

**7. View Rendering:**
```php
// resources/views/transfers/show.php
<div class="transfer-details">
    <h1>Transfer <?= htmlspecialchars($transfer->reference) ?></h1>
    <div class="status-badge status-<?= $transfer->status ?>">
        <?= ucfirst($transfer->status) ?>
    </div>
    <!-- ... -->
</div>
```

**8. Response:**
```
View (HTML) → PHP-FPM → Nginx → HTTP Response → Client
```

### API Architecture

**RESTful API Design:**
```
GET    /api/transfers              # List transfers
POST   /api/transfers              # Create transfer
GET    /api/transfers/{id}         # Get transfer details
PATCH  /api/transfers/{id}         # Update transfer
DELETE /api/transfers/{id}         # Delete transfer

GET    /api/transfers/{id}/items   # List transfer items
POST   /api/transfers/{id}/items   # Add item to transfer

PATCH  /api/transfers/{id}/status  # Update status
POST   /api/transfers/{id}/approve # Approve transfer
POST   /api/transfers/{id}/cancel  # Cancel transfer
```

**API Response Format:**
```json
{
  "success": true,
  "data": {
    "transfer_id": 12345,
    "reference": "TR-20251009-001",
    "status": "approved"
  },
  "meta": {
    "timestamp": "2025-10-09T15:30:00Z",
    "version": "1.0"
  }
}
```

**Error Response Format:**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": {
      "from_store_id": ["The from store id field is required."]
    }
  },
  "meta": {
    "timestamp": "2025-10-09T15:30:00Z",
    "version": "1.0"
  }
}
```

---

## Integration Architecture

### External System Integration

**Vend POS Integration:**
```
┌─────────────────────┐
│  Transfer Engine    │
└──────────┬──────────┘
           │
           │ HTTPS REST API
           │
┌──────────▼──────────┐
│   Vend API v2.0     │
│  api.vend.com       │
└──────────┬──────────┘
           │
   ┌───────┼───────┐
   │       │       │
┌──▼──┐ ┌──▼──┐ ┌──▼──┐
│Prods│ │Outlt│ │Cons │
└─────┘ └─────┘ └─────┘
```

**Integration Flow:**
```
1. Sync Products (Every 5 minutes)
   - GET /api/2.0/products
   - Store in local database
   - Update inventory levels

2. Sync Outlets (Every 30 minutes)
   - GET /api/2.0/outlets
   - Update store information

3. Create Consignment (On transfer approval)
   - POST /api/2.0/consignments
   - Link transfer to consignment
   - Track status in Vend
```

**Circuit Breaker Pattern:**
```php
class VendCircuitBreaker
{
    private const FAILURE_THRESHOLD = 5;
    private const TIMEOUT_DURATION = 60; // seconds
    private string $state = 'closed'; // closed, open, half-open
    
    public function call(callable $apiCall)
    {
        if ($this->state === 'open') {
            if ($this->shouldAttemptReset()) {
                $this->state = 'half-open';
            } else {
                throw new ServiceUnavailableException('Vend API circuit breaker is open');
            }
        }
        
        try {
            $result = $apiCall();
            
            if ($this->state === 'half-open') {
                $this->state = 'closed';
                $this->resetFailureCount();
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();
            
            if ($this->getFailureCount() >= self::FAILURE_THRESHOLD) {
                $this->state = 'open';
                $this->setOpenTimestamp();
            }
            
            throw $e;
        }
    }
}
```

### Message Queue Architecture

**Queue System:**
```
┌──────────────────┐
│  Application     │
└────────┬─────────┘
         │
         │ Push Job
         │
┌────────▼─────────┐     ┌──────────────────┐
│  Job Queue       │────►│  Queue Workers   │
│  (Database)      │     │  (Background)    │
└──────────────────┘     └────────┬─────────┘
                                  │
                     ┌────────────┼────────────┐
                     │            │            │
              ┌──────▼───┐ ┌──────▼───┐ ┌──────▼───┐
              │ Worker 1 │ │ Worker 2 │ │ Worker 3 │
              └──────────┘ └──────────┘ └──────────┘
```

**Job Types:**
```php
// app/Jobs/SendEmailJob.php
class SendEmailJob implements Job
{
    public function handle()
    {
        Mail::to($this->recipient)->send(new TransferNotification($this->transfer));
    }
}

// app/Jobs/SyncToVendJob.php
class SyncToVendJob implements Job
{
    public function handle()
    {
        $this->vendService->syncTransfer($this->transferId);
    }
}

// app/Jobs/GeneratePDFJob.php
class GeneratePDFJob implements Job
{
    public function handle()
    {
        $pdf = $this->pdfGenerator->generate($this->transfer);
        Storage::put("pdfs/transfer_{$this->transfer->id}.pdf", $pdf);
    }
}
```

---

## Security Architecture

### Security Layers

**Layer 1: Network Security**
```
- Firewall (UFW)
- Fail2Ban (brute force protection)
- DDoS protection (Cloudflare)
- Rate limiting (Nginx)
```

**Layer 2: Transport Security**
```
- TLS 1.2+ (HTTPS only)
- HSTS (HTTP Strict Transport Security)
- Certificate pinning (mobile app)
```

**Layer 3: Application Security**
```
- Input validation
- Output encoding
- CSRF protection
- XSS prevention
- SQL injection prevention
- Content Security Policy
```

**Layer 4: Authentication & Authorization**
```
- Strong password policy
- Multi-factor authentication
- Session management
- Role-based access control (RBAC)
```

**Layer 5: Data Protection**
```
- Encryption at rest (AES-256)
- Encryption in transit (TLS)
- Sensitive data masking
- Secure deletion
```

**Layer 6: Monitoring & Auditing**
```
- Security logging
- Audit trails
- Intrusion detection
- Anomaly detection
```

### Authentication Flow

```
┌──────────┐
│  User    │
└─────┬────┘
      │
      │ 1. Submit credentials
      │
┌─────▼────────┐
│ Login Form   │
└─────┬────────┘
      │
      │ 2. POST /login
      │
┌─────▼──────────────┐
│ Auth Controller    │
│ - Validate input   │
│ - Check rate limit │
└─────┬──────────────┘
      │
      │ 3. Verify password
      │
┌─────▼──────────────┐
│ Auth Service       │
│ - Hash comparison  │
│ - Password policy  │
└─────┬──────────────┘
      │
      │ 4. Check MFA
      │
┌─────▼──────────────┐
│ MFA Service        │
│ - TOTP validation  │
│ - Backup codes     │
└─────┬──────────────┘
      │
      │ 5. Create session
      │
┌─────▼──────────────┐
│ Session Manager    │
│ - Generate ID      │
│ - Store in Redis   │
│ - Set cookie       │
└─────┬──────────────┘
      │
      │ 6. Redirect to dashboard
      │
┌─────▼────────┐
│ Dashboard    │
└──────────────┘
```

---

## Infrastructure Architecture

### Production Infrastructure

```
┌───────────────────────────────────────────────────────────────┐
│                        CLOUDFLARE CDN                          │
│  - Global CDN                                                  │
│  - DDoS Protection                                             │
│  - SSL/TLS                                                     │
│  - Web Application Firewall (WAF)                              │
└────────────────────────┬──────────────────────────────────────┘
                         │
┌────────────────────────▼──────────────────────────────────────┐
│                    CLOUDWAYS HOSTING                           │
│                   (Managed Cloud Hosting)                      │
│                                                                │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │  Application Server (Ubuntu 22.04 LTS)                   │ │
│  │  ────────────────────────────────────────────────────────│ │
│  │  • Nginx 1.18+ (Web Server)                              │ │
│  │  • PHP 8.2 with FPM (Application Server)                 │ │
│  │  • MariaDB 10.11+ (Database)                             │ │
│  │  • Redis 6.2+ (Cache & Sessions)                         │ │
│  │  • Supervisor (Process Manager)                          │ │
│  │                                                           │ │
│  │  Resources:                                               │ │
│  │  • 8 CPU cores                                            │ │
│  │  • 16 GB RAM                                              │ │
│  │  • 250 GB NVMe SSD                                        │ │
│  │  • 1 Gbps network                                         │ │
│  └──────────────────────────────────────────────────────────┘ │
│                                                                │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │  Backup Storage                                           │ │
│  │  ────────────────────────────────────────────────────────│ │
│  │  • Automated daily backups                               │ │
│  │  • 30-day retention                                       │ │
│  │  • Off-site replication                                   │ │
│  └──────────────────────────────────────────────────────────┘ │
│                                                                │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │  Monitoring & Logging                                     │ │
│  │  ────────────────────────────────────────────────────────│ │
│  │  • Application logs                                       │ │
│  │  • System metrics                                         │ │
│  │  • Performance monitoring                                 │ │
│  │  • Security audit logs                                    │ │
│  └──────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────┘
```

### Disaster Recovery

**Backup Strategy:**
```
Level 1: Real-time
  - Database replication (if configured)
  - Redis persistence (AOF)

Level 2: Hourly
  - Database incremental backups
  - Transaction logs

Level 3: Daily
  - Full database backup
  - File system backup
  - Configuration backup

Level 4: Weekly
  - Archive to off-site storage
  - Backup verification test
```

**Recovery Procedures:**
```
RTO (Recovery Time Objective):
  - Database failure: 30 minutes
  - Application failure: 15 minutes
  - Complete server failure: 4 hours

RPO (Recovery Point Objective):
  - Maximum data loss: 1 hour
```

---

## Deployment Architecture

### Deployment Pipeline

```
┌─────────────┐
│ Developer   │
│ Local Dev   │
└──────┬──────┘
       │
       │ git push
       │
┌──────▼──────────┐
│ Git Repository  │
│ (GitHub)        │
└──────┬──────────┘
       │
       │ webhook
       │
┌──────▼──────────┐
│ CI/CD Pipeline  │
│ (GitHub Actions)│
└──────┬──────────┘
       │
       ├─────────────┐
       │             │
┌──────▼──────┐ ┌────▼────────┐
│   Tests     │ │   Build     │
│  - Unit     │ │  - Composer │
│  - Feature  │ │  - npm      │
│  - E2E      │ │  - Assets   │
└──────┬──────┘ └────┬────────┘
       │             │
       └─────┬───────┘
             │
       ┌─────▼──────┐
       │  Security  │
       │  Scan      │
       └─────┬──────┘
             │
       ┌─────▼──────┐
       │  Deploy to │
       │  Staging   │
       └─────┬──────┘
             │
       ┌─────▼──────┐
       │   QA       │
       │   Testing  │
       └─────┬──────┘
             │
     ┌───────▼────────┐
     │  Manual        │
     │  Approval      │
     └───────┬────────┘
             │
       ┌─────▼──────┐
       │  Deploy to │
       │  Production│
       └────────────┘
```

### Environment Configuration

**Development:**
```yaml
Environment: local
Debug Mode: true
Database: MySQL (local)
Cache: Array (in-memory)
Queue: Sync (immediate execution)
Email: Log (to file)
Vend API: Sandbox
```

**Staging:**
```yaml
Environment: staging
Debug Mode: false
Database: MariaDB (staging server)
Cache: Redis (staging)
Queue: Database
Email: SendGrid (test domain)
Vend API: Test account
```

**Production:**
```yaml
Environment: production
Debug Mode: false
Database: MariaDB (production cluster)
Cache: Redis (production cluster)
Queue: Database with multiple workers
Email: SendGrid (production domain)
Vend API: Production account
Monitoring: Enabled (all levels)
Backups: Automated (daily)
SSL: Required (TLS 1.2+)
Rate Limiting: Strict
```

---

## Technology Stack

### Backend Technologies

```yaml
Language: PHP 8.2+
Features Used:
  - Typed properties
  - Union types
  - Match expressions
  - Attributes
  - Named arguments
  - Nullsafe operator

Database: MariaDB 10.11+
Features:
  - InnoDB storage engine
  - Full-text search
  - JSON support
  - Window functions
  - Common table expressions (CTEs)

Cache: Redis 6.2+
Features:
  - Key-value storage
  - Pub/Sub messaging
  - TTL support
  - Persistence (AOF)
  - Cluster mode (future)

Web Server: Nginx 1.18+
Features:
  - Reverse proxy
  - Load balancing
  - SSL/TLS termination
  - HTTP/2 support
  - FastCGI support
  - Rate limiting
```

### Frontend Technologies

```yaml
HTML5:
  - Semantic markup
  - Accessibility (ARIA)
  - SEO optimization

CSS3:
  - Custom properties (CSS variables)
  - Flexbox & Grid
  - Media queries
  - Animations
  - Modern selectors

JavaScript (ES6+):
  - Modules
  - Classes
  - Async/await
  - Fetch API
  - LocalStorage/SessionStorage
  - Service Workers (PWA)

Libraries:
  - Bootstrap 5.3 (UI framework)
  - Chart.js (data visualization)
  - Axios (HTTP client)
  - Socket.io (real-time)
```

### Development Tools

```yaml
Version Control:
  - Git 2.40+
  - GitHub (repository hosting)

Package Management:
  - Composer (PHP dependencies)
  - npm (JavaScript dependencies)

Testing:
  - PHPUnit (unit & integration tests)
  - Pest (modern PHP testing)
  - Jest (JavaScript testing)
  - Cypress (E2E testing)

Code Quality:
  - PHP_CodeSniffer (coding standards)
  - PHPStan (static analysis)
  - ESLint (JavaScript linting)
  - Prettier (code formatting)

Build Tools:
  - Webpack (asset bundling)
  - Babel (JavaScript transpilation)
  - PostCSS (CSS processing)

Documentation:
  - phpDocumentor (API docs)
  - Markdown (general docs)
```

---

## Design Patterns

### Creational Patterns

**Singleton (Database Connection):**
```php
class Database
{
    private static ?Database $instance = null;
    private PDO $connection;
    
    private function __construct()
    {
        $this->connection = new PDO(/* ... */);
    }
    
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        
        return self::$instance;
    }
    
    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
```

**Factory (Model Factory):**
```php
class TransferFactory
{
    public static function create(array $data): Transfer
    {
        $transfer = new Transfer();
        $transfer->fromStoreId = $data['from_store_id'];
        $transfer->toStoreId = $data['to_store_id'];
        $transfer->status = 'created';
        $transfer->createdAt = date('Y-m-d H:i:s');
        
        return $transfer;
    }
    
    public static function createFromVendConsignment(array $consignment): Transfer
    {
        // Different creation logic for Vend data
        // ...
    }
}
```

### Structural Patterns

**Adapter (Vend API Adapter):**
```php
interface PosInterface
{
    public function getProducts(): array;
    public function createConsignment(array $data): string;
}

class VendAdapter implements PosInterface
{
    private VendClient $client;
    
    public function getProducts(): array
    {
        $vendProducts = $this->client->get('/products');
        
        // Convert Vend format to internal format
        return array_map(function($vendProduct) {
            return [
                'id' => $vendProduct['id'],
                'name' => $vendProduct['name'],
                'sku' => $vendProduct['sku'],
                'price' => $vendProduct['retail_price'],
            ];
        }, $vendProducts);
    }
}
```

**Repository (Data Repository):**
```php
interface TransferRepositoryInterface
{
    public function find(int $id): ?Transfer;
    public function findAll(): array;
    public function save(Transfer $transfer): bool;
    public function delete(int $id): bool;
}

class TransferRepository implements TransferRepositoryInterface
{
    public function find(int $id): ?Transfer
    {
        return DB::table('transfers')->where('transfer_id', $id)->first();
    }
    
    public function findByStatus(string $status): array
    {
        return DB::table('transfers')->where('status', $status)->get();
    }
}
```

### Behavioral Patterns

**Strategy (Notification Strategy):**
```php
interface NotificationStrategy
{
    public function send(string $recipient, string $message): bool;
}

class EmailNotification implements NotificationStrategy
{
    public function send(string $recipient, string $message): bool
    {
        return Mail::to($recipient)->send(new GenericNotification($message));
    }
}

class SMSNotification implements NotificationStrategy
{
    public function send(string $recipient, string $message): bool
    {
        return SMS::to($recipient)->send($message);
    }
}

class NotificationContext
{
    private NotificationStrategy $strategy;
    
    public function setStrategy(NotificationStrategy $strategy): void
    {
        $this->strategy = $strategy;
    }
    
    public function notify(string $recipient, string $message): bool
    {
        return $this->strategy->send($recipient, $message);
    }
}
```

**Observer (Event System):**
```php
class TransferEvent
{
    private array $observers = [];
    
    public function attach(TransferObserver $observer): void
    {
        $this->observers[] = $observer;
    }
    
    public function notify(string $event, Transfer $transfer): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($event, $transfer);
        }
    }
}

class EmailObserver implements TransferObserver
{
    public function update(string $event, Transfer $transfer): void
    {
        if ($event === 'transfer.approved') {
            // Send approval email
            Mail::to($transfer->createdBy->email)->send(
                new TransferApprovedMail($transfer)
            );
        }
    }
}
```

---

## Scalability

### Horizontal Scaling

**Load Balancing:**
```nginx
upstream transfer_app {
    least_conn;  # Route to least connected server
    
    server app1.internal:9000 weight=1;
    server app2.internal:9000 weight=1;
    server app3.internal:9000 weight=1;
    
    keepalive 32;
}
```

**Session Affinity (Sticky Sessions):**
```nginx
upstream transfer_app {
    ip_hash;  # Same client → same server
    
    server app1.internal:9000;
    server app2.internal:9000;
}
```

### Vertical Scaling

**Resource Optimization:**
```
Current: 8 CPU, 16GB RAM
  ↓ (if CPU > 80% sustained)
Upgrade: 16 CPU, 32GB RAM
  ↓ (if memory > 80% sustained)
Upgrade: 32 CPU, 64GB RAM
```

### Database Scaling

**Read Replicas:**
```
┌─────────────────┐
│ Primary (Write) │
└────────┬────────┘
         │ Replication
    ┌────┴────┐
    │         │
┌───▼──┐  ┌───▼──┐
│Replica│  │Replica│
│  (R)  │  │  (R)  │
└───────┘  └───────┘
```

**Sharding (Future):**
```
Shard by store_id:
- Shard 1: Stores 1-10
- Shard 2: Stores 11-20
- Shard 3: Stores 21-30
```

### Cache Strategy

**Multi-Tier Caching:**
```
Request → Browser Cache (HTTP headers)
       → CDN Cache (Cloudflare)
       → Application Cache (Redis)
       → Database Query Cache
       → Database
```

---

## Monitoring & Observability

### Metrics Collection

**Application Metrics:**
```
- Request rate (requests/second)
- Response time (p50, p95, p99)
- Error rate (errors/total requests)
- Active users (concurrent sessions)
- Queue depth (pending jobs)
```

**System Metrics:**
```
- CPU usage (%)
- Memory usage (%)
- Disk I/O (MB/s)
- Network throughput (Mbps)
- Database connections (active/idle)
```

**Business Metrics:**
```
- Transfers created (per hour/day)
- Transfer approval time (average)
- Inventory accuracy (%)
- User activity (active users/day)
```

### Logging

**Log Levels:**
```
DEBUG: Detailed debugging information
INFO: General informational messages
WARNING: Warning messages (potential issues)
ERROR: Error messages (handled errors)
CRITICAL: Critical issues (requires immediate attention)
```

**Log Aggregation:**
```
Application → Log Files → Log Aggregator → Analysis Tool
                                        → Alerting System
```

### Health Checks

**Endpoint:**
```
GET /api/health

Response:
{
  "status": "ok",
  "timestamp": "2025-10-09T15:30:00Z",
  "checks": {
    "database": "ok",
    "redis": "ok",
    "vend_api": "ok",
    "disk_space": "ok",
    "queue": "ok"
  }
}
```

---

**Document Version:** 2.0.0  
**Last Updated:** October 9, 2025  
**Architecture Review:** Quarterly  
**Maintained By:** Ecigdis Limited Engineering Team

**Architecture Contact:**  
📧 Email: engineering@vapeshed.co.nz  
📞 Phone: 0800-VAPESHED ext. 5  
🏗️ Architecture Review Board: arb@vapeshed.co.nz
