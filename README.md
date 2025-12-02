# SyncFlow - Quality Control & Measurement System

[![Laravel](https://img.shields.io/badge/Laravel-10.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A comprehensive quality control and measurement management system with advanced formula calculation capabilities. Built with Laravel 10, featuring real-time measurement validation, mathematical formula processing, and production issue tracking.

---

## 🎯 Key Features

### 📊 **Product & Measurement Management**
- Create and manage products with configurable measurement points
- Support for quantitative and qualitative measurements
- Multiple measurement types: SINGLE, BEFORE_AFTER
- Flexible evaluation methods: PER_SAMPLE, JOINT, SKIP_CHECK
- Real-time OK/NG status evaluation

### 🧮 **Advanced Formula System**
- **Excel-like formula syntax** (must start with `=`)
- **40+ mathematical functions** support:
  - Aggregation: `avg`, `sum`, `min`, `max`, `count`
  - Trigonometric: `sin`, `cos`, `tan`, `asin`, `acos`, `atan`, `atan2`
  - Hyperbolic: `sinh`, `cosh`, `tanh`, `asinh`, `acosh`, `atanh`
  - Rounding: `ceil`, `floor`, `round`, `trunc`
  - Math: `sqrt`, `abs`, `sign`, `fmod`, `hypot`
  - Logarithmic: `log`, `ln`, `log10`, `log2`, `exp`, `pow`
  - Conversion: `deg2rad`, `rad2deg`, `degrees`, `radians`
  - Conditional: `if`
- **Auto-validation** of formula dependencies
- **Case-insensitive** function names (AVG/avg/Avg → avg)
- **Auto-complete API** for formula suggestions
- **PEMDAS/BODMAS** order of operations

### 🔧 **Tools & Instruments Management**
- Manage measurement tools/instruments
- Track calibration dates (auto-calculate next calibration)
- IMEI-based tool selection
- Support for OPTICAL and MECHANICAL tools

### 🐛 **Issue Tracking**
- Create and track production issues
- Comment system for collaboration
- Status management: PENDING, ON_GOING, SOLVED
- Due date tracking

### 🔐 **Authentication & Authorization**
- JWT-based authentication
- Role-based access control (User, Admin, SuperAdmin)
- Secure API endpoints

---

## 🚀 Quick Start

### Prerequisites

- PHP >= 8.1
- Composer
- MySQL >= 5.7 or MariaDB >= 10.3
- Node.js & NPM (optional, for frontend)

### Installation

1. **Clone the repository**
```bash
git clone https://github.com/yourusername/syncflow.git
cd syncflow
```

2. **Install PHP dependencies**
```bash
composer install
```

3. **Install MathExecutor library** (for formula calculations)
```bash
composer require nxp/math-executor
```
> ✅ Already included in `composer.json`, will be installed automatically with `composer install`

4. **Install JWT Auth**
```bash
composer require tymon/jwt-auth
```
> ✅ Already included in `composer.json`

5. **Setup environment**
```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

6. **Configure database and timezone in `.env`**
```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=syncflow
DB_USERNAME=root
DB_PASSWORD=
```

7. **Run migrations**
```bash
php artisan migrate
```

8. **Seed database (REQUIRED)**
```bash
# Seed quarters (REQUIRED - products need active quarter)
php artisan db:seed --class=QuarterSeeder

# Seed product categories (REQUIRED)
php artisan db:seed --class=ProductCategorySeeder

# Seed measurement instruments (REQUIRED for INSTRUMENT source)
php artisan db:seed --class=MeasurementInstrumentSeeder

# Seed tools (REQUIRED for TOOL source)
php artisan db:seed --class=ToolSeeder

# Seed super admin user (REQUIRED for first login)
php artisan db:seed --class=SuperAdminSeeder

# Seed additional users (OPTIONAL)
php artisan db:seed --class=LoginUserSeeder

# Or seed all at once
php artisan db:seed
```

9. **Activate Quarter (REQUIRED)**
```bash
# Interactive mode - will show available quarters
php artisan quarter:activate

# Or direct mode - activate specific quarter
php artisan quarter:activate 2024 Q4
```

> ⚠️ **Important:** Products cannot be created without an active quarter!

10. **Start the server**
```bash
php artisan serve
```

The application will be available at `http://localhost:8000`

---

## 📖 Formula System Usage

### Understanding Pre-Processing Aggregation Functions

**How Aggregation Works:**

When you reference another measurement item in a formula, you can use aggregation functions to process the sample data:

**Example Data:**
- `thickness_a` has 3 samples: [30, 40, 10]

**Different Ways to Reference:**

1. **Direct Reference (Not Common)**
   ```javascript
   =thickness_a
   ```
   - Accesses the variable directly (used for DERIVED sources or variables)
   - Does NOT automatically aggregate samples

2. **Using Aggregation Functions (Recommended)**
   
   | Formula | Description | Calculation | Result |
   |---------|-------------|-------------|--------|
   | `=avg(thickness_a)` | Average (mean) | (30+40+10) / 3 | **26.67** |
   | `=sum(thickness_a)` | Total sum | 30+40+10 | **80** |
   | `=min(thickness_a)` | Minimum value | min(30,40,10) | **10** |
   | `=max(thickness_a)` | Maximum value | max(30,40,10) | **40** |
   | `=count(thickness_a)` | Count samples | count of values | **3** |

**Real-World Example:**

```javascript
// Scenario: room_temp references thickness_a, thickness_b, thickness_c
// thickness_a samples: [30, 40, 10] → avg = 26.67
// thickness_b samples: [25, 35, 15] → avg = 25
// thickness_c samples: [28, 38, 12] → avg = 26

// Calculate average of all three measurement items:
=(avg(thickness_a) + avg(thickness_b) + avg(thickness_c)) / 3
// Result: (26.67 + 25 + 26) / 3 = 25.89
```

**Important Notes:**
- ✅ Aggregation functions process **all raw sample values** from the referenced measurement item
- ✅ Works exactly like Excel/spreadsheet functions
- ✅ Functions follow standard mathematical definitions
- ✅ Can combine multiple aggregation functions in one formula

### Basic Formula Examples

```javascript
// Simple average
=avg(thickness_a)

// Multiple measurements with aggregation
=(avg(thickness_a) + avg(thickness_b) + avg(thickness_c)) / 3

// Using different aggregation functions
=max(thickness_a) - min(thickness_a)

// Count and sum
=sum(thickness_a) / count(thickness_a)

// Temperature conversion (Fahrenheit to Celsius)
=(temperature - 32) * 5 / 9

// Trigonometric calculation
=sin(angle) * radius

// Complex formula with math functions
=sqrt(pow(x, 2) + pow(y, 2))

// Conditional logic
=if(avg(thickness_a) > 1.5, 1, 0)

// Statistical analysis
=if(max(thickness_a) - min(thickness_a) > 5, avg(thickness_a), 0)
```

### Formula Rules

1. ✅ **Must start with `=`** (like Excel)
2. ✅ **Case-insensitive** function names (AVG = avg = Avg)
3. ✅ **Referenced measurement items must exist** before formula
4. ✅ **Order matters** - dependencies must be defined first
5. ✅ **Use aggregation functions** (avg, sum, min, max, count) to reference other measurement items
6. ✅ **Follows PEMDAS/BODMAS** order of operations

### Create Product with Formula

```bash
POST /api/v1/products
Authorization: Bearer {token}

# Example payload with aggregation formula:
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "VO"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness A",
        "name_id": "thickness_a",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0
      }
    },
    {
      "setup": {
        "name": "Room Temp",
        "name_id": "room_temp",
        "sample_amount": 1,
        "source": "DERIVED",
        "source_derived_name_id": "thickness_a",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FORMULA",
          "name": "calculated_value",
          "formula": "=avg(thickness_a)",
          "is_show": true
        }
      ],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": {"_skip": true},
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0
      }
    }
  ]
}
```

### Formula Autocomplete API

```bash
GET /api/v1/products/{productId}/measurement-items/suggest?query=thick
Authorization: Bearer {token}
```

---

## 🧪 Testing

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suite
```bash
# Formula validation tests
php artisan test --filter FormulaValidationTest

# Product tests
php artisan test --filter ProductTest

# Tool tests
php artisan test --filter ToolTest

# Issue tests
php artisan test --filter IssueTest
```

### Run with Coverage
```bash
php artisan test --coverage
```

---

## 📚 API Documentation

### Authentication

**Login**
```bash
POST /api/v1/login

{
  "username": "admin",
  "password": "password"
}
```

> 🔑 Use the returned `token` in Authorization header: `Bearer {token}`

### Main Endpoints

| Method | Endpoint | Description | Operator | Admin | SuperAdmin |
|--------|----------|-------------|----------|-------|------------|
| **Authentication** ||||||
| POST | `/api/v1/login` | Login user | ✅ | ✅ | ✅ |
| POST | `/api/v1/logout` | Logout user | ✅ | ✅ | ✅ |
| POST | `/api/v1/refresh` | Refresh token | ✅ | ✅ | ✅ |
| **Products** ||||||
| POST | `/api/v1/products` | Create product | ❌ | ✅ | ✅ |
| GET | `/api/v1/products` | List products | ✅ | ✅ | ✅ |
| GET | `/api/v1/products/{id}` | Get product detail | ✅ | ✅ | ✅ |
| PUT | `/api/v1/products/{id}` | Update product | ❌ | ✅ | ✅ |
| DELETE | `/api/v1/products/{id}` | Delete product | ❌ | ✅ | ✅ |
| **Product Categories** ||||||
| GET | `/api/v1/products/categories` | Get categories | ✅ | ✅ | ✅ |
| GET | `/api/v1/products/{id}/measurement-items/suggest` | Formula autocomplete | ✅ | ✅ | ✅ |
| **Product Measurement** ||||||
| GET | `/api/v1/product-measurement` | List measurements | ✅ | ✅ | ✅ |
| GET | `/api/v1/product-measurement/available-products` | Get available products | ✅ | ✅ | ✅ |
| POST | `/api/v1/product-measurement` | Create measurement | ✅ | ✅ | ✅ |
| POST | `/api/v1/product-measurement/bulk` | Bulk create | ✅ | ✅ | ✅ |
| GET | `/api/v1/product-measurement/{id}` | Get single measurement | ✅ | ✅ | ✅ |
| POST | `/api/v1/product-measurement/{id}/set-batch-number` | Set batch number | ✅ | ✅ | ✅ |
| POST | `/api/v1/product-measurement/{id}/check-samples` | Check samples | ✅ | ✅ | ✅ |
| POST | `/api/v1/product-measurement/{id}/save-progress` | Save progress | ✅ | ✅ | ✅ |
| POST | `/api/v1/product-measurement/{id}/submit` | Submit measurement | ✅ | ✅ | ✅ |
| **Scale Measurement** ||||||
| GET | `/api/v1/scale-measurement` | List scale measurements | ✅ | ✅ | ✅ |
| GET | `/api/v1/scale-measurement/available-products` | Get available products | ✅ | ✅ | ✅ |
| GET | `/api/v1/scale-measurement/{id}` | Get single | ✅ | ✅ | ✅ |
| POST | `/api/v1/scale-measurement` | Create measurement | ✅ | ✅ | ✅ |
| POST | `/api/v1/scale-measurement/bulk` | Bulk create | ✅ | ✅ | ✅ |
| PUT | `/api/v1/scale-measurement/{id}` | Update measurement | ❌ | ✅ | ✅ |
| DELETE | `/api/v1/scale-measurement/{id}` | Delete measurement | ❌ | ✅ | ✅ |
| **Measurement Instruments** ||||||
| GET | `/api/v1/measurement-instruments` | List instruments | ✅ | ✅ | ✅ |
| GET | `/api/v1/measurement-instruments/{id}` | Get instrument detail | ✅ | ✅ | ✅ |
| **Tools** ||||||
| GET | `/api/v1/tools` | List tools | ✅ | ✅ | ✅ |
| GET | `/api/v1/tools/models` | Get tool models | ✅ | ✅ | ✅ |
| GET | `/api/v1/tools/by-model` | Get tools by model | ✅ | ✅ | ✅ |
| POST | `/api/v1/tools` | Create tool | ❌ | ✅ | ✅ |
| **Issues** ||||||
| GET | `/api/v1/issues` | List issues | ✅ | ✅ | ✅ |
| GET | `/api/v1/issues/{id}` | Get issue detail | ✅ | ✅ | ✅ |
| POST | `/api/v1/issues` | Create issue | ❌ | ✅ | ✅ |
| PUT | `/api/v1/issues/{id}` | Update issue | ❌ | ✅ | ✅ |
| POST | `/api/v1/issues/{id}/comments` | Add comment | ✅ | ✅ | ✅ |
| **Quarters** ||||||
| GET | `/api/v1/quarters` | List quarters | ✅ | ✅ | ✅ |
| GET | `/api/v1/quarters/active` | Get active quarter | ✅ | ✅ | ✅ |
| **Notifications** ||||||
| GET | `/api/v1/notifications` | List notifications | ✅ | ✅ | ✅ |
| GET | `/api/v1/notifications/{id}` | Get single notification | ✅ | ✅ | ✅ |
| PUT | `/api/v1/notifications/{id}/read` | Mark as read | ✅ | ✅ | ✅ |
| PUT | `/api/v1/notifications/read-all` | Mark all as read | ✅ | ✅ | ✅ |
| DELETE | `/api/v1/notifications/{id}` | Delete notification | ✅ | ✅ | ✅ |

### Response Format

All API endpoints use consistent response format:

**Success Response:**
```json
{
  "http_code": 200,
  "message": "Success message",
  "error_id": null,
  "data": { ... }
}
```

**Error Response:**
```json
{
  "http_code": 400,
  "message": "Error message",
  "error_id": "ERROR_CODE",
  "data": null
}
```

**Validation Error:**
```json
{
  "http_code": 400,
  "message": "Validation failed",
  "data": {
    "field_name": ["Error message"]
  }
}
```

### Quick Examples for Frontend

#### Scale Measurement (Daily Weight Tracking)

**1. Create Measurement**
```bash
POST /api/v1/scale-measurement
Authorization: Bearer {token}

{
  "product_id": "PRD-A1B2C3D4",
  "measurement_date": "2025-12-02",
  "weight": 4.5,              # Optional, bisa null
  "notes": "Morning check"     # Optional
}

# Response:
{
  "http_code": 201,
  "data": {
    "scale_measurement_id": "SCL-X1Y2Z3A4",
    "status": "CHECKED"  # CHECKED jika weight ≠ null, NOT_CHECKED jika weight = null
  }
}
```

**2. Bulk Create (untuk setup target harian)**
```bash
POST /api/v1/scale-measurement/bulk
Authorization: Bearer {token}

{
  "product_ids": ["PRD-001", "PRD-002", "PRD-003"],
  "measurement_date": "2025-12-02"
}

# Response: Semua dibuat dengan status NOT_CHECKED (weight = null)
{
  "http_code": 201,
  "data": {
    "PRD-001": "SCL-AAA111",
    "PRD-002": "SCL-BBB222",
    "PRD-003": "SCL-CCC333"
  }
}
```

**3. Get List dengan Filter**
```bash
# Filter by date
GET /api/v1/scale-measurement?date=2025-12-02

# Filter yang belum dicek (untuk monitoring)
GET /api/v1/scale-measurement?status=NOT_CHECKED&date=2025-12-02

# Filter yang sudah dicek
GET /api/v1/scale-measurement?status=CHECKED&date=2025-12-02

# Filter date range (untuk reporting)
GET /api/v1/scale-measurement?start_date=2025-12-01&end_date=2025-12-07

# Search by product name/code
GET /api/v1/scale-measurement?query=CIVIUSAS

# Response:
{
  "http_code": 200,
  "data": [
    {
      "scale_measurement_id": "SCL-X1Y2Z3A4",
      "measurement_date": "2025-12-02",
      "weight": 4.5,
      "status": "CHECKED",  # NOT_CHECKED atau CHECKED
      "product": {
        "id": "PRD-A1B2C3D4",
        "product_name": "CIVIUSAS-S",
        "article_code": "ART-001"
      },
      "measured_by": {
        "username": "operator1"
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_docs": 10
  }
}
```

**4. Update Weight (Admin Only)**
```bash
PUT /api/v1/scale-measurement/SCL-X1Y2Z3A4
Authorization: Bearer {admin_token}

{
  "weight": 5.2,
  "notes": "Updated"
}

# ⚠️ Operator coba update → 403 Forbidden
```

**5. Available Products**
```bash
# Get products yang belum ada measurement hari ini
GET /api/v1/scale-measurement/available-products?date=2025-12-02

# Response: Products yang bisa di-create
{
  "http_code": 200,
  "data": [
    {
      "id": "PRD-A1B2C3D4",
      "product_name": "CIVIUSAS-S",
      "article_code": "ART-001"
    }
  ]
}
```

---

#### Product Measurement (Quarter-based)

```bash
# Filter by quarter
GET /api/v1/product-measurement?quarter_id=1

# Filter by status
GET /api/v1/product-measurement?status=IN_PROGRESS

# Filter by product category
GET /api/v1/product-measurement?product_category_id=1
```

---

### 📖 Important Notes for Frontend

**Scale Measurement:**
- ✅ **Operator BISA:** View, Create, Bulk Create
- ❌ **Operator TIDAK BISA:** Update, Delete (403 Forbidden)
- 📅 **Limit:** 1 product per hari (duplicate return 400)
- 🔄 **Status Otomatis:**
  - weight = null → `NOT_CHECKED`
  - weight ≠ null → `CHECKED`
- 🚫 **Tidak ada OK/NG judgment** (hanya record data)

**Access Control Matrix:**

| Endpoint | Operator | Admin/SuperAdmin |
|----------|----------|------------------|
| GET (view) | ✅ | ✅ |
| POST (create) | ✅ | ✅ |
| PUT (update) | ❌ 403 | ✅ |
| DELETE | ❌ 403 | ✅ |

---

> 📖 **Full API documentation:**
> - **[Scale Measurement API](SCALE_MEASUREMENT.md)** - Complete guide dengan semua contoh payload
> - **[API Changes & Integration Guide](API_CHANGES_AND_INTEGRATION_GUIDE.md)** - Complete API guide
> - **[Formula System Documentation](FORMULA_SYSTEM_DOCUMENTATION.md)** - Formula guide

---

## 📁 Project Structure

```
syncflow/
├── app/
│   ├── Enums/              # Enum classes
│   ├── Helpers/            # Helper classes (FormulaHelper)
│   ├── Http/
│   │   ├── Controllers/    # API controllers
│   │   └── Middleware/     # Custom middleware
│   ├── Models/             # Eloquent models
│   └── Traits/             # Reusable traits
├── config/                 # Configuration files
├── database/
│   ├── factories/          # Model factories
│   ├── migrations/         # Database migrations
│   └── seeders/            # Database seeders
├── routes/
│   └── api.php            # API routes
├── tests/
│   ├── Feature/           # Feature tests
│   └── Unit/              # Unit tests
├── composer.json          # PHP dependencies
├── FORMULA_SYSTEM_DOCUMENTATION.md  # Formula documentation
├── TOOLS_API_DOCUMENTATION.md       # Tools API documentation
└── ISSUE_API_DOCUMENTATION.md       # Issue API documentation
```

---

## 🔧 Configuration

### JWT Authentication

Configure JWT in `config/jwt.php`:
```php
'ttl' => 60,  // Token lifetime in minutes
'refresh_ttl' => 20160,  // Refresh token lifetime
```

### Formula System

The formula system uses **NXP MathExecutor** library:
- Installed via Composer: `composer require nxp/math-executor`
- Source: https://github.com/neonxp/MathExecutor
- All functions registered in `app/Models/ProductMeasurement.php`

---

## 📖 Detailed Documentation

### For Developers
- **[API Changes & Integration Guide](API_CHANGES_AND_INTEGRATION_GUIDE.md)** - 🆕 Complete guide for frontend/mobile integration
- **[Formula System Documentation](FORMULA_SYSTEM_DOCUMENTATION.md)** - Complete formula guide with examples
- **[Tools Logic Explanation](TOOLS_LOGIC_EXPLANATION.md)** - Tools & instruments management
- **[Issue API Documentation](ISSUE_API_DOCUMENTATION.md)** - Issue tracking system

### Key Documentation for Frontend/Mobile Teams
👉 **[Start here: API Changes & Integration Guide](API_CHANGES_AND_INTEGRATION_GUIDE.md)**

This guide includes:
- ✅ All recent changes and fixes
- ✅ Complete payload examples
- ✅ Response examples
- ✅ Common errors & solutions
- ✅ Raw data access explanation
- ✅ Formula cross-reference guide

## ⚙️ Important Commands

### Quarter Management
```bash
# Activate a quarter (interactive)
php artisan quarter:activate

# Activate specific quarter
php artisan quarter:activate 2024 Q4

# Check active quarter via tinker
php artisan tinker
>>> App\Models\Quarter::getActiveQuarter();
```

### Database Seeding
```bash
# Seed all required data
php artisan db:seed

# Or seed individually
php artisan db:seed --class=QuarterSeeder
php artisan db:seed --class=ProductCategorySeeder
php artisan db:seed --class=MeasurementInstrumentSeeder
php artisan db:seed --class=ToolSeeder
php artisan db:seed --class=SuperAdminSeeder
php artisan db:seed --class=LoginUserSeeder
```

### Fresh Install
```bash
# Complete fresh installation
php artisan migrate:fresh
php artisan db:seed
php artisan quarter:activate 2024 Q4
```

---

## 🛠️ Development

### Code Style

This project follows PSR-12 coding standard:
```bash
# Check code style
./vendor/bin/pint --test

# Fix code style
./vendor/bin/pint
```

### Database Migrations

```bash
# Create new migration
php artisan make:migration create_table_name

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Fresh migration (drop all tables and re-run)
php artisan migrate:fresh
```

### Create New Components

```bash
# Create controller
php artisan make:controller Api/V1/ExampleController

# Create model
php artisan make:model Example -m

# Create seeder
php artisan make:seeder ExampleSeeder

# Create test
php artisan make:test ExampleTest
```

---

## 🚢 Deployment

### Production Setup

1. **Set environment to production**
```bash
APP_ENV=production
APP_DEBUG=false
```

2. **Optimize application**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

3. **Set proper permissions**
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

4. **Setup supervisor for queue workers** (if using queues)
```ini
[program:syncflow-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/syncflow/artisan queue:work
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/syncflow/storage/logs/worker.log
```

---

## 🐛 Troubleshooting

### Common Issues

**1. Error: "Tidak ada quarter aktif" / "No active quarter"**
```bash
# Solution: Activate a quarter
php artisan quarter:activate

# Or check current status
php artisan tinker
>>> App\Models\Quarter::getActiveQuarter();

# If no quarters exist, seed them first
php artisan db:seed --class=QuarterSeeder
```

**2. Error: "source_instrument_id must be integer"**
```
Problem: Sending tool model name instead of instrument ID

Solution: 
1. Fetch instruments from: GET /api/v1/measurement-instruments
2. Use the 'id' field (integer), not 'model' field (string)
3. Example: source_instrument_id: 1 (not "MITUTOYO-DC-150")
```

**3. Error: "Setup.source wajib diisi" for QUALITATIVE**
```
Problem: Old validation rules

Solution: Update to latest code. 
For QUALITATIVE measurements, source and type are now OPTIONAL.
```

**4. Formula validation errors**
```
Error: "Formula harus dimulai dengan '='"
Solution: Add = prefix to your formula (e.g., =avg(thickness_a))
```

**5. Missing dependency in formula**
```
Error: "Formula references measurement items yang belum dibuat"
Solution: Ensure referenced measurement items are defined before the formula
```

**6. JWT token expired**
```
Error: "Token has expired"
Solution: Use refresh token endpoint to get new token
POST /api/v1/refresh
```

**7. Database connection error**
```
Solution: Check .env database credentials and ensure MySQL is running
```

**8. Laravel serve error on Windows**
```
Error: "Undefined array key 1" in ServeCommand.php

Solution: This is a known Laravel bug on Windows and doesn't affect functionality.
The server still works normally. Ignore the error or use Laragon/XAMPP instead.
```

---

## 📊 System Requirements

### Minimum Requirements

- **PHP:** >= 8.1
- **MySQL:** >= 5.7 or MariaDB >= 10.3
- **Composer:** >= 2.0
- **Memory:** 512 MB RAM
- **Disk Space:** 500 MB

### Recommended Requirements

- **PHP:** 8.2+
- **MySQL:** 8.0+ or MariaDB 10.6+
- **Memory:** 1 GB RAM
- **Disk Space:** 1 GB
- **Redis:** For caching and queue management (optional)

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Standards

- Follow PSR-12 coding standard
- Write tests for new features
- Update documentation
- Add meaningful commit messages

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 👥 Authors

- **Development Team** - Initial work

---

## 🙏 Acknowledgments

- [Laravel Framework](https://laravel.com)
- [NXP MathExecutor](https://github.com/neonxp/MathExecutor) - Formula calculation engine
- [JWT Auth](https://github.com/tymondesigns/jwt-auth) - Authentication

---

## 📞 Support

For support, email support@syncflow.com or open an issue in the GitHub repository.

---

## 📌 Important Notes

### Product Creation Requirements

**1. Active Quarter Required**
- Products MUST have an active quarter to be created
- Run `php artisan quarter:activate` before creating products
- Check active quarter: `App\Models\Quarter::getActiveQuarter()`

**2. Source Types**

| Source | Field Required | Value Type | API Endpoint |
|--------|---------------|------------|--------------|
| **INSTRUMENT** | `source_instrument_id` | Integer (ID) | `/api/v1/measurement-instruments` |
| **TOOL** | `source_tool_model` | String (Model) | `/api/v1/tools/models` |
| **MANUAL** | - | - | - |
| **DERIVED** | `source_derived_name_id` | String (name_id) | - |

**3. Nature-based Validation**

| Nature | `source` Required? | `type` Required? | `rule_evaluation_setting` Required? |
|--------|-------------------|------------------|-------------------------------------|
| **QUANTITATIVE** | ✅ Yes | ✅ Yes | ✅ Yes |
| **QUALITATIVE** | ❌ No (optional) | ❌ No (optional) | ❌ No (must be null) |

---

## 🔄 Changelog

### Version 1.1.0 (Current - Nov 2024)
- ✅ Fixed: QUALITATIVE validation (source & type now optional)
- ✅ Added: Quarter activation command (`php artisan quarter:activate`)
- ✅ Fixed: measurement_groups null handling
- ✅ Fixed: Laravel ServeCommand Windows bug
- ✅ Enhanced: Comprehensive seeder system
- ✅ Improved: API documentation and troubleshooting

### Version 1.0.0
- ✅ Product & measurement management
- ✅ Advanced formula system with 40+ functions
- ✅ Tools & instruments tracking
- ✅ Issue tracking system
- ✅ JWT authentication
- ✅ Role-based access control

---

**Made with ❤️ using Laravel**

