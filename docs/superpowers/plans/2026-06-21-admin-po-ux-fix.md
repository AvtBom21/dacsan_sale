# Admin PO Workflow and UX Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make order/PO actions reliable and understandable, repair stale order–PO status data, and replace the rough admin styling with a practical operations dashboard.

**Architecture:** Keep the existing PHP/PDO/vanilla-JavaScript structure. Add explicit PO linkage fields to the admin order query, centralize linked-order status synchronization in the purchase-plan repository/service, and expose eligibility reasons in server-rendered HTML. Use temporary external regression scripts and Playwright for verification because the production repository intentionally has no retained test suite.

**Tech Stack:** PHP 8, PDO MySQL/MariaDB, vanilla JavaScript, CSS, XAMPP Apache/MySQL, temporary PHP/Playwright checks outside the repository.

---

### Task 1: Reproduce and guard the PO status bug

**Files:**
- Create temporarily outside repository: `%TEMP%\dsnd-admin-fix\po_status_check.php`
- Modify: `app/Repositories/PurchasePlanRepository.php`
- Modify: `app/Services/PurchasePlanService.php`

- [ ] Write a temporary regression script that creates a draft PO with a linked confirmed order, calls `markPlanOrdered()`, and asserts both PO and order become `ordered`.
- [ ] Run the script and verify it fails because the linked order remains `confirmed`.
- [ ] Add repository methods that synchronize linked orders for `draft`, `ordered`, and `received` PO states.
- [ ] Call synchronization from PO mark-ordered, cancellation, and receipt paths.
- [ ] Run the temporary regression script and verify it passes.

### Task 2: Repair production status inconsistencies

**Files:**
- Create: `database/migrations/20260621_reconcile_order_po_status.sql`

- [ ] Add idempotent SQL that maps linked orders to `received` for fully received PO, `ordered` for ordered/partial PO, and `confirmed` for draft PO.
- [ ] Apply the migration once to `dac_san_nha_dan`.
- [ ] Query all orders and verify no linked order remains `new`.

### Task 3: Explain PO eligibility in the order list

**Files:**
- Modify: `app/Repositories/AdminDashboardRepository.php`
- Modify: `views/admin/orders.php`
- Modify: `public/assets/js/admin.js`

- [ ] Extend order rows with linked PO ID/status.
- [ ] Render Vietnamese status labels and a “Tình trạng PO” column.
- [ ] For eligible rows, render an enabled checkbox; for ineligible rows, render a reason and link to the existing PO.
- [ ] Disable PO preview/create buttons when no eligible order is selected and show the selected count.
- [ ] Show inline success/error feedback instead of silent controls.

### Task 4: Restyle the admin operations interface

**Files:**
- Modify: `public/assets/css/admin.css`
- Modify: `views/admin/layout.php`
- Modify: `views/admin/dashboard.php`

- [ ] Introduce consistent color, spacing, typography, surface, focus, disabled, loading, table, and responsive tokens.
- [ ] Improve sidebar, topbar, panels, toolbars, tables, action buttons, forms, status chips, and mobile layout.
- [ ] Preserve the table-first information architecture and current PHP routes.

### Task 5: Production verification and cleanup

**Files:**
- Temporary only: `%TEMP%\dsnd-admin-fix\*`

- [ ] Run PHP lint and JavaScript syntax checks.
- [ ] Run temporary service regression checks.
- [ ] Run Playwright on Apache for order confirmation, eligible selection, PO preview/create, draft→ordered, and detail links.
- [ ] Inspect desktop and mobile screenshots against the approved concept.
- [ ] Remove all temporary fixtures, scripts, screenshots, and QA rows.
- [ ] Commit only application, migration, and documentation changes; preserve unrelated storefront edits.
