# API Integration Guide: Frontend Development

**Target Audience:** Frontend developers, React/Vue/Angular teams  
**API Version:** 2.0.0  
**Last Updated:** 2025-10-07

## Overview

The Transfer Engine provides a modern RESTful API with consistent envelopes, SSE real-time updates, CORS support, and comprehensive observability. This guide covers frontend integration patterns and best practices.

## Base Configuration

### API Base URL
```javascript
const API_BASE = 'https://staff.vapeshed.co.nz/transfer-engine/api';
const SSE_BASE = 'https://staff.vapeshed.co.nz/transfer-engine';
```

### Request Configuration
```javascript
const defaultHeaders = {
  'Content-Type': 'application/json',
  'Accept': 'application/json',
  'X-Correlation-ID': generateCorrelationId(),
};

// If using token auth
if (apiToken) {
  defaultHeaders['Authorization'] = `Bearer ${apiToken}`;
}
```

## HTTP Client Setup

### Axios Example
```javascript
import axios from 'axios';

const apiClient = axios.create({
  baseURL: API_BASE,
  timeout: 10000,
  headers: defaultHeaders,
});

// Response interceptor for error handling
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 429) {
      const retryAfter = error.response.headers['retry-after'];
      console.warn(`Rate limited. Retry after ${retryAfter}s`);
      // Implement backoff logic
    }
    return Promise.reject(error);
  }
);
```

### Fetch API Example
```javascript
async function apiRequest(endpoint, options = {}) {
  const response = await fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers: {
      ...defaultHeaders,
      ...options.headers,
    },
  });

  if (response.status === 429) {
    const retryAfter = response.headers.get('retry-after');
    throw new Error(`Rate limited. Retry after ${retryAfter}s`);
  }

  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.error?.message || 'API request failed');
  }

  return data;
}
```

## Common Patterns

### 1. Transfer Module

#### Get Transfer Status
```javascript
async function getTransferStatus() {
  const response = await apiClient.get('/transfer.php?action=status');
  return response.data.stats;
}

// Usage in React
function TransferDashboard() {
  const [stats, setStats] = useState(null);

  useEffect(() => {
    getTransferStatus().then(setStats);
    
    // Poll every 5 seconds
    const interval = setInterval(() => {
      getTransferStatus().then(setStats);
    }, 5000);

    return () => clearInterval(interval);
  }, []);

  return (
    <div>
      <StatCard label="Pending" value={stats?.pending} />
      <StatCard label="Today" value={stats?.today} />
      <StatCard label="Failed" value={stats?.failed} />
    </div>
  );
}
```

#### Execute Transfers
```javascript
async function executeTransfers(ids) {
  // Get CSRF token first
  const session = await apiClient.get('/session.php');
  const csrfToken = session.data.data.csrf_token;

  const response = await apiClient.post(
    '/transfer.php?action=execute',
    { ids },
    {
      headers: {
        'X-CSRF-Token': csrfToken,
      },
    }
  );

  return response.data.data;
}

// Usage
const handleExecute = async (selectedIds) => {
  try {
    setLoading(true);
    const result = await executeTransfers(selectedIds);
    showSuccess(`Transfer ${result.transfer_id} queued`);
  } catch (error) {
    showError(error.message);
  } finally {
    setLoading(false);
  }
};
```

### 2. Pricing Module

#### Get Pricing Candidates
```javascript
async function getPricingCandidates(limit = 50) {
  const response = await apiClient.get(
    `/pricing.php?action=candidates&limit=${limit}`
  );
  return response.data.candidates;
}

// Usage with filtering
function PricingCandidates() {
  const [candidates, setCandidates] = useState([]);
  const [band, setBand] = useState('all');

  useEffect(() => {
    getPricingCandidates(50).then((data) => {
      const filtered = band === 'all' 
        ? data 
        : data.filter(c => c.band === band);
      setCandidates(filtered);
    });
  }, [band]);

  return (
    <div>
      <BandFilter value={band} onChange={setBand} />
      <CandidateList items={candidates} />
    </div>
  );
}
```

#### Apply Pricing Proposals
```javascript
async function applyPricingProposals(proposalIds = null, applyAll = false) {
  const session = await apiClient.get('/session.php');
  const csrfToken = session.data.data.csrf_token;

  const response = await apiClient.post(
    '/pricing.php?action=apply',
    {
      apply_all: applyAll,
      proposal_ids: proposalIds || [],
    },
    {
      headers: {
        'X-CSRF-Token': csrfToken,
      },
    }
  );

  return response.data.data;
}
```

### 3. Dashboard Stats

#### Comprehensive Dashboard Hook
```javascript
import { useState, useEffect } from 'react';

function useDashboardStats(pollInterval = 5000) {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    async function fetchStats() {
      try {
        const response = await apiClient.get('/stats.php');
        setStats(response.data);
        setError(null);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    }

    fetchStats();
    const interval = setInterval(fetchStats, pollInterval);

    return () => clearInterval(interval);
  }, [pollInterval]);

  return { stats, loading, error };
}

// Usage
function Dashboard() {
  const { stats, loading, error } = useDashboardStats(5000);

  if (loading) return <Spinner />;
  if (error) return <ErrorMessage>{error}</ErrorMessage>;

  return (
    <div className="dashboard">
      <TransferStats data={stats.transfers} />
      <ProposalStats data={stats.proposals} />
      <AlertStats data={stats.alerts} />
      <HealthStats data={stats.health} />
    </div>
  );
}
```

### 4. Real-Time Updates with SSE

#### SSE Manager Hook
```javascript
import { useEffect, useState, useRef } from 'react';

function useSSE(topics = ['status', 'transfer', 'pricing']) {
  const [events, setEvents] = useState([]);
  const [connected, setConnected] = useState(false);
  const eventSourceRef = useRef(null);

  useEffect(() => {
    const topicsParam = topics.join(',');
    const url = `${SSE_BASE}/sse.php?topics=${topicsParam}`;

    const eventSource = new EventSource(url);
    eventSourceRef.current = eventSource;

    eventSource.onopen = () => {
      console.log('SSE connected');
      setConnected(true);
    };

    eventSource.onerror = (error) => {
      console.error('SSE error:', error);
      setConnected(false);
    };

    // Listen to all requested topics
    topics.forEach((topic) => {
      eventSource.addEventListener(topic, (event) => {
        const data = JSON.parse(event.data);
        setEvents((prev) => [...prev.slice(-99), { topic, data, ts: Date.now() }]);
      });
    });

    // Cleanup
    return () => {
      eventSource.close();
    };
  }, [topics.join(',')]);

  return { events, connected };
}

// Usage
function LiveDashboard() {
  const { events, connected } = useSSE(['status', 'transfer', 'pricing']);

  useEffect(() => {
    events.forEach((event) => {
      if (event.topic === 'transfer' && event.data.type === 'transfer_completed') {
        showNotification(`Transfer completed: ${event.data.items_count} items`);
      }
      if (event.topic === 'pricing' && event.data.type === 'pricing_proposal') {
        refreshPricingCandidates();
      }
    });
  }, [events]);

  return (
    <div>
      <ConnectionStatus connected={connected} />
      <EventFeed events={events} />
    </div>
  );
}
```

### 5. History and Traces

#### History with Pagination
```javascript
function useHistory(type = null, limit = 50, offset = 0) {
  const [history, setHistory] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function fetchHistory() {
      setLoading(true);
      const params = new URLSearchParams({
        limit: limit.toString(),
      });
      if (type) params.append('type', type);

      const response = await apiClient.get(`/history.php?${params}`);
      setHistory(response.data.data.items);
      setLoading(false);
    }

    fetchHistory();
  }, [type, limit, offset]);

  return { history, loading };
}

// Usage
function HistoryView() {
  const [type, setType] = useState('transfer');
  const { history, loading } = useHistory(type, 50, 0);

  return (
    <div>
      <TypeFilter value={type} onChange={setType} />
      {loading ? <Spinner /> : <HistoryTable items={history} />}
    </div>
  );
}
```

#### Guardrail Traces
```javascript
async function getGuardrailTraces(proposalId) {
  const response = await apiClient.get(`/traces.php?proposal_id=${proposalId}`);
  return response.data.data.traces;
}

// Usage
function ProposalDetails({ proposalId }) {
  const [traces, setTraces] = useState([]);

  useEffect(() => {
    getGuardrailTraces(proposalId).then(setTraces);
  }, [proposalId]);

  return (
    <div>
      <h3>Guardrail Evaluation</h3>
      <TraceTimeline traces={traces} />
    </div>
  );
}
```

## Error Handling

### Comprehensive Error Handler
```javascript
class APIError extends Error {
  constructor(code, message, details, correlationId) {
    super(message);
    this.code = code;
    this.details = details;
    this.correlationId = correlationId;
  }
}

function handleAPIError(error) {
  if (error.response) {
    const { data } = error.response;
    const apiError = new APIError(
      data.error?.code || 'UNKNOWN_ERROR',
      data.error?.message || 'An error occurred',
      data.error?.details,
      data.meta?.correlation_id
    );

    // Log for debugging
    console.error('API Error:', {
      code: apiError.code,
      message: apiError.message,
      correlationId: apiError.correlationId,
      details: apiError.details,
    });

    // User-friendly messages
    switch (apiError.code) {
      case 'RATE_LIMITED':
        return 'Too many requests. Please slow down.';
      case 'UNAUTHORIZED':
        return 'Authentication required. Please log in.';
      case 'FORBIDDEN':
        return 'You do not have permission to perform this action.';
      case 'VALIDATION_ERROR':
        return `Invalid input: ${apiError.message}`;
      default:
        return 'An unexpected error occurred. Please try again.';
    }
  }

  return 'Network error. Please check your connection.';
}
```

## Rate Limit Handling

### Automatic Retry with Backoff
```javascript
async function requestWithRetry(fn, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      return await fn();
    } catch (error) {
      if (error.response?.status === 429 && i < maxRetries - 1) {
        const retryAfter = parseInt(error.response.headers['retry-after'] || '5', 10);
        console.log(`Rate limited. Retrying in ${retryAfter}s...`);
        await new Promise((resolve) => setTimeout(resolve, retryAfter * 1000));
      } else {
        throw error;
      }
    }
  }
}

// Usage
const data = await requestWithRetry(() => getTransferStatus());
```

### Rate Limit Display
```javascript
function RateLimitIndicator() {
  const [rateLimit, setRateLimit] = useState(null);

  const updateRateLimit = (response) => {
    setRateLimit({
      limit: response.headers['x-ratelimit-limit'],
      remaining: response.headers['x-ratelimit-remaining'],
      reset: response.headers['x-ratelimit-reset'],
    });
  };

  // Intercept responses
  useEffect(() => {
    const interceptor = apiClient.interceptors.response.use(
      (response) => {
        updateRateLimit(response);
        return response;
      }
    );

    return () => apiClient.interceptors.response.eject(interceptor);
  }, []);

  if (!rateLimit) return null;

  const percentage = (rateLimit.remaining / rateLimit.limit) * 100;
  const isLow = percentage < 20;

  return (
    <div className={`rate-limit ${isLow ? 'warning' : ''}`}>
      <span>{rateLimit.remaining}/{rateLimit.limit}</span>
      <ProgressBar value={percentage} />
    </div>
  );
}
```

## Best Practices

### 1. Correlation IDs
Always generate and track correlation IDs:
```javascript
function generateCorrelationId() {
  return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
}
```

### 2. Polling Optimization
Use adaptive polling based on activity:
```javascript
function useAdaptivePolling(fetchFn, baseInterval = 5000) {
  const [interval, setInterval] = useState(baseInterval);
  const [isActive, setIsActive] = useState(document.visibilityState === 'visible');

  useEffect(() => {
    const handleVisibilityChange = () => {
      setIsActive(document.visibilityState === 'visible');
      setInterval(document.visibilityState === 'visible' ? baseInterval : baseInterval * 4);
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
  }, [baseInterval]);

  // Use this interval for your polling
  return interval;
}
```

### 3. Request Deduplication
Prevent duplicate requests:
```javascript
const pendingRequests = new Map();

async function deduplicatedRequest(key, fn) {
  if (pendingRequests.has(key)) {
    return pendingRequests.get(key);
  }

  const promise = fn().finally(() => {
    pendingRequests.delete(key);
  });

  pendingRequests.set(key, promise);
  return promise;
}
```

### 4. Optimistic Updates
Update UI before server confirms:
```javascript
async function optimisticExecuteTransfer(ids) {
  // Update UI immediately
  setTransfers((prev) =>
    prev.map((t) => (ids.includes(t.id) ? { ...t, status: 'executing' } : t))
  );

  try {
    const result = await executeTransfers(ids);
    // Confirm success
    setTransfers((prev) =>
      prev.map((t) => (ids.includes(t.id) ? { ...t, status: 'executed' } : t))
    );
    return result;
  } catch (error) {
    // Rollback on error
    setTransfers((prev) =>
      prev.map((t) => (ids.includes(t.id) ? { ...t, status: 'pending' } : t))
    );
    throw error;
  }
}
```

## TypeScript Support

### Type Definitions
```typescript
interface APIResponse<T> {
  success: boolean;
  data?: T;
  error?: {
    code: string;
    message: string;
    details?: Record<string, any>;
  };
  meta: {
    correlation_id: string;
    method: string;
    endpoint: string;
    path: string;
    ts: number;
    duration_ms: number;
  };
}

interface TransferStats {
  pending: number;
  today: number;
  failed: number;
  total: number;
}

interface PricingCandidate {
  id: number;
  product_sku: string;
  current_price: number;
  proposed_price: number;
  band: 'low' | 'medium' | 'high';
  confidence: number;
  status: 'pending' | 'approved' | 'rejected';
}
```

## Testing

### Mock API Responses
```javascript
// jest.mock or msw handlers
const handlers = [
  rest.get(`${API_BASE}/transfer.php`, (req, res, ctx) => {
    return res(
      ctx.json({
        success: true,
        stats: { pending: 3, today: 1, failed: 0, total: 12 },
        meta: {
          correlation_id: 'test-123',
          method: 'GET',
          endpoint: 'transfer.php',
          path: '/api/transfer.php',
          ts: 1696412345,
          duration_ms: 42,
        },
      })
    );
  }),
];
```

## Performance Tips

1. **Bundle SSE separately**: Load SSE connection only when needed
2. **Lazy load modules**: Code-split pricing/transfer modules
3. **Cache responses**: Use React Query or SWR for automatic caching
4. **Debounce searches**: Wait for user to finish typing
5. **Virtual scrolling**: For large history/candidate lists

## Support

- **API Docs**: `docs/PROJECT_SPECIFICATION.md`
- **Quick Start**: `docs/QUICK_START.md`
- **Rate Limits**: `docs/runbooks/RATE_LIMIT_MANAGEMENT.md`
- **Support**: <pearce.stephens@ecigdis.co.nz>
