# Customer Account, Best Sellers, and Reviews Design

## Scope

Build a customer account flow before OTP integration:

- Register and log in with phone number and password.
- Save customer name, phone, and default address.
- Prefill checkout for signed-in customers while keeping fields editable.
- Show customer order history.
- Allow reviews only for products in completed customer orders.
- Publish only approved 4–5 star reviews on the storefront.
- Show automatic best sellers from non-cancelled orders in section 1.
- Replace the two regional cards in section 2 with approved customer reviews.
- Add an admin review moderation page.

## Authentication

PHP session stores the authenticated `customer_id`. Passwords use `password_hash()` and `password_verify()`. The service boundary is `CustomerAuthService`; a future OTP provider can replace credential verification without changing profiles, orders, reviews, or checkout.

Existing customers created during guest checkout can claim their phone number by registering once if they do not already have a password.

## Data model

`customers` gains:

- `password_hash`
- `is_active`
- `last_login_at`

`product_reviews` stores customer, order, product, rating, comment, moderation status, and timestamps. A unique key on order/product/customer prevents duplicate reviews.

## Storefront

- Header button changes between “Đăng nhập” and “Tài khoản”.
- Authentication modal supports login and registration.
- Account modal supports profile editing, order history, logout, and review submission.
- Section 1 replaces trust pills with three best-selling product links.
- Section 2 keeps story/contact content and uses the right column for approved positive reviews.
- Empty review state is honest; no fake reviews are seeded.

## Admin

Owner/admin can open “Đánh giá”, inspect pending reviews, and approve or reject them. Only approved ratings of 4 or 5 appear publicly.

## Security

- Normalize and validate Vietnamese phone numbers.
- Minimum password length: 8 characters.
- Require CSRF checkout token for customer mutations.
- Regenerate the session ID after login/register/logout.
- Never expose password hashes.
- Verify review ownership using session customer ID and completed order items.

