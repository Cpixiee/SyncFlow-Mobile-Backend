# Dashboard Endpoints Documentation

## Update 13 Januari 2026

Dokumentasi untuk 2 endpoint baru yang ditambahkan untuk homepage/dashboard.

---

## 1. Get Overdue Calibration Tools

Get list of tools yang next calibration-nya sudah lewat dari tanggal yang ditentukan.

### Endpoint
```
GET /api/v1/tools/overdue-calibration
```

### Authentication
Required: JWT Token

### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | date | Yes | Tanggal pembanding (format: YYYY-MM-DD) |

### Business Rules
- Tool dianggap **overdue** jika `next_calibration_at < date`
- Hanya menampilkan tools dengan status `ACTIVE`
- Tools yang `next_calibration_at` adalah `null` tidak dimasukkan
- Result diurutkan berdasarkan `next_calibration_at` ASC (yang paling overdue di atas)

### Request Example
```bash
GET /api/v1/tools/overdue-calibration?date=2026-01-16
Authorization: Bearer {jwt_token}
```

### Response Success (200 OK)
```json
{
  "http_code": 200,
  "message": "Overdue calibration tools retrieved successfully",
  "error_id": null,
  "data": [
    {
      "id": 1,
      "toolName": "Micrometer Digital",
      "toolModel": "MDC-250",
      "toolType": "MECHANICAL",
      "toolTypeDescription": "Mechanical",
      "lastCalibration": "2025-06-15T00:00:00.000000Z",
      "nextCalibration": "2025-12-15T00:00:00.000000Z",
      "imei": "IMEI-001-MDC250",
      "status": "ACTIVE",
      "statusDescription": "Active",
      "createdAt": "2025-01-10T08:30:00.000000Z",
      "updatedAt": "2025-06-15T10:00:00.000000Z"
    },
    {
      "id": 2,
      "toolName": "Optical Comparator",
      "toolModel": "OPT-500",
      "toolType": "OPTICAL",
      "toolTypeDescription": "Optical",
      "lastCalibration": "2025-07-01T00:00:00.000000Z",
      "nextCalibration": "2026-01-01T00:00:00.000000Z",
      "imei": "IMEI-002-OPT500",
      "status": "ACTIVE",
      "statusDescription": "Active",
      "createdAt": "2025-02-05T09:00:00.000000Z",
      "updatedAt": "2025-07-01T11:30:00.000000Z"
    }
  ]
}
```

### Response Error (400 Bad Request)
```json
{
  "http_code": 400,
  "message": "Request invalid",
  "error_id": "VALIDATION_XXX",
  "data": {
    "date": ["The date field is required."]
  }
}
```

### Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Tool ID |
| `toolName` | string | Nama tool |
| `toolModel` | string | Model tool |
| `toolType` | string | Tipe tool (`OPTICAL`, `MECHANICAL`) |
| `toolTypeDescription` | string | Deskripsi tipe tool |
| `lastCalibration` | datetime (ISO 8601) | Tanggal calibration terakhir |
| `nextCalibration` | datetime (ISO 8601) | Tanggal calibration berikutnya |
| `imei` | string | IMEI tool |
| `status` | string | Status tool (`ACTIVE`, `INACTIVE`) |
| `statusDescription` | string | Deskripsi status |
| `createdAt` | datetime (ISO 8601) | Waktu tool dibuat |
| `updatedAt` | datetime (ISO 8601) | Waktu tool terakhir diupdate |

---

## 2. Get Overdue Issues

Get list of issues yang due date-nya sudah lewat dari tanggal yang ditentukan dan belum resolved.

### Endpoint
```
GET /api/v1/issue-tracking/overdue
```

### Authentication
Required: JWT Token

### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | date | Yes | Tanggal pembanding (format: YYYY-MM-DD) |

### Business Rules
- Issue dianggap **overdue** jika:
  - `due_date < date` **AND**
  - `status NOT IN (SOLVED)` - yang berarti `status IN (PENDING, ON_GOING)`
- Issues yang `due_date` adalah `null` tidak dimasukkan
- Result diurutkan berdasarkan `due_date` ASC (yang paling overdue di atas)

### Request Example
```bash
GET /api/v1/issue-tracking/overdue?date=2026-01-16
Authorization: Bearer {jwt_token}
```

### Response Success (200 OK)
```json
{
  "http_code": 200,
  "message": "Overdue issues retrieved successfully",
  "error_id": null,
  "data": [
    {
      "id": 1,
      "title": "Equipment malfunction in production line",
      "description": "Production line A equipment showing error code E123",
      "status": "PENDING",
      "createdAt": "2026-01-01T08:00:00.000000Z",
      "priority": null,
      "assignedTo": null,
      "reportedBy": "john_doe",
      "dueDate": "2026-01-10T00:00:00.000000Z",
      "commentCount": 3
    },
    {
      "id": 2,
      "title": "Quality control issue on batch B123",
      "description": "Multiple defects found in batch B123",
      "status": "ON_GOING",
      "createdAt": "2026-01-05T10:30:00.000000Z",
      "priority": null,
      "assignedTo": null,
      "reportedBy": "jane_smith",
      "dueDate": "2026-01-14T00:00:00.000000Z",
      "commentCount": 7
    }
  ]
}
```

### Response Error (400 Bad Request)
```json
{
  "http_code": 400,
  "message": "Request invalid",
  "error_id": "VALIDATION_XXX",
  "data": {
    "date": ["The date field is required."]
  }
}
```

### Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Issue ID |
| `title` | string | Judul issue (mapped from `issue_name`) |
| `description` | string | Deskripsi issue |
| `status` | string | Status issue (`PENDING`, `ON_GOING`, `SOLVED`) |
| `createdAt` | datetime (ISO 8601) | Waktu issue dibuat |
| `priority` | string\|null | **Not implemented** - Priority issue (always `null` for now) |
| `assignedTo` | string\|null | **Not implemented** - User yang ditugaskan (always `null` for now) |
| `reportedBy` | string\|null | Username pembuat issue |
| `dueDate` | datetime (ISO 8601) | Due date issue |
| `commentCount` | integer | Jumlah comment pada issue |

---

## Status Enums

### Tool Status
- `ACTIVE` - Tool aktif dan bisa digunakan
- `INACTIVE` - Tool tidak aktif

### Tool Type
- `OPTICAL` - Tool tipe optical
- `MECHANICAL` - Tool tipe mechanical

### Issue Status
- `PENDING` - Issue belum dikerjakan
- `ON_GOING` - Issue sedang dikerjakan
- `SOLVED` - Issue sudah diselesaikan

---

## Notes

### Field Mapping untuk Issue Response

Karena schema database Issue saat ini tidak memiliki field `priority` dan `assignedTo`, response sementara mengembalikan:
- `priority`: `null`
- `assignedTo`: `null`
- `title`: diambil dari field `issue_name`
- `reportedBy`: diambil dari `creator.username`

Jika di masa depan field ini ditambahkan ke schema, response akan otomatis include data yang sebenarnya.

### Integration dengan Dashboard

Kedua endpoint ini bisa dipanggil dari homepage untuk menampilkan:
1. **Tools Overdue Card**: Menampilkan jumlah dan list tools yang perlu kalibrasi
2. **Issues Overdue Card**: Menampilkan jumlah dan list issues yang sudah overdue

### Example Frontend Integration

```javascript
// Get today's date
const today = new Date().toISOString().split('T')[0]; // "2026-01-16"

// Fetch overdue tools
const overdueTools = await fetch(
  `/api/v1/tools/overdue-calibration?date=${today}`,
  {
    headers: { 'Authorization': `Bearer ${token}` }
  }
);

// Fetch overdue issues
const overdueIssues = await fetch(
  `/api/v1/issue-tracking/overdue?date=${today}`,
  {
    headers: { 'Authorization': `Bearer ${token}` }
  }
);
```

---

## Files Modified

```
app/Http/Controllers/Api/V1/
├── ToolController.php
│   └── Added getOverdueCalibration() method
├── IssueController.php
│   └── Added getOverdue() method
routes/
└── api.php
    ├── Added GET /tools/overdue-calibration route
    └── Added GET /issue-tracking/overdue route
```

---

## Testing

### Test Overdue Calibration Tools
```bash
# Test with today's date
GET /api/v1/tools/overdue-calibration?date=2026-01-16

# Test with future date
GET /api/v1/tools/overdue-calibration?date=2026-12-31

# Test with past date
GET /api/v1/tools/overdue-calibration?date=2025-01-01
```

### Test Overdue Issues
```bash
# Test with today's date
GET /api/v1/issue-tracking/overdue?date=2026-01-16

# Test with future date
GET /api/v1/issue-tracking/overdue?date=2026-12-31

# Test with past date
GET /api/v1/issue-tracking/overdue?date=2025-01-01
```

---

**Last Updated:** 2026-01-13  
**Status:** ✅ Ready for Testing
