<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$pdo = db();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_GET['action'] ?? '') === 'checkout') {
    handle_store_checkout($pdo);
}

$storeCatalog = load_store_catalog($pdo);
$storeSettings = get_settings_map($pdo);
$defaultShippingZone = fetch_shipping_zone($pdo, (string)($storeSettings['default_shipping_zone_id'] ?? ''));
$checkoutToken = ensure_checkout_token();
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Đặc Sản Nhà Dân — Cinematic Journey</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* === 1. CORE TOKENS & RESET === */
        :root {
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --primary: #E8651A;
            --forest: #173f2a;
            --earth: #6f4a2f;
            --sun: #f2a83b;
            --sea: #0d6f83;
            --sand: #e8c78d;
            --glass-bg: rgba(10, 15, 20, 0.4);
            --glass-border: rgba(255, 255, 255, 0.1);
            --scrollbar-track: rgba(4, 8, 12, 0.52);
            --scrollbar-track-line: rgba(255, 255, 255, 0.055);
            --scrollbar-thumb-ff: rgba(80, 88, 96, 0.86);
            --scrollbar-thumb-hover-ff: rgba(112, 100, 88, 0.92);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body, html {
            background-color: #020202; color: #fff;
            font-family: var(--font-main);
            overscroll-behavior: none; /* Ngăn iOS bounce */
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* === DARK GLASS SCROLLBARS === */
        .glass-scroll {
            --scrollbar-width: 10px;
            --scrollbar-height: 8px;
            --scrollbar-thumb-border: 2px;
            --scrollbar-thumb-bg: linear-gradient(180deg, rgba(92, 102, 112, 0.82), rgba(36, 44, 52, 0.92));
            --scrollbar-thumb-hover-bg: linear-gradient(180deg, rgba(124, 111, 99, 0.92), rgba(58, 49, 43, 0.96));
            --scrollbar-thumb-active-bg: linear-gradient(180deg, rgba(70, 77, 86, 0.98), rgba(24, 30, 38, 0.98));
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb-ff) var(--scrollbar-track);
        }

        .glass-scroll:is(:hover, :focus-within) {
            scrollbar-color: var(--scrollbar-thumb-hover-ff) var(--scrollbar-track);
        }

        .glass-scroll::-webkit-scrollbar {
            width: var(--scrollbar-width);
            height: var(--scrollbar-height);
        }

        .glass-scroll::-webkit-scrollbar-track {
            border-radius: 999px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.035), rgba(255, 255, 255, 0.012)),
                var(--scrollbar-track);
            box-shadow: inset 0 0 0 1px var(--scrollbar-track-line);
        }

        .glass-scroll::-webkit-scrollbar-thumb {
            border: var(--scrollbar-thumb-border) solid rgba(3, 6, 9, 0.7);
            border-radius: 999px;
            background: var(--scrollbar-thumb-bg);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.18),
                inset 0 0 0 1px rgba(255, 255, 255, 0.07),
                0 0 0 1px rgba(232, 101, 26, 0.08);
        }

        .glass-scroll:is(:hover, :focus-within)::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb-hover-bg);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.22),
                inset 0 0 0 1px rgba(232, 101, 26, 0.2),
                0 0 12px rgba(232, 101, 26, 0.12);
        }

        .glass-scroll::-webkit-scrollbar-thumb:hover {
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.24),
                inset 0 0 0 1px rgba(232, 101, 26, 0.28),
                0 0 14px rgba(232, 101, 26, 0.16);
        }

        .glass-scroll::-webkit-scrollbar-thumb:active {
            background: var(--scrollbar-thumb-active-bg);
            box-shadow:
                inset 0 2px 8px rgba(0, 0, 0, 0.34),
                inset 0 0 0 1px rgba(232, 101, 26, 0.16);
        }

        .glass-scroll::-webkit-scrollbar-corner {
            background: transparent;
        }

        .glass-scroll--thin {
            --scrollbar-width: 8px;
            --scrollbar-height: 8px;
            --scrollbar-thumb-border: 2px;
            --scrollbar-track: rgba(4, 8, 12, 0.38);
            --scrollbar-thumb-ff: rgba(72, 80, 88, 0.82);
        }

        @media (pointer: coarse), (max-width: 768px) {
            .glass-scroll {
                --scrollbar-width: 8px;
                --scrollbar-height: 7px;
            }

            .glass-scroll::-webkit-scrollbar-track {
                background: transparent;
                box-shadow: none;
            }
        }

        /* === HEADER NAVIGATION === */
        .glass-header {
            position: fixed;
            top: 16px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            width: min(calc(100% - 32px), 1180px);
            min-height: 64px;
            padding: 10px 14px 10px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.02));
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-top: 1px solid rgba(255, 255, 255, 0.35);
            border-left: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 8px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
        }

        .logo {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            white-space: nowrap;
        }

        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .nav-links a {
            min-height: 38px;
            padding: 0 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.055);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
            transition: color 0.25s ease, background 0.25s ease, border-color 0.25s ease, transform 0.25s ease;
            white-space: nowrap;
        }

        .nav-links a:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.13);
            border-color: rgba(255, 255, 255, 0.34);
            transform: translateY(-1px);
        }

        .nav-links a.active {
            color: #fff;
            border-color: rgba(232, 101, 26, 0.82);
            background: rgba(232, 101, 26, 0.2);
            box-shadow: 0 0 0 3px rgba(232, 101, 26, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.12);
        }

        .auth-cart {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            white-space: nowrap;
        }

        .header-btn {
            position: relative;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font: inherit;
            font-size: 0.86rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.25s ease, border-color 0.25s ease;
        }

        .header-btn:hover {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.34);
        }

        .login-popover,
        .password-popover {
            position: absolute;
            top: calc(100% + 12px);
            right: 56px;
            z-index: 1300;
            width: min(320px, calc(100vw - 28px));
            padding: 18px;
            display: none;
            background: rgba(9, 12, 15, 0.78);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-top-color: rgba(255, 255, 255, 0.32);
            border-left-color: rgba(255, 255, 255, 0.28);
            border-radius: 8px;
            box-shadow: 0 18px 48px rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            white-space: normal;
        }

        .password-popover {
            right: 56px;
        }

        .login-popover.open,
        .password-popover.open {
            display: block;
        }

        .login-popover::before,
        .password-popover::before {
            content: '';
            position: absolute;
            top: -7px;
            right: 34px;
            width: 12px;
            height: 12px;
            background: rgba(9, 12, 15, 0.78);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            border-left: 1px solid rgba(255, 255, 255, 0.2);
            transform: rotate(45deg);
        }

        .login-title,
        .password-title {
            margin-bottom: 4px;
            font-size: 1.05rem;
            font-weight: 700;
        }

        .login-hint,
        .password-hint {
            margin-bottom: 14px;
            color: rgba(255, 255, 255, 0.62);
            font-size: 0.78rem;
            line-height: 1.45;
        }

        .login-form,
        .password-form {
            display: grid;
            gap: 10px;
        }

        .login-field,
        .password-field {
            display: grid;
            gap: 6px;
        }

        .login-field label,
        .password-field label {
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.78rem;
            font-weight: 600;
        }

        .login-field input,
        .password-field input {
            width: 100%;
            min-height: 42px;
            padding: 0 12px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.07);
            color: #fff;
            font: inherit;
            outline: none;
        }

        .login-field input:focus,
        .password-field input:focus {
            border-color: rgba(232, 101, 26, 0.9);
            box-shadow: 0 0 0 3px rgba(232, 101, 26, 0.12);
        }

        .login-error,
        .password-message {
            min-height: 18px;
            color: #ff9c7d;
            font-size: 0.78rem;
            line-height: 1.35;
        }

        .password-message.success {
            color: #9ee6b8;
        }

        .password-step[hidden] {
            display: none;
        }

        .password-step {
            display: grid;
            gap: 10px;
        }

        .login-submit,
        .password-submit {
            min-height: 42px;
            border: 0;
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .login-submit:hover,
        .password-submit:hover {
            background: #d05815;
        }

        .login-links {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 2px;
        }

        .login-link-btn {
            border: 0;
            background: transparent;
            color: rgba(255, 255, 255, 0.74);
            font: inherit;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
        }

        .login-link-btn:hover {
            color: #fff;
        }

        .account-anchor {
            --account-toggle-size: 42px;
            --account-arrow-size: 12px;
            position: relative;
            display: none;
            flex: 0 0 auto;
        }

        body.logged-in .account-anchor {
            display: inline-flex;
        }

        .account-toggle {
            width: var(--account-toggle-size);
            height: var(--account-toggle-size);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 50%;
            overflow: hidden;
        }

        .account-toggle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .account-menu {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            z-index: 1300;
            width: min(280px, calc(100vw - 28px));
            padding: 12px;
            display: none;
            background: rgba(9, 12, 15, 0.78);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-top-color: rgba(255, 255, 255, 0.32);
            border-left-color: rgba(255, 255, 255, 0.28);
            border-radius: 8px;
            box-shadow: 0 18px 48px rgba(0, 0, 0, 0.42);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            white-space: normal;
        }

        .account-menu.open {
            display: block;
        }

        .account-menu::before {
            content: '';
            position: absolute;
            top: -7px;
            right: calc((var(--account-toggle-size) - var(--account-arrow-size)) / 2);
            width: var(--account-arrow-size);
            height: var(--account-arrow-size);
            background: rgba(9, 12, 15, 0.78);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            border-left: 1px solid rgba(255, 255, 255, 0.2);
            transform: rotate(45deg);
        }

        .account-card {
            display: grid;
            grid-template-columns: 44px 1fr;
            gap: 10px;
            align-items: center;
            padding: 8px;
            margin-bottom: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .account-card img {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
        }

        .account-name {
            font-weight: 700;
            line-height: 1.25;
        }

        .account-email {
            margin-top: 3px;
            color: rgba(255, 255, 255, 0.58);
            font-size: 0.78rem;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .account-menu-btn {
            width: 100%;
            min-height: 40px;
            padding: 0 10px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: rgba(255, 255, 255, 0.82);
            font: inherit;
            font-size: 0.86rem;
            font-weight: 600;
            text-align: left;
            cursor: pointer;
        }

        .account-menu-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .account-menu-btn.danger {
            color: #ffb199;
        }

        .cart-btn,
        .favorite-btn {
            min-width: 46px;
            padding: 0 12px;
        }

        .cart-badge,
        .favorite-badge {
            position: absolute;
            top: -7px;
            right: -7px;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: var(--primary);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
            line-height: 1;
        }

        /* === CART & FAVORITES DRAWERS === */
        .cart-sidebar,
        .favorite-sidebar {
            position: fixed;
            top: 0;
            right: 0;
            z-index: 1200;
            width: min(420px, 100vw);
            height: 100vh;
            display: flex;
            flex-direction: column;
            background:
                linear-gradient(135deg, rgba(15, 19, 24, 0.66), rgba(6, 10, 14, 0.46)),
                rgba(6, 9, 12, 0.38);
            border-left: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: -22px 0 64px rgba(0, 0, 0, 0.34);
            backdrop-filter: blur(16px) saturate(1.15);
            -webkit-backdrop-filter: blur(16px) saturate(1.15);
            transform: translateX(105%);
            transition: transform 0.42s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .cart-sidebar.open,
        .favorite-sidebar.open { transform: translateX(0); }

        .cart-header,
        .favorite-header,
        .cart-footer,
        .favorite-footer {
            padding: 22px;
            border-color: rgba(255, 255, 255, 0.1);
        }

        .cart-header,
        .favorite-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.035);
        }

        .cart-header h3,
        .favorite-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .close-cart {
            border: 0;
            background: transparent;
            color: rgba(255, 255, 255, 0.72);
            font: inherit;
            font-size: 1.8rem;
            line-height: 1;
            cursor: pointer;
            transition: color 0.25s ease;
        }

        .close-cart:hover { color: #fff; }

        .cart-items,
        .favorite-items {
            flex: 1;
            overflow-y: auto;
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .cart-item,
        .favorite-item {
            display: grid;
            grid-template-columns: 76px 1fr auto;
            gap: 14px;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.105), rgba(255, 255, 255, 0.035)),
                rgba(7, 10, 14, 0.28);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.08),
                0 12px 30px rgba(0, 0, 0, 0.18);
        }

        .cart-item img,
        .favorite-item img {
            width: 76px;
            height: 76px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .cart-item-info,
        .favorite-item-info {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .cart-item-name,
        .favorite-item-name {
            font-size: 0.96rem;
            font-weight: 600;
            line-height: 1.35;
        }

        .cart-item-meta,
        .favorite-item-meta {
            color: rgba(255, 255, 255, 0.56);
            font-size: 0.82rem;
        }

        .cart-item-price,
        .favorite-item-price {
            color: var(--primary);
            font-size: 0.92rem;
            font-weight: 700;
        }

        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }

        .cart-uom-select {
            width: fit-content;
            min-height: 36px;
            padding: 0 34px 0 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0.055)),
                rgba(10, 15, 20, 0.72);
            color: #fff;
            font: inherit;
            font-size: 0.82rem;
            font-weight: 700;
            outline: none;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.18),
                0 10px 22px rgba(0, 0, 0, 0.22);
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .cart-uom-control {
            position: relative;
            width: fit-content;
            display: inline-flex;
        }

        .cart-uom-control::after {
            content: '⌄';
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-58%);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            pointer-events: none;
        }

        .cart-uom-select:hover {
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.34);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.22),
                0 14px 28px rgba(0, 0, 0, 0.28);
        }

        .cart-uom-select:focus {
            border-color: rgba(232, 101, 26, 0.75);
            box-shadow:
                0 0 0 3px rgba(232, 101, 26, 0.14),
                inset 0 1px 0 rgba(255, 255, 255, 0.22),
                0 14px 28px rgba(0, 0, 0, 0.28);
        }

        .cart-uom-select option {
            background: #111820;
            color: #fff;
        }

        .qty-control {
            height: 32px;
            min-width: 32px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.07);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .qty-control:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .cart-qty {
            min-width: 24px;
            text-align: center;
            font-weight: 700;
        }

        .remove-item-btn {
            align-self: start;
            width: 34px;
            height: 34px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.6);
            font-size: 1.25rem;
            cursor: pointer;
        }

        .remove-item-btn:hover {
            color: #fff;
            border-color: rgba(232, 101, 26, 0.8);
            background: rgba(232, 101, 26, 0.14);
        }

        .cart-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.035);
        }

        .favorite-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.035);
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.92rem;
        }

        .cart-total-row strong {
            color: #fff;
            font-size: 1.12rem;
        }

        .checkout-btn,
        .cart-secondary-btn {
            width: 100%;
            min-height: 46px;
            border-radius: 8px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.25s ease, background 0.25s ease, border-color 0.25s ease;
        }

        .checkout-btn {
            border: 0;
            background: var(--primary);
            color: #fff;
        }

        .checkout-btn:hover { background: #d05815; }

        .checkout-btn:disabled {
            cursor: not-allowed;
            opacity: 0.45;
        }

        .cart-secondary-btn {
            margin-top: 10px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }

        .cart-secondary-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* === 2. VIRTUAL SCROLL ARCHITECTURE === */
        .scroll-container { height: 500vh; }
        
        .viewport {
            position: fixed; inset: 0;
            overflow: hidden; background: #000;
            perspective: 1000px;
        }

        /* === 3. CINEMATIC BACKGROUNDS === */
        .scene {
            position: absolute; inset: -5vh -5vw;
            background-size: cover; background-position: center;
            opacity: 0; 
            will-change: transform, opacity;
        }

        .scene video {
            position: absolute; inset: 0;
            width: 100%; height: 100%; object-fit: cover;
            pointer-events: none;
        }
        
        .scene::after {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at center, transparent 30%, rgba(0,0,0,0.65) 120%);
        }

        .veil {
            position: absolute; inset: 0; opacity: 0; pointer-events: none;
            will-change: opacity; z-index: 10;
        }
        .veil.light { background: linear-gradient(to top, rgba(255,240,210,0.15), transparent); mix-blend-mode: overlay; }
        .veil.water { background: rgba(4, 20, 35, 0.85); backdrop-filter: blur(16px); }

        .env-canvas {
            position: absolute; inset: 0; width: 100%; height: 100%;
            z-index: 12; pointer-events: none; mix-blend-mode: screen;
            opacity: 0; 
            transition: opacity 1.5s ease;
        }

        /* === 4. STORYTELLING UI LAYER === */
        .story-layer {
            position: absolute; inset: 0; z-index: 20; pointer-events: none;
        }

        .scrim {
            position: absolute; bottom: 0; left: 0; width: 100%; height: 60vh;
            background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.5) 40%, transparent 100%);
            opacity: 0.9; pointer-events: none;
        }

        /* Dùng clamp() để co giãn padding an toàn trên mọi thiết bị */
        .chapter {
            position: absolute; inset: 0; 
            display: flex; flex-direction: column; justify-content: center;
            padding: clamp(20px, 6vw, 80px); 
            opacity: 0; will-change: transform, opacity;
            pointer-events: none;
            max-width: 100vw; overflow: hidden; /* Tránh tràn ngang */
        }

        /* Fluid Typography (Tự động scale không vỡ font) */
        .chapter.is-active { pointer-events: auto; }

        .label {
            font-size: 0.82rem; 
            font-weight: 700; letter-spacing: 0.16em;
            text-transform: uppercase; color: var(--primary); margin-bottom: clamp(10px, 2vw, 16px);
            display: flex; align-items: center; gap: 12px;
        }
        .label::before { content:''; display:block; width: 30px; height: 1px; background: var(--primary); }
        
        .title {
            font-size: 4.65rem; 
            font-weight: 330; line-height: 1.06; letter-spacing: 0; 
            text-shadow: 0 4px 25px rgba(0,0,0,0.7);
            margin-bottom: clamp(16px, 3vw, 24px); 
            max-width: 980px;
        }
        .desc {
            font-size: 1.06rem; 
            color: rgba(255,255,255,0.76); 
            max-width: 640px; line-height: 1.66;
        }

        .chapter-inner {
            width: min(1080px, 100%);
            display: grid;
            gap: 22px;
        }

        .story-copy {
            display: grid;
            gap: 16px;
            max-width: 760px;
        }

        .origin-duo {
            width: min(760px, 100%);
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            pointer-events: auto;
        }

        .origin-card {
            min-height: 170px;
            padding: 18px;
            display: flex;
            align-items: flex-end;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            background-size: cover;
            background-position: center;
            overflow: hidden;
            position: relative;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
        }

        .origin-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.08), rgba(0, 0, 0, 0.78));
        }

        .origin-card span {
            position: relative;
            color: #fff;
            font-weight: 850;
            line-height: 1.35;
            text-shadow: 0 2px 14px rgba(0, 0, 0, 0.55);
        }

        .region-tags,
        .final-proof {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            pointer-events: auto;
        }

        .region-tag,
        .final-proof span {
            min-height: 32px;
            padding: 0 12px;
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.075);
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.8rem;
            font-weight: 800;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        #ch3 .label,
        #ch3 .card-price,
        #ch3 .uom-chip.active {
            color: #ffd18d;
        }

        #ch3 .label::before {
            background: var(--sun);
        }

        #ch4 .label,
        #ch4 .card-price,
        #ch4 .uom-chip.active {
            color: #9be7f2;
        }

        #ch4 .label::before {
            background: var(--sea);
        }

        .rail-copy {
            display: grid;
            gap: 12px;
            max-width: 760px;
        }

        .rail-copy .title {
            font-size: 3.15rem;
            line-height: 1.08;
        }

        #ch3,
        #ch4 {
            justify-content: flex-start;
            padding-top: clamp(108px, 9vh, 132px);
        }

        #ch3 .product-showcase,
        #ch4 .product-showcase {
            margin-top: 22px;
        }

        .final-cta {
            width: min(720px, 100%);
            margin-top: clamp(18px, 3vw, 28px);
            padding: 18px;
            display: grid;
            gap: 12px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 12px;
            background:
                linear-gradient(135deg, rgba(13, 111, 131, 0.2), rgba(232, 101, 26, 0.13)),
                rgba(7, 10, 14, 0.48);
            box-shadow: 0 20px 54px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            pointer-events: auto;
        }

        #ch4 .final-cta {
            position: absolute;
            top: clamp(108px, 9vh, 132px);
            right: clamp(20px, 6vw, 80px);
            width: min(410px, calc(100vw - 40px));
            margin-top: 0;
        }

        .final-cta h3 {
            font-size: 1.38rem;
            line-height: 1.25;
        }

        .final-cta p {
            color: rgba(255, 255, 255, 0.72);
            line-height: 1.58;
        }

        .rail-empty {
            width: min(420px, 86vw);
            min-height: 220px;
            margin-top: clamp(20px, 4vw, 40px);
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px dashed rgba(255, 255, 255, 0.22);
            border-radius: 14px;
            background: rgba(8, 12, 16, 0.46);
            color: rgba(255, 255, 255, 0.74);
            text-align: center;
        }

        /* VŨ ĐẠO MỞ MÀN */
        #ch1 .label, #ch1 .title, #ch1 .desc {
            opacity: 0; transform: translate3d(0, 30px, 0);
            animation: cinematicReveal 1.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }
        #ch1 .label { animation-delay: 0.5s; }
        #ch1 .title { animation-delay: 0.7s; }
        #ch1 .desc { animation-delay: 0.9s; }

        @keyframes cinematicReveal { to { opacity: 1; transform: translate3d(0, 0, 0); } }

        /* === 5. 3D PRODUCT SHOWCASE COMPONENTS === */
        .product-rail {
            position: relative;
            width: 100%;
        }

        .product-showcase {
            display: flex; gap: clamp(16px, 2.5vw, 30px); 
            margin-top: clamp(20px, 4vw, 40px);
            pointer-events: auto;
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            scroll-snap-type: x mandatory;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding-bottom: 18px;
            overscroll-behavior-x: contain;
        }

        .product-showcase::-webkit-scrollbar {
            display: none;
        }

        .rail-controls {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            transform: translateY(-50%);
            z-index: 4;
            display: flex;
            justify-content: space-between;
            gap: 8px;
            pointer-events: none;
        }

        .rail-btn {
            width: 42px;
            height: 42px;
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 8px;
            background: rgba(8, 12, 16, 0.58);
            color: #fff;
            font: inherit;
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            pointer-events: auto;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            transition: background 0.25s ease, border-color 0.25s ease, transform 0.25s ease;
        }

        .rail-btn:first-child {
            transform: translateX(-50%);
        }

        .rail-btn:last-child {
            transform: translateX(50%);
        }

        .rail-btn:hover {
            background: rgba(255,255,255,0.14);
            border-color: rgba(255,255,255,0.36);
        }

        .rail-btn:first-child:hover {
            transform: translateX(-50%) translateY(-1px);
        }

        .rail-btn:last-child:hover {
            transform: translateX(50%) translateY(-1px);
        }

        .cinematic-card {
            width: clamp(300px, 24vw, 340px); flex-shrink: 0;
            scroll-snap-align: start;
            background: var(--glass-bg); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border); border-radius: 16px;
            padding: clamp(16px, 2vw, 20px);
            opacity: 0; transform: translate3d(0, 40px, 0);
            transition: border-color 0.4s ease, box-shadow 0.4s ease;
            will-change: transform, opacity;
        }

        .cinematic-card:hover {
            border-color: rgba(255,255,255,0.3);
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }

        .card-img {
            width: 100%; aspect-ratio: 1; border-radius: 12px; overflow: hidden;
            margin-bottom: 20px; background: rgba(255,255,255,0.05);
        }

        .card-favorite-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 3;
            width: 38px;
            height: 38px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 50%;
            background: rgba(8, 12, 16, 0.62);
            color: rgba(255, 255, 255, 0.82);
            font-size: 1.1rem;
            cursor: pointer;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: background 0.25s ease, border-color 0.25s ease, color 0.25s ease;
        }

        .card-favorite-btn:hover,
        .card-favorite-btn.active {
            color: #fff;
            border-color: rgba(232, 101, 26, 0.82);
            background: rgba(232, 101, 26, 0.22);
        }

        body:not(.logged-in) .favorite-btn,
        body:not(.logged-in) .card-favorite-btn,
        body:not(.logged-in) .modal-favorite-btn {
            display: none;
        }

        .icon {
            width: 18px;
            height: 18px;
            display: inline-block;
            flex: 0 0 auto;
            background: currentColor;
            transition: transform 0.22s ease;
        }

        .icon-heart {
            clip-path: path('M9 16.4C3.7 12.1 1 9.3 1 5.7 1 3.1 3 1.1 5.5 1.1c1.5 0 2.8.7 3.5 1.8.7-1.1 2-1.8 3.5-1.8C15 1.1 17 3.1 17 5.7c0 3.6-2.7 6.4-8 10.7Z');
        }

        .icon-bag {
            clip-path: path('M4.2 6.5h9.6l.7 9.5h-11L4.2 6.5ZM6 6.5V5a3 3 0 0 1 6 0v1.5h-1.6V5a1.4 1.4 0 0 0-2.8 0v1.5H6Z');
        }

        .icon-bag-plus {
            position: relative;
            background: transparent;
        }

        .icon-bag-plus::before,
        .icon-bag-plus::after {
            content: '';
            position: absolute;
            background: currentColor;
        }

        .icon-bag-plus::before {
            inset: 0;
            clip-path: path('M3.8 6.3h8.9l.6 9.2H3.1l.7-9.2ZM5.5 6.3V5a2.8 2.8 0 0 1 5.6 0v1.3H9.7V5a1.4 1.4 0 0 0-2.8 0v1.3H5.5Z');
        }

        .icon-bag-plus::after {
            width: 7px;
            height: 7px;
            right: 0;
            top: 1px;
            clip-path: polygon(40% 0, 60% 0, 60% 40%, 100% 40%, 100% 60%, 60% 60%, 60% 100%, 40% 100%, 40% 60%, 0 60%, 0 40%, 40% 40%);
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .card-img img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .cinematic-card:hover .card-img img { transform: scale3d(1.08, 1.08, 1); }

        .card-name { font-size: clamp(1rem, 1.5vw, 1.1rem); font-weight: 600; margin-bottom: 8px; line-height: 1.4; cursor: pointer; }
        .card-meta { font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-bottom: 16px; display: flex; justify-content: space-between;}
        .card-price { font-size: 1.2rem; font-weight: 700; color: var(--primary); }

        .card-purchase-controls {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            margin-bottom: 12px;
        }

        .card-uom-chips {
            display: flex;
            gap: 8px;
            min-width: 0;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .card-uom-chips::-webkit-scrollbar {
            display: none;
        }

        .uom-chip {
            flex: 0 0 auto;
            min-height: 34px;
            padding: 0 12px;
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 8px;
            background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.78);
            font: inherit;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.25s ease, border-color 0.25s ease, color 0.25s ease;
        }

        .uom-chip:hover,
        .uom-chip.active {
            border-color: var(--primary);
            background: rgba(232, 101, 26, 0.16);
            color: #fff;
        }

        .card-qty {
            display: inline-flex;
            align-items: center;
            height: 42px;
            border: 1px solid rgba(255,255,255,0.16);
            border-radius: 8px;
            overflow: hidden;
            background: rgba(255,255,255,0.05);
        }

        .card-qty button {
            width: 38px;
            height: 40px;
            border: 0;
            background: transparent;
            color: #fff;
            font: inherit;
            font-size: 1.1rem;
            cursor: pointer;
        }

        .card-qty button:hover { background: rgba(255,255,255,0.12); }

        .card-qty span {
            min-width: 38px;
            text-align: center;
            font-weight: 700;
        }

        .card-action-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            align-items: center;
        }

        .card-btn-add,
        .card-btn-buy {
            min-height: 42px;
            padding: 0 10px;
            border-radius: 8px;
            color: #fff;
            font: inherit;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.25s ease, border-color 0.25s ease;
        }

        .card-btn-add {
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.08);
        }

        .card-btn-add:hover {
            background: rgba(255,255,255,0.16);
            border-color: rgba(255,255,255,0.34);
        }

        .card-btn-buy {
            border: 0;
            background: var(--primary);
        }

        .card-btn-buy:hover { background: #d05815; }

        /* === 6. RESPONSIVE STRATEGY (TABLET & MOBILE) === */
        @media (max-width: 1024px) {
            .title {
                font-size: 3.3rem;
            }

            /* Biến showcase thành Horizontal Swipe Carousel để tránh dài trang web */
            .product-showcase {
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                padding-bottom: 20px;
                /* Ẩn scrollbar ngang cho mượt */
                scrollbar-width: none; 
                -ms-overflow-style: none; 
            }
            .product-showcase::-webkit-scrollbar { display: none; }
            
            .cinematic-card {
                scroll-snap-align: start;
            }
        }

        @media (max-width: 768px) {
            /* Căn lại vị trí các khối nội dung để không bị lẹm viền */
            #ch1, #ch2, #ch3, #ch4 {
                align-items: flex-start !important; /* Căn trái hết trên mobile dễ đọc */
                justify-content: flex-end;
                padding-bottom: 9vh;
            }
            
            .label { justify-content: flex-start !important; }
            .desc { margin: 0 !important; }

            .title {
                font-size: 2.45rem;
                line-height: 1.12;
            }

            .desc {
                font-size: 0.96rem;
                line-height: 1.58;
            }

            .chapter-inner,
            .story-copy,
            .rail-copy {
                gap: 12px;
            }

            .rail-copy .title {
                font-size: 2.08rem;
                line-height: 1.12;
            }

            #ch3,
            #ch4 {
                padding-top: 96px;
            }

            .origin-duo {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .origin-card {
                min-height: 112px;
                padding: 14px;
            }

            .region-tag,
            .final-proof span,
            .trust-pill {
                min-height: 30px;
                font-size: 0.74rem;
            }

            .final-cta {
                position: static !important;
                width: 100% !important;
                margin-top: 14px;
                padding: 14px;
                gap: 10px;
            }

            .final-cta h3 {
                font-size: 1.08rem;
            }

            .final-cta p {
                font-size: 0.9rem;
                line-height: 1.5;
            }

            /* Kéo full viền cho khu vực thẻ vuốt ngang */
            .product-showcase {
                width: 100vw;
                margin-left: calc(-1 * clamp(20px, 6vw, 80px)); /* Bleed ra sát lề thiết bị */
                padding-left: clamp(20px, 6vw, 80px);
                padding-right: clamp(20px, 6vw, 80px);
            }

            .cinematic-card {
                width: 75vw; /* Thẻ chiếm 75% màn hình để hé lộ thẻ tiếp theo */
                max-width: 360px;
            }
            
            .card-img {
                aspect-ratio: 4/3; /* Tỷ lệ vàng cho màn hình dọc */
            }
        }

        @media (max-width: 768px) {
            .card-purchase-controls {
                grid-template-columns: 1fr;
            }

            .card-qty {
                width: 100%;
                justify-content: space-between;
            }

            .rail-btn:first-child {
                transform: translateX(0);
            }

            .rail-btn:last-child {
                transform: translateX(0);
            }

            .rail-btn:first-child:hover,
            .rail-btn:last-child:hover {
                transform: translateY(-1px);
            }

            .glass-header {
                top: 10px;
                width: calc(100% - 20px);
                min-height: auto;
                padding: 10px;
                gap: 10px;
                align-items: flex-start;
            }

            .logo {
                max-width: 132px;
                white-space: normal;
                line-height: 1.15;
            }

            .nav-links {
                max-width: 44vw;
                overflow-x: auto;
                justify-content: flex-start;
                gap: 14px;
                padding: 0 2px 6px;
                scrollbar-width: none;
            }

            .nav-links::-webkit-scrollbar { display: none; }

            .logo { font-size: 0.92rem; }

            .auth-cart { gap: 6px; }

            .header-btn {
                min-height: 36px;
                padding: 0 10px;
                font-size: 0.78rem;
            }

            .login-popover {
                top: calc(100% + 10px);
                right: 0;
            }

            .login-popover::before {
                right: 24px;
            }

            .password-popover {
                top: calc(100% + 10px);
                right: 0;
            }

            .password-popover::before {
                right: 24px;
            }

            .account-menu {
                top: calc(100% + 10px);
                right: 0;
            }

            .account-menu::before {
                right: 15px;
            }
        }

        /* === 7. PRODUCT MODAL === */
        .modal {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(0, 0, 0, 0.42); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none;
            transition: opacity 0.4s ease;
        }
        .modal.active { opacity: 1; pointer-events: auto; }
        .modal-content {
            background: linear-gradient(135deg, rgba(18, 22, 26, 0.82), rgba(9, 12, 15, 0.66));
            width: 90%; max-width: 1000px; max-height: 90vh;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-top-color: rgba(255, 255, 255, 0.34);
            border-left-color: rgba(255, 255, 255, 0.28);
            box-shadow: 0 28px 90px rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            overflow-y: auto; position: relative;
            transform: translateY(30px); transition: transform 0.4s ease;
            display: flex; flex-direction: column;
            overscroll-behavior: contain;
        }
        .modal.active .modal-content { transform: translateY(0); }
        #product-modal .product-detail-content {
            width: min(1180px, calc(100vw - 32px));
            max-width: 1180px;
            height: min(90vh, 840px);
            max-height: calc(100vh - 32px);
            overflow: hidden;
        }
        .close-btn {
            position: absolute; top: 20px; right: 20px; z-index: 10;
            background: rgba(255,255,255,0.1); border: none; color: white;
            width: 40px; height: 40px; border-radius: 50%;
            font-size: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: background 0.3s;
        }
        .close-btn:hover { background: rgba(255,255,255,0.3); }
        .modal-body { display: flex; flex-wrap: wrap; padding: clamp(20px, 4vw, 40px); gap: clamp(20px, 4vw, 40px); }
        #product-modal .modal-body {
            display: grid;
            grid-template-columns: minmax(350px, 0.92fr) minmax(460px, 1.08fr);
            align-items: stretch;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }
        .modal-gallery { flex: 1 1 400px; display: flex; flex-direction: column; gap: 16px; min-height: 0; }
        #product-modal .modal-gallery {
            min-width: 0;
            overflow: hidden;
        }
        .main-img-container { width: 100%; aspect-ratio: 1; border-radius: 12px; overflow: hidden; background: #222; }
        #product-modal .main-img-container {
            flex: 1;
            min-height: 280px;
            aspect-ratio: auto;
        }
        #modal-main-img { width: 100%; height: 100%; object-fit: cover; transition: opacity 0.3s; }
        .thumbnail-list { display: flex; gap: 12px; overflow-x: auto; scrollbar-width: none; padding-bottom: 5px; }
        .thumbnail-list::-webkit-scrollbar { display: none; }
        .thumb-img {
            width: 80px; height: 80px; border-radius: 8px; object-fit: cover;
            cursor: pointer; opacity: 0.5; transition: all 0.3s;
            border: 2px solid transparent; flex-shrink: 0;
        }
        .thumb-img.active, .thumb-img:hover { opacity: 1; border-color: var(--primary); }
        .modal-info { flex: 1 1 300px; display: flex; flex-direction: column; min-width: 0; }
        #product-modal .modal-info {
            min-height: 0;
            overflow: hidden;
        }
        .modal-label { color: var(--primary); font-size: 0.85rem; font-weight: 600; letter-spacing: 0.2em; text-transform: uppercase; margin-bottom: 12px; }
        .modal-title { font-size: clamp(1.5rem, 3vw, 2.2rem); font-weight: 300; margin-bottom: 16px; line-height: 1.2; }
        #product-modal .modal-title {
            margin-right: 50px;
            overflow-wrap: anywhere;
        }
        .modal-desc { font-size: 1rem; color: rgba(255,255,255,0.7); line-height: 1.6; margin-bottom: 22px; }
        #product-modal .modal-desc {
            margin-bottom: 0;
            overflow-wrap: anywhere;
        }
        .product-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 18px;
            padding: 4px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.055);
        }

        .product-tab {
            min-height: 40px;
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: rgba(255, 255, 255, 0.68);
            font: inherit;
            font-size: 0.86rem;
            font-weight: 800;
            cursor: pointer;
        }

        .product-tab.active {
            background: rgba(232, 101, 26, 0.2);
            color: #fff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 8px 18px rgba(0, 0, 0, 0.18);
        }

        .product-tab-panel {
            display: none;
        }

        .product-tab-panel.active {
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        #product-modal .product-tab-panel.active {
            flex: 1;
            overflow: hidden;
        }
        .product-tab-scroll {
            flex: 1;
            min-height: 0;
            display: grid;
            align-content: start;
            gap: 16px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .modal-options-title {
            color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .modal-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .price-option {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px; border: 1px solid rgba(255,255,255,0.2); border-radius: 10px;
            cursor: pointer; transition: all 0.3s;
        }
        .price-option:hover { border-color: rgba(255,255,255,0.5); background: rgba(255,255,255,0.05); }
        .price-option.selected { border-color: var(--primary); background: rgba(232, 101, 26, 0.1); }
        .opt-uom { font-weight: 600; font-size: 1.1rem; }
        .opt-price { color: var(--primary); font-weight: 700; font-size: 1.2rem; }

        .modal-ingredients {
            padding: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.04);
        }

        .modal-ingredients h4 {
            margin-bottom: 8px;
            font-size: 0.92rem;
        }

        .modal-ingredients p {
            color: rgba(255, 255, 255, 0.68);
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .product-actions-fixed {
            flex: 0 0 auto;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .product-reviews {
            margin-bottom: 28px;
            padding: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.045);
        }

        #product-panel-reviews .product-reviews {
            flex: 1;
            min-height: 0;
            margin-bottom: 0;
            display: flex;
            flex-direction: column;
        }

        .reviews-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .reviews-head h4 {
            font-size: 0.92rem;
        }

        .review-score {
            color: #ffd28b;
            font-size: 0.84rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .reviews-list {
            display: grid;
            gap: 10px;
            max-height: 220px;
            overflow-y: auto;
            padding-right: 3px;
        }

        #product-panel-reviews .reviews-list {
            flex: 1;
            min-height: 0;
            max-height: none;
        }

        .review-card {
            padding: 11px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.14);
        }

        .review-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 6px;
        }

        .review-author {
            font-size: 0.86rem;
            font-weight: 800;
        }

        .review-stars {
            color: #ffd28b;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .review-text {
            color: rgba(255, 255, 255, 0.68);
            font-size: 0.86rem;
            line-height: 1.5;
        }

        .review-date {
            margin-top: 6px;
            color: rgba(255, 255, 255, 0.42);
            font-size: 0.74rem;
        }

        .empty-review {
            color: rgba(255, 255, 255, 0.56);
            font-size: 0.86rem;
            line-height: 1.5;
        }

        .modal-actions-container {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: auto;
        }

        .quantity-selector {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 8px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.05);
        }

        .quantity-selector button {
            width: 42px;
            height: 40px;
            border: 0;
            background: transparent;
            color: #fff;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .quantity-selector button:hover { background: rgba(255, 255, 255, 0.12); }

        .qty-value {
            min-width: 48px;
            text-align: center;
            font-weight: 700;
        }

        .modal-actions-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .modal-add-cart,
        .modal-buy-now,
        .order-submit-btn {
            min-height: 48px;
            border-radius: 8px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.25s ease, border-color 0.25s ease;
        }

        .modal-add-cart {
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .modal-add-cart:hover {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.34);
        }

        .modal-buy-now,
        .order-submit-btn {
            border: 0;
            background: var(--primary);
            color: #fff;
        }

        .modal-buy-now:hover,
        .order-submit-btn:hover {
            background: #d05815;
        }

        .modal-favorite-btn,
        .favorite-add-cart-btn {
            min-height: 40px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font: inherit;
            font-size: 0.86rem;
            font-weight: 700;
            cursor: pointer;
        }

        .modal-favorite-btn:hover,
        .modal-favorite-btn.active,
        .favorite-add-cart-btn:hover {
            background: rgba(232, 101, 26, 0.18);
            border-color: rgba(232, 101, 26, 0.82);
        }

        .checkout-content {
            max-width: 920px;
        }

        .profile-content {
            max-width: 760px;
        }

        .orders-content {
            width: min(1180px, calc(100vw - 32px));
            height: min(92vh, 860px);
            max-height: calc(100vh - 32px);
            overflow: hidden;
        }

        .checkout-body {
            display: grid;
            grid-template-columns: minmax(280px, 0.9fr) minmax(300px, 1.1fr);
            gap: 28px;
        }

        .profile-body {
            display: grid;
            grid-template-columns: 210px minmax(0, 1fr);
            gap: 28px;
        }

        .profile-preview {
            min-width: 0;
            padding-right: 6px;
        }

        .profile-avatar-large {
            width: 112px;
            height: 112px;
            margin-bottom: 10px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, 0.24);
            box-shadow: 0 12px 34px rgba(0, 0, 0, 0.35);
        }

        .avatar-change-btn {
            min-height: 36px;
            margin-bottom: 18px;
            padding: 0 12px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font: inherit;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.25s ease, border-color 0.25s ease;
        }

        .avatar-change-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.34);
        }

        .profile-preview-text {
            color: rgba(255, 255, 255, 0.62);
            font-size: 0.9rem;
            line-height: 1.55;
        }

        .orders-body {
            display: grid;
            grid-template-columns: 360px minmax(0, 1fr);
            gap: 18px;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }

        .orders-master,
        .orders-detail {
            min-width: 0;
            min-height: 0;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.045);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .orders-master {
            display: flex;
            flex-direction: column;
            gap: 14px;
            overflow: hidden;
            padding: 16px;
        }

        .orders-master-head {
            display: grid;
            gap: 12px;
            flex: 0 0 auto;
        }

        .orders-master-head .modal-title {
            margin-bottom: 0;
        }

        .orders-count-badge,
        .order-status {
            width: fit-content;
            min-width: 126px;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(232, 101, 26, 0.16);
            color: #ffd7c4;
            font-size: 0.78rem;
            font-weight: 800;
            text-align: center;
        }

        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
            padding-right: 6px;
            -webkit-overflow-scrolling: touch;
        }

        .order-card {
            width: 100%;
            display: grid;
            gap: 10px;
            padding: 13px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.14);
            color: #fff;
            font: inherit;
            text-align: left;
            cursor: pointer;
            transition: background 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease, transform 0.25s ease;
        }

        .order-card:hover,
        .order-card.active {
            border-color: rgba(232, 101, 26, 0.68);
            background: rgba(232, 101, 26, 0.12);
            box-shadow: 0 0 0 3px rgba(232, 101, 26, 0.08);
        }

        .order-card.active {
            transform: translateY(-1px);
        }

        .order-card:focus-visible {
            outline: 2px solid rgba(232, 101, 26, 0.9);
            outline-offset: 2px;
        }

        .order-card-top,
        .order-card-bottom {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .order-card-code {
            font-weight: 850;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .order-card-date,
        .order-card-meta {
            color: rgba(255, 255, 255, 0.58);
            font-size: 0.82rem;
            line-height: 1.4;
        }

        .order-card-date {
            margin-top: 4px;
        }

        .order-card-total {
            color: var(--primary);
            font-weight: 850;
            text-align: right;
            white-space: nowrap;
        }

        .orders-detail {
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
            padding: 18px 6px 28px 18px;
            -webkit-overflow-scrolling: touch;
        }

        .orders-detail-empty,
        .empty-orders {
            min-height: 180px;
            display: grid;
            place-items: center;
            padding: 28px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.58);
            text-align: center;
            background: rgba(255, 255, 255, 0.04);
        }

        .order-detail-panel {
            display: grid;
            gap: 16px;
            min-width: 0;
            padding-right: 10px;
        }

        .order-detail-hero {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 16px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.16);
        }

        .order-detail-code {
            margin-bottom: 6px;
            font-size: clamp(1.35rem, 2vw, 1.8rem);
            font-weight: 750;
            line-height: 1.18;
            overflow-wrap: anywhere;
        }

        .order-detail-date {
            color: rgba(255, 255, 255, 0.58);
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .order-detail-total {
            min-width: 148px;
            padding: 10px 12px;
            border: 1px solid rgba(232, 101, 26, 0.34);
            border-radius: 8px;
            background: rgba(232, 101, 26, 0.12);
            color: var(--primary);
            font-weight: 900;
            text-align: right;
            white-space: nowrap;
        }

        .order-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .order-detail-cell {
            min-width: 0;
            padding: 11px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.12);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.84rem;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .order-detail-cell strong {
            display: block;
            margin-bottom: 4px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.8rem;
        }

        .order-detail-cell.wide {
            grid-column: 1 / -1;
        }

        .order-section-title {
            color: rgba(255, 255, 255, 0.58);
            font-size: 0.8rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .invoice-items {
            display: grid;
            gap: 12px;
            min-width: 0;
        }

        .invoice-row {
            display: grid;
            grid-template-columns: 64px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: start;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.12);
        }

        .invoice-row img {
            width: 64px;
            height: 64px;
            border-radius: 8px;
            object-fit: cover;
        }

        .invoice-item-name {
            font-weight: 780;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .invoice-item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 10px;
            margin-top: 6px;
            color: rgba(255, 255, 255, 0.56);
            font-size: 0.82rem;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .invoice-item-total {
            color: var(--primary);
            font-weight: 850;
            text-align: right;
            white-space: nowrap;
        }

        .invoice-review {
            grid-column: 1 / -1;
            padding: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.035);
        }

        .review-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: stretch;
        }

        .review-form-title,
        .review-done-title {
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.82rem;
            font-weight: 800;
        }

        .review-form-title,
        .rating-choice {
            grid-column: 1 / -1;
        }

        .rating-choice {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .rating-choice input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .rating-choice label {
            min-height: 34px;
            padding: 0 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.07);
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.8rem;
            font-weight: 800;
            cursor: pointer;
        }

        .rating-choice input:checked + label {
            border-color: rgba(232, 101, 26, 0.82);
            background: rgba(232, 101, 26, 0.18);
            color: #fff;
        }

        .review-form textarea {
            width: 100%;
            min-height: 68px;
            padding: 11px 12px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.07);
            color: #fff;
            font: inherit;
            resize: vertical;
            outline: none;
        }

        .review-form textarea:focus {
            border-color: rgba(232, 101, 26, 0.75);
            box-shadow: 0 0 0 3px rgba(232, 101, 26, 0.12);
        }

        .review-submit-btn {
            min-width: 128px;
            min-height: 54px;
            padding: 0 14px;
            border: 0;
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            font: inherit;
            font-size: 0.84rem;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }

        .review-submit-btn:hover {
            background: #d05815;
        }

        .review-done {
            display: grid;
            gap: 6px;
            color: rgba(255, 255, 255, 0.66);
            font-size: 0.84rem;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .order-summary,
        .buyer-form {
            min-width: 0;
        }

        .order-summary-title,
        .buyer-form-title {
            margin-bottom: 14px;
            color: rgba(255, 255, 255, 0.56);
            font-size: 0.86rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .order-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 360px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .order-item {
            display: grid;
            grid-template-columns: 58px 1fr;
            gap: 12px;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.045);
        }

        .order-item img {
            width: 58px;
            height: 58px;
            border-radius: 8px;
            object-fit: cover;
        }

        .order-item-name {
            font-weight: 700;
            line-height: 1.35;
        }

        .order-item-meta {
            margin-top: 4px;
            color: rgba(255, 255, 255, 0.58);
            font-size: 0.82rem;
        }

        .order-item-price {
            margin-top: 8px;
            color: var(--primary);
            font-weight: 700;
        }

        .order-total {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            font-weight: 700;
        }

        .order-total strong {
            color: var(--primary);
            font-size: 1.15rem;
        }

        .buyer-form {
            display: grid;
            gap: 12px;
        }

        .form-field {
            display: grid;
            gap: 7px;
        }

        .form-field label {
            color: rgba(255, 255, 255, 0.68);
            font-size: 0.84rem;
            font-weight: 600;
        }

        .form-field input,
        .form-field textarea,
        .form-field select {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.07);
            color: #fff;
            font: inherit;
            padding: 12px 13px;
            outline: none;
        }

        .form-field textarea {
            min-height: 92px;
            resize: vertical;
        }

        .form-field input:focus,
        .form-field textarea:focus,
        .form-field select:focus {
            border-color: rgba(232, 101, 26, 0.75);
            box-shadow: 0 0 0 3px rgba(232, 101, 26, 0.12);
        }

        .select-shell {
            position: relative;
        }

        .select-shell select {
            min-height: 48px;
            padding: 0 42px 0 14px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0.055)),
                rgba(10, 15, 20, 0.72);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.18),
                0 12px 26px rgba(0, 0, 0, 0.24);
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .select-shell::after {
            content: '⌄';
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-58%);
            color: rgba(255, 255, 255, 0.72);
            font-size: 1rem;
            pointer-events: none;
        }

        .select-shell:hover select {
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.34);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.22),
                0 16px 32px rgba(0, 0, 0, 0.3);
        }

        .select-shell select:focus {
            border-color: rgba(232, 101, 26, 0.78);
            box-shadow:
                0 0 0 3px rgba(232, 101, 26, 0.14),
                inset 0 1px 0 rgba(255, 255, 255, 0.22),
                0 16px 32px rgba(0, 0, 0, 0.3);
        }

        .select-shell option {
            background: #111820;
            color: #fff;
        }

        @media (max-width: 980px) {
            #product-modal .product-detail-content {
                width: min(720px, calc(100vw - 24px));
                height: 92vh;
            }

            .orders-content {
                width: min(940px, calc(100vw - 24px));
                height: 92vh;
            }

            #product-modal .modal-body {
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            #product-modal .modal-gallery {
                flex: 0 0 auto;
                overflow: hidden;
            }

            #product-modal .main-img-container {
                flex: 0 0 auto;
                min-height: 0;
                aspect-ratio: 16 / 10;
                max-height: 30vh;
            }

            #product-modal .modal-info {
                flex: 1;
                min-height: 0;
                overflow: hidden;
            }

            #product-modal .product-tab-panel.active {
                overflow: hidden;
            }

            .product-tab-scroll {
                overflow-y: auto;
            }

            .orders-body {
                grid-template-columns: 320px minmax(0, 1fr);
                gap: 14px;
            }

            .order-detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .modal-body { padding: 20px; gap: 20px; }
            .modal-title { font-size: 1.5rem; }
            .modal-content { max-height: 95vh; width: 95%; }
            #product-modal .product-detail-content {
                height: 92vh;
            }

            #product-modal .main-img-container {
                aspect-ratio: 4 / 3;
                max-height: 28vh;
            }

            #product-modal .modal-title {
                margin-right: 44px;
            }

            .modal-actions-row,
            .checkout-body,
            .profile-body {
                grid-template-columns: 1fr;
            }

            .orders-content {
                width: 95%;
                height: 94vh;
            }

            .orders-body {
                grid-template-columns: 1fr;
                grid-template-rows: minmax(154px, 30vh) minmax(0, 1fr);
                padding: 16px;
                gap: 14px;
            }

            .orders-master {
                padding: 12px;
                gap: 10px;
            }

            .orders-master-head {
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: end;
                gap: 10px;
            }

            .orders-master-head .modal-label {
                margin-bottom: 6px;
                font-size: 0.68rem;
                letter-spacing: 0.14em;
            }

            .orders-master-head .modal-title {
                font-size: 1.35rem;
                line-height: 1.15;
            }

            .orders-count-badge {
                min-width: auto;
                white-space: nowrap;
            }

            .orders-list {
                flex-direction: row;
                overflow-x: auto;
                overflow-y: hidden;
                padding: 0 0 6px;
                scroll-snap-type: x proximity;
            }

            .order-card {
                flex: 0 0 min(280px, 82vw);
                padding: 12px;
                scroll-snap-align: start;
            }

            .orders-detail {
                padding: 14px 5px 22px 14px;
            }

            .order-detail-panel {
                gap: 14px;
                padding-right: 8px;
            }

            .order-detail-hero {
                display: grid;
                gap: 12px;
                padding: 14px;
            }

            .order-detail-total {
                min-width: 0;
                text-align: left;
                width: fit-content;
            }

            .order-detail-grid {
                grid-template-columns: 1fr;
            }

            .invoice-row {
                grid-template-columns: 56px minmax(0, 1fr);
                gap: 10px;
                padding: 10px;
            }

            .invoice-row img {
                width: 56px;
                height: 56px;
            }

            .invoice-item-total {
                grid-column: 2;
                text-align: left;
            }

            .review-form {
                grid-template-columns: 1fr;
            }

            .review-submit-btn {
                width: 100%;
                min-height: 44px;
            }

            .profile-preview {
                padding-right: 0;
            }

            .cart-sidebar {
                width: 100vw;
            }

            .favorite-sidebar {
                width: 100vw;
            }
        }

        /* === 8. PREMIUM UX REFINEMENT LAYER === */
        .btn-primary,
        .btn-secondary,
        .btn-tertiary,
        .login-submit,
        .password-submit,
        .checkout-btn,
        .cart-secondary-btn,
        .card-btn-add,
        .card-btn-buy,
        .modal-add-cart,
        .modal-buy-now,
        .order-submit-btn,
        .review-submit-btn,
        .favorite-add-cart-btn,
        .avatar-change-btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 8px;
            font: inherit;
            font-weight: 800;
            text-decoration: none;
            transition: transform 0.22s ease, background 0.22s ease, border-color 0.22s ease, opacity 0.22s ease;
        }

        .btn-primary,
        .login-submit,
        .password-submit,
        .checkout-btn,
        .card-btn-add,
        .modal-buy-now,
        .order-submit-btn,
        .review-submit-btn {
            border: 0;
            background: linear-gradient(180deg, #f37a25, var(--primary));
            color: #fff;
            box-shadow: 0 14px 28px rgba(232, 101, 26, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.18);
        }

        .btn-primary:hover,
        .login-submit:hover,
        .password-submit:hover,
        .checkout-btn:hover,
        .card-btn-add:hover,
        .modal-buy-now:hover,
        .order-submit-btn:hover,
        .review-submit-btn:hover {
            transform: translateY(-1px);
            background: linear-gradient(180deg, #ff8430, #d95c15);
        }

        .btn-secondary,
        .cart-secondary-btn,
        .card-btn-buy,
        .modal-add-cart,
        .favorite-add-cart-btn,
        .avatar-change-btn {
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .btn-secondary:hover,
        .cart-secondary-btn:hover,
        .card-btn-buy:hover,
        .modal-add-cart:hover,
        .favorite-add-cart-btn:hover,
        .avatar-change-btn:hover {
            transform: translateY(-1px);
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.34);
        }

        .btn-tertiary {
            min-height: 38px;
            border: 0;
            background: transparent;
            color: rgba(255, 255, 255, 0.72);
        }

        button:disabled,
        button.is-loading {
            cursor: not-allowed !important;
            opacity: 0.58;
            transform: none !important;
        }

        button.is-loading::before {
            content: '';
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.36);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .mobile-nav-toggle {
            display: none;
        }

        .login-btn-short {
            display: none;
        }

        .toast-stack {
            position: fixed;
            top: 96px;
            right: clamp(14px, 3vw, 32px);
            z-index: 12000;
            width: min(360px, calc(100vw - 28px));
            display: grid;
            gap: 10px;
            pointer-events: none;
        }

        .toast {
            padding: 13px 14px;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 10px;
            align-items: start;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-top-color: rgba(255, 255, 255, 0.32);
            border-left-color: rgba(255, 255, 255, 0.24);
            border-radius: 8px;
            background: linear-gradient(135deg, rgba(18, 22, 26, 0.88), rgba(8, 11, 14, 0.76));
            box-shadow: 0 18px 46px rgba(0, 0, 0, 0.42);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            color: #fff;
            pointer-events: auto;
            opacity: 0;
            transform: translate3d(12px, -6px, 0);
            animation: toastIn 0.28s ease forwards;
        }

        .toast.leaving {
            animation: toastOut 0.22s ease forwards;
        }

        .toast-icon {
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(232, 101, 26, 0.18);
            color: #ffd7c4;
            font-size: 0.85rem;
            font-weight: 900;
        }

        .toast.success .toast-icon {
            background: rgba(74, 222, 128, 0.14);
            color: #a7f3c1;
        }

        .toast.error .toast-icon {
            background: rgba(255, 156, 125, 0.14);
            color: #ffb199;
        }

        .toast-title {
            font-size: 0.88rem;
            font-weight: 850;
            line-height: 1.35;
        }

        .toast-message {
            margin-top: 2px;
            color: rgba(255, 255, 255, 0.64);
            font-size: 0.8rem;
            line-height: 1.45;
        }

        @keyframes toastIn {
            to { opacity: 1; transform: translate3d(0, 0, 0); }
        }

        @keyframes toastOut {
            to { opacity: 0; transform: translate3d(12px, -6px, 0); }
        }

        .empty-state {
            min-height: 160px;
            padding: 26px 18px;
            display: grid;
            place-items: center;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background:
                radial-gradient(circle at 50% 0%, rgba(232, 101, 26, 0.12), transparent 46%),
                rgba(255, 255, 255, 0.04);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07);
        }

        .empty-state.compact {
            min-height: 0;
            padding: 14px;
            place-items: start;
            text-align: left;
        }

        .empty-state-icon {
            width: 42px;
            height: 42px;
            margin: 0 auto 10px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.07);
            color: #ffd7c4;
            font-weight: 900;
        }

        .empty-state.compact .empty-state-icon {
            display: none;
        }

        .empty-state-title {
            color: rgba(255, 255, 255, 0.92);
            font-weight: 850;
            line-height: 1.35;
        }

        .empty-state-text {
            max-width: 300px;
            margin: 6px auto 0;
            color: rgba(255, 255, 255, 0.58);
            font-size: 0.84rem;
            line-height: 1.55;
        }

        .inline-message {
            padding: 11px 12px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.055);
            color: rgba(255, 255, 255, 0.68);
            font-size: 0.82rem;
            line-height: 1.45;
        }

        .inline-message.success {
            border-color: rgba(74, 222, 128, 0.24);
            background: rgba(74, 222, 128, 0.09);
            color: #bdf5cd;
        }

        .inline-message.error {
            border-color: rgba(255, 156, 125, 0.28);
            background: rgba(255, 156, 125, 0.09);
            color: #ffbea9;
        }

        .hero-actions,
        .hero-trust {
            opacity: 0;
            transform: translate3d(0, 30px, 0);
            animation: cinematicReveal 1.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        .hero-actions {
            margin-top: clamp(22px, 4vw, 34px);
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            pointer-events: auto;
            animation-delay: 1.08s;
        }

        .hero-cta {
            min-width: 164px;
            min-height: 48px;
            padding: 0 18px;
            cursor: pointer;
        }

        .hero-trust {
            margin-top: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            pointer-events: auto;
            animation-delay: 1.24s;
        }

        .trust-pill {
            min-height: 32px;
            padding: 0 12px;
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.07);
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.78rem;
            font-weight: 750;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .cinematic-card {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 14px;
            border-radius: 14px;
        }

        .card-img {
            margin-bottom: 0;
            cursor: pointer;
        }

        .card-content {
            display: grid;
            gap: 10px;
            min-width: 0;
        }

        .card-kicker {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: rgba(255, 255, 255, 0.52);
            font-size: 0.74rem;
            font-weight: 850;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .card-name {
            min-height: 3.05em;
            margin-bottom: 0;
            display: -webkit-box;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            overflow-wrap: anywhere;
        }

        .card-price-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
        }

        .card-price {
            font-size: 1.18rem;
            line-height: 1.15;
        }

        .card-unit {
            color: rgba(255, 255, 255, 0.48);
            font-size: 0.78rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .card-purchase-controls {
            grid-template-columns: minmax(0, 1fr) auto;
            margin-bottom: 0;
        }

        .uom-chip {
            min-height: 32px;
            padding: 0 10px;
            font-size: 0.78rem;
        }

        .card-qty {
            height: 36px;
            border-radius: 8px;
        }

        .card-qty button {
            width: 34px;
            height: 34px;
        }

        .card-qty span {
            min-width: 30px;
            font-size: 0.86rem;
        }

        .card-action-row {
            grid-template-columns: 44px 44px minmax(0, 1fr);
            gap: 8px;
        }

        .card-btn-add,
        .card-btn-buy {
            min-height: 42px;
            padding: 0 9px;
            font-size: 0.78rem;
            white-space: nowrap;
        }

        .card-favorite-btn {
            position: static;
            width: 44px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.07);
            color: rgba(255, 255, 255, 0.7);
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
        }

        .card-favorite-btn .icon,
        .card-btn-add .icon {
            width: 18px;
            height: 18px;
        }

        .card-favorite-btn.active {
            color: #ffd7c4;
        }

        .card-btn-add {
            width: 44px;
            min-width: 44px;
            padding: 0;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .card-btn-buy {
            width: 100%;
            border: 0;
            background: linear-gradient(180deg, #f37a25, var(--primary));
            color: #fff;
            box-shadow: 0 14px 28px rgba(232, 101, 26, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.18);
        }

        .card-btn-add:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.34);
        }

        .card-btn-buy:hover {
            background: linear-gradient(180deg, #ff8430, #d95c15);
            transform: translateY(-1px);
        }

        body:not(.logged-in) .card-action-row {
            grid-template-columns: 44px minmax(0, 1fr);
        }

        body:not(.logged-in) .card-btn-add {
            grid-column: 1;
        }

        .modal-label {
            margin-bottom: 8px;
            letter-spacing: 0.18em;
        }

        #product-modal .modal-title {
            margin-bottom: 14px;
            font-weight: 420;
        }

        .product-tabs {
            margin-bottom: 14px;
        }

        .modal-ingredients,
        .modal-info-note,
        .price-option,
        .product-reviews {
            border-radius: 8px;
        }

        .modal-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .modal-info-note {
            min-width: 0;
            padding: 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.04);
        }

        .modal-info-note h4 {
            margin-bottom: 7px;
            font-size: 0.88rem;
        }

        .modal-info-note p {
            color: rgba(255, 255, 255, 0.64);
            font-size: 0.86rem;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .price-option {
            padding: 13px 14px;
            background: rgba(255, 255, 255, 0.035);
        }

        .product-purchase-panel {
            display: grid;
            gap: 12px;
            padding: 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.16);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07);
        }

        .purchase-fields {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(112px, 0.34fr);
            gap: 12px;
            align-items: end;
        }

        .purchase-field {
            min-width: 0;
            display: grid;
            gap: 8px;
        }

        .purchase-label {
            color: rgba(255, 255, 255, 0.56);
            font-size: 0.76rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .product-purchase-panel .modal-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .product-purchase-panel .price-option {
            min-height: 44px;
            padding: 9px 10px;
            gap: 8px;
        }

        .product-purchase-panel .opt-uom {
            font-size: 0.9rem;
        }

        .product-purchase-panel .opt-price {
            font-size: 0.92rem;
            white-space: nowrap;
        }

        .product-purchase-panel .quantity-selector {
            width: 100%;
            justify-content: space-between;
            min-height: 44px;
        }

        .product-cta-row {
            grid-template-columns: 48px 48px minmax(0, 1fr);
            gap: 9px;
            align-items: center;
        }

        body:not(.logged-in) .product-cta-row {
            grid-template-columns: 48px minmax(0, 1fr);
        }

        .product-cta-row .modal-favorite-btn,
        .product-cta-row .modal-add-cart,
        .product-cta-row .modal-buy-now {
            min-height: 46px;
        }

        .modal-favorite-icon-btn {
            width: 48px;
            min-width: 48px;
            padding: 0;
            color: rgba(255, 255, 255, 0.72);
        }

        .modal-favorite-icon-btn.active {
            color: #ffd7c4;
        }

        .product-cta-row .modal-add-cart {
            width: 48px;
            min-width: 48px;
            padding: 0;
        }

        .product-cta-row .modal-buy-now {
            width: 100%;
            white-space: nowrap;
            font-size: 0.9rem;
        }

        .header-btn.favorite-btn,
        .header-btn.cart-btn {
            width: 44px;
            min-width: 44px;
            height: 42px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }

        .header-btn.favorite-btn .icon,
        .header-btn.cart-btn .icon {
            width: 17px;
            height: 17px;
            color: rgba(255, 255, 255, 0.84);
        }

        .header-btn.favorite-btn:hover .icon,
        .header-btn.cart-btn:hover .icon {
            transform: translateY(-1px);
            color: #fff;
        }

        .cart-badge,
        .favorite-badge {
            top: -6px;
            right: -6px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border: 1px solid rgba(8, 11, 14, 0.82);
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.32);
            font-size: 0.66rem;
        }

        .profile-content,
        .checkout-content {
            width: min(940px, calc(100vw - 32px));
        }

        .profile-preview,
        .order-summary {
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.04);
        }

        .profile-preview-text {
            margin-top: 10px;
        }

        .buyer-form {
            gap: 13px;
        }

        .form-field input,
        .form-field textarea,
        .form-field select,
        .login-field input,
        .password-field input {
            transition: border-color 0.22s ease, box-shadow 0.22s ease, background 0.22s ease;
        }

        .checkout-success {
            min-height: 420px;
            padding: clamp(28px, 5vw, 46px);
            display: grid;
            place-items: center;
            text-align: center;
        }

        .checkout-success[hidden] {
            display: none;
        }

        .checkout-success-card {
            max-width: 520px;
            display: grid;
            gap: 14px;
        }

        .checkout-success-mark {
            width: 62px;
            height: 62px;
            margin: 0 auto 4px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: rgba(74, 222, 128, 0.12);
            border: 1px solid rgba(74, 222, 128, 0.28);
            color: #bdf5cd;
            font-size: 1.6rem;
            font-weight: 900;
        }

        .checkout-success-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 420;
            line-height: 1.2;
        }

        .checkout-success-text {
            color: rgba(255, 255, 255, 0.64);
            line-height: 1.65;
        }

        .checkout-success-actions {
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }

        .order-card {
            border-radius: 10px;
        }

        .order-status.completed {
            background: rgba(74, 222, 128, 0.12);
            color: #bdf5cd;
        }

        .order-detail-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .order-detail-group {
            display: grid;
            gap: 10px;
            min-width: 0;
        }

        .order-detail-group-title {
            color: rgba(255, 255, 255, 0.52);
            font-size: 0.76rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .invoice-row {
            grid-template-columns: 62px minmax(0, 1fr) auto;
            border-radius: 10px;
        }

        .invoice-review {
            margin-top: 2px;
            border-top: 0;
        }

        .review-locked,
        .review-cta-row,
        .review-done {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .review-locked {
            color: rgba(255, 255, 255, 0.58);
            font-size: 0.82rem;
            line-height: 1.45;
        }

        .review-cta-copy,
        .review-done-copy {
            min-width: 0;
            display: grid;
            gap: 4px;
        }

        .review-toggle-btn {
            min-height: 36px;
            padding: 0 12px;
            border: 1px solid rgba(232, 101, 26, 0.38);
            border-radius: 8px;
            background: rgba(232, 101, 26, 0.14);
            color: #ffd7c4;
            font: inherit;
            font-size: 0.8rem;
            font-weight: 850;
            cursor: pointer;
        }

        .review-form {
            margin-top: 10px;
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .review-form textarea {
            min-height: 58px;
            resize: vertical;
        }

        .review-done {
            align-items: flex-start;
        }

        .review-done-text {
            color: rgba(255, 255, 255, 0.62);
            overflow-wrap: anywhere;
        }

        @media (max-width: 980px) {
            .order-detail-summary {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .glass-header {
                top: 10px;
                width: calc(100% - 20px);
                min-height: 54px;
                padding: 8px;
                align-items: center;
                gap: 8px;
            }

            .logo {
                min-width: 0;
                flex: 1;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .mobile-nav-toggle {
                display: inline-flex;
                min-width: 40px;
                min-height: 38px;
                padding: 0 10px;
            }

            .nav-links {
                position: absolute;
                top: calc(100% + 10px);
                left: 0;
                width: min(280px, calc(100vw - 20px));
                max-width: none;
                padding: 10px;
                display: none;
                grid-template-columns: 1fr;
                gap: 8px;
                overflow: visible;
                border: 1px solid rgba(255, 255, 255, 0.16);
                border-top-color: rgba(255, 255, 255, 0.32);
                border-left-color: rgba(255, 255, 255, 0.26);
                border-radius: 8px;
                background: rgba(9, 12, 15, 0.82);
                box-shadow: 0 18px 48px rgba(0, 0, 0, 0.44);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
            }

            body.mobile-nav-open .nav-links {
                display: grid;
            }

            .nav-links a {
                justify-content: flex-start;
                width: 100%;
            }

            .auth-cart {
                flex: 0 0 auto;
                gap: 5px;
            }

            .header-btn {
                min-height: 38px;
                padding: 0 9px;
            }

            .login-btn-label {
                display: none;
            }

            .login-btn-short {
                display: inline;
            }

            .cart-btn,
            .favorite-btn {
                min-width: 40px;
                padding: 0 10px;
            }

            .login-popover,
            .password-popover {
                position: fixed;
                top: 76px;
                right: 10px;
                left: 10px;
                width: auto;
            }

            .login-popover::before,
            .password-popover::before {
                display: none;
            }

            .account-anchor {
                --account-toggle-size: 38px;
                --account-menu-mobile-width: clamp(210px, 66vw, 260px);
            }

            .account-menu {
                position: absolute;
                top: calc(100% + 10px);
                right: 0;
                left: auto;
                width: min(var(--account-menu-mobile-width), calc(100vw - 20px));
            }

            .account-menu::before {
                display: block;
                right: calc((var(--account-toggle-size) - var(--account-arrow-size)) / 2);
            }

            .toast-stack {
                top: 78px;
                right: 10px;
                left: 10px;
                width: auto;
            }

            .hero-actions {
                width: 100%;
            }

            .hero-cta {
                flex: 1 1 160px;
            }

            .cinematic-card {
                width: min(82vw, 360px);
            }

            .card-purchase-controls {
                grid-template-columns: 1fr;
            }

            .card-qty {
                width: 100%;
                justify-content: space-between;
            }

            .card-action-row {
                grid-template-columns: 42px 42px minmax(0, 1fr);
            }

            .card-favorite-btn,
            .card-btn-add {
                width: 42px;
                min-width: 42px;
                height: 40px;
                min-height: 40px;
            }

            .product-actions-fixed {
                padding: 12px;
            }

            .purchase-fields {
                grid-template-columns: 1fr;
            }

            .product-purchase-panel .modal-options {
                grid-template-columns: 1fr 1fr;
            }

            .product-cta-row {
                grid-template-columns: 44px 44px minmax(0, 1fr);
                gap: 7px;
            }

            .product-cta-row .modal-favorite-btn,
            .product-cta-row .modal-add-cart,
            .product-cta-row .modal-buy-now {
                min-height: 42px;
            }

            .modal-favorite-icon-btn {
                width: 44px;
                min-width: 44px;
            }

            .product-cta-row .modal-buy-now {
                padding: 0 7px;
                font-size: 0.76rem;
            }

            .product-cta-row .modal-add-cart {
                width: 44px;
                min-width: 44px;
                padding: 0;
            }

            body:not(.logged-in) .product-cta-row {
                grid-template-columns: 44px minmax(0, 1fr);
            }

            .modal-info-grid,
            .checkout-success-actions {
                grid-template-columns: 1fr;
            }

            .checkout-success-actions > * {
                width: 100%;
            }

            .profile-preview,
            .order-summary {
                padding: 14px;
            }

            .invoice-row {
                grid-template-columns: 56px minmax(0, 1fr);
            }

            .review-form {
                grid-template-columns: 1fr;
            }
        }

        /* Customer account/favorites are hidden until a real customer-login flow exists. */
        #login-btn,
        .account-anchor,
        .login-popover,
        .password-popover,
        .favorite-btn,
        .favorite-sidebar,
        .card-favorite-btn,
        .modal-favorite-btn,
        .checkout-success-actions .btn-secondary {
            display: none !important;
        }
    </style>
</head>
<body>

    <!-- Menu Header trong suốt -->
    <header class="glass-header">
        <div class="logo">Đặc Sản Nhà Dân</div>
        <button class="header-btn mobile-nav-toggle" id="mobile-nav-toggle" type="button" onclick="toggleMobileNav()" aria-label="Mở điều hướng" aria-expanded="false">Menu</button>
        <nav class="nav-links" id="nav-links" aria-label="Điều hướng chính">
            <a data-nav="ch2" onclick="scrollToSection('ch2')">Câu chuyện</a>
            <a data-nav="ch3" onclick="scrollToSection('ch3')">Gia Lai</a>
            <a data-nav="ch4" onclick="scrollToSection('ch4')">Bình Định</a>
            <a data-nav="ch3" onclick="scrollToSection('ch3')">Sản phẩm</a>
            <a data-nav="cart" onclick="toggleCart()">Giỏ hàng</a>
        </nav>
        <div class="auth-cart">
            <button class="header-btn" id="login-btn" onclick="toggleLoginPopover()"><span class="login-btn-label">Đăng nhập</span><span class="login-btn-short" aria-hidden="true">Vào</span></button>
            <div class="account-anchor">
                <button class="header-btn account-toggle" id="account-toggle" onclick="toggleAccountMenu()" aria-label="Mở menu tài khoản">
                    <img id="account-avatar" src="" alt="Ảnh người dùng">
                </button>
                <div class="account-menu" id="account-menu" aria-hidden="true">
                    <div class="account-card">
                        <img id="account-menu-avatar" src="" alt="Ảnh người dùng">
                        <div>
                            <div class="account-name" id="account-menu-name">Khách</div>
                            <div class="account-email" id="account-menu-email">Chưa kết nối tài khoản</div>
                        </div>
                    </div>
                    <button class="account-menu-btn" type="button" onclick="openPasswordPopover()">Đổi mật khẩu</button>
                    <button class="account-menu-btn" type="button" onclick="openProfileModal()">Cập nhật thông tin</button>
                    <button class="account-menu-btn" type="button" onclick="openOrdersModal()">Đơn hàng của tôi</button>
                    <button class="account-menu-btn danger" type="button" onclick="logoutUser()">Đăng xuất</button>
                </div>
            </div>
            <div class="password-popover" id="password-popover" aria-hidden="true">
                <div class="password-title">Đổi mật khẩu</div>
                <p class="password-hint" id="password-hint">Nhập mật khẩu cũ và mật khẩu mới trước.</p>
                <form class="password-form" onsubmit="submitPasswordChange(event)">
                    <div class="password-step" id="password-step-fields">
                        <div class="password-field">
                            <label for="current-password">Mật khẩu cũ</label>
                            <input id="current-password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu cũ">
                        </div>
                        <div class="password-field">
                            <label for="new-password">Mật khẩu mới</label>
                            <input id="new-password" type="password" autocomplete="new-password" placeholder="Nhập mật khẩu mới">
                        </div>
                        <div class="password-field">
                            <label for="confirm-password">Xác nhận mật khẩu mới</label>
                            <input id="confirm-password" type="password" autocomplete="new-password" placeholder="Nhập lại mật khẩu mới">
                        </div>
                    </div>
                    <div class="password-step password-field" id="password-step-code" hidden>
                        <label for="verify-code">Mã xác nhận</label>
                        <input id="verify-code" type="text" inputmode="numeric" autocomplete="one-time-code" placeholder="Nhập mã xác nhận">
                    </div>
                    <div class="password-message" id="password-message" aria-live="polite"></div>
                    <button class="password-submit" id="password-submit-btn" type="submit">Tiếp tục</button>
                </form>
            </div>
            <div class="login-popover" id="login-popover" aria-hidden="true">
                <div class="login-title">Đăng nhập</div>
                <p class="login-hint">Tài khoản khách chưa được kết nối; đơn hàng được ghi trực tiếp vào hệ thống.</p>
                <form class="login-form" onsubmit="submitLogin(event)">
                    <div class="login-field">
                        <label for="login-username">Tài khoản</label>
                        <input id="login-username" type="text" autocomplete="username" placeholder="Nhập tài khoản">
                    </div>
                    <div class="login-field">
                        <label for="login-password">Mật khẩu</label>
                        <input id="login-password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu">
                    </div>
                    <div class="login-error" id="login-error" aria-live="polite"></div>
                    <button class="login-submit" type="submit">Đăng nhập</button>
                    <div class="login-links">
                        <button class="login-link-btn" type="button" onclick="showAuthPending('Đăng ký')">Đăng ký</button>
                        <button class="login-link-btn" type="button" onclick="showAuthPending('Quên mật khẩu')">Quên mật khẩu?</button>
                    </div>
                </form>
            </div>
            <button class="header-btn favorite-btn" onclick="toggleFavorites()" aria-label="Sản phẩm yêu thích">
                <span class="icon icon-heart" aria-hidden="true"></span>
                <span class="favorite-badge" id="favorite-badge">0</span>
            </button>
            <button class="header-btn cart-btn" onclick="toggleCart()">
                <span class="icon icon-bag" aria-hidden="true"></span>
                <span class="cart-badge" id="cart-badge">0</span>
            </button>
        </div>
    </header>

    <div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="false"></div>

    <!-- Sidebar Giỏ Hàng -->
    <div class="cart-sidebar" id="cart-sidebar">
        <div class="cart-header">
            <h3>Giỏ Hàng</h3>
            <button class="close-cart" onclick="toggleCart()">×</button>
        </div>
        <div class="cart-items glass-scroll" id="cart-items">
            <div class="empty-state">
                <div>
                    <div class="empty-state-icon">0</div>
                    <div class="empty-state-title">Giỏ hàng đang trống</div>
                    <div class="empty-state-text">Chọn một đặc sản để bắt đầu đơn hàng.</div>
                </div>
            </div>
        </div>
        <div class="cart-footer">
            <div class="cart-total-row">
                <span>Tạm tính</span>
                <strong id="cart-total">0đ</strong>
            </div>
            <button class="checkout-btn" id="cart-checkout-btn" onclick="openCheckoutFromCart()" disabled>Thanh Toán</button>
            <button class="cart-secondary-btn" onclick="toggleCart(false)">Thoát</button>
        </div>
    </div>

    <div class="favorite-sidebar" id="favorite-sidebar">
        <div class="favorite-header">
            <h3>Sản Phẩm Yêu Thích</h3>
            <button class="close-cart" onclick="toggleFavorites()">×</button>
        </div>
        <div class="favorite-items glass-scroll" id="favorite-items">
            <div class="empty-state">
                <div>
                    <div class="empty-state-icon">♡</div>
                    <div class="empty-state-title">Chưa có sản phẩm yêu thích</div>
                    <div class="empty-state-text">Đăng nhập và lưu lại những món bạn muốn quay lại sau.</div>
                </div>
            </div>
        </div>
        <div class="favorite-footer">
            <button class="cart-secondary-btn" onclick="toggleFavorites(false)">Thoát</button>
        </div>
    </div>

    <div class="scroll-container"></div>

    <div class="viewport">
        <div class="scene" id="bg1" style="opacity: 0; background-image: url('Highland_mountains_forests_202604231537_1.jpeg');"></div>
        <div class="veil light" id="veil1"></div>
        <div class="scene" id="bg2" style="background-image: url('Fishing_boats_on_202604231537_2.jpeg');"></div>
        <div class="scene" id="bg3" style="background-image: url('Cows_grazing_in_202604231537_3.jpeg');"></div>
        <div class="veil water" id="veil2"></div>
        <div class="scene" id="bg4" style="background-image: url('Fish_swimming_in_202604231537_4.jpeg');"></div>

        <canvas class="env-canvas" id="env-canvas"></canvas>

        <div class="story-layer">
            <div class="scrim"></div>

            <section class="chapter" id="ch1">
                <div class="label">Đặc Sản Nhà Dân</div>
                <h1 class="title">Đặc sản nhà làm từ cao nguyên Gia Lai đến duyên hải Bình Định</h1>
                <p class="desc">Tuyển chọn những món đặc sản quen vị, làm theo mẻ nhỏ, đóng gói sạch và giao tận nơi.</p>
                <div class="hero-actions" aria-label="Hành động mua hàng">
                    <button class="btn-primary hero-cta" type="button" onclick="scrollToSection('ch3')">Khám phá Gia Lai</button>
                    <button class="btn-secondary hero-cta" type="button" onclick="scrollToSection('ch4')">Khám phá Bình Định</button>
                </div>
                <div class="hero-trust" aria-label="Cam kết chất lượng">
                    <span class="trust-pill">Làm theo mẻ nhỏ</span>
                    <span class="trust-pill">Nguồn gốc vùng miền</span>
                    <span class="trust-pill">Đóng gói sạch</span>
                    <span class="trust-pill">Giao tận nơi</span>
                </div>
            </section>

            <section class="chapter" id="ch2">
                <div class="chapter-inner">
                    <div class="story-copy">
                        <div class="label">Câu chuyện</div>
                        <h2 class="title">Khởi nguồn từ hai miền vị nhớ</h2>
                        <p class="desc">Một bên là nắng cao nguyên Gia Lai, một bên là vị biển Bình Định. Đặc Sản Nhà Dân chọn những món quen thuộc, dễ ăn, dễ làm quà và giữ đúng tinh thần món nhà làm.</p>
                    </div>
                    <div class="origin-duo" aria-label="Hai vùng đặc sản">
                        <div class="origin-card" style="background-image: url('Highland_mountains_forests_202604231537_1.jpeg');">
                            <span>Nắng gió cao nguyên, vị đậm mộc mạc.</span>
                        </div>
                        <div class="origin-card" style="background-image: url('Fishing_boats_on_202604231537_2.jpeg');">
                            <span>Làng chài duyên hải, món quen dễ chia sẻ.</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="chapter" id="ch3">
                <div class="rail-copy">
                    <div class="label">Gia Lai</div>
                    <h2 class="title" style="margin-bottom: 0;">Vị nắng cao nguyên</h2>
                    <p class="desc">Từ những thớ thịt được tẩm ướp vừa vị, phơi qua nắng cao nguyên để giữ độ ngọt tự nhiên, Gia Lai mang đến nhóm đặc sản đậm đà, thơm nồng và rất hợp cho bữa ăn gia đình hoặc món nhâm nhi.</p>
                    <div class="region-tags" aria-label="Đặc trưng Gia Lai">
                        <span class="region-tag">Nắng cao nguyên</span>
                        <span class="region-tag">Thịt một nắng</span>
                        <span class="region-tag">Đậm vị</span>
                    </div>
                </div>
                <div class="product-rail">
                    <div class="rail-controls">
                        <button class="rail-btn" onclick="moveProductRail('gialai-products', -1)" aria-label="Sản phẩm trước">‹</button>
                        <button class="rail-btn" onclick="moveProductRail('gialai-products', 1)" aria-label="Sản phẩm tiếp theo">›</button>
                    </div>
                    <div class="product-showcase" id="gialai-products"></div>
                </div>
            </section>

            <section class="chapter" id="ch4">
                <div class="rail-copy">
                    <div class="label">Bình Định</div>
                    <h2 class="title" style="margin-bottom: 0;">Hương vị duyên hải</h2>
                    <p class="desc">Bình Định là vị mặn mòi của biển, vị thơm của chả ram tôm đất, vị quen của chả lụa, nem chua và các món khô được làm theo kiểu nhà dân, dễ ăn và dễ chia sẻ.</p>
                    <div class="region-tags" aria-label="Đặc trưng Bình Định">
                        <span class="region-tag">Vị biển miền Trung</span>
                        <span class="region-tag">Nhà làm</span>
                        <span class="region-tag">Dễ ăn, dễ làm quà</span>
                    </div>
                </div>
                <div class="product-rail">
                    <div class="rail-controls">
                        <button class="rail-btn" onclick="moveProductRail('binhdinh-products', -1)" aria-label="Sản phẩm trước">‹</button>
                        <button class="rail-btn" onclick="moveProductRail('binhdinh-products', 1)" aria-label="Sản phẩm tiếp theo">›</button>
                    </div>
                    <div class="product-showcase" id="binhdinh-products"></div>
                </div>
                <div class="final-cta">
                    <h3>Chọn đặc sản hôm nay</h3>
                    <p>Đơn hàng được ghi nhận trực tiếp trên hệ thống, shop sẽ liên hệ xác nhận trước khi giao.</p>
                    <div class="final-proof" aria-label="Cam kết đặt hàng">
                        <span>Không bắt buộc đăng nhập</span>
                        <span>Xác nhận trước khi giao</span>
                    </div>
                    <button class="btn-primary hero-cta" type="button" onclick="scrollToSection('ch3')">Chọn đặc sản hôm nay</button>
                </div>
            </section>
        </div>
    </div>

    <!-- Khung HTML cho Popup chi tiết sản phẩm -->
    <div id="product-modal" class="modal">
        <div class="modal-content product-detail-content glass-scroll">
            <button class="close-btn" onclick="closeModal()">×</button>
            <div class="modal-body">
                <div class="modal-gallery">
                    <div class="main-img-container">
                        <img id="modal-main-img" src="" alt="">
                    </div>
                    <div class="thumbnail-list" id="modal-thumbnails"></div>
                    <div class="product-purchase-panel">
                        <div class="purchase-fields">
                            <div class="purchase-field purchase-field-options">
                                <div class="purchase-label">Chọn khối lượng</div>
                                <div class="modal-options" id="modal-options"></div>
                            </div>
                            <div class="purchase-field purchase-field-qty">
                                <div class="purchase-label">Số lượng</div>
                                <div class="quantity-selector">
                                    <button onclick="changeModalQty(-1)" aria-label="Giảm số lượng">−</button>
                                    <span class="qty-value" id="modal-qty">1</span>
                                    <button onclick="changeModalQty(1)" aria-label="Tăng số lượng">+</button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-actions-row product-cta-row">
                            <button class="modal-favorite-btn modal-favorite-icon-btn" id="modal-favorite-btn" onclick="toggleFavoriteFromModal()" type="button" aria-label="Thêm vào yêu thích">
                                <span class="icon icon-heart" aria-hidden="true"></span>
                                <span class="sr-only">Thêm vào yêu thích</span>
                            </button>
                            <button class="modal-add-cart modal-cart-icon-btn" onclick="addToCart()" type="button" aria-label="Thêm vào giỏ">
                                <span class="icon icon-bag-plus" aria-hidden="true"></span>
                                <span class="sr-only">Thêm vào giỏ</span>
                            </button>
                            <button class="modal-buy-now" onclick="buyNow()" type="button">Mua ngay</button>
                        </div>
                    </div>
                </div>
                <div class="modal-info">
                    <div class="modal-label" id="modal-region"></div>
                    <h2 class="modal-title" id="modal-name"></h2>
                    <div class="product-tabs" role="tablist" aria-label="Thông tin sản phẩm">
                        <button class="product-tab active" id="product-tab-overview" type="button" onclick="switchProductTab('overview')" role="tab" aria-selected="true">Tổng quan</button>
                        <button class="product-tab" id="product-tab-reviews" type="button" onclick="switchProductTab('reviews')" role="tab" aria-selected="false">Đánh giá</button>
                    </div>

                    <div class="product-tab-panel active" id="product-panel-overview" role="tabpanel" aria-labelledby="product-tab-overview">
                        <div class="product-tab-scroll glass-scroll glass-scroll--thin">
                            <p class="modal-desc" id="modal-desc"></p>

                            <div class="modal-ingredients">
                                <h4>Thành phần</h4>
                                <p id="modal-ingredients"></p>
                            </div>

                            <div class="modal-info-grid">
                                <div class="modal-info-note">
                                    <h4>Nguồn gốc</h4>
                                    <p id="modal-origin"></p>
                                </div>
                                <div class="modal-info-note">
                                    <h4>Bảo quản</h4>
                                    <p id="modal-storage"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="product-tab-panel" id="product-panel-reviews" role="tabpanel" aria-labelledby="product-tab-reviews">
                        <div class="product-reviews">
                            <div class="reviews-head">
                                <h4>Đánh giá sản phẩm</h4>
                                <div class="review-score" id="product-review-score">Chưa có đánh giá</div>
                            </div>
                            <div class="reviews-list glass-scroll glass-scroll--thin" id="product-review-list"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="profile-modal" class="modal">
        <div class="modal-content profile-content glass-scroll">
            <button class="close-btn" onclick="closeProfileModal()">×</button>
            <form class="modal-body profile-body" onsubmit="submitProfile(event)">
                <div class="profile-preview">
                    <img class="profile-avatar-large" id="profile-preview-avatar" src="" alt="Ảnh người dùng">
                    <input id="profile-avatar-input" type="file" accept="image/*" hidden onchange="previewProfileAvatar(event)">
                    <button class="avatar-change-btn" type="button" onclick="document.getElementById('profile-avatar-input').click()">Đổi Ảnh</button>
                    <div class="modal-label">Tài khoản</div>
                    <h2 class="modal-title">Cập Nhật Thông Tin</h2>
                    <p class="profile-preview-text">Thông tin này đang lưu tạm trên giao diện để bạn test, chưa kết nối database.</p>
                </div>
                <div class="buyer-form">
                    <div class="buyer-form-title">Thông tin cá nhân</div>
                    <div class="form-field">
                        <label for="profile-name">Tên</label>
                        <input id="profile-name" type="text" autocomplete="name" required>
                    </div>
                    <div class="form-field">
                        <label for="profile-email">Gmail</label>
                        <input id="profile-email" type="email" autocomplete="email" required>
                    </div>
                    <div class="form-field">
                        <label for="profile-phone">Số điện thoại</label>
                        <input id="profile-phone" type="tel" autocomplete="tel" required>
                    </div>
                    <div class="form-field">
                        <label for="profile-address">Địa chỉ</label>
                        <textarea id="profile-address" autocomplete="street-address" required></textarea>
                    </div>
                    <div class="inline-message success" id="profile-message" hidden aria-live="polite"></div>
                    <button class="order-submit-btn" type="submit">Lưu Thông Tin</button>
                </div>
            </form>
        </div>
    </div>

    <div id="checkout-modal" class="modal">
        <div class="modal-content checkout-content glass-scroll">
            <button class="close-btn" onclick="closeCheckout()">×</button>
            <form class="modal-body checkout-body" id="checkout-form" onsubmit="submitCheckout(event)">
                <div class="order-summary">
                    <div class="modal-label">Xác nhận đơn hàng</div>
                    <h2 class="modal-title">Thông Tin Mua Hàng</h2>
                    <div class="order-summary-title">Sản phẩm đã chọn</div>
                    <div class="order-list glass-scroll glass-scroll--thin" id="checkout-items"></div>
                    <div class="order-total">
                        <span>Tổng cộng</span>
                        <strong id="checkout-total">0đ</strong>
                    </div>
                </div>

                <div class="buyer-form">
                    <div class="buyer-form-title">Thông tin người mua</div>
                    <div class="form-field">
                        <label for="buyer-name">Họ và tên</label>
                        <input id="buyer-name" type="text" autocomplete="name" required>
                    </div>
                    <div class="form-field">
                        <label for="buyer-phone">Số điện thoại</label>
                        <input id="buyer-phone" type="tel" autocomplete="tel" required>
                    </div>
                    <div class="form-field">
                        <label for="buyer-address">Địa chỉ nhận hàng</label>
                        <textarea id="buyer-address" autocomplete="street-address" required></textarea>
                    </div>
                    <div class="form-field">
                        <label for="buyer-payment">Hình thức thanh toán</label>
                        <div class="select-shell">
                            <select id="buyer-payment" required>
                                <option value="COD">Thanh toán khi nhận hàng</option>
                                <option value="BANK">Chuyển khoản ngân hàng</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="buyer-note">Ghi chú</label>
                        <textarea id="buyer-note" placeholder="Thời gian nhận hàng, yêu cầu đóng gói..."></textarea>
                    </div>
                    <div class="inline-message" id="checkout-message" hidden aria-live="polite"></div>
                    <button class="order-submit-btn" id="checkout-submit-btn" type="submit">Hoàn Tất Đặt Hàng</button>
                </div>
            </form>
            <div class="checkout-success" id="checkout-success" hidden>
                <div class="checkout-success-card">
                    <div class="checkout-success-mark">✓</div>
                    <div class="modal-label">Đã ghi nhận</div>
                    <h2 class="checkout-success-title">Đơn hàng đã được xác nhận</h2>
                    <p class="checkout-success-text" id="checkout-success-text">Cảm ơn bạn. Chúng tôi sẽ chuẩn bị đơn hàng và liên hệ xác nhận trước khi giao.</p>
                    <div class="checkout-success-actions">
                        <button class="btn-primary" type="button" onclick="closeCheckout()">Tiếp tục mua hàng</button>
                        <button class="btn-secondary" type="button" onclick="openOrdersFromSuccess()">Xem đơn hàng</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="orders-modal" class="modal">
        <div class="modal-content orders-content glass-scroll">
            <button class="close-btn" onclick="closeOrdersModal()">×</button>
            <div class="modal-body orders-body">
                <aside class="orders-master" aria-label="Tổng quan đơn hàng">
                    <div class="orders-master-head">
                        <div>
                            <div class="modal-label">Tình trạng & lịch sử</div>
                            <h2 class="modal-title">Đơn Hàng Của Tôi</h2>
                        </div>
                        <div class="orders-count-badge" id="orders-count">0 đơn hàng</div>
                    </div>
                    <div class="orders-list glass-scroll" id="orders-list" aria-label="Danh sách đơn hàng"></div>
                </aside>
                <section class="orders-detail glass-scroll" id="orders-detail" aria-live="polite">
                    <div class="empty-state">
                        <div>
                            <div class="empty-state-icon">→</div>
                            <div class="empty-state-title">Chưa chọn đơn hàng</div>
                            <div class="empty-state-text">Chọn một đơn ở danh sách bên trái để xem chi tiết.</div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        /**
         * 1. DATA ADAPTER
         */
        const STORE_CATALOG_FROM_DB = <?= json_encode($storeCatalog, $jsonFlags) ?>;
        let CHECKOUT_TOKEN = <?= json_encode($checkoutToken, $jsonFlags) ?>;
        const STORE_DEFAULT_SHIPPING_ZONE_ID = <?= json_encode($defaultShippingZone['zone_id'] ?? null, $jsonFlags) ?>;
        const STORE_PRODUCTS = Array.isArray(STORE_CATALOG_FROM_DB) ? STORE_CATALOG_FROM_DB : [];

        function expandCatalogRegion(region, labels) {
            const seeds = STORE_PRODUCTS.filter(product => product.region === region);
            if (seeds.length === 0) return;

            labels.forEach((label, index) => {
                const seed = seeds[index % seeds.length];
                STORE_PRODUCTS.push({
                    ...seed,
                    id: `${region}-${index + 3}`,
                    name: `${seed.name} ${label}`,
                    desc: seed.desc,
                    price: seed.options[0].price,
                    uom: seed.options[0].uom
                });
            });
        }

        if (!STORE_PRODUCTS.length) {
            console.warn('Không có sản phẩm active từ MySQL để hiển thị.');
        }

        const cardStates = {};

        function getOptionKey(option) {
            return String(option?.uomId || option?.uom_id || option?.uom || '');
        }

        function getItemUomKey(item) {
            return String(item?.uomId || item?.uom_id || item?.uom || '');
        }

        function selectCardUom(productId, uom, price, btnElement) {
            const option = getProductOptions(productId).find(opt => opt.uom === uom && opt.price === price)
                || getProductOptions(productId).find(opt => opt.uom === uom);
            if(cardStates[productId]) {
                cardStates[productId].uom = uom;
                cardStates[productId].uomId = getOptionKey(option) || uom;
                cardStates[productId].price = price;
            }
            const card = document.querySelector(`.cinematic-card[data-id="${productId}"]`);
            if(card) {
                card.querySelector('.card-price').textContent = price;
                const unit = card.querySelector('.card-unit');
                if (unit) unit.textContent = `/ ${uom}`;
                card.querySelectorAll('.uom-chip').forEach(c => c.classList.remove('active'));
                btnElement.classList.add('active');
            }
        }

        function changeCardQty(productId, delta) {
            let state = cardStates[productId];
            if(state) {
                let newQty = state.qty + delta;
                if (newQty >= 1 && newQty <= 99) {
                    state.qty = newQty;
                    document.getElementById(`qty-${productId}`).textContent = newQty;
                }
            }
        }

        function addToCartFromCard(productId) {
            const product = STORE_PRODUCTS.find(p => p.id === productId);
            const state = cardStates[productId];
            if(!product || !state) return;

            const stateUomKey = state.uomId || state.uom;
            const existing = cartList.find(item => item.id === product.id && getItemUomKey(item) === stateUomKey);
            if (existing) {
                existing.qty += state.qty;
            } else {
                cartList.push({
                    id: product.id, name: product.name,
                    img: product.images[0], uom: state.uom, uomId: stateUomKey,
                    price: state.price, qty: state.qty
                });
            }
            updateCartUI();
            showToast('Đã thêm vào giỏ', `${product.name} · ${state.uom} · SL ${state.qty}`, 'success');
        }

        function buyNowFromCard(productId) {
            const product = STORE_PRODUCTS.find(p => p.id === productId);
            const state = cardStates[productId];
            if(!product || !state) return;

            const item = {
                id: product.id, name: product.name,
                img: product.images[0], uom: state.uom, uomId: state.uomId || state.uom,
                price: state.price, qty: state.qty
            };
            openCheckout([item], 'instant');
        }

        function buildCardHTML(product) {
            // Khởi tạo trạng thái mặc định cho sản phẩm
            cardStates[product.id] = {
                uom: product.options[0].uom,
                uomId: getOptionKey(product.options[0]),
                price: product.options[0].price,
                qty: 1
            };

            const uomChips = product.options.map((opt, idx) => `
                <button class="uom-chip ${idx === 0 ? 'active' : ''}" onclick="selectCardUom('${product.id}', '${opt.uom}', '${opt.price}', this)">${escapeHtml(opt.uom)}</button>
            `).join('');
            const regionLabel = product.region === 'gia-lai' ? 'Gia Lai' : 'Bình Định';
            const regionMicrocopy = product.region === 'gia-lai' ? 'Nắng cao nguyên' : 'Vị biển miền Trung';
            const primaryImage = getProductImages(product)[0] || product.img || '';

            return `
                <div class="cinematic-card" data-id="${product.id}">
                    <div class="card-img" onclick="openModal('${product.id}')"><img src="${escapeHtml(primaryImage)}" alt="${escapeHtml(product.name)}"></div>
                    <div class="card-content">
                        <div class="card-kicker">
                            <span>${regionLabel}</span>
                            <span>${regionMicrocopy}</span>
                        </div>
                        <h3 class="card-name" onclick="openModal('${product.id}')">${escapeHtml(product.name)}</h3>
                        <div class="card-price-row">
                            <div class="card-price">${escapeHtml(product.options[0].price)}</div>
                            <div class="card-unit">/ ${escapeHtml(product.options[0].uom)}</div>
                        </div>
                        
                        <div class="card-purchase-controls">
                            <div class="card-uom-chips">
                                ${uomChips}
                            </div>
                            <div class="card-qty">
                                <button onclick="changeCardQty('${product.id}', -1)">−</button>
                                <span id="qty-${product.id}">1</span>
                                <button onclick="changeCardQty('${product.id}', 1)">+</button>
                            </div>
                        </div>
                        
                        <div class="card-action-row">
                            <button class="card-favorite-btn" data-favorite-id="${product.id}" onclick="toggleFavoriteProduct('${product.id}')" type="button" aria-label="Thêm sản phẩm yêu thích">
                                <span class="icon icon-heart" aria-hidden="true"></span>
                                <span class="sr-only">Thêm sản phẩm yêu thích</span>
                            </button>
                            <button class="card-btn-add" onclick="addToCartFromCard('${product.id}')" type="button" aria-label="Thêm vào giỏ">
                                <span class="icon icon-bag-plus" aria-hidden="true"></span>
                                <span class="sr-only">Thêm vào giỏ</span>
                            </button>
                            <button class="card-btn-buy" onclick="buyNowFromCard('${product.id}')">Mua ngay</button>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderProductRail(railId, products) {
            const rail = document.getElementById(railId);
            if (!rail) return;

            rail.innerHTML = products.length
                ? products.map(buildCardHTML).join('')
                : `<div class="rail-empty">${renderEmptyState('Sản phẩm đang được cập nhật', 'Shop đang bổ sung món mới cho vùng này. Vui lòng quay lại sau.', '•', true)}</div>`;
        }

        renderProductRail('gialai-products', STORE_PRODUCTS.filter(p => p.region === 'gia-lai'));
        renderProductRail('binhdinh-products', STORE_PRODUCTS.filter(p => p.region === 'binh-dinh'));

        const railIntroState = {
            'gialai-products': { played: false, running: false },
            'binhdinh-products': { played: false, running: false }
        };

        function getRailStep(rail) {
            const card = rail.querySelector('.cinematic-card');
            if (!card) return rail.clientWidth * 0.8;

            const gap = parseFloat(getComputedStyle(rail).gap) || 24;
            return card.getBoundingClientRect().width + gap;
        }

        function moveProductRail(railId, direction) {
            const rail = document.getElementById(railId);
            if (!rail) return;

            const intro = railIntroState[railId];
            if (intro) intro.running = false;

            const step = getRailStep(rail);
            const targetIndex = Math.max(0, Math.round(rail.scrollLeft / step) + direction);

            rail.scrollTo({
                left: targetIndex * step,
                behavior: 'smooth'
            });
        }

        function playFilmStripIntro(railId) {
            const rail = document.getElementById(railId);
            const intro = railIntroState[railId];
            if (!rail || !intro || intro.played || intro.running) return;

            const maxScroll = rail.scrollWidth - rail.clientWidth;
            if (maxScroll <= 0) return;

            intro.played = true;
            intro.running = true;

            const step = getRailStep(rail);
            const visibleCards = Math.max(1, Math.floor(rail.clientWidth / step));
            const travelCards = Math.max(4, visibleCards + 3);
            const startScroll = 0;
            const endScroll = Math.min(step * travelCards, maxScroll);
            const settle = Math.round(endScroll / step) * step;
            const duration = 3200;
            const start = performance.now();

            rail.style.scrollBehavior = 'auto';
            rail.style.scrollSnapType = 'none';
            rail.scrollLeft = startScroll;

            function tick(now) {
                if (!intro.running) {
                    rail.style.scrollBehavior = 'smooth';
                    rail.style.scrollSnapType = '';
                    return;
                }

                const t = Math.min((now - start) / duration, 1);
                const cinematicEase = t < 0.72
                    ? 0.86 * (1 - Math.pow(1 - t / 0.72, 2))
                    : 0.86 + 0.14 * (1 - Math.pow(1 - (t - 0.72) / 0.28, 3));

                rail.scrollLeft = lerp(startScroll, endScroll, cinematicEase);

                if (t < 1) {
                    requestAnimationFrame(tick);
                } else {
                    rail.scrollLeft = settle;
                    intro.running = false;
                    rail.style.scrollBehavior = 'smooth';
                    rail.style.scrollSnapType = '';
                    rail.scrollTo({ left: settle, behavior: 'smooth' });
                }
            }

            requestAnimationFrame(tick);
        }

        // === HÀM ĐIỀU KHIỂN POPUP MODAL ===
        let currentModalQty = 1;
        let currentSelectedProduct = null;

        function openModal(productId) {
            const product = STORE_PRODUCTS.find(p => p.id === productId);
            if (!product) return;

            currentSelectedProduct = product;
            currentModalQty = 1;
            document.getElementById('modal-qty').textContent = currentModalQty;
            switchProductTab('overview');

            document.getElementById('modal-region').textContent = product.region === 'gia-lai' ? 'Sản Vật Gia Lai' : 'Sản Vật Bình Định';
            document.getElementById('modal-name').textContent = product.name;
            const productDesc = product.fullDesc || product.desc || 'Thông tin sản phẩm đang được cập nhật.';
            const ingredientsText = product.ingredients || 'Nguyên liệu tuyển chọn, chế biến thủ công theo từng mẻ nhỏ.';
            const originText = product.region === 'gia-lai'
                ? 'Tuyển chọn từ vùng Gia Lai, chế biến theo mẻ nhỏ để giữ vị nắng và hương núi rừng.'
                : 'Tuyển chọn từ Bình Định, giữ tinh thần món nhà làm và vị duyên hải đặc trưng.';
            const storageText = product.storage || 'Bảo quản nơi khô mát hoặc ngăn mát/ngăn đông theo hướng dẫn trên bao bì. Dùng ngon nhất sau khi làm nóng nhẹ.';
            document.getElementById('modal-desc').textContent = productDesc;
            document.getElementById('modal-ingredients').textContent = ingredientsText;
            document.getElementById('modal-origin').textContent = originText;
            document.getElementById('modal-storage').textContent = storageText;
            
            const productImages = getProductImages(product);
            const mainImg = document.getElementById('modal-main-img');
            mainImg.src = productImages[0] || '';
            
            const thumbContainer = document.getElementById('modal-thumbnails');
            thumbContainer.innerHTML = productImages.map((img, idx) => 
                `<img src="${img}" class="thumb-img ${idx === 0 ? 'active' : ''}" onclick="setMainImg(this, '${img}')">`
            ).join('');

            const productOptions = getProductOptionsForDetail(product);
            const optionsContainer = document.getElementById('modal-options');
            optionsContainer.innerHTML = productOptions.map((opt, idx) => 
                `<div class="price-option ${idx === 0 ? 'selected' : ''}" onclick="selectOption(this)" data-uom-id="${escapeHtml(getOptionKey(opt))}" data-uom="${escapeHtml(opt.uom)}" data-price="${escapeHtml(opt.price)}">
                    <span class="opt-uom">${escapeHtml(opt.uom)}</span>
                    <span class="opt-price">${escapeHtml(opt.price)}</span>
                </div>`
            ).join('');

            renderProductReviews(product.id);
            updateFavoriteButtons();
            document.getElementById('product-modal').classList.add('active');
            document.body.style.overflow = 'hidden'; // Khóa cuộn trang nền bên dưới
        }

        function getProductImages(product) {
            const images = Array.isArray(product.images) ? product.images.filter(Boolean) : [];
            if (images.length > 0) return images;
            return product.img ? [product.img] : [];
        }

        function getProductOptionsForDetail(product) {
            const options = Array.isArray(product.options) ? product.options.filter(opt => opt && opt.uom && opt.price) : [];
            if (options.length > 0) return options;
            return [{ uom: product.uom || 'Mặc định', price: product.price || 'Liên hệ' }];
        }

        function changeModalQty(delta) {
            let newQty = currentModalQty + delta;
            if (newQty >= 1 && newQty <= 99) {
                currentModalQty = newQty;
                document.getElementById('modal-qty').textContent = currentModalQty;
            }
        }

        function setMainImg(element, src) {
            document.getElementById('modal-main-img').src = src;
            document.querySelectorAll('.thumb-img').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
        }

        function selectOption(element) {
            document.querySelectorAll('.price-option').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
        }

        function closeModal() {
            document.getElementById('product-modal').classList.remove('active');
            document.body.style.overflow = ''; // Trả lại cuộn trang
            currentSelectedProduct = null;
        }

        // === HỆ THỐNG MENU, GIỎ HÀNG VÀ ĐĂNG NHẬP ===
        let cartList = [];
        let favoriteList = [];
        let isLoggedIn = false;
        const testAccount = {
            username: '__disabled_customer_login__',
            password: '__disabled__',
            displayName: 'Khách',
            email: '',
            phone: '',
            address: ''
        };
        let userProfile = {
            name: testAccount.displayName,
            email: testAccount.email,
            phone: testAccount.phone,
            address: testAccount.address,
            avatar: ''
        };
        let pendingNewPassword = '';
        let pendingAvatar = '';
        const defaultOrderHistory = [];
        const defaultProductReviews = [];
        let orderHistory = defaultOrderHistory.map(order => ({
            ...order,
            buyer: { ...order.buyer },
            items: order.items.map(item => ({ ...item }))
        }));
        let productReviews = [];
        let selectedOrderId = null;
        let expandedReviewKey = '';
        let checkoutSubmitting = false;
        let checkoutSuccess = false;
        let mobileNavOpen = false;
        let toastSeed = 0;

        function showToast(title, message = '', type = 'info', timeout = 3200) {
            const stack = document.getElementById('toast-stack');
            if (!stack) return;

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.dataset.toastId = String(++toastSeed);

            const icon = document.createElement('div');
            icon.className = 'toast-icon';
            icon.textContent = type === 'success' ? '✓' : type === 'error' ? '!' : 'i';

            const copy = document.createElement('div');
            const titleEl = document.createElement('div');
            titleEl.className = 'toast-title';
            titleEl.textContent = title;
            copy.appendChild(titleEl);

            if (message) {
                const messageEl = document.createElement('div');
                messageEl.className = 'toast-message';
                messageEl.textContent = message;
                copy.appendChild(messageEl);
            }

            toast.append(icon, copy);
            stack.prepend(toast);

            const closeToast = () => {
                toast.classList.add('leaving');
                setTimeout(() => toast.remove(), 240);
            };

            toast.addEventListener('click', closeToast);
            window.setTimeout(closeToast, timeout);
        }

        function renderEmptyState(title, text, icon = '•', compact = false) {
            return `
                <div class="empty-state ${compact ? 'compact' : ''}">
                    <div>
                        <div class="empty-state-icon">${escapeHtml(icon)}</div>
                        <div class="empty-state-title">${escapeHtml(title)}</div>
                        <div class="empty-state-text">${escapeHtml(text)}</div>
                    </div>
                </div>
            `;
        }

        function setInlineMessage(elementOrId, message = '', type = 'info') {
            const element = typeof elementOrId === 'string'
                ? document.getElementById(elementOrId)
                : elementOrId;
            if (!element) return;

            element.textContent = message;
            element.hidden = !message;
            element.classList.remove('success', 'error');
            if (message && type !== 'info') {
                element.classList.add(type);
            }
        }

        function normalizeOrderStatus(status) {
            return String(status || '')
                .trim()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/đ/g, 'd')
                .replace(/\s+/g, ' ');
        }

        function isOrderCompletedForReview(status) {
            const normalized = normalizeOrderStatus(status);
            return ['da giao hang', 'hoan tat', 'hoan thanh', 'completed']
                .some(statusName => normalized === statusName || normalized.includes(statusName));
        }

        function toggleMobileNav(forceOpen) {
            const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !mobileNavOpen;
            mobileNavOpen = shouldOpen;
            document.body.classList.toggle('mobile-nav-open', shouldOpen);
            const trigger = document.getElementById('mobile-nav-toggle');
            if (trigger) {
                trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
                trigger.textContent = shouldOpen ? 'Đóng' : 'Menu';
            }
            if (shouldOpen) {
                toggleLoginPopover(false);
                toggleAccountMenu(false);
                togglePasswordPopover(false);
            }
        }

        function getAvatarDataUrl(name) {
            const initial = (name || 'U').trim().charAt(0).toUpperCase();
            const svg = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96">
                    <defs>
                        <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#E8651A"/>
                            <stop offset="1" stop-color="#FFE1B8"/>
                        </linearGradient>
                    </defs>
                    <rect width="96" height="96" rx="48" fill="#111820"/>
                    <circle cx="48" cy="38" r="16" fill="url(#g)"/>
                    <path d="M20 84c5-19 19-30 28-30s23 11 28 30" fill="url(#g)" opacity=".92"/>
                    <text x="48" y="43" text-anchor="middle" font-family="Arial" font-size="20" font-weight="700" fill="#fff">${initial}</text>
                </svg>`;

            return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
        }

        function getAccountStorageKey(type) {
            return `dac-san:${testAccount.username}:${type}`;
        }

        function getGlobalStorageKey(type) {
            return `dac-san:global:${type}`;
        }

        function cloneData(value) {
            return JSON.parse(JSON.stringify(value));
        }

        function readAccountStorage(type, fallback) {
            try {
                const raw = localStorage.getItem(getAccountStorageKey(type));
                return raw ? JSON.parse(raw) : cloneData(fallback);
            } catch (error) {
                return cloneData(fallback);
            }
        }

        function writeAccountStorage(type, value) {
            if (!isLoggedIn) return;
            try {
                localStorage.setItem(getAccountStorageKey(type), JSON.stringify(value));
            } catch (error) {
                console.warn('Không thể lưu dữ liệu tài khoản.', error);
            }
        }

        function readGlobalStorage(type, fallback) {
            try {
                const raw = localStorage.getItem(getGlobalStorageKey(type));
                return raw ? JSON.parse(raw) : cloneData(fallback);
            } catch (error) {
                return cloneData(fallback);
            }
        }

        function writeGlobalStorage(type, value) {
            try {
                localStorage.setItem(getGlobalStorageKey(type), JSON.stringify(value));
            } catch (error) {
                console.warn('Không thể lưu dữ liệu chung.', error);
            }
        }

        function saveAccountState() {
            writeAccountStorage('cart', cartList);
            writeAccountStorage('favorites', favoriteList);
            writeAccountStorage('orders', orderHistory);
        }

        function saveProductReviews() {
            writeGlobalStorage('reviews', productReviews);
        }

        function mergeCartItems(baseItems, extraItems) {
            const merged = [...baseItems.map(item => ({ ...item }))];
            extraItems.forEach(item => {
                const existing = merged.find(entry => entry.id === item.id && entry.uom === item.uom);
                if (existing) {
                    existing.qty += item.qty;
                } else {
                    merged.push({ ...item });
                }
            });
            return merged;
        }

        function loadAccountState(guestCart = []) {
            cartList = mergeCartItems(readAccountStorage('cart', []), guestCart);
            favoriteList = readAccountStorage('favorites', []);
            const storedOrders = readAccountStorage('orders', null);
            orderHistory = Array.isArray(storedOrders) ? storedOrders : cloneData(defaultOrderHistory);
            saveAccountState();
            updateCartUI();
            updateFavoriteUI();
        }

        const storedProductReviews = readGlobalStorage('reviews', defaultProductReviews);
        productReviews = Array.isArray(storedProductReviews) ? storedProductReviews : cloneData(defaultProductReviews);

        function scrollToProgress(p) {
            const max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
            window.scrollTo({ top: p * max, left: 0, behavior: 'smooth' });
        }

        function scrollToSection(sectionId) {
            const sectionProgress = {
                ch1: 0.00,
                ch2: 0.35,
                ch3: 0.7,
                ch4: 1
            };

            toggleMobileNav(false);
            scrollToProgress(sectionProgress[sectionId] ?? 0);
        }

        function toggleLoginPopover(forceOpen) {
            const popover = document.getElementById('login-popover');
            const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !popover.classList.contains('open');

            popover.classList.toggle('open', shouldOpen);
            popover.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
            document.getElementById('login-error').textContent = '';
            if (shouldOpen) {
                toggleMobileNav(false);
                toggleAccountMenu(false);
                togglePasswordPopover(false);
            }

            if (shouldOpen) {
                setTimeout(() => document.getElementById('login-username').focus(), 0);
            }
        }

        function toggleAccountMenu(forceOpen) {
            const menu = document.getElementById('account-menu');
            const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !menu.classList.contains('open');

            menu.classList.toggle('open', shouldOpen);
            menu.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
            if (shouldOpen) {
                toggleMobileNav(false);
                toggleLoginPopover(false);
                togglePasswordPopover(false);
            }
        }

        function togglePasswordPopover(forceOpen) {
            const popover = document.getElementById('password-popover');
            const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !popover.classList.contains('open');

            popover.classList.toggle('open', shouldOpen);
            popover.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
            resetPasswordPopover();

            if (shouldOpen) {
                toggleMobileNav(false);
                toggleLoginPopover(false);
                toggleAccountMenu(false);
                setTimeout(() => document.getElementById('current-password').focus(), 0);
            }
        }

        function openPasswordPopover() {
            togglePasswordPopover(true);
        }

        function resetPasswordPopover() {
            pendingNewPassword = '';
            document.getElementById('password-step-fields').hidden = false;
            document.getElementById('password-step-code').hidden = true;
            document.getElementById('password-hint').innerHTML = 'Nhập mật khẩu cũ và mật khẩu mới trước.';
            document.getElementById('password-submit-btn').textContent = 'Tiếp tục';
            document.getElementById('password-message').textContent = '';
            document.getElementById('password-message').classList.remove('success');
            document.getElementById('verify-code').value = '';
        }

        function updateLoginUI() {
            document.body.classList.toggle('logged-in', isLoggedIn);
            document.getElementById('login-btn').style.display = isLoggedIn ? 'none' : 'block';
            document.getElementById('account-toggle').style.display = isLoggedIn ? 'inline-flex' : 'none';
            document.getElementById('account-menu-name').textContent = userProfile.name;
            document.getElementById('account-menu-email').textContent = userProfile.email;

            const avatarUrl = userProfile.avatar || getAvatarDataUrl(userProfile.name);
            document.getElementById('account-avatar').src = avatarUrl;
            document.getElementById('account-menu-avatar').src = avatarUrl;
            document.getElementById('profile-preview-avatar').src = avatarUrl;
        }

        function submitLogin(event) {
            event.preventDefault();

            const username = document.getElementById('login-username').value.trim();
            const password = document.getElementById('login-password').value;
            const error = document.getElementById('login-error');

            if (username === testAccount.username && password === testAccount.password) {
                const guestCart = cartList;
                isLoggedIn = true;
                loadAccountState(guestCart);
                updateLoginUI();
                toggleLoginPopover(false);
                event.target.reset();
                showToast('Đăng nhập thành công', 'Giỏ hàng đã được đồng bộ.', 'success');
                return;
            }

            error.textContent = 'Tài khoản hoặc mật khẩu chưa đúng.';
            showToast('Không thể đăng nhập', 'Vui lòng kiểm tra lại tài khoản hoặc mật khẩu.', 'error');
        }

        function logoutUser() {
            saveAccountState();
            isLoggedIn = false;
            cartList = [];
            favoriteList = [];
            updateLoginUI();
            updateCartUI();
            updateFavoriteUI();
            toggleLoginPopover(false);
            toggleAccountMenu(false);
            togglePasswordPopover(false);
            toggleFavorites(false);
            toggleCart(false);
            closeProfileModal();
            closeOrdersModal();
            showToast('Đã đăng xuất', 'Dữ liệu tài khoản đã được ẩn khỏi phiên hiện tại.', 'info');
        }

        function showAuthPending(featureName) {
            document.getElementById('login-error').textContent = `${featureName} chưa được kết nối.`;
            showToast('Tính năng chưa kết nối', `${featureName} chưa được kết nối trong phiên bản này.`, 'info');
        }

        function openProfileModal() {
            toggleAccountMenu(false);
            togglePasswordPopover(false);
            closeOrdersModal();
            document.getElementById('profile-name').value = userProfile.name;
            document.getElementById('profile-email').value = userProfile.email;
            document.getElementById('profile-phone').value = userProfile.phone;
            document.getElementById('profile-address').value = userProfile.address;
            document.getElementById('profile-avatar-input').value = '';
            pendingAvatar = userProfile.avatar;
            document.getElementById('profile-preview-avatar').src = userProfile.avatar || getAvatarDataUrl(userProfile.name);
            setInlineMessage('profile-message');
            document.getElementById('profile-modal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeProfileModal() {
            const modal = document.getElementById('profile-modal');
            if (!modal.classList.contains('active')) return;
            modal.classList.remove('active');
            pendingAvatar = '';
            document.body.style.overflow = '';
        }

        function previewProfileAvatar(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = () => {
                pendingAvatar = reader.result;
                document.getElementById('profile-preview-avatar').src = pendingAvatar;
            };
            reader.readAsDataURL(file);
        }

        function submitProfile(event) {
            event.preventDefault();

            userProfile = {
                name: document.getElementById('profile-name').value.trim(),
                email: document.getElementById('profile-email').value.trim(),
                phone: document.getElementById('profile-phone').value.trim(),
                address: document.getElementById('profile-address').value.trim(),
                avatar: pendingAvatar
            };

            updateLoginUI();
            pendingAvatar = userProfile.avatar;
            setInlineMessage('profile-message', 'Thông tin đã được cập nhật tạm thời trên giao diện.', 'success');
            showToast('Đã lưu hồ sơ', 'Thông tin tài khoản đã được cập nhật tạm thời.', 'success');
        }

        function getOrderTotal(order) {
            return order.items.reduce((sum, item) => sum + parsePrice(item.price) * item.qty, 0);
        }

        function getOrderItemCount(order) {
            return order.items.reduce((sum, item) => sum + item.qty, 0);
        }

        function formatOrderDate(value) {
            return new Intl.DateTimeFormat('vi-VN', {
                dateStyle: 'medium',
                timeStyle: 'short'
            }).format(new Date(value));
        }

        function getPaymentLabel(payment) {
            const labels = {
                COD: 'Thanh toán khi nhận hàng',
                BANK: 'Chuyển khoản ngân hàng'
            };
            return labels[payment] || payment || 'Chưa chọn';
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
        }

        function formatStars(rating) {
            const safeRating = Math.max(1, Math.min(5, Number(rating) || 1));
            return '★★★★★'.slice(0, safeRating) + '☆☆☆☆☆'.slice(0, 5 - safeRating);
        }

        function switchProductTab(tabName) {
            const isReviews = tabName === 'reviews';
            document.getElementById('product-tab-overview').classList.toggle('active', !isReviews);
            document.getElementById('product-tab-reviews').classList.toggle('active', isReviews);
            document.getElementById('product-tab-overview').setAttribute('aria-selected', String(!isReviews));
            document.getElementById('product-tab-reviews').setAttribute('aria-selected', String(isReviews));
            document.getElementById('product-panel-overview').classList.toggle('active', !isReviews);
            document.getElementById('product-panel-reviews').classList.toggle('active', isReviews);
        }

        function getProductReviews(productId) {
            return productReviews
                .filter(review => review.productId === productId)
                .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
        }

        function getReviewKey(orderId, itemIndex) {
            return `${orderId}:${itemIndex}`;
        }

        function getInvoiceReview(orderId, itemIndex) {
            const reviewKey = getReviewKey(orderId, itemIndex);
            return productReviews.find(review => review.reviewKey === reviewKey && review.username === testAccount.username);
        }

        function renderProductReviews(productId) {
            const reviews = getProductReviews(productId);
            const scoreEl = document.getElementById('product-review-score');
            const listEl = document.getElementById('product-review-list');

            if (!scoreEl || !listEl) return;

            if (reviews.length === 0) {
                scoreEl.textContent = 'Chưa có đánh giá';
                listEl.innerHTML = renderEmptyState('Chưa có đánh giá', 'Sau khi đơn hàng hoàn tất, bạn có thể đánh giá sản phẩm trong mục đơn hàng.', '☆', true);
                return;
            }

            const average = reviews.reduce((sum, review) => sum + review.rating, 0) / reviews.length;
            scoreEl.textContent = `${average.toFixed(1)}/5 · ${reviews.length} đánh giá`;
            listEl.innerHTML = reviews.map(review => `
                <div class="review-card">
                    <div class="review-card-top">
                        <div class="review-author">${escapeHtml(review.displayName)}</div>
                        <div class="review-stars">${formatStars(review.rating)}</div>
                    </div>
                    <div class="review-text">${escapeHtml(review.comment)}</div>
                    <div class="review-date">${formatOrderDate(review.createdAt)}</div>
                </div>
            `).join('');
        }

        function renderInvoiceReview(order, item, itemIndex) {
            const existingReview = getInvoiceReview(order.id, itemIndex);
            const reviewKey = getReviewKey(order.id, itemIndex);
            const canReview = isOrderCompletedForReview(order.status);

            if (existingReview) {
                return `
                    <div class="invoice-review">
                        <div class="review-done">
                            <div class="review-done-copy">
                                <div class="review-done-title">Bạn đã đánh giá sản phẩm này</div>
                                <div class="review-done-text">${escapeHtml(existingReview.comment)}</div>
                            </div>
                            <div class="review-stars">${formatStars(existingReview.rating)}</div>
                        </div>
                    </div>
                `;
            }

            if (!canReview) {
                return `
                    <div class="invoice-review">
                        <div class="review-locked">Bạn có thể đánh giá sản phẩm sau khi đơn hàng đã hoàn tất.</div>
                    </div>
                `;
            }

            const fieldName = `rating-${order.id}-${itemIndex}`;
            const isExpanded = expandedReviewKey === reviewKey;
            return `
                <div class="invoice-review">
                    <div class="review-cta-row">
                        <div class="review-cta-copy">
                            <div class="review-form-title">Chưa đánh giá sản phẩm này</div>
                            <div class="review-locked">Mỗi sản phẩm trong đơn có một đánh giá riêng.</div>
                        </div>
                        <button class="review-toggle-btn" type="button" onclick="toggleReviewForm('${order.id}', ${itemIndex})">${isExpanded ? 'Thu gọn' : 'Đánh giá sản phẩm'}</button>
                    </div>
                    ${isExpanded ? `
                        <form class="review-form" onsubmit="submitProductReview(event, '${order.id}', ${itemIndex})">
                            <div class="rating-choice">
                                ${[1, 2, 3, 4, 5].map(value => `
                                    <input id="${fieldName}-${value}" name="${fieldName}" type="radio" value="${value}" ${value === 5 ? 'checked' : ''}>
                                    <label for="${fieldName}-${value}">${value} ★</label>
                                `).join('')}
                            </div>
                            <textarea name="comment" maxlength="240" placeholder="Chia sẻ cảm nhận của bạn về ${escapeHtml(item.name)}..." required></textarea>
                            <button class="review-submit-btn" type="submit">Gửi đánh giá</button>
                        </form>
                    ` : ''}
                </div>
            `;
        }

        function toggleReviewForm(orderId, itemIndex) {
            const reviewKey = getReviewKey(orderId, itemIndex);
            expandedReviewKey = expandedReviewKey === reviewKey ? '' : reviewKey;
            const order = orderHistory.find(entry => entry.id === orderId);
            if (order) renderOrderDetail(order);
        }

        function submitProductReview(event, orderId, itemIndex) {
            event.preventDefault();

            if (!isLoggedIn) {
                showToast('Cần đăng nhập', 'Vui lòng đăng nhập để đánh giá sản phẩm.', 'error');
                return;
            }

            const order = orderHistory.find(entry => entry.id === orderId);
            const item = order?.items?.[itemIndex];
            if (!order || !item) {
                showToast('Không thể gửi đánh giá', 'Không tìm thấy sản phẩm trong hóa đơn.', 'error');
                return;
            }

            if (!isOrderCompletedForReview(order.status)) {
                showToast('Chưa thể đánh giá', 'Bạn có thể đánh giá sản phẩm sau khi đơn hàng đã hoàn tất.', 'info');
                return;
            }

            if (getInvoiceReview(orderId, itemIndex)) {
                showToast('Đã đánh giá rồi', 'Sản phẩm này đã có đánh giá trong hóa đơn này.', 'info');
                return;
            }

            const formData = new FormData(event.target);
            const rating = Number(formData.get(`rating-${orderId}-${itemIndex}`)) || 5;
            const comment = String(formData.get('comment') || '').trim();
            if (!comment) {
                showToast('Thiếu nội dung', 'Vui lòng nhập nội dung đánh giá.', 'error');
                return;
            }

            productReviews.unshift({
                id: `rv-${Date.now()}`,
                reviewKey: getReviewKey(orderId, itemIndex),
                productId: item.id,
                orderId,
                uom: item.uom,
                username: testAccount.username,
                displayName: userProfile.name || testAccount.displayName,
                rating,
                comment,
                createdAt: new Date().toISOString()
            });

            saveProductReviews();
            expandedReviewKey = '';
            selectedOrderId = orderId;
            renderOrders(selectedOrderId);
            if (currentSelectedProduct && currentSelectedProduct.id === item.id) {
                renderProductReviews(item.id);
            }
            showToast('Đã gửi đánh giá', 'Cảm ơn bạn đã chia sẻ trải nghiệm sản phẩm.', 'success');
        }

        function renderOrders(selectedId = null) {
            const sortedOrders = [...orderHistory].sort((a, b) => new Date(b.date) - new Date(a.date));
            const ordersList = document.getElementById('orders-list');
            const ordersDetail = document.getElementById('orders-detail');
            const ordersCount = document.getElementById('orders-count');

            if (!ordersList || !ordersDetail || !ordersCount) return;

            ordersCount.textContent = `${sortedOrders.length} đơn hàng`;

            if (sortedOrders.length === 0) {
                selectedOrderId = null;
                ordersList.innerHTML = renderEmptyState('Chưa có đơn hàng', 'Khi bạn hoàn tất checkout, đơn hàng sẽ xuất hiện ở đây.', '0');
                renderOrderDetail(null);
                return;
            }

            const requestedId = selectedId || selectedOrderId;
            selectedOrderId = sortedOrders.some(order => order.id === requestedId)
                ? requestedId
                : sortedOrders[0].id;

            ordersList.innerHTML = sortedOrders.map(order => {
                const isSelected = order.id === selectedOrderId;
                const completed = isOrderCompletedForReview(order.status);
                return `
                    <button class="order-card ${isSelected ? 'active' : ''}" type="button" data-order-id="${escapeHtml(order.id)}" aria-selected="${isSelected}">
                        <div class="order-card-top">
                            <div>
                                <div class="order-card-code">${escapeHtml(order.id)}</div>
                                <div class="order-card-date">${formatOrderDate(order.date)}</div>
                            </div>
                            <div class="order-status ${completed ? 'completed' : ''}">${escapeHtml(order.status)}</div>
                        </div>
                        <div class="order-card-bottom">
                            <div class="order-card-meta">${getOrderItemCount(order)} sản phẩm</div>
                            <div class="order-card-total">${formatPrice(getOrderTotal(order))}</div>
                        </div>
                    </button>
                `;
            }).join('');

            ordersList.querySelectorAll('.order-card').forEach(card => {
                card.addEventListener('click', () => selectOrder(card.dataset.orderId));
            });

            renderOrderDetail(sortedOrders.find(order => order.id === selectedOrderId));
        }

        function selectOrder(orderId) {
            const order = orderHistory.find(entry => entry.id === orderId);
            if (!order) return;

            selectedOrderId = orderId;
            document.querySelectorAll('#orders-list .order-card').forEach(card => {
                const isSelected = card.dataset.orderId === selectedOrderId;
                card.classList.toggle('active', isSelected);
                card.setAttribute('aria-selected', isSelected ? 'true' : 'false');

                if (isSelected) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
                }
            });

            renderOrderDetail(order);
        }

        function renderOrderDetail(order) {
            const ordersDetail = document.getElementById('orders-detail');
            if (!ordersDetail) return;

            if (!order) {
                ordersDetail.innerHTML = renderEmptyState('Chưa chọn đơn hàng', 'Chọn một đơn ở danh sách bên trái để xem chi tiết sản phẩm, giao hàng và đánh giá.', '→');
                return;
            }

            const items = Array.isArray(order.items) ? order.items : [];
            const orderCompleted = isOrderCompletedForReview(order.status);
            ordersDetail.innerHTML = `
                <div class="order-detail-panel">
                    <div class="order-detail-hero">
                        <div>
                            <div class="modal-label">Chi tiết đơn hàng</div>
                            <div class="order-detail-code">${escapeHtml(order.id)}</div>
                            <div class="order-detail-date">${formatOrderDate(order.date)}</div>
                        </div>
                        <div class="order-detail-total">${formatPrice(getOrderTotal(order))}</div>
                    </div>

                    <div class="order-detail-summary">
                        <div class="order-detail-group">
                            <div class="order-detail-group-title">Đơn hàng</div>
                            <div class="order-detail-cell"><strong>Trạng thái</strong>${escapeHtml(order.status)}</div>
                            <div class="order-detail-cell"><strong>Thanh toán</strong>${escapeHtml(getPaymentLabel(order.payment))}</div>
                        </div>
                        <div class="order-detail-group">
                            <div class="order-detail-group-title">Người nhận</div>
                            <div class="order-detail-cell"><strong>Họ tên</strong>${escapeHtml(order.buyer?.name || '')}</div>
                            <div class="order-detail-cell"><strong>Số điện thoại</strong>${escapeHtml(order.buyer?.phone || '')}</div>
                        </div>
                        <div class="order-detail-group">
                            <div class="order-detail-group-title">Tổng quan</div>
                            <div class="order-detail-cell"><strong>Sản phẩm</strong>${getOrderItemCount(order)} sản phẩm</div>
                            <div class="order-detail-cell"><strong>Đánh giá</strong>${orderCompleted ? 'Đã mở' : 'Mở sau khi hoàn tất'}</div>
                        </div>
                    </div>

                    <div class="order-detail-grid">
                        <div class="order-detail-cell wide"><strong>Địa chỉ nhận hàng</strong>${escapeHtml(order.buyer?.address || '')}</div>
                        ${order.note ? `<div class="order-detail-cell wide"><strong>Ghi chú</strong>${escapeHtml(order.note)}</div>` : ''}
                    </div>

                    <div class="order-section-title">Sản phẩm trong đơn</div>
                    <div class="invoice-items">
                        ${items.length > 0 ? items.map((item, itemIndex) => `
                            <div class="invoice-row">
                                <img src="${escapeHtml(item.img || '')}" alt="${escapeHtml(item.name || 'Sản phẩm')}">
                                <div>
                                    <div class="invoice-item-name">${escapeHtml(item.name || '')}</div>
                                    <div class="invoice-item-meta">
                                        <span>${escapeHtml(item.uom || '')}</span>
                                        <span>Đơn giá: ${escapeHtml(item.price || '')}</span>
                                        <span>Số lượng: ${escapeHtml(item.qty ?? 0)}</span>
                                    </div>
                                </div>
                                <div class="invoice-item-total">${formatPrice(parsePrice(item.price) * (Number(item.qty) || 0))}</div>
                                ${renderInvoiceReview(order, item, itemIndex)}
                            </div>
                        `).join('') : renderEmptyState('Đơn hàng chưa có sản phẩm', 'Sản phẩm sẽ được hiển thị tại đây khi đơn hàng có dữ liệu.', '0', true)}
                    </div>
                </div>
            `;

            ordersDetail.scrollTop = 0;
        }

        function openOrdersModal() {
            if (!isLoggedIn) {
                showToast('Cần đăng nhập', 'Vui lòng đăng nhập để xem đơn hàng.', 'error');
                toggleLoginPopover(true);
                return;
            }

            toggleAccountMenu(false);
            togglePasswordPopover(false);
            closeProfileModal();
            selectedOrderId = null;
            expandedReviewKey = '';
            renderOrders();
            document.getElementById('orders-modal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeOrdersModal() {
            const modal = document.getElementById('orders-modal');
            if (!modal.classList.contains('active')) return;
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        function submitPasswordChange(event) {
            event.preventDefault();

            const isCodeStep = !document.getElementById('password-step-code').hidden;
            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const verifyCode = document.getElementById('verify-code').value.trim();
            const message = document.getElementById('password-message');

            message.classList.remove('success');

            if (isCodeStep) {
                if (verifyCode !== '123456') {
                    message.textContent = 'Mã xác nhận chưa đúng.';
                    return;
                }

                testAccount.password = pendingNewPassword;
                event.target.reset();
                resetPasswordPopover();
                message.textContent = 'Mật khẩu đã được cập nhật tạm thời.';
                message.classList.add('success');
                showToast('Đã đổi mật khẩu', 'Mật khẩu đã được cập nhật tạm thời.', 'success');
                return;
            }

            if (currentPassword !== testAccount.password) {
                message.textContent = 'Mật khẩu cũ chưa đúng.';
                return;
            }

            if (newPassword.length < 6) {
                message.textContent = 'Mật khẩu mới cần ít nhất 6 ký tự.';
                return;
            }

            if (newPassword !== confirmPassword) {
                message.textContent = 'Xác nhận mật khẩu mới chưa khớp.';
                return;
            }

            pendingNewPassword = newPassword;
            document.getElementById('password-step-fields').hidden = true;
            document.getElementById('password-step-code').hidden = false;
            document.getElementById('password-hint').innerHTML = 'Nhập mã xác nhận tạm thời: <strong>123456</strong>';
            document.getElementById('password-submit-btn').textContent = 'Xác nhận đổi mật khẩu';
            message.textContent = '';
            setTimeout(() => document.getElementById('verify-code').focus(), 0);
        }

        document.addEventListener('click', (event) => {
            const authArea = document.querySelector('.auth-cart');
            const header = document.querySelector('.glass-header');
            const popover = document.getElementById('login-popover');
            const accountMenu = document.getElementById('account-menu');
            const passwordPopover = document.getElementById('password-popover');

            if (mobileNavOpen && header && !header.contains(event.target)) {
                toggleMobileNav(false);
            }

            if (!authArea) return;

            if (popover && popover.classList.contains('open') && !authArea.contains(event.target)) {
                toggleLoginPopover(false);
            }

            if (accountMenu && accountMenu.classList.contains('open') && !authArea.contains(event.target)) {
                toggleAccountMenu(false);
            }

            if (passwordPopover && passwordPopover.classList.contains('open') && !authArea.contains(event.target)) {
                togglePasswordPopover(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                toggleLoginPopover(false);
                toggleAccountMenu(false);
                togglePasswordPopover(false);
                toggleMobileNav(false);
                toggleFavorites(false);
                toggleCart(false);
                closeProfileModal();
                closeOrdersModal();
            }
        });

        updateLoginUI();

        function toggleCart(forceOpen) {
            const sidebar = document.getElementById('cart-sidebar');
            if (typeof forceOpen === 'boolean') {
                sidebar.classList.toggle('open', forceOpen);
            } else {
                sidebar.classList.toggle('open');
            }
            if (sidebar.classList.contains('open')) {
                toggleFavorites(false);
                toggleMobileNav(false);
            }
        }

        function toggleFavorites(forceOpen) {
            if (!isLoggedIn && forceOpen !== false) {
                showToast('Cần đăng nhập', 'Vui lòng đăng nhập để xem sản phẩm yêu thích.', 'error');
                toggleLoginPopover(true);
                return;
            }

            const sidebar = document.getElementById('favorite-sidebar');
            if (typeof forceOpen === 'boolean') {
                sidebar.classList.toggle('open', forceOpen);
            } else {
                sidebar.classList.toggle('open');
            }
            if (sidebar.classList.contains('open')) {
                toggleCart(false);
                toggleMobileNav(false);
                renderFavoriteItems();
            }
        }

        function parsePrice(price) {
            return Number(String(price).replace(/[^\d]/g, '')) || 0;
        }

        function formatPrice(value) {
            return `${value.toLocaleString('vi-VN')}đ`;
        }

        function getCartTotal() {
            return cartList.reduce((sum, item) => sum + parsePrice(item.price) * item.qty, 0);
        }

        function getSelectedModalItem() {
            const selectedElement = document.querySelector('.price-option.selected');
            const selectedOption = selectedElement?.dataset.uom || selectedElement?.querySelector('.opt-uom')?.textContent || '';
            const selectedPrice = selectedElement?.dataset.price || selectedElement?.querySelector('.opt-price')?.textContent || '';
            const selectedUomId = selectedElement?.dataset.uomId || selectedOption;

            return {
                id: currentSelectedProduct.id,
                name: currentSelectedProduct.name,
                img: getProductImages(currentSelectedProduct)[0] || '',
                uom: selectedOption,
                uomId: selectedUomId,
                price: selectedPrice,
                qty: currentModalQty
            };
        }

        function getProductOptions(productId) {
            const product = STORE_PRODUCTS.find(item => item.id === productId);
            return product ? product.options : [];
        }

        function renderCartUomSelect(item, index) {
            const options = getProductOptions(item.id);
            if (options.length === 0) {
                return `<span class="cart-item-meta">Khối lượng: ${escapeHtml(item.uom)}</span>`;
            }

            return `
                <span class="cart-uom-control">
                    <select class="cart-uom-select" onchange="changeCartUom(${index}, this.value)" aria-label="Chọn khối lượng">
                        ${options.map(opt => `<option value="${escapeHtml(getOptionKey(opt))}" ${getOptionKey(opt) === getItemUomKey(item) ? 'selected' : ''}>${escapeHtml(opt.uom)}</option>`).join('')}
                    </select>
                </span>
            `;
        }

        function updateCartUI() {
            const totalQty = cartList.reduce((sum, item) => sum + item.qty, 0);
            document.getElementById('cart-badge').textContent = totalQty;
            document.getElementById('cart-total').textContent = formatPrice(getCartTotal());
            document.getElementById('cart-checkout-btn').disabled = cartList.length === 0;
            saveAccountState();

            const container = document.getElementById('cart-items');
            if (cartList.length === 0) {
                container.innerHTML = renderEmptyState('Giỏ hàng đang trống', 'Chọn một đặc sản để bắt đầu đơn hàng.', '0');
                return;
            }

            container.innerHTML = cartList.map((item, index) => `
                <div class="cart-item">
                    <img src="${escapeHtml(item.img)}" alt="${escapeHtml(item.name)}">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${escapeHtml(item.name)}</div>
                        ${renderCartUomSelect(item, index)}
                        <div class="cart-item-price">${escapeHtml(item.price)} x ${escapeHtml(item.qty)}</div>
                        <div class="cart-item-actions">
                            <button class="qty-control" onclick="changeCartQty(${index}, -1)" aria-label="Giảm số lượng">−</button>
                            <span class="cart-qty">${item.qty}</span>
                            <button class="qty-control" onclick="changeCartQty(${index}, 1)" aria-label="Tăng số lượng">+</button>
                        </div>
                    </div>
                    <button class="remove-item-btn" onclick="removeFromCart(${index})" aria-label="Xóa sản phẩm">×</button>
                </div>
            `).join('');
        }

        function changeCartUom(index, nextUomKey) {
            const item = cartList[index];
            if (!item) return;

            const option = getProductOptions(item.id).find(opt => getOptionKey(opt) === nextUomKey || opt.uom === nextUomKey);
            if (!option) return;

            const existingIndex = cartList.findIndex((entry, entryIndex) =>
                entryIndex !== index && entry.id === item.id && getItemUomKey(entry) === getOptionKey(option)
            );

            if (existingIndex >= 0) {
                cartList[existingIndex].qty += item.qty;
                cartList.splice(index, 1);
            } else {
                item.uom = option.uom;
                item.uomId = getOptionKey(option);
                item.price = option.price;
            }

            updateCartUI();
        }

        function changeCartQty(index, delta) {
            const item = cartList[index];
            if (!item) return;

            item.qty += delta;
            if (item.qty <= 0) {
                cartList.splice(index, 1);
            }
            updateCartUI();
        }

        function removeFromCart(index) {
            cartList.splice(index, 1);
            updateCartUI();
        }

        function isFavorite(productId) {
            return favoriteList.includes(productId);
        }

        function updateFavoriteButtons() {
            document.querySelectorAll('[data-favorite-id]').forEach(button => {
                const active = isFavorite(button.dataset.favoriteId);
                button.classList.toggle('active', active);
                button.setAttribute('aria-label', active ? 'Bỏ yêu thích' : 'Thêm sản phẩm yêu thích');
                const buttonLabel = button.querySelector('.sr-only');
                if (buttonLabel) {
                    buttonLabel.textContent = active ? 'Bỏ yêu thích' : 'Thêm sản phẩm yêu thích';
                }
            });

            if (currentSelectedProduct) {
                const modalBtn = document.getElementById('modal-favorite-btn');
                const active = isFavorite(currentSelectedProduct.id);
                modalBtn.classList.toggle('active', active);
                modalBtn.setAttribute('aria-label', active ? 'Bỏ yêu thích' : 'Thêm vào yêu thích');
                const modalBtnLabel = modalBtn.querySelector('.sr-only');
                if (modalBtnLabel) {
                    modalBtnLabel.textContent = active ? 'Bỏ yêu thích' : 'Thêm vào yêu thích';
                }
            }
        }

        function updateFavoriteUI() {
            const badge = document.getElementById('favorite-badge');
            if (badge) badge.textContent = favoriteList.length;
            if (document.getElementById('favorite-sidebar').classList.contains('open')) {
                renderFavoriteItems();
            }
            updateFavoriteButtons();
            saveAccountState();
        }

        function toggleFavoriteProduct(productId) {
            if (!isLoggedIn) {
                showToast('Cần đăng nhập', 'Vui lòng đăng nhập để lưu sản phẩm yêu thích.', 'error');
                toggleLoginPopover(true);
                return;
            }

            const product = STORE_PRODUCTS.find(item => item.id === productId);
            if (isFavorite(productId)) {
                favoriteList = favoriteList.filter(id => id !== productId);
                showToast('Đã bỏ yêu thích', product?.name || 'Sản phẩm', 'info');
            } else {
                favoriteList.push(productId);
                showToast('Đã lưu yêu thích', product?.name || 'Sản phẩm', 'success');
            }

            updateFavoriteUI();
        }

        function toggleFavoriteFromModal() {
            if (!currentSelectedProduct) return;
            toggleFavoriteProduct(currentSelectedProduct.id);
        }

        function renderFavoriteItems() {
            const container = document.getElementById('favorite-items');
            if (!container) return;

            const products = favoriteList
                .map(id => STORE_PRODUCTS.find(product => product.id === id))
                .filter(Boolean);

            if (products.length === 0) {
                container.innerHTML = renderEmptyState('Chưa có sản phẩm yêu thích', 'Lưu lại những món bạn muốn quay lại sau.', '♡');
                return;
            }

            container.innerHTML = products.map(product => `
                <div class="favorite-item">
                    <img src="${escapeHtml(product.images[0])}" alt="${escapeHtml(product.name)}">
                    <div class="favorite-item-info">
                        <div class="favorite-item-name">${escapeHtml(product.name)}</div>
                        <div class="favorite-item-meta">${product.region === 'gia-lai' ? 'Gia Lai' : 'Bình Định'}</div>
                        <div class="favorite-item-price">${escapeHtml(product.options[0].price)}</div>
                        <button class="favorite-add-cart-btn" type="button" onclick="addFavoriteToCart('${product.id}')">Thêm vào giỏ</button>
                    </div>
                    <button class="remove-item-btn" onclick="removeFavorite('${product.id}')" aria-label="Xóa khỏi yêu thích">×</button>
                </div>
            `).join('');
        }

        function addFavoriteToCart(productId) {
            const product = STORE_PRODUCTS.find(item => item.id === productId);
            if (!product) return;

            const selected = {
                id: product.id,
                name: product.name,
                img: product.images[0],
                uom: product.options[0].uom,
                uomId: getOptionKey(product.options[0]),
                price: product.options[0].price,
                qty: 1
            };
            const existing = cartList.find(item => item.id === selected.id && getItemUomKey(item) === selected.uomId);

            if (existing) {
                existing.qty += 1;
            } else {
                cartList.push(selected);
            }

            updateCartUI();
            showToast('Đã thêm vào giỏ', `${product.name} · ${selected.uom}`, 'success');
        }

        function removeFavorite(productId) {
            favoriteList = favoriteList.filter(id => id !== productId);
            updateFavoriteUI();
        }

        function addToCart() {
            const selectedItem = getSelectedModalItem();
            const existing = cartList.find(item => item.id === selectedItem.id && getItemUomKey(item) === getItemUomKey(selectedItem));

            if (existing) {
                existing.qty += selectedItem.qty;
            } else {
                cartList.push(selectedItem);
            }

            updateCartUI();
            showToast('Đã thêm vào giỏ', `${selectedItem.name} · ${selectedItem.uom} · SL ${selectedItem.qty}`, 'success');
        }

        let checkoutItems = [];
        let checkoutSource = 'cart';

        function setCheckoutSubmitting(isSubmitting) {
            checkoutSubmitting = isSubmitting;
            const form = document.getElementById('checkout-form');
            const submitBtn = document.getElementById('checkout-submit-btn');

            if (form) {
                form.querySelectorAll('input, textarea, select, button').forEach(control => {
                    control.disabled = isSubmitting;
                });
            }

            if (submitBtn) {
                submitBtn.classList.toggle('is-loading', isSubmitting);
                submitBtn.textContent = isSubmitting ? 'Đang xác nhận' : 'Hoàn Tất Đặt Hàng';
            }
        }

        function setCheckoutSuccessState(isSuccess, message = '') {
            checkoutSuccess = isSuccess;
            const form = document.getElementById('checkout-form');
            const successPanel = document.getElementById('checkout-success');
            const successText = document.getElementById('checkout-success-text');

            if (form) form.hidden = isSuccess;
            if (successPanel) successPanel.hidden = !isSuccess;
            if (successText && message) successText.textContent = message;
        }

        function renderCheckout(items) {
            checkoutItems = items.map(item => ({ ...item }));
            const total = checkoutItems.reduce((sum, item) => sum + parsePrice(item.price) * item.qty, 0);

            document.getElementById('checkout-total').textContent = formatPrice(total);
            document.getElementById('checkout-items').innerHTML = checkoutItems.map(item => `
                <div class="order-item">
                    <img src="${escapeHtml(item.img)}" alt="${escapeHtml(item.name)}">
                    <div>
                        <div class="order-item-name">${escapeHtml(item.name)}</div>
                        <div class="order-item-meta">${escapeHtml(item.uom)} · Số lượng: ${escapeHtml(item.qty)}</div>
                        <div class="order-item-price">${escapeHtml(item.price)} x ${escapeHtml(item.qty)}</div>
                    </div>
                </div>
            `).join('');
        }

        function fillCheckoutBuyerInfo() {
            if (!isLoggedIn) return;

            document.getElementById('buyer-name').value = userProfile.name || '';
            document.getElementById('buyer-phone').value = userProfile.phone || '';
            document.getElementById('buyer-address').value = userProfile.address || '';
        }

        function openCheckout(items, source = 'cart') {
            if (!items || items.length === 0) {
                showToast('Giỏ hàng đang trống', 'Chọn sản phẩm trước khi thanh toán.', 'error');
                return;
            }

            checkoutSource = source;
            const form = document.getElementById('checkout-form');
            if (form) form.reset();
            setCheckoutSubmitting(false);
            setCheckoutSuccessState(false);
            setInlineMessage('checkout-message');
            renderCheckout(items);
            fillCheckoutBuyerInfo();
            document.getElementById('checkout-modal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function openCheckoutFromCart() {
            toggleCart(false);
            openCheckout(cartList, 'cart');
        }

        function closeCheckout() {
            if (checkoutSubmitting) return;
            document.getElementById('checkout-modal').classList.remove('active');
            setCheckoutSuccessState(false);
            setInlineMessage('checkout-message');
            document.body.style.overflow = '';
        }

        async function submitCheckout(event) {
            event.preventDefault();
            if (checkoutSubmitting) return;

            const buyerName = document.getElementById('buyer-name').value.trim();
            const buyerPhone = document.getElementById('buyer-phone').value.trim();
            const buyerAddress = document.getElementById('buyer-address').value.trim();
            const buyerPayment = document.getElementById('buyer-payment').value;
            const buyerNote = document.getElementById('buyer-note').value.trim();
            const total = checkoutItems.reduce((sum, item) => sum + parsePrice(item.price) * item.qty, 0);

            if (!checkoutItems.length) {
                setInlineMessage('checkout-message', 'Chưa có sản phẩm nào trong đơn hàng.', 'error');
                return;
            }

            const invalidClientItem = checkoutItems.find(item => !item.id || !getItemUomKey(item) || !(Number(item.qty) > 0));
            if (invalidClientItem) {
                setInlineMessage('checkout-message', 'Giỏ hàng có sản phẩm thiếu UOM hợp lệ. Vui lòng chọn lại sản phẩm.', 'error');
                return;
            }

            try {
                setInlineMessage('checkout-message', 'Đang gửi đơn hàng đến hệ thống...', 'info');
                setCheckoutSubmitting(true);
                const response = await fetch('index.php?action=checkout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        checkout_token: CHECKOUT_TOKEN,
                        customer_name: buyerName,
                        customer_phone: buyerPhone,
                        customer_address: buyerAddress,
                        shipping_method: 'delivery',
                        shipping_zone_id: STORE_DEFAULT_SHIPPING_ZONE_ID,
                        payment_method: buyerPayment,
                        note: buyerNote,
                        items: checkoutItems.map(item => ({
                            product_id: item.id,
                            uom_id: getItemUomKey(item),
                            qty: Number(item.qty) || 0
                        }))
                    })
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Không thể tạo đơn hàng. Vui lòng thử lại.');
                }

                const serverOrder = data.order || {};
                CHECKOUT_TOKEN = data.next_checkout_token || CHECKOUT_TOKEN;
                const serverTotal = Number(serverOrder.total_vnd || total);

                if (isLoggedIn) {
                    orderHistory.unshift({
                        id: serverOrder.order_id || `DH-${Date.now()}`,
                        date: new Date().toISOString(),
                        status: serverOrder.status_label || 'Đã tiếp nhận',
                        buyer: {
                            name: buyerName,
                            phone: buyerPhone,
                            address: buyerAddress
                        },
                        payment: buyerPayment,
                        note: buyerNote,
                        items: checkoutItems.map(item => ({ ...item }))
                    });
                    saveAccountState();
                }

                if (checkoutSource === 'cart') {
                    cartList = [];
                    updateCartUI();
                    saveAccountState();
                }

                event.target.reset();
                setCheckoutSubmitting(false);
                setInlineMessage('checkout-message');
                setCheckoutSuccessState(true, `Cảm ơn ${buyerName}. Mã đơn ${serverOrder.order_id} đã được ghi nhận. Tổng thanh toán: ${formatPrice(serverTotal)}.`);
                showToast('Đặt hàng thành công', `Mã đơn: ${serverOrder.order_id}`, 'success');
                return;
            } catch (error) {
                setCheckoutSubmitting(false);
                setInlineMessage('checkout-message', error.message || 'Không thể tạo đơn hàng lúc này.', 'error');
                showToast('Không thể đặt hàng', error.message || 'Vui lòng kiểm tra lại thông tin.', 'error');
                return;
            }

        }

        function openOrdersFromSuccess() {
            showToast('Đơn hàng đã được ghi nhận', 'Mã đơn nằm trong thông báo xác nhận. Admin có thể tra cứu trong admin.php.', 'info');
        }

        function buyNow() {
            const selectedItem = getSelectedModalItem();
            closeModal();
            openCheckout([selectedItem], 'instant');
        }

        updateCartUI();
        updateFavoriteUI();

        /**
         * 2. STATE & MATH HELPERS
         */
        const state = { 
            targetY: 0, currentY: 0, progress: 0, time: 0, 
            ease: 0.06, isMobile: window.innerWidth < 768,
            introAlpha: 0 
        };
        const lerp = (start, end, f) => start + (end - start) * f;
        const clamp = (val, min, max) => Math.max(min, Math.min(max, val));
        const smoothstep = (min, max, value) => {
            if (min >= max) return value >= max ? 1 : 0;
            const x = clamp((value - min) / (max - min), 0, 1);
            return x * x * (3 - 2 * x);
        };
        const windowAlpha = (p, inA, inB, outA, outB) => smoothstep(inA, inB, p) * (1 - smoothstep(outA, outB, p));
        const mapRange = (v, iM, iX, oM, oX) => clamp(oM + (oX - oM) * ((v - iM) / (iX - iM)), Math.min(oM, oX), Math.max(oM, oX));

        // BỎ MAGNETIC SCROLL - Lắng nghe scroll đơn thuần không ép cuộn
        window.addEventListener('scroll', () => {
            state.targetY = window.scrollY || 0;
        }, { passive: true });

        /**
         * 3. THE METEOROLOGICAL ENGINE
         */
        const canvas = document.getElementById('env-canvas');
        const ctx = canvas.getContext('2d', { alpha: true });
        let cw, ch;
        const eco = { s1: [], s2: [], s3: [], s4: [] };

        function resize() {
            cw = window.innerWidth; ch = window.innerHeight;
            state.isMobile = cw < 768; // Cập nhật state thiết bị
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            canvas.width = Math.floor(cw * dpr); canvas.height = Math.floor(ch * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            initEco();
        }
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(resize, 120);
        });

        function initEco() {
            eco.s1 = []; eco.s2 = []; eco.s3 = []; eco.s4 = [];
            const m = state.isMobile ? 0.42 : 0.72;

            for(let i=0; i<15*m; i++) eco.s1.push({ type: 'haze', x: Math.random()*cw, y: Math.random()*ch, z: Math.random(), size: 100 + Math.random()*200, seed: Math.random()*100 });
            for(let i=0; i<40*m; i++) eco.s1.push({ type: 'mote', x: Math.random()*cw, y: Math.random()*ch, z: Math.random(), size: 0.8 + Math.random()*1.5, seed: Math.random()*100 });

            for(let i=0; i<10*m; i++) eco.s2.push({ type: 'haze', x: Math.random()*cw, y: ch*0.4 + Math.random()*ch*0.6, z: Math.random(), size: 150 + Math.random()*250, seed: Math.random()*100 });
            for(let i=0; i<60*m; i++) eco.s2.push({ type: 'pollen', x: Math.random()*cw, y: ch*0.3 + Math.random()*ch*0.7, z: Math.random(), size: 1 + Math.random()*2.5, seed: Math.random()*100, vx: 0 });

            for(let i=0; i<15*m; i++) eco.s3.push({ type: 'mist', x: Math.random()*cw, y: ch*0.4 + Math.random()*ch*0.4, z: Math.random(), size: 80 + Math.random()*150 });
            for(let i=0; i<40*m; i++) eco.s3.push({ type: 'spray', x: Math.random()*cw, y: ch*0.6 + Math.random()*ch*0.4, z: Math.random(), size: 0.8 + Math.random()*2, seed: Math.random()*100 });
            for(let i=0; i<30*m; i++) eco.s3.push({ type: 'shimmer', x: Math.random()*cw, y: ch*0.53 + Math.random()*ch*0.08, size: 0.5 + Math.random(), seed: Math.random()*100 });

            for(let i=0; i<40*m; i++) eco.s4.push({ x: Math.random()*cw, y: Math.random()*ch, z: Math.random(), size: 1.5 + Math.random()*4, seed: Math.random()*100 });
        }
        resize();

        function drawEco(p1, p2, p3, p4) {
            ctx.clearRect(0, 0, cw, ch); 
            state.time += 0.005;
            
            if(p1 > 0.005) {
                ctx.globalCompositeOperation = 'screen';
                eco.s1.forEach(pt => {
                    pt.x += (0.1 + pt.z * 0.3); pt.y -= (0.2 + pt.z * 0.4); 
                    if(pt.y < -pt.size) { pt.y = ch + pt.size; pt.x = Math.random()*cw; }
                    if(pt.x > cw + pt.size) pt.x = -pt.size;

                    if(pt.type === 'haze') {
                        let grad = ctx.createRadialGradient(pt.x, pt.y, 0, pt.x, pt.y, pt.size);
                        grad.addColorStop(0, `rgba(235, 245, 255, ${0.04 * p1 * pt.z})`);
                        grad.addColorStop(1, 'rgba(235, 245, 255, 0)');
                        ctx.beginPath(); ctx.fillStyle = grad; ctx.arc(pt.x, pt.y, pt.size, 0, Math.PI*2); ctx.fill();
                    } else {
                        let lightAlpha = Math.max(0, Math.sin(pt.x*0.003 - pt.y*0.003 + state.time));
                        let alpha = 0.4 * p1 * lightAlpha * (0.5 + pt.z);
                        if (alpha > 0.02) {
                            ctx.beginPath(); ctx.fillStyle = `rgba(255, 250, 240, ${alpha})`;
                            ctx.arc(pt.x, pt.y, pt.size, 0, Math.PI*2); ctx.fill();
                        }
                    }
                });
            }

            if(p2 > 0.005) {
                ctx.globalCompositeOperation = 'screen';
                eco.s2.forEach(pt => {
                    if(pt.type === 'haze') {
                        pt.x += (0.5 + pt.z); if(pt.x > cw + pt.size) pt.x = -pt.size;
                        let grad = ctx.createRadialGradient(pt.x, pt.y, 0, pt.x, pt.y, pt.size);
                        grad.addColorStop(0, `rgba(255, 240, 180, ${0.03 * p2 * pt.z})`);
                        grad.addColorStop(1, 'rgba(255, 240, 180, 0)');
                        ctx.beginPath(); ctx.fillStyle = grad; ctx.arc(pt.x, pt.y, pt.size, 0, Math.PI*2); ctx.fill();
                    } else {
                        let localGust = Math.max(0, Math.sin(pt.x * 0.002 - state.time * 3));
                        pt.vx = lerp(pt.vx, 0.5 + localGust * 3, 0.05);
                        
                        pt.x += pt.vx * (0.5 + pt.z);
                        pt.y += Math.sin(pt.seed + state.time * 2) * 0.4;
                        if(pt.x > cw + 50) { pt.x = -50; pt.vx = 0; }

                        let stretch = pt.size + pt.vx * 1.5;
                        ctx.beginPath(); ctx.fillStyle = `rgba(255, 245, 190, ${0.5 * p2 * (0.5 + pt.z)})`;
                        ctx.ellipse(pt.x, pt.y, stretch, pt.size, 0, 0, Math.PI*2); ctx.fill();
                    }
                });
            }

            if(p3 > 0.005) {
                ctx.globalCompositeOperation = 'screen';
                eco.s3.forEach(pt => {
                    if(pt.type === 'mist') {
                        pt.x -= (0.4 + pt.z * 0.8); if(pt.x < -pt.size) pt.x = cw + pt.size;
                        let grad = ctx.createRadialGradient(pt.x, pt.y, 0, pt.x, pt.y, pt.size);
                        grad.addColorStop(0, `rgba(220, 240, 255, ${0.03 * p3 * pt.z})`);
                        grad.addColorStop(1, 'rgba(220, 240, 255, 0)');
                        ctx.beginPath(); ctx.fillStyle = grad; 
                        ctx.ellipse(pt.x, pt.y, pt.size*1.5, pt.size*0.5, 0, 0, Math.PI*2); ctx.fill();
                    } else if (pt.type === 'spray') {
                        pt.x -= (2 + pt.z * 3); pt.y += Math.sin(pt.seed + pt.x*0.01)*0.2;
                        if(pt.x < -20) pt.x = cw + 20;
                        ctx.beginPath(); ctx.fillStyle = `rgba(230, 245, 255, ${0.3 * p3 * pt.z})`;
                        ctx.ellipse(pt.x, pt.y, pt.size*3, pt.size, 0, 0, Math.PI*2); ctx.fill();
                    } else {
                        let twinkle = (Math.sin(state.time * 15 + pt.seed) + 1) / 2;
                        ctx.beginPath(); ctx.fillStyle = `rgba(255, 230, 200, ${0.4 * p3 * twinkle})`;
                        ctx.fillRect(pt.x, pt.y, pt.size * 5, pt.size);
                    }
                });
            }

            if(p4 > 0.005) {
                ctx.globalCompositeOperation = 'source-over';
                eco.s4.forEach(pt => {
                    pt.y -= (0.5 + pt.z * 1.5);
                    pt.x += Math.sin(state.time * 1.5 + pt.seed) * 0.6;
                    if(pt.y < -20) pt.y = ch + 20;

                    let bAlpha = p4 * (0.2 + pt.z * 0.3);
                    ctx.beginPath(); ctx.arc(pt.x, pt.y, pt.size, 0, Math.PI*2);
                    ctx.fillStyle = `rgba(180, 235, 255, ${bAlpha * 0.2})`;
                    ctx.strokeStyle = `rgba(180, 235, 255, ${bAlpha})`;
                    ctx.lineWidth = 1; ctx.fill(); ctx.stroke();
                });
            }
        }

        /**
         * 4. CHOREOGRAPHY & COMPONENT REVEAL
         */
        const els = {
            bg1: document.getElementById('bg1'), ch1: document.getElementById('ch1'),
            bg2: document.getElementById('bg2'), ch2: document.getElementById('ch2'),
            bg3: document.getElementById('bg3'), ch3: document.getElementById('ch3'),
            bg4: document.getElementById('bg4'), ch4: document.getElementById('ch4'),
            veil1: document.getElementById('veil1'), veil2: document.getElementById('veil2')
        };

        const glCards = document.querySelectorAll('#gialai-products .cinematic-card');
        const bdCards = document.querySelectorAll('#binhdinh-products .cinematic-card');

        function animateCards(cards, progress, isLast = false) {
            cards.forEach((card, index) => {
                let startOffset = Math.min(index * 0.04, 0.42); 
                let cardP = smoothstep(startOffset, startOffset + 0.3, progress);
                
                if (!isLast && progress > 0.85) {
                    cardP = 1 - smoothstep(0.85, 1, progress);
                }

                card.style.opacity = cardP;
                card.style.transform = `translate3d(0, ${lerp(40, 0, cardP)}px, 0)`;
                card.style.pointerEvents = cardP > 0.35 ? 'auto' : 'none';
            });
        }

        function setActiveChapter(activeChapter) {
            [els.ch1, els.ch2, els.ch3, els.ch4].forEach(chapter => {
                const isActive = chapter === activeChapter;
                chapter.classList.toggle('is-active', isActive);
                chapter.style.zIndex = isActive ? 2 : 1;
            });

            document.querySelectorAll('.nav-links a').forEach(link => {
                link.classList.toggle('active', link.dataset.nav === activeChapter.id);
            });
        }

        function updateDOM(p) {
            const p1 = windowAlpha(p, 0, 0, 0.25, 0.35);
            const p2 = windowAlpha(p, 0.2, 0.3, 0.5, 0.6);
            const p3 = windowAlpha(p, 0.45, 0.55, 0.75, 0.85);
            const p4 = windowAlpha(p, 0.7, 0.8, 1, 1);
            const ch1Alpha = windowAlpha(p, 0, 0, 0.15, 0.25);
            const ch2Alpha = windowAlpha(p, 0.25, 0.35, 0.45, 0.55);
            const ch3Alpha = windowAlpha(p, 0.5, 0.58, 0.72, 0.8);
            const ch4Alpha = windowAlpha(p, 0.8, 0.88, 1, 1);

            if (p >= 0.78) {
                setActiveChapter(els.ch4);
            } else if (p >= 0.48) {
                setActiveChapter(els.ch3);
            } else if (p >= 0.22) {
                setActiveChapter(els.ch2);
            } else {
                setActiveChapter(els.ch1);
            }

            if (p >= 0.54 && p < 0.78) {
                playFilmStripIntro('gialai-products');
            }

            if (p >= 0.84) {
                playFilmStripIntro('binhdinh-products');
            }

            drawEco(p1, p2, p3, p4);

            state.introAlpha += (1 - state.introAlpha) * 0.03;
            if (state.introAlpha > 0.999) state.introAlpha = 1;

            const ambient1 = 1 + Math.sin(state.time * 0.4) * 0.005;
            const ambient2 = 1 + Math.sin(state.time * 0.3) * 0.005;
            const ambient3 = 1 + Math.sin(state.time * 0.5) * 0.005;
            const ambient4 = 1 + Math.sin(state.time * 0.2) * 0.005;

            const introScale = lerp(1.15, 1.0, state.introAlpha);
            const scrollScale1 = lerp(1.05, 1, p1); 
            
            els.bg1.style.opacity = p1 * state.introAlpha; 
            els.bg1.style.transform = `scale3d(${introScale * scrollScale1 * ambient1}, ${introScale * scrollScale1 * ambient1}, 1)`;
            
            els.ch1.style.opacity = ch1Alpha;
            els.ch1.style.transform = `translate3d(0, ${lerp(0, -50, 1 - p1)}px, 0)`;
            els.veil1.style.opacity = windowAlpha(p, 0.15, 0.25, 0.35, 0.45);

            canvas.style.opacity = state.introAlpha;

            els.bg2.style.opacity = windowAlpha(p, 0.22, 0.32, 0.55, 0.65);
            els.bg2.style.transform = `scale3d(${lerp(1, 1.04, p2) * ambient2}, ${lerp(1, 1.04, p2) * ambient2}, 1)`;
            els.ch2.style.opacity = ch2Alpha;
            els.ch2.style.transform = `translate3d(0, ${mapRange(p, 0.15, 0.5, 50, -50)}px, 0)`;

            els.bg3.style.opacity = windowAlpha(p, 0.48, 0.58, 0.82, 0.92);
            els.bg3.style.transform = `scale3d(${lerp(1, 1.04, p3) * ambient3}, ${lerp(1, 1.04, p3) * ambient3}, 1)`;
            els.ch3.style.opacity = ch3Alpha;
            
            let internalP3 = clamp((p - 0.48) / 0.3, 0, 1);
            animateCards(glCards, internalP3, false);

            els.veil2.style.opacity = windowAlpha(p, 0.7, 0.78, 0.88, 0.98);

            els.bg4.style.opacity = windowAlpha(p, 0.75, 0.85, 1, 1);
            els.bg4.style.transform = `scale3d(${lerp(1, 1.05, p4) * ambient4}, ${lerp(1, 1.05, p4) * ambient4}, 1)`;
            els.ch4.style.opacity = ch4Alpha;
            
            let internalP4 = clamp((p - 0.78) / 0.22, 0, 1);
            animateCards(bdCards, internalP4, true);
        }

        function loop() {
            state.currentY = lerp(state.currentY, state.targetY, state.ease);
            const max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
            state.progress = max > 0 ? clamp(state.currentY / max, 0, 1) : 0;
            updateDOM(state.progress);
            requestAnimationFrame(loop);
        }
        
        canvas.style.opacity = 0; 
        loop();

    </script>
</body>
</html>
