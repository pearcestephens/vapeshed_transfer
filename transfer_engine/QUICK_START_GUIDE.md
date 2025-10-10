# 🚀 VAPESHED TRANSFER ENGINE - QUICK START GUIDE

## 📍 ACCESS URLS

### Production URL (Primary Access)
```
https://staff.vapeshed.co.nz/assets/cron/utility_scripts/VAPESHED_TRANSFER_OLD/vapeshed_transfer/transfer_engine/public/
```

### Alternative URLs
```
Main Entry:     /path/to/transfer_engine/public/index.php
Root Redirector: /path/to/transfer_engine/index.php
```

### Health Check URLs
```
Health:    https://staff.vapeshed.co.nz/.../public/?endpoint=health
Readiness: https://staff.vapeshed.co.nz/.../public/?endpoint=ready
API Health: https://staff.vapeshed.co.nz/.../public/?endpoint=api/health
```

---

## ⚡ QUICK START (5 MINUTES)

### Step 1: Access the Dashboard
1. Open your browser
2. Navigate to the production URL above
3. You'll see the main dashboard

### Step 2: First Transfer
1. Click **"Transfer"** in the navigation
2. Select your warehouse (default: Warehouse Web)
3. Choose outlets to transfer to
4. Set minimum lines (default: 5)
5. Click **"Execute Transfer"**
6. View results in real-time

### Step 3: View Reports
1. Click **"Reports"** in navigation
2. See transfer history
3. Export to Excel/CSV if needed
4. View allocation fairness metrics

---

## 📖 COMPLETE USER MANUAL

### TABLE OF CONTENTS
1. [Dashboard Overview](#dashboard-overview)
2. [Configuration Management](#configuration-management)
3. [Transfer Execution](#transfer-execution)
4. [Reports & Analytics](#reports-analytics)
5. [Logs & Monitoring](#logs-monitoring)
6. [Settings & Presets](#settings-presets)
7. [API Usage](#api-usage)
8. [Troubleshooting](#troubleshooting)

---

## 1. 📊 DASHBOARD OVERVIEW

### Main Dashboard
**URL**: `GET /` or `GET /dashboard`

**Features**:
- **Engine Status**: Real-time transfer engine health
- **Queue Status**: Active transfers and queue depth
- **Database Status**: Connection pool metrics
- **System Status**: Server health and resources

**Metrics Displayed**:
- Active Connections: Current database connections
- Queries Executed: Total queries since startup
- Throughput: Requests per second
- Memory Usage: Current system memory
- Uptime: System uptime

**Health Indicators**:
- 🟢 Green: All systems operational
- 🟡 Yellow: Warning state
- 🔴 Red: Critical issue requiring attention

---

## 2. ⚙️ CONFIGURATION MANAGEMENT

### View All Configurations
**URL**: `GET /config`

**Purpose**: Manage saved transfer configurations (presets)

**Actions**:
- **View** all saved configurations
- **Create** new configuration
- **Edit** existing configuration
- **Delete** unused configuration

### Create New Configuration
**URL**: `GET /config/create`

**Parameters**:
- **Name**: Configuration name (e.g., "Daily Transfer")
- **Warehouse ID**: Source warehouse outlet ID
- **Target Outlets**: List of destination outlet IDs
- **Min Lines**: Minimum lines per transfer
- **Max Lines**: Maximum lines per transfer (optional)
- **Dry Run**: Enable for testing without actual transfers

**Example**:
```
Name: Morning Restock
Warehouse: 020b2c2a-4671-11f0-e200-8e55f1689700
Target Outlets: Store 1, Store 2, Store 3
Min Lines: 5
Max Lines: 50
Dry Run: No
```

### Edit Configuration
**URL**: `GET /config/{id}/edit`

**Steps**:
1. Click "Edit" on any configuration
2. Modify parameters as needed
3. Click "Save"
4. Configuration updated immediately

### Presets (Quick Load)
**URL**: `GET /api/presets`

**Built-in Presets**:
- **Minimal**: 3 lines, fast execution
- **Balanced**: 5 lines, standard fairness
- **Conservative**: 10 lines, maximum fairness
- **Custom**: User-defined parameters

---

## 3. 🚚 TRANSFER EXECUTION

### Manual Transfer
**URL**: `GET /transfer`

**Steps**:
1. **Select Configuration** or create new
2. **Choose Outlets**: Select destination stores
3. **Set Parameters**:
   - Min Lines: Minimum products per transfer
   - Dry Run: Test mode (no actual transfers)
   - Test Mode: Use test data
4. **Execute**: Click "Execute Transfer"
5. **Monitor**: Watch real-time progress
6. **Review**: Check results and allocations

### Execute Transfer
**URL**: `POST /transfer/execute`

**Request Body**:
```json
{
  "warehouse_id": "020b2c2a-4671-11f0-e200-8e55f1689700",
  "target_outlets": ["outlet1", "outlet2", "outlet3"],
  "min_lines": 5,
  "max_lines": 50,
  "dry": false,
  "test_mode": false
}
```

**Response**:
```json
{
  "success": true,
  "allocations": [
    {
      "outlet_id": "outlet1",
      "outlet_name": "Store 1",
      "products": 15,
      "total_quantity": 150
    }
  ],
  "summary": {
    "total_outlets": 3,
    "total_products": 45,
    "total_quantity": 450,
    "total_lines": 45,
    "execution_time": "1.95ms"
  }
}
```

### Transfer Status
**URL**: `GET /transfer/status`

**Real-time Information**:
- Transfer progress (0-100%)
- Current outlet being processed
- Products allocated so far
- Estimated time remaining

### View Results
**URL**: `GET /transfer/results`

**Details**:
- Complete allocation breakdown
- Per-outlet product list
- Quantity summaries
- Fairness metrics
- Execution statistics

---

## 4. 📈 REPORTS & ANALYTICS

### Reports Dashboard
**URL**: `GET /reports`

**Available Reports**:
1. **Transfer History**: All past transfers
2. **Allocation Fairness**: Fairness metrics over time
3. **Outlet Performance**: Per-outlet statistics
4. **Product Movement**: Product transfer patterns
5. **Execution Performance**: Speed and efficiency metrics

### Generate Report
**URL**: `POST /reports/generate`

**Parameters**:
- **Report Type**: History, Fairness, Performance, etc.
- **Date Range**: Start and end dates
- **Outlets**: Filter by specific outlets (optional)
- **Format**: HTML, Excel, CSV, PDF

**Example Request**:
```json
{
  "type": "transfer_history",
  "start_date": "2025-10-01",
  "end_date": "2025-10-10",
  "outlets": ["outlet1", "outlet2"],
  "format": "excel"
}
```

### Export Report
**URL**: `GET /reports/export`

**Formats**:
- **Excel**: `.xlsx` with formatted tables
- **CSV**: Plain CSV for data analysis
- **PDF**: Formatted printable report
- **JSON**: Raw data for API consumption

### Report Viewer
**URL**: `GET /reports/viewer`

**Features**:
- Interactive charts
- Sortable tables
- Filterable columns
- Drill-down capabilities
- Export buttons

---

## 5. 📋 LOGS & MONITORING

### View Logs
**URL**: `GET /logs`

**Log Types**:
- **Transfer Logs**: All transfer executions
- **Error Logs**: Errors and exceptions
- **Debug Logs**: Detailed execution traces
- **System Logs**: Server and database events

### Logs API
**URL**: `GET /logs/api`

**Query Parameters**:
- `level`: info, warning, error, debug
- `start_date`: Filter by date
- `limit`: Number of entries (default: 100)
- `offset`: Pagination offset

**Example**:
```
GET /logs/api?level=error&limit=50
```

### Clear Logs
**URL**: `POST /logs/clear`

**Options**:
- Clear all logs
- Clear logs older than X days
- Clear specific log types
- Archive before clearing

### Console View
**URL**: `GET /console`

**Features**:
- Live log streaming (SSE)
- Real-time updates
- Color-coded by severity
- Search and filter
- Auto-scroll

---

## 6. 🎛️ SETTINGS & PRESETS

### Application Settings
**URL**: `GET /settings`

**Categories**:

#### Engine Settings
- Warehouse ID
- Min/Max lines defaults
- Timeout settings
- Retry attempts
- Dry run default

#### Database Settings
- Connection pool size
- Query timeout
- Reconnect attempts
- Ping interval

#### Performance Settings
- Cache duration
- Query batch size
- Concurrent transfers
- Memory limit

#### Notification Settings
- Email alerts
- Slack integration
- Error notifications
- Daily reports

### Save Settings
**URL**: `POST /api/settings`

**Request Body**:
```json
{
  "engine": {
    "warehouse_id": "020b2c2a-4671-11f0-e200-8e55f1689700",
    "min_lines": 5,
    "max_lines": 50
  },
  "performance": {
    "cache_duration": 300,
    "batch_size": 100
  }
}
```

### Load Preset
**URL**: `POST /api/presets`

**Request Body**:
```json
{
  "preset": "balanced"
}
```

**Available Presets**:
- `minimal`: Fast, 3 lines
- `balanced`: Standard, 5 lines
- `conservative`: Safe, 10 lines
- `aggressive`: High volume, 1 line

---

## 7. 🔌 API USAGE

### API Authentication
All API endpoints require authentication via session or API key.

**Session Auth**:
```bash
# Login first
curl -X POST https://.../login \
  -d "username=admin&password=xxx"

# Then use session cookie
curl -b cookies.txt https://.../api/health
```

**API Key Auth** (if configured):
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  https://.../api/health
```

### Health Check
**URL**: `GET /api/health`

**Response**:
```json
{
  "success": true,
  "status": "healthy",
  "timestamp": "2025-10-10T11:30:00Z",
  "services": {
    "engine": "operational",
    "database": "connected",
    "queue": "ready"
  }
}
```

### Engine Status
**URL**: `GET /api/engine/status`

**Response**:
```json
{
  "status": "idle",
  "active_transfers": 0,
  "queue_depth": 0,
  "last_execution": "2025-10-10T10:15:00Z",
  "uptime": "24h 35m"
}
```

### Dashboard Metrics
**URL**: `GET /api/dashboard/metrics`

**Response**:
```json
{
  "engine": {
    "status": "operational",
    "throughput": 1931,
    "avg_response_time": "1.95ms"
  },
  "database": {
    "connections": 1,
    "queries_executed": 1250,
    "pool_size": 1
  },
  "system": {
    "memory_used": "45MB",
    "cpu_usage": "12%",
    "uptime": "24h 35m"
  }
}
```

### Execute Transfer (API)
**URL**: `POST /api/transfer/execute`

**Request**:
```bash
curl -X POST https://.../api/transfer/execute \
  -H "Content-Type: application/json" \
  -d '{
    "warehouse_id": "020b2c2a-4671-11f0-e200-8e55f1689700",
    "min_lines": 5,
    "dry": false
  }'
```

### Live Progress Stream (SSE)
**URL**: `GET /api/transfer/stream`

**Usage**:
```javascript
const eventSource = new EventSource('/api/transfer/stream');

eventSource.onmessage = function(event) {
  const data = JSON.parse(event.data);
  console.log('Progress:', data.progress + '%');
  console.log('Current:', data.current_outlet);
};
```

---

## 8. 🔧 TROUBLESHOOTING

### Common Issues

#### Issue: "Database connection failed"
**Symptoms**: Error messages about database connectivity

**Solutions**:
1. Check database credentials in settings
2. Verify database server is running
3. Check connection pool stats: `GET /api/health`
4. Test connection: Run helper methods test

**Command**:
```bash
php bin/test_helper_methods.php
```

#### Issue: "No eligible outlets found"
**Symptoms**: Transfer execution fails with this error

**Solutions**:
1. Verify warehouse ID is correct
2. Check outlets exist in database
3. Ensure outlets are not warehouses (is_warehouse = 0)
4. Check outlet stock levels

**Query**:
```sql
SELECT id, name, is_warehouse 
FROM vend_outlets 
WHERE is_warehouse = 0;
```

#### Issue: "Slow transfer execution"
**Symptoms**: Transfers take longer than expected

**Solutions**:
1. Check database connection pool: `GET /api/dashboard/metrics`
2. Review query execution counts
3. Check system resources (CPU, memory)
4. Optimize min_lines parameter (higher = fewer queries)
5. Enable query caching in settings

**Benchmark**:
- Target: <1000ms per transfer
- Achieved: ~2ms per transfer
- If >100ms: Check database indexes

#### Issue: "Memory usage growing"
**Symptoms**: System memory increasing over time

**Solutions**:
1. Check for memory leaks: Run performance tests
2. Close stale connections: `Database::closeAllConnections()`
3. Review connection pool stats
4. Restart PHP-FPM if needed

**Command**:
```bash
bash bin/run_advanced_tests.sh
```

#### Issue: "Transfer results not showing"
**Symptoms**: Execute succeeds but no results displayed

**Solutions**:
1. Check response format (JSON vs HTML)
2. Verify JavaScript console for errors
3. Check logs: `GET /logs?level=error`
4. Try API directly: `GET /api/transfer/results`

---

## 9. 💡 BEST PRACTICES

### Transfer Configuration
- ✅ **Start with dry run** to test configurations
- ✅ **Use presets** for common scenarios
- ✅ **Set reasonable min_lines** (5-10 for balance)
- ✅ **Test with small outlet sets** first
- ❌ Don't set min_lines too low (causes many transfers)
- ❌ Don't transfer without checking warehouse stock

### Performance Optimization
- ✅ **Enable connection pooling** (default)
- ✅ **Use caching** for repeated queries
- ✅ **Batch operations** where possible
- ✅ **Monitor pool stats** regularly
- ❌ Don't create new connections unnecessarily
- ❌ Don't run transfers during peak hours

### Monitoring & Maintenance
- ✅ **Check health endpoint** daily
- ✅ **Review error logs** weekly
- ✅ **Archive old logs** monthly
- ✅ **Run tests** after updates
- ✅ **Monitor connection pool** growth
- ❌ Don't ignore warning states
- ❌ Don't disable logging

### Security
- ✅ **Use HTTPS** always
- ✅ **Enable CSRF protection**
- ✅ **Validate all inputs**
- ✅ **Use API keys** for automation
- ✅ **Audit access logs**
- ❌ Don't share credentials
- ❌ Don't disable security features

---

## 10. 📞 SUPPORT & RESOURCES

### Documentation
- **Quick Start**: This guide
- **Technical Docs**: `COMPLETE_SYSTEM_VALIDATION_REPORT.md`
- **Helper Methods**: `HELPER_METHODS_IMPLEMENTATION.md`
- **API Reference**: See API section above

### Testing
- **Helper Methods**: `php bin/test_helper_methods.php`
- **Entry Points**: `php bin/test_entry_points.php`
- **Full Suite**: `bash bin/run_advanced_tests.sh`
- **Complete System**: `bash bin/test_complete_system.sh`

### Health Checks
```bash
# Quick health check
curl https://.../public/?endpoint=health

# Detailed metrics
curl https://.../public/?endpoint=api/dashboard/metrics

# Connection pool stats
curl https://.../public/?endpoint=api/engine/diagnostics
```

### Logs & Debugging
```bash
# View recent errors
curl https://.../public/logs/api?level=error&limit=50

# Live console (browser)
https://.../public/console

# Transfer execution log
tail -f storage/logs/transfer_engine.log
```

### Contact
- **Email**: pearce.stephens@ecigdis.co.nz
- **Company**: Ecigdis Ltd (The Vape Shed)
- **Internal Wiki**: https://www.wiki.vapeshed.co.nz
- **Staff Portal**: https://www.staff.vapeshed.co.nz

---

## 11. 🎓 TRAINING SCENARIOS

### Scenario 1: Your First Transfer
**Goal**: Execute a successful transfer

**Steps**:
1. Open dashboard
2. Navigate to Transfer page
3. Select preset: "Balanced"
4. Enable dry run
5. Execute transfer
6. Review results
7. Disable dry run
8. Execute again (real transfer)

**Expected Result**: Transfer completes in <10ms with allocations displayed

---

### Scenario 2: Monitor System Health
**Goal**: Verify system is healthy

**Steps**:
1. Open dashboard
2. Check all 4 health indicators (green)
3. Navigate to `/api/health`
4. Review connection pool stats
5. Check query execution count
6. Verify uptime

**Expected Result**: All systems operational, 1-2 connections active

---

### Scenario 3: Generate Weekly Report
**Goal**: Create transfer history report

**Steps**:
1. Navigate to Reports
2. Select "Transfer History"
3. Set date range: Last 7 days
4. Select all outlets
5. Choose format: Excel
6. Generate report
7. Download file

**Expected Result**: Excel file with all transfers for the week

---

### Scenario 4: Troubleshoot Slow Transfer
**Goal**: Identify and fix performance issue

**Steps**:
1. Run transfer, note slow execution
2. Open `/api/dashboard/metrics`
3. Check connection count (should be 1-2)
4. Check queries_executed growth
5. Run performance test suite
6. Review results
7. Adjust min_lines if needed

**Expected Result**: Transfer speed improves to <10ms

---

## 12. ⚡ KEYBOARD SHORTCUTS

### Dashboard
- `Ctrl+D` or `Cmd+D`: Dashboard
- `Ctrl+T` or `Cmd+T`: Transfer page
- `Ctrl+R` or `Cmd+R`: Reports
- `Ctrl+L` or `Cmd+L`: Logs
- `Ctrl+S` or `Cmd+S`: Settings

### Transfer Execution
- `Ctrl+Enter`: Execute transfer
- `Ctrl+E`: Enable/disable dry run
- `Esc`: Cancel execution

### Console
- `Ctrl+K`: Clear console
- `Ctrl+F`: Find in logs
- `Ctrl+Shift+R`: Refresh logs

---

## 13. 🔄 WORKFLOW EXAMPLES

### Daily Morning Restock
```
1. Open dashboard → Check system health (green)
2. Navigate to Transfer
3. Load preset: "Balanced"
4. Select outlets: All stores
5. Dry run: Disabled
6. Execute transfer
7. Wait ~5ms for completion
8. Review allocations
9. Generate report (optional)
```

### Weekly Performance Review
```
1. Navigate to Reports
2. Select "Performance Analytics"
3. Date range: Last 7 days
4. Generate report
5. Review metrics:
   - Average execution time
   - Total transfers
   - Allocation fairness
6. Export to Excel
7. Share with team
```

### Monthly Maintenance
```
1. Run full test suite: bash bin/test_complete_system.sh
2. Check all tests pass
3. Review error logs: GET /logs?level=error
4. Archive old logs: POST /logs/clear (archive option)
5. Check database pool stats
6. Verify backup processes
7. Update documentation if needed
```

---

## 14. 📊 KEY METRICS TO MONITOR

### Daily
- ✅ Transfer success rate (target: 100%)
- ✅ Average execution time (target: <10ms)
- ✅ Active database connections (target: 1-2)
- ✅ Error count (target: 0)

### Weekly
- ✅ Total transfers executed
- ✅ Allocation fairness score
- ✅ Query execution count
- ✅ System uptime percentage

### Monthly
- ✅ Performance trends
- ✅ Resource utilization
- ✅ Test suite results
- ✅ Documentation updates

---

## 🎉 CONGRATULATIONS!

You're now ready to use the Vapeshed Transfer Engine!

**Remember**:
- 🟢 Always check health status first
- 🧪 Use dry run when testing
- 📊 Monitor metrics regularly
- 📝 Review logs for issues
- 🔧 Run tests after updates
- 📞 Contact support if needed

**Happy Transferring!** 🚀

---

**Last Updated**: October 10, 2025  
**Version**: 1.0.0  
**Status**: Production Ready ✅
