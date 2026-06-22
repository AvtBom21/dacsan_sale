# Customer Account, Best Sellers, and Reviews Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add customer authentication/profile/order history, verified reviews, best sellers, and admin review moderation without requiring an OTP provider.

**Architecture:** Extend the current MySQL schema and repository/service pattern. Public API session endpoints serve the vanilla-JS storefront; review moderation uses the existing admin authorization and API patterns. Temporary integration and Playwright tests live outside the repository.

**Tech Stack:** PHP 8, PDO/MySQL, PHP sessions, vanilla JavaScript/CSS, Playwright.

---

### Task 1: Schema and customer authentication

**Files:**
- Modify: `database/database.sql`
- Modify: `app/Repositories/CustomerRepository.php`
- Create: `app/Services/CustomerAuthService.php`
- Modify: `public/api/index.php`

- [ ] Write temporary failing API tests for session, registration, login, profile update, and logout.
- [ ] Run tests and confirm missing endpoint failures.
- [ ] Add customer credential fields and product review schema.
- [ ] Implement session-based customer authentication and profile endpoints.
- [ ] Run tests and confirm authentication/profile behavior passes.

### Task 2: Customer orders and verified reviews

**Files:**
- Modify: `app/Repositories/OrderRepository.php`
- Create: `app/Repositories/ReviewRepository.php`
- Create: `app/Services/ReviewService.php`
- Modify: `public/api/index.php`

- [ ] Write temporary failing tests for authenticated order history and review eligibility.
- [ ] Implement order history by customer ID.
- [ ] Implement completed-order review creation and public approved review listing.
- [ ] Run tests and confirm authorization and validation behavior.

### Task 3: Best sellers and storefront account UI

**Files:**
- Modify: `app/Repositories/ProductRepository.php`
- Modify: `app/Services/CatalogService.php`
- Modify: `views/store/home.php`
- Modify: `public/assets/js/store.js`
- Modify: `public/assets/css/store.css`

- [ ] Write failing browser checks for login button, best seller region, review region, account profile, and checkout prefill.
- [ ] Add best-seller API payload based on non-cancelled order quantities.
- [ ] Replace section 1 trust pills with best sellers and section 2 regional cards with reviews.
- [ ] Add login/register/account/profile/order/review UI.
- [ ] Run desktop and mobile browser checks.

### Task 4: Admin review moderation

**Files:**
- Modify: `app/Services/AdminAuthorizationService.php`
- Modify: `admin/index.php`
- Modify: `admin/api/index.php`
- Modify: `views/admin/layout.php`
- Create: `views/admin/reviews.php`
- Modify: `public/assets/js/admin.js`

- [ ] Write failing HTTP/UI checks for the admin reviews page.
- [ ] Add review permissions, page data, moderation endpoints, and controls.
- [ ] Verify approve/reject controls use the internal admin modal feedback.

### Task 5: Production migration and verification

**Files:**
- No retained test files.

- [ ] Apply idempotent schema changes to `dac_san_nha_dan`.
- [ ] Run PHP/JS syntax checks and `git diff --check`.
- [ ] Run fresh API and browser QA at 1440×900 and 390×844.
- [ ] Remove temporary scripts/screenshots and confirm no test artefacts remain.
- [ ] Commit the implementation.

