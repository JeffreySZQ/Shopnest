
# ShopNest - E-Commerce Web Application

ShopNest is a student e-commerce web application developed using Vue 3, Bootstrap, Vuetify, PHP and MySQL.

The application provides a shopping experience for customers and a management dashboard for administrators.

Product information, customer accounts, shopping carts, orders and product version history are managed through a PHP REST API connected to a MariaDB database.

---

## 1. Technology Stack

### Frontend
- Vue 3
- JavaScript
- Vite
- Vue Router
- Pinia
- Bootstrap 5
- Vuetify 3
- Material Design Icons
- Lucide Vue Next

### Backend
- PHP
- PHP MySQLi
- REST API
- MariaDB / MySQL
- PHP Sessions
- JSON data exchange

### Additional Libraries
- jsPDF
- jsPDF AutoTable

---

## 2. System Architecture

ShopNest uses a frontend and backend architecture.

The frontend communicates with the PHP REST API using JavaScript fetch requests.

The PHP API processes requests, validates information and communicates with the MariaDB database.

### Application flow

    Customer / Administrator
              |
              v
    Vue 3 Frontend
              |
              v
    Vue Router + Pinia
              |
              v
    shopApi.js
              |
              v
    JavaScript fetch()
              |
              v
    PHP REST API
              |
              v
    MariaDB Database

### Data Management

- MariaDB is the permanent source of application data.
- PHP handles database operations and server-side validation.
- Pinia manages reactive frontend state and cached data.
- JavaScript fetch communicates with the REST API.
- PHP sessions maintain authenticated user information.

---

## 3. Main Features

### Customer Features

- Browse published products.
- Search products by name and SKU.
- Filter products by category and type.
- Sort products.
- View product details.
- Add products to shopping cart.
- Update shopping cart quantities.
- Add products to wishlist.
- Register customer accounts.
- Sign in and sign out.
- Complete simulated checkout.
- View order confirmation.
- View purchase history.
- Request order cancellation.
- Download PDF receipts.
- View purchased digital products.

### Administrator Features

- Administrator authentication.
- View store dashboard.
- View sales and order information.
- View product listings.
- Create new products.
- Update existing products.
- Archive products.
- Manage product prices and stock.
- Change publication status.
- View product version history.
- Compare product versions.
- Restore earlier product versions as drafts.
- View customer orders.
- Update order fulfillment status.
- Review cancellation requests.
- View activity logs.

### Product Version Control

ShopNest includes database-backed product version history.

Each product can have multiple versions.

The system supports:

- Recording product snapshots.
- Tracking product changes.
- Comparing earlier and newer versions.
- Identifying changed fields.
- Restoring an earlier version as a new draft.
- Preserving historical product information.

Restoring an earlier version does not immediately overwrite the published product. Administrators must review and save the restored draft.

---

## 4. Project Structure

    ShopNest/
    |
    |-- public/
    |
    |-- resources/
    |   |
    |   |-- apis.php
    |   |-- shopnest-api.php
    |   |-- ShopnestTable.sql
    |   |-- MockData.sql
    |   |-- db-config.example.php
    |   |-- db-config.php
    |
    |-- src/
    |   |
    |   |-- components/
    |   |
    |   |-- data/
    |   |
    |   |-- layouts/
    |   |
    |   |-- router/
    |   |
    |   |-- services/
    |   |   |
    |   |   |-- shopApi.js
    |   |
    |   |-- stores/
    |   |
    |   |-- views/
    |   |   |
    |   |   |-- admin/
    |   |
    |   |-- main.js
    |   |-- App.vue
    |   |-- styles.css
    |
    |-- index.html
    |-- package.json
    |-- vite.config.js
    |-- README.md
    |-- .gitignore

Note:

- `apis.php` is a small PHP wrapper that loads `shopnest-api.php`.
- `shopnest-api.php` contains the main REST API implementation.
- `shopApi.js` handles frontend API requests.
- `db-config.php` contains private database credentials and must not be committed to GitHub.

---

## 5. Requirements

### Frontend

- Node.js 20.19+ or 22.12+
- npm

### Backend

- PHP with MySQLi support.
- PHP Sessions.
- JSON support.
- MariaDB / MySQL database.
- Database credentials provided by the university hosting environment.

The API source is written to support the PHP 5.4.16 environment reported by the Mercury deployment.

---

## 6. Installation and Local Development

### Step 1: Clone the repository

    git clone https://github.com/JeffreySZQ/Shopnest.git

### Step 2: Enter the project directory

    cd Shopnest

### Step 3: Install dependencies

    npm install

### Step 4: Start the development server

    npm run dev

Open the local URL displayed by Vite.

### Development API Configuration

The development server uses the proxy configured in `vite.config.js`.

Requests made to:

    /api

are forwarded to the Mercury PHP API.

The current development proxy targets the Mercury database.

Important:

Local development requests may therefore modify the deployed database. Use test products and test accounts when experimenting with Create, Update and Delete operations.

---

## 7. Database Setup

ShopNest uses MariaDB / MySQL for permanent data storage.

### Step 1: Create or select your assigned database

Use the database account and database name provided by the university.

### Step 2: Create database tables

Import:

    resources/ShopnestTable.sql

This creates the database tables required by ShopNest.

### Step 3: Insert demonstration data

Import:

    resources/MockData.sql

This inserts sample products and demonstration records.

Import the schema and seed data in the correct order, and avoid importing the seed data repeatedly into an existing database.

Back up existing data before running database scripts again.

### Step 4: Configure database credentials

Create:

    resources/db-config.php

Use the following structure:

    <?php

    return array(
        'host' => 'feenix-mariadb.swin.edu.au',
        'username' => 'YOUR_DATABASE_USERNAME',
        'password' => 'YOUR_DATABASE_PASSWORD',
        'database' => 'YOUR_DATABASE_NAME'
    );

    ?>

Replace the placeholders with your assigned database credentials.

Never publish this file or its actual credentials on GitHub.

---

## 8. REST API

The main API implementation is:

    resources/shopnest-api.php

The frontend communicates with the API through:

    src/services/shopApi.js

The API supports JSON requests and responses.

### Main API Operations

| HTTP Method | Operation | Description |
|---|---|---|
| GET | Retrieve | Retrieve products and other records |
| POST | Create | Register accounts, create products and orders |
| PUT | Update | Update product information |
| PATCH | Partial update | Update selected information such as order status |
| DELETE | Archive / Remove | Archive products or remove cart items |

### Product Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | /products | Retrieve published products |
| GET | /products/{id} | Retrieve product details |
| POST | /products | Create a product |
| PUT | /products/{id} | Update a product |
| DELETE | /products/{id} | Archive a product |
| GET | /admin/products | Retrieve administrator product listings |
| GET | /admin/products/{id}/versions | Retrieve product version history |
| POST | /admin/products/{id}/restore/{version} | Restore a version as a draft |

### Other API Features

- User registration and authentication.
- Session restoration.
- Shopping cart operations.
- Product likes.
- Checkout.
- Customer order retrieval.
- Order cancellation.
- Administrator order management.
- Activity logging.
- Dashboard statistics.

Administrator endpoints require server-side administrator authentication.

---

## 9. Frontend Libraries

### Vue 3

Used to create reusable components, reactive interfaces and application pages.

### Vue Router

Used for navigation between storefront, customer and administrator pages.

### Pinia

Used for frontend state management and reactive cached data.

### Bootstrap

Used for responsive layouts, grid systems, spacing and general styling.

### Vuetify

Used for interface components including:

- v-app
- v-btn
- v-table
- v-form
- v-text-field
- v-select
- v-combobox
- v-alert
- v-chip
- v-pagination

### JavaScript Fetch API

Used to communicate with the PHP REST API asynchronously.

---

## 10. Build and Mercury Deployment

### Step 1: Build the frontend

Run:

    npm run build

Vite generates the production frontend inside:

    dist/

### Step 2: Upload frontend files

Upload the contents of `dist/` into your Mercury project web directory.

The deployed directory should contain the generated `index.html` and `assets/` directory.

### Step 3: Upload backend files

Ensure the following directory is available alongside the deployed frontend:

    resources/

It must contain the PHP API, SQL files and the private server-side database configuration.

The actual `db-config.php` must be installed on Mercury separately and must not be published to GitHub.

### Step 4: Check deployment configuration

The Mercury deployment path is configured in:

    vite.config.js

Make sure the configured base path matches your actual Mercury project directory.

### Step 5: Test the API

Open the health endpoint:

    resources/shopnest-api.php/health

A successful response should indicate that the API and database are connected.

Also test product retrieval:

    resources/shopnest-api.php/products

---

## 11. Testing Checklist

### Customer Testing

- Register a customer account.
- Sign in and sign out.
- Search and filter products.
- Add products to cart.
- Update cart quantities.
- Complete simulated checkout.
- View order history.
- Download a PDF receipt.
- Request cancellation.

### Administrator Testing

- Sign in as administrator.
- View the dashboard.
- Retrieve product listings.
- Create a product.
- Update a product.
- Archive a product.
- Compare product versions.
- Restore an earlier version.
- Update order fulfillment.
- Review cancellation requests.
- Check activity logs.

### Database Testing

- Verify that products are retrieved from MariaDB.
- Verify that new products are inserted into MariaDB.
- Verify that product updates persist after refreshing.
- Verify that archived products remain in the database.
- Verify that product version snapshots are stored.
- Verify that order information persists.

---

## 12. Security and Limitations

ShopNest is a student demonstration application.

- Database credentials must remain private.
- Administrator authorization is enforced by the PHP API.
- Passwords are handled through server-side password hashing.
- PHP sessions are used for authentication.
- Database operations should use prepared statements.
- Checkout uses simulated payments.
- No actual financial transactions or refunds are processed.
- Digital downloads are demonstration functionality and require secure file delivery for a production system.
- Product images use existing image paths; image uploads require separate backend storage functionality.

Do not use real payment details or sensitive personal information for demonstration accounts.

---

## 13. Project Information

Project: ShopNest

Course: COS30043 - Interface Design and Development

Institution: Swinburne University of Technology

Repository:

https://github.com/JeffreySZQ/Shopnest

Deployment:

Swinburne Mercury Web Hosting

This project demonstrates frontend development, responsive design, Vue application architecture, REST API integration, database operations and product management.
