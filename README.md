<<<<<<< HEAD
# Shopnest
=======
# ShopNest — Vue 3 E-Commerce Demo

A complete **frontend demo** for the requested ecommerce pages. Original design inspired only by the functionality of the supplied reference, not a replica. This is a **student prototype** with browser localStorage persistence; it does **not** include a backend database, real payment, email delivery or production-grade authentication.

## Requirements

- Node.js 20.19+ or 22.12+ (recommended: current LTS)
- npm

## Quick start

```bash
npm install
npm run dev
```

Open the URL printed by Vite (often `http://localhost:5173`).

## Demo accounts

| Role | Email | Password |
|---|---|---|
| Customer | `customer@shopnest.test` | `Customer123!` |
| Admin | `admin@shopnest.test` | `Admin123!` |

On the Login screen, click the relevant **Sign in as ...** button. You can also create a demo customer account. **Never use a real password or sensitive information** in this demo: all account records, including plaintext demo passwords, are kept in browser localStorage.

## Included pages

- Home `/`
- Login `/login`; Signup `/signup`
- Catalog `/catalog`; Product details `/products/:id`
- Cart `/cart`; Checkout `/checkout`; Confirmation `/order-confirmed/:id`
- User dashboard `/dashboard`; Purchase history `/orders`; Order details `/orders/:id`
- Admin overview `/admin`; Products `/admin/products`; Add `/admin/products/new`; Edit `/admin/products/:id/edit`
- Admin orders `/admin/orders`; Admin order detail `/admin/orders/:id`; Activity log `/admin/activity`
- Fallback 404

## Working demo features

- Search, combined filters, sort, pagination with URL query synchronization
- Product likes (signed-in only), cart updates and browser persistence
- Register/sign in as demo user; role-gated route navigation (**not real server authorization**)
- Responsive customer/store/admin layouts, semantic forms and basic keyboard accessibility
- Simulated checkout and order confirmation; purchase history and user dashboard
- Admin product CRUD (including draft/published and physical stock), admin fulfillment status, activity log
- Responsive admin table scrolling and navigation drawer; accessible sales table alternative

## IMPORTANT: Limitations and assignment completion

The assignment PDF specifically requires a **RESTful API and backend database**. This ZIP does NOT fulfill that part yet: its demo stores everything client-side in localStorage. For the final assessed deployment you must integrate a backend and database, move login/session/role checks and CRUD validation to the server, use server-created audit records, and replace the simulated checkout with an appropriate test or real payment service. Do not treat the demo as production-ready.

Product imagery is an original CSS/emoji illustrative treatment (not uploaded product photos). To add true product images or secure digital files, implement server-side upload/file storage.

Do not collect real card numbers using this prototype.

## Build/deployment

```bash
npm run build
npm run preview
```

Deploy the `dist/` output to a static host supporting SPA fallback routes. The assignment specifies Mercury; verify Mercury supports rewriting missing paths to `index.html`, or adapt routing to hash mode if necessary. Check the server-provided Node version and hosting rules.

## Design and accessibility

- Mobile-first responsive design with Bootstrap grid, scoped reusable Vue components and CSS tokens.
- Text and interactive color palette selected for WCAG AA contrast on intended surfaces.
- Please still complete real-device, keyboard, screen-reader, zoom/reflow and automated axe/Lighthouse testing before submission. Automated tools cannot fully verify accessibility.

## Resetting the demo

Open developer tools → Application → Local Storage → remove `shopnest-demo-v1` and reload.

## Suggested next steps for your project

1. Run app; sign in as customer; create a demo order.
2. Sign out; sign in as admin; view that order and update fulfillment.
3. Create/edit/delete a product; browse Activity Log and storefront.
4. Add the backend REST API/database and formal tests before submitting the university assignment.

## PDF receipt & cancellation demonstration

This edition adds `jspdf` and `jspdf-autotable`. Run `npm install` again after extracting. Paid **demo** orders offer a **Download PDF Receipt** action on confirmation, order details, and purchase history. The generated PDF contains the order snapshot, itemized prices, shipping, and demo-paid status.

On Order Details, customers can **Cancel / Request Refund** for processing or digital-delivered orders. An admin can review requests on **Admin > Orders > select order**. Approving simulates cancellation and refund status; **no actual payment or refund is made**. This demo uses browser localStorage and must not be treated as a secure payment/receipt solution.

## Digital purchases and product version comparison

- The customer dashboard now shows **View purchased products** under **Digital items purchased**. It navigates to `/orders?type=digital`, where purchased digital items have View Product links (when the catalog listing still exists).
- Admin > Products > Edit Product now includes **Version history & compare**, also accessible directly from each product row. The page `/admin/products/:id/versions` shows a version list and two selectable snapshots, including comparison of product descriptions, specifications, illustrations, prices and other fields.
- The first planner has illustrative v1/v2 history to demonstrate comparisons. Further admin edits create actual snapshots preserved in localStorage.
- **Restore** adds a new draft snapshot without changing the live store product. It opens the editor populated with that draft; choose the appropriate publication status and **Save product** to apply changes, creating a new version.
- All version history is browser-local demonstration data, not a secure server-side revision log. A production deployment needs backend persistence, permissions and audit controls.
>>>>>>> 0306554 (Upload frontend files)
