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

6. **Configure database in `.env`**
```env
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

### Basic Formula Examples

```javascript
// Simple average
=avg(thickness_a)

// Multiple measurements
=(avg(thickness_a) + avg(thickness_b) + avg(thickness_c)) / 3

// Temperature conversion (Fahrenheit to Celsius)
=(temperature - 32) * 5 / 9

// Trigonometric calculation
=sin(angle) * radius

// Complex formula
=sqrt(pow(x, 2) + pow(y, 2))

// Conditional logic
=if(avg(thickness_a) > 1.5, 1, 0)
```

### Formula Rules

1. ✅ **Must start with `=`** (like Excel)
2. ✅ **Case-insensitive** function names (AVG = avg = Avg)
3. ✅ **Referenced measurement items must exist** before formula
4. ✅ **Order matters** - dependencies must be defined first

### Create Product with Formula

```bash
POST /api/v1/products
Authorization: Bearer {token}

# See FORMULA_SYSTEM_DOCUMENTATION.md for complete JSON examples
```

### Formula Autocomplete API

```bash
GET /api/v1/products/{productId}/measurement-items/suggest?query=thick
Authorization: Bearer {token}
```

> 📖 **Complete API examples with request/response:** See [FORMULA_SYSTEM_DOCUMENTATION.md](FORMULA_SYSTEM_DOCUMENTATION.md)

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

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| **Products** ||||
| POST | `/api/v1/products` | Create product | Admin/SuperAdmin |
| GET | `/api/v1/products` | List products | Admin/SuperAdmin |
| GET | `/api/v1/products/{id}` | Get product detail | Admin/SuperAdmin |
| GET | `/api/v1/products/categories` | Get product categories | Admin/SuperAdmin |
| GET | `/api/v1/products/{id}/measurement-items/suggest` | Autocomplete | All |
| **Measurements** ||||
| POST | `/api/v1/product-measurement` | Create measurement | All |
| GET | `/api/v1/product-measurement` | List measurements | All |
| POST | `/api/v1/product-measurement/{id}/submit` | Submit measurement | All |
| **Instruments** ||||
| GET | `/api/v1/measurement-instruments` | List instruments | All |
| GET | `/api/v1/measurement-instruments/{id}` | Get instrument detail | All |
| **Tools** ||||
| GET | `/api/v1/tools` | List tools | All |
| GET | `/api/v1/tools/models` | Get tool models | All |
| GET | `/api/v1/tools/by-model` | Get tools by model | All |
| POST | `/api/v1/tools` | Create tool | Admin/SuperAdmin |
| **Issues** ||||
| GET | `/api/v1/issues` | List issues | All |
| POST | `/api/v1/issues` | Create issue | Admin/SuperAdmin |
| POST | `/api/v1/issues/{id}/comments` | Add comment | All |

### Response Format

All API endpoints use consistent response format:
- **Success:** `http_code: 200/201`, `message`, `data`
- **Error:** `http_code: 400/404/500`, `message`, `error_id`, `data` (validation errors)

> 📖 **Full response examples:** See individual API documentation files

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

- **[Formula System Documentation](FORMULA_SYSTEM_DOCUMENTATION.md)** - Complete formula guide with examples
- **[Tools Logic Explanation](TOOLS_LOGIC_EXPLANATION.md)** - Tools & instruments management
- **[Issue API Documentation](ISSUE_API_DOCUMENTATION.md)** - Issue tracking system

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

