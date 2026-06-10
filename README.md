# Shopify CSV Product Import System

Laravel application for uploading Shopify-compatible CSV files, processing them asynchronously via queues, and importing products to Shopify using the **GraphQL Admin API**.

## Features

- CSV upload with server-side and Vue client-side validation
- Asynchronous import pipeline using Laravel queues
- Shopify GraphQL integration (`productSet` for create/update)
- Dashboard with upload progress, product statuses, and import logs
- Comprehensive logging (database + dedicated log files)
- Email notifications on import completion/failure (optional)
- Product update when handle already exists in Shopify

## Tech Stack

- **Backend:** Laravel 12
- **Frontend:** Blade layouts + Vue 3 (Vite)
- **Database:** MySQL
- **Queue:** Database driver
- **Shopify:** GraphQL Admin API

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8+
- Shopify store with Admin API access token (`write_products` scope)

## Setup

### 1. Clone and install dependencies

```bash
composer install
npm install
```

### 2. Environment configuration

Copy `.env.example` to `.env` if needed, then configure:

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shopify_import
DB_USERNAME=root
DB_PASSWORD=root

QUEUE_CONNECTION=database

SHOPIFY_STORE_DOMAIN=laravel-import-test.myshopify.com
SHOPIFY_ACCESS_TOKEN=shpat_xxxxxxxx
SHOPIFY_API_VERSION=2025-01
SHOPIFY_COLLECTION_ID=464337174767
SHOPIFY_LOCATION_ID=

MAIL_ADMIN_ADDRESS=you@example.com
```

Create the database:

```sql
CREATE DATABASE shopify_import CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Generate app key and run migrations:

```bash
php artisan key:generate
php artisan migrate
```

### 3. Build frontend assets

```bash
npm run dev
# or for production
npm run build
```

### 4. Start the application

Run these in separate terminals:

```bash
php artisan serve
php artisan queue:work
npm run dev
```

Visit:
- Upload: `http://localhost:8000`
- Dashboard: `http://localhost:8000/dashboard`

## Testing the Application

1. Use the sample CSV at `samples/shopify-products-sample.csv` or your own Shopify export.
2. Open the upload page and drag/drop the CSV file.
3. Confirm success message and open the dashboard.
4. Watch statuses move from `pending` → `processing` → `successful` / `failed`.
5. Re-upload the same CSV to verify **update** behavior for existing handles.
6. Check logs in the dashboard and in `storage/logs/import.log`.

### Without Shopify credentials

Upload and CSV parsing still work. Product rows will fail at Shopify sync with a clear error in the dashboard unless credentials are configured.

## Architecture

```
CSV Upload → Upload record → ProcessCsvUpload job
    → Parse & validate CSV → ProductImport records
    → ImportProductToShopify jobs (per row)
    → Shopify GraphQL (productSet)
    → Update counters & completion status
```

### Database tables

| Table | Purpose |
|-------|---------|
| `uploads` | CSV upload batches and aggregate status |
| `product_imports` | Per-row import status and Shopify IDs |
| `import_error_logs` | Structured import event/error logs |

### CSV mapping

| CSV Column | Shopify Field |
|------------|---------------|
| Handle | handle (used for update lookup) |
| Title | title |
| Body HTML | descriptionHtml |
| Vendor | vendor |
| Product Type | productType |
| Tags | tags |
| Published | status (ACTIVE/DRAFT) |
| Variant SKU | variants.sku |
| Variant Price | variants.price |
| Image Src | files.originalSource |

## Design Decisions

1. **GraphQL `productSet`** — Used instead of REST for unified create/update and alignment with Shopify's current product APIs.
2. **Two-stage queue** — One job parses CSV and fans out per-product jobs for resilience and parallel processing.
3. **Vue + Blade** — Blade provides layout/navigation; Vue handles interactive upload and live dashboard polling.
4. **Handle-based updates** — If `productByHandle` returns a product, the import updates it instead of creating a duplicate.
5. **MySQL** — Required for uploads, product status tracking, and log viewer data.
6. **Collection assignment** — Every imported product is added to the configured Shopify collection via `collectionAddProducts`.

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/uploads` | Upload CSV file |
| GET | `/api/uploads` | List uploads |
| GET | `/api/uploads/{id}` | Upload details with products |
| GET | `/api/products` | Paginated product imports |
| GET | `/api/logs` | Import event logs |

## Assumptions

- Single default variant per CSV row (Shopify "Title" option).
- Inventory quantities require `SHOPIFY_LOCATION_ID` when tracker is `shopify`.
- Email notifications use the `log` mail driver by default in local development.
- Queue worker must be running for background processing.

## License

MIT
