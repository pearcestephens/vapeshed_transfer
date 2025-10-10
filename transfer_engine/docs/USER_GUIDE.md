# User Guide
**Vape Shed Transfer Engine - Complete User Manual**

Version: 1.0.0  
Last Updated: October 9, 2025  
For: Store Managers, Warehouse Staff, Administrators

---

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Dashboard Overview](#dashboard-overview)
4. [Creating Transfers](#creating-transfers)
5. [Managing Transfers](#managing-transfers)
6. [Inventory Management](#inventory-management)
7. [Analytics & Reports](#analytics--reports)
8. [Settings & Configuration](#settings--configuration)
9. [Mobile App Usage](#mobile-app-usage)
10. [Troubleshooting](#troubleshooting)
11. [Best Practices](#best-practices)
12. [FAQs](#faqs)

---

## Introduction

### What is the Transfer Engine?

The Vape Shed Transfer Engine is a comprehensive stock transfer management system designed to streamline inventory movement across all 17 retail stores in New Zealand. It automates transfer creation, tracking, and completion while integrating seamlessly with your existing Vend POS system.

### Key Benefits

- **Automated Stock Rebalancing:** AI-powered recommendations ensure optimal stock levels
- **Real-Time Tracking:** Monitor transfers from creation to completion
- **Reduced Errors:** Automated validation and barcode scanning minimize mistakes
- **Time Savings:** Create transfers in seconds, not minutes
- **Complete Visibility:** Comprehensive analytics and reporting
- **Mobile Ready:** Manage transfers on-the-go with mobile-optimized interface

### System Requirements

**Desktop/Laptop:**
- Modern web browser (Chrome 90+, Firefox 88+, Safari 14+, Edge 90+)
- Screen resolution: 1280x720 minimum (1920x1080 recommended)
- Stable internet connection (5 Mbps minimum)

**Mobile/Tablet:**
- iOS 13+ or Android 8+
- Safari, Chrome, or Firefox mobile browser
- 4G/LTE or Wi-Fi connection

---

## Getting Started

### Accessing the System

1. Navigate to: `https://transfer.vapeshed.co.nz`
2. Enter your username and password
3. Click "Sign In"

**First-Time Login:**
- Check your email for temporary password
- You'll be prompted to change your password
- Set up two-factor authentication (recommended)

### User Roles & Permissions

| Role | Permissions |
|------|-------------|
| **Administrator** | Full system access, configuration, user management |
| **Store Manager** | Create/approve transfers for assigned stores |
| **Warehouse Staff** | Pick, pack, and receive transfers |
| **Viewer** | Read-only access to transfers and reports |

### Navigation Overview

**Top Navigation Bar:**
- 🏠 Dashboard: Overview and quick actions
- 🔄 Transfers: Manage all transfers
- 📦 Inventory: Stock levels and product info
- 📊 Analytics: Reports and insights
- ⚙️ Settings: System configuration

**Side Navigation:**
- Quick filters by transfer status
- Recent transfers
- Favorite reports
- Notifications

---

## Dashboard Overview

### Main Dashboard

When you log in, the dashboard provides an at-a-glance view of your transfer operations.

#### Key Metrics Cards

**Active Transfers**
- Shows count of transfers currently in progress
- Click to view detailed list
- Color-coded by urgency (green/yellow/red)

**Pending Approvals**
- Transfers awaiting manager approval
- Requires action from you
- Badge notification on sidebar

**Today's Completions**
- Transfers completed in last 24 hours
- Percentage vs. target
- Trend indicator (↑/↓)

**Total Value**
- Combined value of active transfers
- Updated in real-time
- Filterable by date range

#### Recent Activity Feed

View latest transfer updates:
- ✅ Completed transfers
- 🔄 Status changes
- ⚠️ Alerts and issues
- 📝 Notes and comments

#### Quick Actions

**Create New Transfer**
```
Click "New Transfer" button → Select stores → Add items → Submit
```

**Approve Pending**
```
Click notification badge → Review transfer → Approve/Reject
```

**Check Status**
```
Enter transfer ID in search → View details
```

### System Health Panel

Monitor system status in real-time:
- **Engine Status:** Running/Stopped
- **Queue Depth:** Pending jobs count
- **Vend Sync:** Last sync timestamp
- **Alerts:** Active warnings

🟢 Green = Healthy  
🟡 Yellow = Warning  
🔴 Red = Critical

---

## Creating Transfers

### Manual Transfer Creation

#### Step 1: Select Stores

1. Click "New Transfer" button
2. **From Store:** Select source store from dropdown
3. **To Store:** Select destination store
4. Add **Notes** (optional but recommended)

**Tips:**
- Start typing store name to filter
- Recent store pairs appear at top
- Can't select same store for both

#### Step 2: Add Items

**Method A: Search by Product Name**
```
1. Type product name in search box
2. Select from dropdown
3. Enter quantity
4. Click "Add Item"
```

**Method B: Scan Barcode**
```
1. Click barcode icon
2. Scan product barcode
3. Quantity auto-fills to 1
4. Adjust if needed
```

**Method C: Browse Categories**
```
1. Click "Browse Products"
2. Navigate categories
3. Select products
4. Add to transfer
```

#### Item Details

For each item, specify:
- **Quantity:** How many units to transfer
- **Unit Cost:** Auto-filled from system
- **Notes:** Special handling instructions (optional)

**Validation Checks:**
- ✓ Sufficient stock at source store
- ✓ Valid product ID
- ✓ Quantity within limits (1-999)
- ✓ Total value under threshold ($10,000 default)

#### Step 3: Set Priority

Choose transfer urgency:
- **Low:** 5-7 business days
- **Normal:** 2-3 business days (default)
- **High:** 1-2 business days
- **Urgent:** Same day (requires approval)

#### Step 4: Review & Submit

**Review Screen Shows:**
- Source and destination stores
- Complete item list with quantities
- Total value
- Estimated delivery date
- Required approvals

**Actions:**
- ✏️ Edit: Modify any details
- 💾 Save Draft: Complete later
- 📧 Submit: Send for approval

**After Submission:**
- Transfer ID generated
- Confirmation email sent
- Appears in "Pending" list
- Notifications sent to approvers

### Bulk Transfer Creation

Create multiple transfers at once using CSV import.

#### Step 1: Download Template

1. Navigate to Transfers → Bulk Create
2. Click "Download CSV Template"
3. Open in Excel/Google Sheets

#### Step 2: Fill Template

**Required Columns:**
- `from_store_id`: Source store ID
- `to_store_id`: Destination store ID
- `product_sku`: Product SKU
- `quantity`: Transfer quantity

**Optional Columns:**
- `priority`: low/normal/high/urgent
- `notes`: Transfer notes
- `auto_approve`: true/false

**Example:**
```csv
from_store_id,to_store_id,product_sku,quantity,priority,notes
1,5,VAPE-MOD-001,10,normal,Weekly restock
1,5,VAPE-JUICE-123,25,normal,Weekly restock
2,8,VAPE-COIL-45,50,high,Customer request
```

#### Step 3: Upload & Validate

1. Click "Choose File"
2. Select your CSV
3. Click "Validate"
4. Review validation results

**Common Validation Errors:**
- ❌ Invalid store ID
- ❌ Product not found
- ❌ Insufficient stock
- ❌ Invalid quantity format

#### Step 4: Confirm & Create

1. Review transfer summary
2. Check "I confirm these transfers are correct"
3. Click "Create Transfers"

**Result:**
- All valid transfers created
- Transfer IDs emailed
- Error report for failed rows

### Smart Transfer Recommendations

Let the AI suggest optimal transfers based on:
- Historical sales data
- Current stock levels
- Seasonal trends
- Store performance

#### Using Recommendations

1. Navigate to Transfers → Recommendations
2. Review suggested transfers
3. Adjust quantities if needed
4. Select transfers to create
5. Click "Create Selected"

**Recommendation Details:**
- **Priority Score:** 1-100 (higher = more urgent)
- **Reasoning:** Why transfer is recommended
- **Projected Impact:** Expected sales increase
- **Risk Level:** Low/Medium/High

---

## Managing Transfers

### Viewing Transfer Details

#### Access Transfer Details

**Method 1: From Dashboard**
```
Dashboard → Recent Transfers → Click transfer
```

**Method 2: Search**
```
Enter transfer ID or reference → Press Enter
```

**Method 3: Filter List**
```
Transfers → Apply filters → Click transfer
```

#### Transfer Detail Screen

**Header Section:**
- Transfer ID & Reference
- Current Status Badge
- Priority Indicator
- Creation Date

**Store Information:**
- From: Store name, address, contact
- To: Store name, address, contact
- Distance & estimated transit time

**Items List:**
| Product | SKU | Quantity | Picked | Received | Value |
|---------|-----|----------|--------|----------|-------|
| Premium Vape Mod X1 | VAPE-MOD-001 | 10 | 10 | 0 | $250.00 |

**Timeline:**
```
✓ Created       Oct 9, 15:30  admin
✓ Approved      Oct 9, 15:35  manager
✓ Picked        Oct 9, 16:00  warehouse_staff
→ In Transit    Oct 9, 16:30  NZ Post
  Received      Pending
  Completed     Pending
```

**Actions:**
- 📝 Add Note
- 📄 Print Packing Slip
- 📧 Send Notification
- 🗑️ Cancel Transfer
- 📊 View Analytics

### Transfer Statuses

#### Status Flow

```
Created → Pending Approval → Approved → Picking → Packed → 
In Transit → Receiving → Completed
```

**Alternative Paths:**
- Rejected (from Pending Approval)
- Cancelled (from any status before Packed)
- On Hold (temporary pause)

#### Status Descriptions

**Created**
- Transfer just created
- Awaiting approval if required
- Can be edited or cancelled

**Pending Approval**
- Requires manager approval
- Notification sent to approvers
- Visible in approval queue

**Approved**
- Manager approved transfer
- Ready for picking
- Cannot be cancelled without reason

**Picking**
- Warehouse staff gathering items
- Items can be marked as picked
- Partial picks allowed

**Packed**
- All items picked and packed
- Ready for shipment
- Packing slip printed

**In Transit**
- Package shipped
- Tracking number available
- ETA displayed

**Receiving**
- Package arrived at destination
- Items being checked in
- Discrepancies can be reported

**Completed**
- All items received and verified
- Stock levels updated
- Transfer closed

**Cancelled**
- Transfer cancelled
- Reason required
- Stock restored if picked

### Approving Transfers

#### For Managers

1. Navigate to Dashboard → Pending Approvals
2. Click transfer to review
3. Review details:
   - Source/destination stores
   - Items and quantities
   - Total value
   - Priority and notes
   - Requestor information

4. **Approve:**
   - Click "Approve" button
   - Optionally add approval notes
   - Confirm approval

5. **Reject:**
   - Click "Reject" button
   - **Required:** Rejection reason
   - Notify requestor

6. **Request Changes:**
   - Click "Request Changes"
   - Specify what needs modification
   - Transfer returns to creator

#### Bulk Approval

Approve multiple transfers at once:

1. Go to Pending Approvals
2. Select transfers (checkboxes)
3. Click "Bulk Approve"
4. Review summary
5. Confirm

**Auto-Approval Rules:**
- Transfers under $500 (configurable)
- Between specific store pairs
- For specific product categories
- During scheduled rebalancing

### Picking Transfers

#### For Warehouse Staff

**Step 1: Start Picking**
```
1. Navigate to Transfers → Ready for Picking
2. Select transfer
3. Click "Start Picking"
```

**Step 2: Pick Items**

**Method A: Manual Check**
```
For each item:
1. Locate product
2. Verify SKU matches
3. Count quantity
4. Check "Picked" box
```

**Method B: Barcode Scanning**
```
1. Scan product barcode
2. System shows expected quantity
3. Scan each unit (or enter count)
4. System marks as picked
```

**Handling Issues:**
- **Item Not Found:** Mark as unavailable, system suggests substitute
- **Wrong Quantity:** Enter actual available quantity
- **Damaged Stock:** Mark item, add note, exclude from transfer

**Step 3: Complete Picking**
```
1. Verify all items picked
2. Print packing slip
3. Click "Complete Picking"
4. Transfer moves to "Packed" status
```

### Receiving Transfers

#### For Destination Store

**Step 1: Check In Package**
```
1. Navigate to Transfers → In Transit
2. Find your incoming transfer
3. Click "Start Receiving"
4. Enter tracking number (optional)
```

**Step 2: Verify Items**

**Method A: Manual Verification**
```
For each item:
1. Open package
2. Count items
3. Check for damage
4. Enter received quantity
```

**Method B: Barcode Scanning**
```
1. Scan each item barcode
2. System counts automatically
3. Verify against packing slip
4. Flag any discrepancies
```

**Handling Discrepancies:**
- **Missing Items:** Enter actual quantity received
- **Damaged Items:** Mark as damaged, add photos
- **Wrong Items:** Report incorrect product
- **Surplus Items:** Report extra items

**Step 3: Complete Receiving**
```
1. Verify all items processed
2. Review discrepancies (if any)
3. Add receiving notes
4. Click "Complete Receiving"
5. Stock automatically updated
```

### Cancelling Transfers

#### When Can You Cancel?

- ✅ Created status
- ✅ Pending Approval status
- ✅ Approved status (with reason)
- ⚠️ Picking status (requires manager approval)
- ❌ Packed or later (contact support)

#### Cancellation Process

1. Open transfer details
2. Click "Cancel Transfer"
3. **Required:** Select cancellation reason
   - Stock no longer needed
   - Incorrect items
   - Store request
   - Duplicate transfer
   - Other (specify)
4. Add detailed notes
5. Confirm cancellation

**Effects of Cancellation:**
- Transfer status → Cancelled
- Picked items returned to stock
- All parties notified
- Recorded in audit log

---

## Inventory Management

### Viewing Stock Levels

#### Store Stock View

1. Navigate to Inventory → Stock Levels
2. Select store from dropdown
3. View product list with:
   - Product name & SKU
   - Current quantity
   - Reorder point
   - Status (OK/Low/Out)

**Color Coding:**
- 🟢 Green: Stock OK
- 🟡 Yellow: Low stock (below reorder point)
- 🔴 Red: Out of stock

#### Multi-Store Comparison

View stock across all stores:

1. Navigate to Inventory → Multi-Store View
2. Products listed in rows
3. Stores listed in columns
4. Heat map shows stock levels

**Features:**
- Sort by any column
- Filter by product category
- Export to CSV
- Quick transfer creation

### Stock Alerts

#### Alert Types

**Low Stock Alert**
- Triggered when stock below reorder point
- Daily email summary
- Dashboard notification

**Out of Stock Alert**
- Immediate notification
- High priority
- Includes sales velocity data

**Overstock Alert**
- Stock significantly above average
- Suggests transfers to other stores
- Weekly report

#### Managing Alerts

1. Navigate to Inventory → Alerts
2. View active alerts
3. Actions:
   - Create transfer
   - Adjust reorder point
   - Dismiss alert
   - Snooze (7/14/30 days)

### Transfer History

#### View Product Transfer History

1. Navigate to Inventory → Products
2. Search for product
3. Click "Transfer History"

**Shows:**
- All transfers involving product
- Date range selector
- Store-to-store breakdown
- Total quantities moved

**Insights:**
- Most frequent routes
- Average transfer size
- Seasonality patterns
- Success rates

---

## Analytics & Reports

### Pre-Built Reports

#### Transfer Summary Report

**Access:** Analytics → Transfer Summary

**Shows:**
- Total transfers by period
- Completion rates
- Average transfer value
- Top products transferred
- Store-to-store breakdown

**Filters:**
- Date range
- Store selection
- Status filter
- Priority filter

**Export Options:**
- PDF
- Excel
- CSV

#### Store Performance Report

**Access:** Analytics → Store Performance

**Metrics:**
- Transfers initiated vs. received
- Average processing time
- On-time completion rate
- Error rate
- Efficiency score

**Visualization:**
- Bar charts
- Trend lines
- Heat maps

#### Product Movement Report

**Access:** Analytics → Product Movement

**Shows:**
- Most transferred products
- Transfer velocity
- Stock turnover rate
- Store preferences

**Use Cases:**
- Identify popular products
- Optimize stock distribution
- Plan future transfers

### Custom Reports

#### Create Custom Report

1. Navigate to Analytics → Custom Reports
2. Click "New Custom Report"
3. **Select Metrics:**
   - Transfer count
   - Transfer value
   - Completion time
   - Stock levels
   - And more...

4. **Set Filters:**
   - Date range
   - Stores
   - Products
   - Status

5. **Choose Visualization:**
   - Table
   - Line chart
   - Bar chart
   - Pie chart

6. **Save Report:**
   - Name your report
   - Set schedule (optional)
   - Share with team

### Dashboards & Widgets

#### Executive Dashboard

Real-time KPI monitoring:
- Transfer velocity
- Completion rates
- Cost per transfer
- Time to delivery
- System health

**Customization:**
- Drag-and-drop widgets
- Resize panels
- Set refresh intervals
- Export snapshots

#### Operational Dashboard

Store-level metrics:
- Today's transfers
- Pending tasks
- Alerts and issues
- Team performance

### Scheduled Reports

#### Email Reports

1. Open any report
2. Click "Schedule"
3. Set frequency:
   - Daily
   - Weekly
   - Monthly
   - Custom

4. Select recipients
5. Choose format (PDF/Excel)
6. Save schedule

**Example Schedules:**
- Daily transfer summary @ 8:00 AM
- Weekly performance report (Mondays)
- Monthly executive summary

---

## Settings & Configuration

### User Profile

#### Update Profile

1. Click your name (top right)
2. Select "Profile"
3. Edit:
   - Display name
   - Email address
   - Phone number
   - Notification preferences

4. Click "Save Changes"

#### Change Password

1. Profile → Security
2. Enter current password
3. Enter new password (twice)
4. Requirements:
   - Minimum 12 characters
   - Upper and lowercase letters
   - Numbers
   - Special characters

#### Two-Factor Authentication

**Setup:**
1. Profile → Security → 2FA
2. Scan QR code with authenticator app
3. Enter verification code
4. Save backup codes

**Supported Apps:**
- Google Authenticator
- Microsoft Authenticator
- Authy

### Notification Settings

#### Configure Notifications

1. Settings → Notifications
2. Choose channels:
   - ✉️ Email
   - 📱 SMS (if enabled)
   - 🔔 In-app
   - 📧 Webhook

3. Select events:
   - Transfer created
   - Approval required
   - Status changed
   - Delays/issues
   - Completion

4. Set quiet hours (optional)
5. Save preferences

### Transfer Preferences

#### Default Settings

1. Settings → Transfer Defaults
2. Configure:
   - Default priority level
   - Auto-approval thresholds
   - Preferred carriers
   - Packing slip template

3. Save defaults

#### Store Preferences

Set per-store preferences:
- Operating hours
- Receiving capacity
- Preferred delivery days
- Special instructions

---

## Mobile App Usage

### Mobile Features

The Transfer Engine is fully responsive and works great on mobile devices.

**Optimized for Mobile:**
- ✓ Create transfers
- ✓ Approve transfers
- ✓ Pick items (with camera barcode scanning)
- ✓ Receive transfers
- ✓ View status
- ✓ Real-time notifications

### Barcode Scanning

#### Using Mobile Camera

1. Open transfer on mobile
2. Tap barcode icon
3. Allow camera access
4. Point camera at barcode
5. System auto-scans
6. Verify product matches

**Tips:**
- Ensure good lighting
- Hold camera steady
- Clean camera lens
- Hold ~6 inches away

### Offline Mode

**Limited Functionality:**
- View cached transfers
- Record picking/receiving offline
- Syncs when connection restored

**Not Available Offline:**
- Creating new transfers
- Approval workflows
- Real-time status

---

## Troubleshooting

### Common Issues

#### Can't Log In

**Problem:** Invalid username/password

**Solutions:**
1. Verify username (case-sensitive)
2. Reset password: Click "Forgot Password"
3. Check email for reset link
4. Contact IT if still locked out

**Problem:** "Account locked"

**Cause:** Too many failed attempts

**Solution:** Wait 30 minutes or contact administrator

#### Transfer Won't Submit

**Problem:** "Insufficient stock" error

**Solutions:**
1. Check source store stock levels
2. Reduce transfer quantity
3. Split into multiple transfers
4. Contact store manager

**Problem:** "Validation error"

**Solutions:**
1. Review all required fields
2. Check product SKUs are valid
3. Verify store selection
4. Clear browser cache

#### Items Not Scanning

**Problem:** Barcode won't scan

**Solutions:**
1. Clean camera lens
2. Improve lighting
3. Try manual SKU entry
4. Check barcode is valid
5. Report damaged barcode

#### Transfer Stuck "In Transit"

**Problem:** Status not updating

**Solutions:**
1. Check tracking number
2. Contact carrier
3. Manual status update
4. Contact support if >48 hours

### Error Messages

#### "VEND_SYNC_ERROR"

**Meaning:** Connection to Vend POS failed

**Actions:**
1. Check internet connection
2. Verify Vend is accessible
3. Wait 5 minutes, retry
4. Contact support if persists

#### "RATE_LIMIT_EXCEEDED"

**Meaning:** Too many requests

**Actions:**
1. Wait 60 seconds
2. Reduce request frequency
3. Use bulk operations
4. Contact support if frequent

#### "INSUFFICIENT_PERMISSIONS"

**Meaning:** You lack required access

**Actions:**
1. Verify your user role
2. Request permission from manager
3. Contact administrator

### Getting Help

#### In-App Help

- Click "?" icon (any page)
- Contextual help for that page
- Searchable help articles
- Video tutorials

#### Support Channels

**Email:** support@vapeshed.co.nz  
**Phone:** 0800-VAPESHED  
**Hours:** Mon-Fri 9:00-17:00 NZT

**Urgent Issues:**
- Call during business hours
- Email anytime
- Emergency: on-call support

#### Feedback & Suggestions

We love feedback!
- Settings → Feedback
- Describe suggestion
- Attach screenshots
- Vote on others' ideas

---

## Best Practices

### Creating Transfers

**✅ DO:**
- Add detailed notes
- Double-check quantities
- Use appropriate priority
- Group related items
- Schedule during low-traffic times

**❌ DON'T:**
- Create duplicate transfers
- Use "Urgent" unnecessarily
- Skip approval process
- Transfer damaged stock

### Picking & Packing

**✅ DO:**
- Verify each item
- Use bubble wrap for fragile items
- Include packing slip
- Seal boxes securely
- Update status immediately

**❌ DON'T:**
- Rush the process
- Mix multiple transfers
- Forget to update quantities
- Use damaged packaging

### Receiving

**✅ DO:**
- Check package condition
- Count all items
- Report discrepancies immediately
- Update system promptly
- Store properly

**❌ DON'T:**
- Accept damaged packages
- Assume quantities are correct
- Delay status updates
- Skip quality checks

### System Maintenance

**Regular Tasks:**
- Review pending approvals daily
- Check alerts weekly
- Archive old transfers monthly
- Update reorder points quarterly

---

## FAQs

### General Questions

**Q: How long does a typical transfer take?**  
A: Normal priority: 2-3 business days. High priority: 1-2 days. Urgent: Same day (requires approval).

**Q: Can I track my transfer in real-time?**  
A: Yes, once shipped you'll see tracking updates from the carrier.

**Q: What if items are damaged in transit?**  
A: Mark items as damaged during receiving. System will initiate claim process.

**Q: Can I cancel a transfer that's already shipped?**  
A: Contact the destination store to refuse delivery, then contact support.

### Technical Questions

**Q: Why is the system slow?**  
A: Check your internet connection. If persists, contact support.

**Q: Can I use the system on my phone?**  
A: Yes! The system is fully mobile-responsive.

**Q: Is my data secure?**  
A: Yes, we use bank-level encryption and security measures.

**Q: Can I integrate with other systems?**  
A: Yes, via our API. Contact support for documentation.

### Process Questions

**Q: Who needs to approve my transfers?**  
A: Depends on transfer value and store policy. Usually store manager.

**Q: What if I pick the wrong quantity?**  
A: Enter actual quantity picked. System will update transfer.

**Q: Can I modify a transfer after submission?**  
A: Only before approval. After that, you'll need to cancel and recreate.

**Q: How do I report a problem?**  
A: Click "Report Issue" on transfer details page.

---

## Keyboard Shortcuts

Speed up your workflow with these shortcuts:

| Shortcut | Action |
|----------|--------|
| `Ctrl/Cmd + N` | New Transfer |
| `Ctrl/Cmd + F` | Search |
| `Ctrl/Cmd + K` | Command Palette |
| `Ctrl/Cmd + S` | Save Draft |
| `/` | Focus Search |
| `?` | Show Help |
| `Esc` | Close Modal |
| `Alt + D` | Go to Dashboard |
| `Alt + T` | Go to Transfers |
| `Alt + I` | Go to Inventory |
| `Alt + A` | Go to Analytics |

---

## Glossary

**Transfer:** Movement of stock from one store to another

**SKU:** Stock Keeping Unit - unique product identifier

**Picking:** Process of gathering items for transfer

**Packing Slip:** Document listing transfer contents

**Consignment:** Vend term for stock receipt

**Reorder Point:** Stock level triggering replenishment

**Stock Take:** Physical inventory count

**Variance:** Difference between expected and actual stock

**Dead Stock:** Inventory not selling

**Turnover Rate:** How quickly stock sells

---

**Need More Help?**

📧 Email: support@vapeshed.co.nz  
📞 Phone: 0800-VAPESHED  
🌐 Web: https://help.vapeshed.co.nz  
💬 Chat: Available in-app (Mon-Fri 9-5)

---

**Document Version:** 1.0.0  
**Last Updated:** October 9, 2025  
**Feedback:** docs-feedback@vapeshed.co.nz
