# Storefront Contact Information Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hiển thị số điện thoại và Zalo từ cấu hình quản trị trong section 2 của storefront.

**Architecture:** Thêm markup có trạng thái ẩn trong view, tải public settings cùng catalog ở lúc khởi tạo, rồi render dữ liệu vào khối liên hệ. CSS mở rộng hệ kính hiện tại và có breakpoint mobile.

**Tech Stack:** PHP view, vanilla JavaScript, CSS, Playwright QA tạm.

---

### Task 1: Contact UI and data binding

**Files:**
- Modify: `views/store/home.php`
- Modify: `public/assets/js/store.js`
- Modify: `public/assets/css/store.css`
- Test: temporary Playwright script outside repository

- [x] **Step 1: Write the failing browser test**

Kiểm tra section 2 có `[data-store-contact]`, số điện thoại `0378 456 926`, liên kết `tel:0378456926` và liên kết Zalo.

- [x] **Step 2: Run test to verify it fails**

Run: `node %TEMP%\dsnd-store-contact\contact.test.js`

Expected: FAIL vì khối liên hệ chưa tồn tại.

- [x] **Step 3: Add minimal markup and settings rendering**

Thêm khối liên hệ ẩn vào `.story-copy`; tải action `settings`; chuẩn hóa hiển thị số điện thoại; gán `href` an toàn cho điện thoại và Zalo.

- [x] **Step 4: Add responsive styling**

Tạo `.story-contact`, `.story-contact-actions` và breakpoint mobile bảo đảm nút không chồng lấn.

- [x] **Step 5: Verify behavior and layout**

Run Playwright ở 1440×900 và 390×844; kiểm tra nội dung, URL, console và overflow.

- [x] **Step 6: Run static checks and commit**

Run:

```powershell
node --check public/assets/js/store.js
C:\xampp\php\php.exe -l views/store/home.php
git diff --check
```

Commit các file storefront và tài liệu thiết kế/kế hoạch.
