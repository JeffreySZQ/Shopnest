# ShopNest

ShopNest is a e-commerce web application for practical learning resources, journals, workbooks and digital downloads. It is built with Vue 3 using the Options API, Pinia, Vuetify, Bootstrap, PHP and MariaDB/MySQL.

## Technology stack

### Frontend
- Vue 3 (Options API in Vue single-file components)
- Vite
- Vue Router
- Pinia for shared application state
- Vuetify 3 and Material Design Icons
- Bootstrap 5 for responsive grid/layout utilities
- Lucide Vue Next icons
- JavaScript Fetch API

### Backend
- PHP REST API
- PHP sessions for authentication
- MySQLi prepared statements
- MariaDB / MySQL
- JSON API responses

## Main features

### Customer storefront
- Browse, search, sort and filter published products
- Product detail pages and related products
- Guest shopping cart saved in Pinia and browser local storage
- Guest-to-account cart merge after login or registration
- Account cart stored in MariaDB
- Customer registration and login
- Server-side password policy enforcement (client-side validation is not treated as a security boundary)
- Accessible show/hide password controls and live validation feedback
- Wishlist / product likes
- Checkout with live form validation
- Contact phone number saved with the order
- Order history, cancellation requests and PDF receipts

### Administrator
- Dashboard overview and sales summaries
- Product creation, editing and archiving
- Product image uploads to the Mercury project `images/products/` directory
- Product image paths stored in the `products.image_path` column
- Product version history, comparison and draft restoration
- Order management and fulfillment updates
- Cancellation request review
- Activity log
- Admin account: admin@example.com (ShopnestDemo123!)

## Architecture

The PHP API and MariaDB database are the permanent source of truth. Vue pages call the API through `src/services/shopApi.js`. Pinia stores shared reactive state and a frontend cache. The guest cart is stored locally until the user signs in or registers, at which point it is merged into the account cart through an idempotent API endpoint.

```text
Vue 3 Options API + Vuetify + Bootstrap
                  |
                Pinia
                  |
          src/services/shopApi.js
                  |
             fetch() / JSON
                  |
          resources/apis.php
                  |
       resources/shopnest-api.php
                  |
             MariaDB
```

## Project structure

```text
ShopNest/
├── public/
├── images/
│   └── products/              # Uploaded product images on Mercury
├── resources/
│   ├── apis.php               # API entry point
│   ├── shopnest-api.php       # Main PHP REST API
│   ├── db-config.example.php  # Safe configuration template
│   ├── ShopnestTable.sql      # Fresh database schema
│   ├── MockData.sql           # Demonstration data
│   └── migrations/
│       └── 001_guest_cart_and_customer_phone.sql
├── src/
│   ├── components/
│   ├── layouts/
│   ├── router/
│   ├── services/
│   │   └── shopApi.js
│   ├── stores/
│   │   ├── shop.js
│   │   └── currency.js
│   ├── views/
│   │   └── admin/
│   ├── App.vue
│   ├── main.js
│   └── styles.css
├── index.html
├── package.json
├── package-lock.json
├── vite.config.js
├── .gitignore
└── README.md
```

## Vue API style

Vue single-file components use the Options API (`data`, `computed`, `methods`, `watch`, `mounted`, `created` and lifecycle hooks). Pinia stores use Pinia's Options Store syntax (`state`, `getters`, `actions`).

## Install and run locally

Requirements:
- Node.js 20.19+ or 22.12+
- npm
- A working PHP/MariaDB backend

Install dependencies:

```bash
npm install
```

Start Vite:

```bash
npm run dev
```

The development server proxies `/api` requests to the Mercury PHP API using the path configured in `vite.config.js`. Local development currently uses the configured Mercury backend, so changes made during testing may affect the shared database. Use temporary test products and accounts.

Build the frontend:

```bash
npm run build
```

Vite writes the production frontend into `dist/`.

## Database setup

### Fresh database

1. Select the MariaDB database assigned to your project.
2. Import `resources/ShopnestTable.sql`.
3. Import `resources/MockData.sql` once.
4. Create `resources/db-config.php` on the server using the example below.

## Guest cart synchronisation

Guest cart items are kept in Pinia and persisted under the browser key `shopnest-guest-cart-v1`. The guest cart is not copied into the authenticated account cart until login or registration succeeds.

The frontend sends the guest cart to:

```text
POST /cart/merge
```

The request contains a 64-character merge token and the guest product IDs and quantities. The API inserts the token into `cart_merge_tokens` and merges all items in a database transaction. The unique `(user_id, merge_token)` key makes retries idempotent: if a response is lost and the browser retries, the same quantities are not added twice. The browser clears its guest cart only after the API confirms the merge or confirms that the same token was already processed.

## Form validation, accessibility and responsive design

Signup, login, product editing and checkout use Vuetify forms with validation configured to update as the user types. Signup passwords must contain at least 8 characters, an uppercase letter, a lowercase letter and a number. The same password policy is checked again by the PHP registration endpoint.

Password visibility controls have accessible names and pressed states. Form errors use alert semantics, fields have labels and appropriate autocomplete/input modes, and the password requirements are presented as a live status list. The interface includes visible keyboard focus styles, touch-friendly controls, responsive layouts for narrow screens and reduced-motion support.

## Checkout and phone number

Checkout validates contact email and phone number. Delivery address, city and postcode are required when the cart contains physical products. The API repeats the validation server-side and saves the phone number in `orders.customer_phone`. Checkout payments are simulated; no real payment is collected.

## Product image uploads

Administrators can select JPG, PNG or WEBP images (maximum 5 MB) in the product editor. The browser previews the selected image, then sends it as `multipart/form-data` to:

```text
POST /admin/upload-image
```

The PHP API validates the uploaded image and saves it in:

```text
images/products/
```

The API returns a relative path such as `images/products/product-....jpg`. The product create/update request saves that path in `products.image_path` and the alternative text in `products.image_alt`.

The `images/products/` directory must be writable by the PHP process. If Mercury does not allow PHP to create it, create the directory through the file manager and set appropriate owner/group permissions. Do not use world-writable `777` permissions. The server's PHP upload limits (`upload_max_filesize` and `post_max_size`) must permit files up to the configured 5 MB application limit.

## API overview

The API entry point is `resources/apis.php`, which loads `resources/shopnest-api.php`.

Common endpoints include:

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/health` | Check API/database connection |
| GET | `/products` | Retrieve published products |
| GET | `/products/{id}` | Retrieve a product |
| POST | `/products` | Create a product (admin) |
| PUT | `/products/{id}` | Update a product (admin) |
| DELETE | `/products/{id}` | Archive a product (admin) |
| POST | `/admin/upload-image` | Upload a product image (admin) |
| GET | `/cart` | Retrieve authenticated account cart |
| POST | `/cart/items` | Add an item to account cart |
| PATCH | `/cart/items/{productId}` | Update/remove an account cart item |
| POST | `/cart/merge` | Idempotently merge guest cart after login |
| POST | `/checkout` | Create a simulated order |
| GET | `/orders` | Retrieve customer orders |
| GET | `/admin/products` | Retrieve admin product list |
| GET | `/admin/products/{id}/versions` | Retrieve product versions |
| POST | `/admin/products/{id}/restore/{version}` | Restore a version as a draft |

All administrator endpoints enforce administrator authorization on the server.

## Mercury deployment

1. Configure the correct Mercury project base path in `vite.config.js`.
2. Run `npm run build`.
3. Upload the contents of `dist/` to the Mercury project web directory.
4. Upload `resources/apis.php` and `resources/shopnest-api.php` into the deployed `resources/` directory.
5. Install the private `resources/db-config.php` file on Mercury; do not upload it to GitHub.
6. Ensure the database migration has been applied.
7. Ensure `images/products/` exists and is writable by PHP.
8. Test `resources/apis.php/health` and `resources/apis.php/products` in a browser.
9. Test login, guest cart merge, product create/update/archive, image upload, checkout and version history.

## Validation

Vuetify forms use `validate-on="input"` so required-field feedback updates as users type. Client-side rules cover the relevant signup, login, checkout and product editor fields. The PHP API also validates data server-side; frontend validation is not a substitute for backend validation.

## Demonstration limitations

- Payments and refunds are simulated only.
- Digital delivery is demonstration functionality.
- Product image upload supports JPG, PNG and WEBP only.
- The guest cart is stored in the current browser until it is merged into an account.
- Do not use real payment card information or sensitive personal information in the demonstration system.

## Project information

- Project: ShopNest
- Course: COS30043 - Interface Design and Development
- Institution: Swinburne University of Technology
- Repository: https://github.com/JeffreySZQ/Shopnest
- Hosting: Swinburne Mercury
