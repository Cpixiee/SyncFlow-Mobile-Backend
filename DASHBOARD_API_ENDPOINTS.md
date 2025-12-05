# Dashboard API Endpoints - December 5, 2025

## Overview
Dokumentasi untuk 3 endpoint baru yang digunakan untuk menampilkan data di dashboard frontend.

---

## 1. Product Checking Progress (Per Category)

### Endpoint
```http
GET http://localhost:8000/api/v1/product-measurement/progress-category?quarter=2&year=2025
```

### Purpose
Menampilkan progress OK/NG dan status checking per kategori product untuk dashboard "Product Checking".

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `quarter` | integer | Yes | Quarter number (1-4) |
| `year` | integer | Yes | Year (2020-2100) |

### Response Format

```json
{
  "http_code": 200,
  "message": "Progress per category retrieved successfully",
  "error_id": null,
  "data": {
    "perCategory": [
      {
        "category_id": 1,
        "category_name": "Tube Test",
        "product_result": {
          "ok": 25,
          "ng": 10,
          "total": 50
        },
        "product_checking": {
          "todo": 15,
          "checked": 25,
          "done": 40,
          "total": 50
        }
      },
      {
        "category_id": 2,
        "category_name": "Wire Test Reguler",
        "product_result": {
          "ok": 24,
          "ng": 16,
          "total": 40
        },
        "product_checking": {
          "todo": 10,
          "checked": 15,
          "done": 30,
          "total": 40
        }
      }
    ]
  }
}
```

### Field Descriptions

#### `product_result`
Menunjukkan hasil pengukuran product:
- **`ok`**: Jumlah products dengan status COMPLETED dan overall_result = true
- **`ng`**: Jumlah products dengan status COMPLETED dan overall_result = false
- **`total`**: Total products di category ini dalam quarter tersebut

#### `product_checking`
Menunjukkan status checking product:
- **`todo`**: Products dengan status TODO atau batch_number null (belum dimulai)
- **`checked`**: Products dengan status IN_PROGRESS atau PENDING (sedang dikerjakan)
- **`done`**: Products dengan status COMPLETED (selesai dikerjakan)
- **`total`**: Total products di category ini dalam quarter tersebut

### Usage Example

**Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/product-measurement/progress-category?quarter=3&year=2025" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Use Case:**
Untuk menampilkan donut charts di dashboard "Product Checking" per category:
- TUBE TEST: 50% OK (green), 50% PENDING (orange)
- WIRE TEST: 62% OK (green), 38% PENDING (orange)

---

## 2. Product Measurement Progress (Overall)

### Endpoint
```http
GET /api/v1/product-measurement/progress-all
```

### Purpose
Menampilkan overall progress measurement untuk semua products dalam quarter tertentu untuk "Target Progress" gauge di dashboard.

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `quarter` | integer | Yes | Quarter number (1-4) |
| `year` | integer | Yes | Year (2020-2100) |

### Response Format

```json
{
  "http_code": 200,
  "message": "Overall progress retrieved successfully",
  "error_id": null,
  "data": {
    "done": 25,
    "ongoing": 10,
    "backlog": 15
  }
}
```

### Field Descriptions

- **`done`**: Jumlah products dengan status COMPLETED (measurement selesai)
- **`ongoing`**: Jumlah products dengan status IN_PROGRESS atau PENDING (sedang dikerjakan)
- **`backlog`**: Jumlah products dengan status TODO atau lainnya (belum dimulai)

### Status Mapping

| Measurement Status | Dashboard Display |
|-------------------|-------------------|
| `COMPLETED` | DONE |
| `IN_PROGRESS` | ONGOING |
| `PENDING` | ONGOING |
| `TODO` | BACKLOG |
| `CANCELLED` | BACKLOG |

### Usage Example

**Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/product-measurement/progress-all?quarter=3&year=2025" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Overall progress retrieved successfully",
  "data": {
    "done": 25,
    "ongoing": 10,
    "backlog": 15
  }
}
```

**Use Case:**
Untuk menampilkan gauge chart "Target Progress Q3 2025":
- Total: 50 products
- Progress: 60% (25 DONE + 10 ONGOING = 35/50)
- Breakdown:
  - DONE: 25 products
  - ONGOING: 10 products
  - BACKLOG: 15 products

---

## 3. Issue Tracking Progress

### Endpoint
```http
GET /api/v1/issue-tracking/progress
```

### Purpose
Menampilkan progress issue tracking berdasarkan quarter untuk "Issue Tracking" gauge di dashboard.

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `quarter` | integer | Yes | Quarter number (1-4) |
| `year` | integer | Yes | Year (2020-2100) |

### Response Format

```json
{
  "http_code": 200,
  "message": "Issue tracking progress retrieved successfully",
  "error_id": null,
  "data": {
    "solved": 1,
    "in_progress": 2,
    "pending": 1
  }
}
```

### Field Descriptions

- **`solved`**: Jumlah issues dengan status SOLVED
- **`in_progress`**: Jumlah issues dengan status ON_GOING
- **`pending`**: Jumlah issues dengan status PENDING

### Status Mapping

| Issue Status | Dashboard Display | Color |
|-------------|-------------------|-------|
| `SOLVED` | SOLVED | Green |
| `ON_GOING` | IN PROGRESS | Blue |
| `PENDING` | PENDING | Orange |

### Issue Filtering Logic

Issues dihitung berdasarkan:
1. **Primary**: Issues dengan `due_date` dalam quarter tersebut
2. **Fallback**: Jika `due_date` null, gunakan `created_at`

Contoh untuk Q3 2025 (Juli-September):
- Issues dengan `due_date` antara 2025-07-01 sampai 2025-09-30
- ATAU issues dengan `created_at` antara 2025-07-01 sampai 2025-09-30

### Usage Example

**Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/issue-tracking/progress?quarter=3&year=2025" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Response:**
```json
{
  "http_code": 200,
  "message": "Issue tracking progress retrieved successfully",
  "data": {
    "solved": 1,
    "in_progress": 2,
    "pending": 1
  }
}
```

**Use Case:**
Untuk menampilkan gauge chart "Issue Tracking Q3 2025":
- Total: 4 issues
- Progress: 25% (1 SOLVED / 4 TOTAL)
- Breakdown:
  - SOLVED: 1 issue
  - IN PROGRESS: 2 issues
  - PENDING: 1 issue

---

## Quarter Definition

Semua endpoint menggunakan quarter definition yang sama:

| Quarter | Bulan | Date Range |
|---------|-------|------------|
| Q1 | Januari - Maret | Jan 1 - Mar 31 |
| Q2 | April - Juni | Apr 1 - Jun 30 |
| Q3 | Juli - September | Jul 1 - Sep 30 |
| Q4 | Oktober - Desember | Oct 1 - Dec 31 |

---

## Authentication

Semua endpoint memerlukan JWT authentication.

**Header:**
```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Role Access:**
- All authenticated users (operator, admin, superadmin)

---

## Error Responses

### Validation Error (400)

```json
{
  "http_code": 400,
  "message": "Request invalid",
  "error_id": "VALIDATION_ERROR",
  "data": {
    "quarter": ["The quarter field is required."],
    "year": ["The year field is required."]
  }
}
```

### Unauthorized (401)

```json
{
  "http_code": 401,
  "message": "User not authenticated",
  "error_id": "UNAUTHORIZED"
}
```

### Server Error (500)

```json
{
  "http_code": 500,
  "message": "Error getting progress per category: ...",
  "error_id": "PROGRESS_CATEGORY_ERROR"
}
```

---

## Complete Dashboard Integration Example

### Frontend Dashboard Components

#### 1. Product Checking Section (Top Row)

**Components:**
- Donut chart per category (TUBE TEST, WIRE TEST)
- Shows CHECKED vs PENDING per category

**API Call:**
```javascript
// Fetch progress per category
fetch('/api/v1/product-measurement/progress-category?quarter=3&year=2025', {
  headers: { 'Authorization': `Bearer ${token}` }
})
.then(res => res.json())
.then(data => {
  // data.data.perCategory = array of categories
  data.data.perCategory.forEach(category => {
    // Render donut chart for each category
    renderDonutChart({
      title: category.category_name,
      checked: category.product_checking.checked,
      pending: category.product_checking.todo,
      total: category.product_checking.total
    });
  });
});
```

#### 2. Product Result Section (Top Right)

**Components:**
- Single donut chart showing OK vs NG overall

**API Call:**
```javascript
// Aggregate from progress-category
fetch('/api/v1/product-measurement/progress-category?quarter=3&year=2025', {
  headers: { 'Authorization': `Bearer ${token}` }
})
.then(res => res.json())
.then(data => {
  let totalOk = 0, totalNg = 0;
  
  data.data.perCategory.forEach(category => {
    totalOk += category.product_result.ok;
    totalNg += category.product_result.ng;
  });
  
  renderProductResultChart({ ok: totalOk, ng: totalNg });
});
```

#### 3. Target Progress Section (Bottom Left)

**Components:**
- Gauge chart showing done/ongoing/backlog

**API Call:**
```javascript
// Fetch overall progress
fetch('/api/v1/product-measurement/progress-all?quarter=3&year=2025', {
  headers: { 'Authorization': `Bearer ${token}` }
})
.then(res => res.json())
.then(data => {
  const total = data.data.done + data.data.ongoing + data.data.backlog;
  const percentage = (data.data.done / total) * 100;
  
  renderGaugeChart({
    percentage: percentage,
    done: data.data.done,
    ongoing: data.data.ongoing,
    backlog: data.data.backlog,
    total: total
  });
});
```

#### 4. Issue Tracking Section (Bottom Right)

**Components:**
- Gauge chart showing solved/in_progress/pending

**API Call:**
```javascript
// Fetch issue tracking progress
fetch('/api/v1/issue-tracking/progress?quarter=3&year=2025', {
  headers: { 'Authorization': `Bearer ${token}` }
})
.then(res => res.json())
.then(data => {
  const total = data.data.solved + data.data.in_progress + data.data.pending;
  const percentage = (data.data.solved / total) * 100;
  
  renderIssueTrackingChart({
    percentage: percentage,
    solved: data.data.solved,
    inProgress: data.data.in_progress,
    pending: data.data.pending,
    total: total
  });
});
```

---

## Testing Scenarios

### Test Case 1: Product Checking per Category

**Setup:**
```sql
-- Create products in different categories for Q3 2025
INSERT INTO product_measurements (product_id, due_date, status, overall_result, batch_number) VALUES
  -- TUBE TEST products
  (1, '2025-07-15', 'COMPLETED', true, 'BATCH-001'),  -- OK
  (2, '2025-07-16', 'COMPLETED', false, 'BATCH-002'), -- NG
  (3, '2025-07-17', 'IN_PROGRESS', null, 'BATCH-003'), -- CHECKED
  (4, '2025-07-18', 'TODO', null, null),              -- TODO
  
  -- WIRE TEST products
  (5, '2025-08-15', 'COMPLETED', true, 'BATCH-004'),  -- OK
  (6, '2025-08-16', 'PENDING', null, 'BATCH-005');    -- CHECKED
```

**Request:**
```http
GET /api/v1/product-measurement/progress-category?quarter=3&year=2025
```

**Expected Response:**
```json
{
  "perCategory": [
    {
      "category_id": 1,
      "category_name": "Tube Test",
      "product_result": {
        "ok": 1,
        "ng": 1,
        "total": 4
      },
      "product_checking": {
        "todo": 1,
        "checked": 1,
        "done": 2,
        "total": 4
      }
    },
    {
      "category_id": 2,
      "category_name": "Wire Test Reguler",
      "product_result": {
        "ok": 1,
        "ng": 0,
        "total": 2
      },
      "product_checking": {
        "todo": 0,
        "checked": 1,
        "done": 1,
        "total": 2
      }
    }
  ]
}
```

### Test Case 2: Overall Progress

**Request:**
```http
GET /api/v1/product-measurement/progress-all?quarter=3&year=2025
```

**Expected Response:**
```json
{
  "done": 3,      // 2 TUBE + 1 WIRE = 3 COMPLETED
  "ongoing": 2,   // 1 IN_PROGRESS + 1 PENDING
  "backlog": 1    // 1 TODO
}
```

### Test Case 3: Issue Tracking

**Setup:**
```sql
INSERT INTO issues (issue_name, status, due_date) VALUES
  ('Issue 1', 'SOLVED', '2025-07-15'),
  ('Issue 2', 'ON_GOING', '2025-08-10'),
  ('Issue 3', 'PENDING', '2025-09-05');
```

**Request:**
```http
GET /api/v1/issue-tracking/progress?quarter=3&year=2025
```

**Expected Response:**
```json
{
  "solved": 1,
  "in_progress": 1,
  "pending": 1
}
```

---

## Performance Considerations

### Optimization Tips

1. **Caching**: Cache results per quarter untuk mengurangi query load
   ```php
   $cacheKey = "progress_category_q{$quarter}_{$year}";
   $data = Cache::remember($cacheKey, 3600, function() {
       // Query logic
   });
   ```

2. **Eager Loading**: Gunakan eager loading untuk relationships
   ```php
   $measurements = ProductMeasurement::with('product.productCategory')
       ->whereBetween('due_date', [$start, $end])
       ->get();
   ```

3. **Index Database**: Pastikan index ada di kolom yang sering di-query
   ```sql
   CREATE INDEX idx_due_date ON product_measurements(due_date);
   CREATE INDEX idx_status ON product_measurements(status);
   CREATE INDEX idx_category ON products(product_category_id);
   ```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | December 5, 2025 | Initial release dengan 3 endpoint baru untuk dashboard |

---

## Related Documentation

- `04_12_2025_UPDATE_BUGS_FIX.md` - Previous bug fixes
- `NOTIFICATION_SYSTEM.md` - Notification system
- `PRODUCT_MEASUREMENT_API.md` - Complete product measurement API docs

---

**Status**: ✅ **READY FOR INTEGRATION**  
**Author**: Backend Team  
**Last Updated**: December 5, 2025

