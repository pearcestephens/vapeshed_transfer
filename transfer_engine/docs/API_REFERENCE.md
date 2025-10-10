# API Reference Documentation
**Vape Shed Transfer Engine - Complete API Guide**

Version: 1.0.0  
Last Updated: October 9, 2025  
Status: Production Ready

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Request/Response Format](#requestresponse-format)
4. [Error Handling](#error-handling)
5. [Rate Limiting](#rate-limiting)
6. [Dashboard API](#dashboard-api)
7. [Transfer API](#transfer-api)
8. [Analytics API](#analytics-api)
9. [Monitoring API](#monitoring-api)
10. [Configuration API](#configuration-api)
11. [Health & Readiness API](#health--readiness-api)
12. [Testing Lab API](#testing-lab-api)
13. [Security API](#security-api)
14. [WebSocket/SSE Endpoints](#websocketsse-endpoints)
15. [Webhooks](#webhooks)

---

## Overview

The Transfer Engine API provides a comprehensive REST interface for managing stock transfers across The Vape Shed retail network. All endpoints return JSON responses and support standard HTTP methods.

### Base URL
```
Production: https://transfer.vapeshed.co.nz/api
Staging: https://staging-transfer.vapeshed.co.nz/api
```

### API Versioning
Currently using URL path versioning:
```
/api/v1/{resource}
```

### Content Type
All requests and responses use `application/json` unless otherwise specified.

---

## Authentication

### Session-Based Authentication
The API uses session-based authentication with CSRF protection.

**Login Endpoint:**
```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "secure_password",
  "remember": false
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "username": "admin",
      "role": "administrator",
      "permissions": ["transfers.create", "transfers.view"]
    },
    "session": {
      "expires_at": "2025-10-10T15:30:00Z"
    }
  },
  "meta": {
    "timestamp": "2025-10-09T15:30:00Z",
    "request_id": "req_abc123"
  }
}
```

### CSRF Token
Include CSRF token in header or request body:
```http
X-CSRF-Token: abc123def456
```

### Session Validation
```http
GET /api/auth/validate
```

**Response:**
```json
{
  "success": true,
  "data": {
    "valid": true,
    "user": {
      "id": 1,
      "username": "admin"
    }
  }
}
```

---

## Request/Response Format

### Standard Request Format

**GET Requests:**
```http
GET /api/transfers?status=pending&limit=20&offset=0
X-CSRF-Token: abc123
```

**POST Requests:**
```http
POST /api/transfers
Content-Type: application/json
X-CSRF-Token: abc123

{
  "from_store_id": 1,
  "to_store_id": 5,
  "items": [
    {
      "product_id": "12345",
      "quantity": 10
    }
  ],
  "notes": "Urgent transfer"
}
```

### Standard Response Format

**Success Response:**
```json
{
  "success": true,
  "data": {
    "transfer_id": 12345,
    "status": "pending",
    "created_at": "2025-10-09T15:30:00Z"
  },
  "meta": {
    "timestamp": "2025-10-09T15:30:00Z",
    "request_id": "req_abc123",
    "execution_time_ms": 45
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid product ID",
    "details": {
      "field": "product_id",
      "value": "invalid",
      "constraint": "must be numeric"
    }
  },
  "meta": {
    "timestamp": "2025-10-09T15:30:00Z",
    "request_id": "req_abc123"
  }
}
```

### Pagination
All list endpoints support pagination:

**Query Parameters:**
- `limit` (integer, 1-100, default: 20)
- `offset` (integer, default: 0)
- `page` (integer, alternative to offset)

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [...],
    "pagination": {
      "total": 250,
      "limit": 20,
      "offset": 0,
      "pages": 13,
      "current_page": 1,
      "has_more": true
    }
  }
}
```

---

## Error Handling

### Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `VALIDATION_ERROR` | 400 | Input validation failed |
| `UNAUTHORIZED` | 401 | Authentication required |
| `FORBIDDEN` | 403 | Insufficient permissions |
| `NOT_FOUND` | 404 | Resource not found |
| `CONFLICT` | 409 | Resource conflict |
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests |
| `INTERNAL_ERROR` | 500 | Server error |
| `SERVICE_UNAVAILABLE` | 503 | Service temporarily unavailable |

### Error Response Format
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Human-readable error message",
    "details": {
      "field": "product_id",
      "constraint": "required"
    },
    "help_url": "https://docs.vapeshed.co.nz/errors/validation"
  },
  "meta": {
    "timestamp": "2025-10-09T15:30:00Z",
    "request_id": "req_abc123"
  }
}
```

---

## Rate Limiting

### Limits
- **Standard:** 100 requests per minute
- **Burst:** 20 requests per second
- **Heavy Operations:** 10 requests per minute

### Rate Limit Headers
```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1633792800
```

### Rate Limit Exceeded Response
```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Rate limit exceeded. Try again in 45 seconds.",
    "details": {
      "limit": 100,
      "retry_after": 45
    }
  }
}
```

---

## Dashboard API

### Get Dashboard Overview
```http
GET /api/dashboard
```

**Response:**
```json
{
  "success": true,
  "data": {
    "summary": {
      "active_transfers": 15,
      "pending_transfers": 8,
      "completed_today": 42,
      "total_value": 15234.50
    },
    "recent_transfers": [...],
    "alerts": [
      {
        "severity": "warning",
        "message": "Stock low at Store 5",
        "timestamp": "2025-10-09T15:00:00Z"
      }
    ],
    "system_health": {
      "engine_status": "running",
      "queue_depth": 5,
      "last_sync": "2025-10-09T15:28:00Z"
    }
  }
}
```

### Get Dashboard Metrics
```http
GET /api/dashboard/metrics
```

**Query Parameters:**
- `timeframe` (string): `1h`, `24h`, `7d`, `30d`
- `stores` (array): Filter by store IDs
- `metric_types` (array): Specific metrics to retrieve

**Response:**
```json
{
  "success": true,
  "data": {
    "timeframe": "24h",
    "metrics": {
      "transfer_count": {
        "current": 42,
        "previous": 38,
        "change_percent": 10.53
      },
      "transfer_value": {
        "current": 15234.50,
        "previous": 14120.30,
        "change_percent": 7.89
      },
      "completion_rate": {
        "current": 94.5,
        "target": 95.0
      }
    },
    "trends": {
      "hourly": [
        {"hour": "14:00", "count": 3, "value": 450.00},
        {"hour": "15:00", "count": 5, "value": 720.00}
      ]
    }
  }
}
```

### Get Real-Time Status
```http
GET /api/dashboard/status/realtime
```

**Response (Server-Sent Events):**
```
event: status
data: {"engine":"running","queue_depth":5,"active_workers":3}

event: transfer
data: {"transfer_id":12345,"status":"processing","progress":45}

event: alert
data: {"severity":"info","message":"Transfer 12345 completed"}
```

---

## Transfer API

### Create Transfer
```http
POST /api/transfers
Content-Type: application/json

{
  "from_store_id": 1,
  "to_store_id": 5,
  "items": [
    {
      "product_id": "12345",
      "sku": "VAPE-MOD-001",
      "quantity": 10,
      "unit_cost": 25.00
    }
  ],
  "priority": "normal",
  "notes": "Weekly stock rebalancing",
  "auto_approve": false
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "transfer_id": 12345,
    "reference": "TXN-2025-10-09-001",
    "status": "pending_approval",
    "from_store": {
      "id": 1,
      "name": "Auckland Queen Street"
    },
    "to_store": {
      "id": 5,
      "name": "Wellington Lambton Quay"
    },
    "items_count": 1,
    "total_value": 250.00,
    "created_at": "2025-10-09T15:30:00Z",
    "estimated_completion": "2025-10-10T10:00:00Z"
  }
}
```

### Get Transfer Details
```http
GET /api/transfers/{transfer_id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "transfer_id": 12345,
    "reference": "TXN-2025-10-09-001",
    "status": "in_transit",
    "from_store": {
      "id": 1,
      "name": "Auckland Queen Street",
      "address": "123 Queen St, Auckland"
    },
    "to_store": {
      "id": 5,
      "name": "Wellington Lambton Quay",
      "address": "456 Lambton Quay, Wellington"
    },
    "items": [
      {
        "product_id": "12345",
        "sku": "VAPE-MOD-001",
        "name": "Premium Vape Mod X1",
        "quantity": 10,
        "unit_cost": 25.00,
        "total_cost": 250.00,
        "picked": 10,
        "received": 0
      }
    ],
    "timeline": [
      {
        "status": "created",
        "timestamp": "2025-10-09T15:30:00Z",
        "user": "admin"
      },
      {
        "status": "approved",
        "timestamp": "2025-10-09T15:35:00Z",
        "user": "manager"
      },
      {
        "status": "picked",
        "timestamp": "2025-10-09T16:00:00Z",
        "user": "warehouse_staff"
      },
      {
        "status": "in_transit",
        "timestamp": "2025-10-09T16:30:00Z",
        "carrier": "NZ Post"
      }
    ],
    "totals": {
      "items_count": 1,
      "total_quantity": 10,
      "total_value": 250.00
    },
    "metadata": {
      "priority": "normal",
      "notes": "Weekly stock rebalancing",
      "tracking_number": "NZP123456789"
    }
  }
}
```

### List Transfers
```http
GET /api/transfers?status=pending&from_date=2025-10-01&limit=20
```

**Query Parameters:**
- `status` (string): `pending`, `approved`, `in_transit`, `completed`, `cancelled`
- `from_store_id` (integer)
- `to_store_id` (integer)
- `from_date` (ISO 8601 date)
- `to_date` (ISO 8601 date)
- `priority` (string): `low`, `normal`, `high`, `urgent`
- `search` (string): Search in reference, notes, or product names
- `sort` (string): `created_at`, `value`, `status`
- `order` (string): `asc`, `desc`

**Response:**
```json
{
  "success": true,
  "data": {
    "transfers": [
      {
        "transfer_id": 12345,
        "reference": "TXN-2025-10-09-001",
        "status": "pending",
        "from_store": "Auckland Queen Street",
        "to_store": "Wellington Lambton Quay",
        "items_count": 1,
        "total_value": 250.00,
        "created_at": "2025-10-09T15:30:00Z"
      }
    ],
    "pagination": {
      "total": 250,
      "limit": 20,
      "offset": 0
    },
    "summary": {
      "total_value": 15234.50,
      "by_status": {
        "pending": 8,
        "in_transit": 5,
        "completed": 237
      }
    }
  }
}
```

### Update Transfer Status
```http
PATCH /api/transfers/{transfer_id}/status

{
  "status": "approved",
  "notes": "Approved by manager",
  "notify": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "transfer_id": 12345,
    "status": "approved",
    "previous_status": "pending",
    "updated_at": "2025-10-09T15:35:00Z",
    "updated_by": "manager"
  }
}
```

### Cancel Transfer
```http
DELETE /api/transfers/{transfer_id}

{
  "reason": "Stock no longer needed",
  "notify": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "transfer_id": 12345,
    "status": "cancelled",
    "cancelled_at": "2025-10-09T15:40:00Z",
    "cancelled_by": "admin",
    "reason": "Stock no longer needed"
  }
}
```

### Bulk Transfer Operations
```http
POST /api/transfers/bulk

{
  "operation": "approve",
  "transfer_ids": [12345, 12346, 12347],
  "notes": "Batch approval for weekly transfers"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "processed": 3,
    "succeeded": 3,
    "failed": 0,
    "results": [
      {"transfer_id": 12345, "success": true},
      {"transfer_id": 12346, "success": true},
      {"transfer_id": 12347, "success": true}
    ]
  }
}
```

---

## Analytics API

### Get Transfer Analytics
```http
GET /api/analytics/transfers?timeframe=30d&group_by=store
```

**Query Parameters:**
- `timeframe` (string): `1d`, `7d`, `30d`, `90d`, `1y`, `custom`
- `from_date` (ISO 8601 date): Required if timeframe=custom
- `to_date` (ISO 8601 date): Required if timeframe=custom
- `group_by` (string): `store`, `product`, `day`, `week`, `month`
- `stores` (array): Filter by store IDs
- `products` (array): Filter by product IDs

**Response:**
```json
{
  "success": true,
  "data": {
    "timeframe": {
      "start": "2025-09-09T00:00:00Z",
      "end": "2025-10-09T23:59:59Z",
      "days": 30
    },
    "summary": {
      "total_transfers": 450,
      "total_value": 125000.00,
      "avg_transfer_value": 277.78,
      "completion_rate": 94.5
    },
    "by_store": [
      {
        "store_id": 1,
        "store_name": "Auckland Queen Street",
        "sent": 45,
        "received": 52,
        "net_balance": 7,
        "total_value": 15234.50
      }
    ],
    "trends": {
      "daily": [
        {
          "date": "2025-10-09",
          "count": 15,
          "value": 4250.00,
          "completion_rate": 93.3
        }
      ]
    },
    "top_products": [
      {
        "product_id": "12345",
        "sku": "VAPE-MOD-001",
        "name": "Premium Vape Mod X1",
        "transfer_count": 125,
        "total_quantity": 450,
        "total_value": 11250.00
      }
    ]
  }
}
```

### Get Predictive Analytics
```http
GET /api/analytics/predictions?store_id=1&horizon=7d
```

**Response:**
```json
{
  "success": true,
  "data": {
    "store_id": 1,
    "horizon": "7d",
    "predictions": {
      "demand_forecast": [
        {
          "product_id": "12345",
          "predicted_demand": 25,
          "confidence": 0.85,
          "factors": ["historical_trend", "seasonal"]
        }
      ],
      "transfer_recommendations": [
        {
          "product_id": "12345",
          "from_store": 3,
          "quantity": 15,
          "urgency": "medium",
          "reason": "Predicted stockout in 3 days"
        }
      ],
      "optimal_timing": {
        "best_day": "Monday",
        "best_time": "10:00",
        "reasoning": "Lowest transit time and highest success rate"
      }
    }
  }
}
```

### Get Performance Metrics
```http
GET /api/analytics/performance
```

**Response:**
```json
{
  "success": true,
  "data": {
    "transfer_performance": {
      "avg_completion_time": "24.5h",
      "on_time_rate": 92.3,
      "accuracy_rate": 98.7,
      "damage_rate": 0.3
    },
    "store_performance": [
      {
        "store_id": 1,
        "efficiency_score": 94.5,
        "avg_processing_time": "2.5h",
        "error_rate": 1.2
      }
    ],
    "bottlenecks": [
      {
        "type": "approval_delay",
        "avg_delay": "4.5h",
        "impact": "high",
        "recommendation": "Implement auto-approval for low-value transfers"
      }
    ]
  }
}
```

---

## Monitoring API

### Get System Status
```http
GET /api/monitoring/status
```

**Response:**
```json
{
  "success": true,
  "data": {
    "overall_status": "healthy",
    "components": {
      "engine": {
        "status": "running",
        "uptime": "15d 4h 23m",
        "version": "1.0.0"
      },
      "database": {
        "status": "healthy",
        "connections": 5,
        "avg_query_time": "12ms"
      },
      "queue": {
        "status": "healthy",
        "depth": 5,
        "processing_rate": 120.5
      },
      "vend_api": {
        "status": "connected",
        "last_sync": "2025-10-09T15:28:00Z",
        "response_time": "250ms"
      }
    },
    "alerts": [
      {
        "severity": "warning",
        "component": "queue",
        "message": "Queue depth above threshold",
        "timestamp": "2025-10-09T15:00:00Z"
      }
    ]
  }
}
```

### Get Performance Metrics
```http
GET /api/monitoring/metrics?window=1h
```

**Response:**
```json
{
  "success": true,
  "data": {
    "window": "1h",
    "metrics": {
      "requests": {
        "total": 1250,
        "success": 1225,
        "errors": 25,
        "avg_response_time": 45.2
      },
      "transfers": {
        "created": 15,
        "completed": 18,
        "in_progress": 5
      },
      "system": {
        "cpu_usage": 23.5,
        "memory_usage": 45.2,
        "disk_usage": 67.8
      }
    },
    "timeseries": [
      {
        "timestamp": "2025-10-09T15:00:00Z",
        "requests_per_min": 20.5,
        "avg_response_time": 42.0
      }
    ]
  }
}
```

### Get Error Logs
```http
GET /api/monitoring/errors?severity=error&limit=50
```

**Response:**
```json
{
  "success": true,
  "data": {
    "errors": [
      {
        "error_id": "err_abc123",
        "severity": "error",
        "message": "Failed to sync with Vend API",
        "code": "VEND_API_ERROR",
        "timestamp": "2025-10-09T15:25:00Z",
        "context": {
          "endpoint": "/api/products",
          "status_code": 500
        },
        "stack_trace": "..."
      }
    ],
    "summary": {
      "total": 50,
      "by_severity": {
        "error": 5,
        "warning": 15,
        "critical": 0
      }
    }
  }
}
```

---

## Configuration API

### Get Configuration
```http
GET /api/config
```

**Response:**
```json
{
  "success": true,
  "data": {
    "system": {
      "environment": "production",
      "version": "1.0.0",
      "maintenance_mode": false
    },
    "engine": {
      "auto_sync_enabled": true,
      "sync_interval": 300,
      "max_concurrent_transfers": 10
    },
    "notifications": {
      "email_enabled": true,
      "sms_enabled": false,
      "webhook_enabled": true
    },
    "presets": [
      {
        "id": "weekly_rebalance",
        "name": "Weekly Stock Rebalancing",
        "enabled": true
      }
    ]
  }
}
```

### Update Configuration
```http
PATCH /api/config

{
  "engine": {
    "auto_sync_enabled": false,
    "sync_interval": 600
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "updated_fields": ["engine.auto_sync_enabled", "engine.sync_interval"],
    "changes": [
      {
        "field": "engine.auto_sync_enabled",
        "old_value": true,
        "new_value": false
      }
    ],
    "updated_at": "2025-10-09T15:30:00Z",
    "updated_by": "admin"
  }
}
```

### Get Presets
```http
GET /api/config/presets
```

**Response:**
```json
{
  "success": true,
  "data": {
    "presets": [
      {
        "id": "weekly_rebalance",
        "name": "Weekly Stock Rebalancing",
        "description": "Automatically rebalance stock across all stores weekly",
        "enabled": true,
        "schedule": "0 0 * * 1",
        "parameters": {
          "min_threshold": 5,
          "max_threshold": 50
        }
      }
    ]
  }
}
```

---

## Health & Readiness API

### Health Check
```http
GET /api/health
```

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "timestamp": "2025-10-09T15:30:00Z",
    "checks": {
      "database": "pass",
      "vend_api": "pass",
      "queue": "pass",
      "disk_space": "pass"
    },
    "version": "1.0.0",
    "uptime": "15d 4h 23m"
  }
}
```

### Readiness Check
```http
GET /api/ready
```

**Response:**
```json
{
  "success": true,
  "data": {
    "ready": true,
    "checks": {
      "database_connected": true,
      "migrations_current": true,
      "config_loaded": true,
      "dependencies_available": true
    }
  }
}
```

---

## Testing Lab API

### Send Test Webhook
```http
POST /api/test/webhook

{
  "event_type": "transfer.created",
  "url": "https://example.com/webhook",
  "payload": {
    "transfer_id": 12345,
    "status": "created"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "test_id": "test_abc123",
    "response": {
      "status_code": 200,
      "headers": {
        "content-type": "application/json"
      },
      "body": {"received": true},
      "duration_ms": 125
    },
    "timestamp": "2025-10-09T15:30:00Z"
  }
}
```

### Test Vend API Connection
```http
POST /api/test/vend

{
  "endpoint": "products",
  "method": "GET",
  "params": {
    "limit": 10
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "connected": true,
    "response_time": 250,
    "api_version": "2.0",
    "records_retrieved": 10
  }
}
```

---

## Security API

### Audit Log
```http
GET /api/security/audit?user_id=1&action=transfer.create
```

**Response:**
```json
{
  "success": true,
  "data": {
    "entries": [
      {
        "audit_id": "aud_abc123",
        "timestamp": "2025-10-09T15:30:00Z",
        "user_id": 1,
        "username": "admin",
        "action": "transfer.create",
        "resource_type": "transfer",
        "resource_id": 12345,
        "ip_address": "192.168.1.100",
        "user_agent": "Mozilla/5.0...",
        "changes": {
          "from_store_id": 1,
          "to_store_id": 5
        }
      }
    ]
  }
}
```

---

## WebSocket/SSE Endpoints

### Real-Time Transfer Updates (SSE)
```http
GET /api/stream/transfers
Accept: text/event-stream
```

**Stream Format:**
```
event: transfer_created
data: {"transfer_id":12345,"status":"pending"}

event: transfer_updated
data: {"transfer_id":12345,"status":"in_transit","progress":50}

event: transfer_completed
data: {"transfer_id":12345,"status":"completed","completion_time":"2025-10-09T16:00:00Z"}
```

### System Status Stream (SSE)
```http
GET /api/stream/status
Accept: text/event-stream
```

**Stream Format:**
```
event: status
data: {"engine":"running","queue_depth":5}

event: alert
data: {"severity":"warning","message":"Queue depth high"}
```

---

## Webhooks

### Configure Webhook
```http
POST /api/webhooks

{
  "url": "https://example.com/webhook",
  "events": ["transfer.created", "transfer.completed"],
  "secret": "your_webhook_secret",
  "enabled": true
}
```

### Webhook Payload Format
```json
{
  "event": "transfer.created",
  "timestamp": "2025-10-09T15:30:00Z",
  "data": {
    "transfer_id": 12345,
    "reference": "TXN-2025-10-09-001",
    "status": "pending"
  },
  "signature": "sha256=abc123..."
}
```

### Webhook Signature Verification
Verify webhook authenticity using HMAC-SHA256:
```
signature = HMAC-SHA256(secret, payload)
```

---

## SDK Examples

### JavaScript/Node.js
```javascript
const TransferEngineAPI = require('@vapeshed/transfer-engine-api');

const client = new TransferEngineAPI({
  baseURL: 'https://transfer.vapeshed.co.nz/api',
  sessionToken: 'your_session_token'
});

// Create transfer
const transfer = await client.transfers.create({
  from_store_id: 1,
  to_store_id: 5,
  items: [
    { product_id: '12345', quantity: 10 }
  ]
});

console.log(`Transfer created: ${transfer.transfer_id}`);
```

### PHP
```php
<?php
use VapeShed\TransferEngine\Client;

$client = new Client([
    'base_url' => 'https://transfer.vapeshed.co.nz/api',
    'session_token' => 'your_session_token'
]);

$transfer = $client->transfers->create([
    'from_store_id' => 1,
    'to_store_id' => 5,
    'items' => [
        ['product_id' => '12345', 'quantity' => 10]
    ]
]);

echo "Transfer created: {$transfer['transfer_id']}";
```

---

## Rate Limit Guidelines

### Best Practices
1. Implement exponential backoff for retries
2. Cache responses when appropriate
3. Use bulk operations when possible
4. Monitor `X-RateLimit-Remaining` header
5. Implement circuit breaker patterns

### Example Retry Logic
```javascript
async function apiCallWithRetry(fn, maxRetries = 3) {
  let attempt = 0;
  while (attempt < maxRetries) {
    try {
      return await fn();
    } catch (error) {
      if (error.code === 'RATE_LIMIT_EXCEEDED') {
        const delay = Math.pow(2, attempt) * 1000;
        await new Promise(resolve => setTimeout(resolve, delay));
        attempt++;
      } else {
        throw error;
      }
    }
  }
}
```

---

## Support & Resources

- **Documentation:** https://docs.vapeshed.co.nz
- **API Status:** https://status.vapeshed.co.nz
- **Support Email:** api-support@vapeshed.co.nz
- **Slack Community:** https://vapeshed-dev.slack.com

---

**Document Version:** 1.0.0  
**Last Updated:** October 9, 2025  
**Maintained By:** Ecigdis Limited Engineering Team
