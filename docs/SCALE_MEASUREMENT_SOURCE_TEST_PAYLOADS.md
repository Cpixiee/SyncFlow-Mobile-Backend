# Scale Measurement Source Test Payloads

## Endpoint: GET /api/v1/scale-measurement/source

### Request
- **Method:** `GET`
- **URL:** `/api/v1/scale-measurement/source`
- **Headers:**
  - `Authorization: Bearer {token}`

### Response (Success 200)
```json
{
  "http_code": 200,
  "message": "Scale measurement source setting retrieved successfully",
  "error_id": null,
  "data": {
    "is_automatic": false
  }
}
```

### Test Cases

**1. Get Current Setting (Default: false)**
- **Expected:** `is_automatic: false`
- **Description:** Should return the current setting, default is false (manual)

**2. Get Setting After Update**
- **Expected:** `is_automatic: true` (if previously set to true)
- **Description:** Should return the updated setting value

---

## Endpoint: POST /api/v1/scale-measurement/source

### Request
- **Method:** `POST`
- **URL:** `/api/v1/scale-measurement/source`
- **Headers:**
  - `Authorization: Bearer {token}`
  - `Content-Type: application/json`

### Test Payload 1: Set to Automatic (true)
```json
{
  "is_automatic": true
}
```

**Expected Response (Success 200):**
```json
{
  "http_code": 200,
  "message": "Scale measurement source setting updated successfully",
  "error_id": null,
  "data": {
    "is_automatic": true
  }
}
```

### Test Payload 2: Set to Manual (false)
```json
{
  "is_automatic": false
}
```

**Expected Response (Success 200):**
```json
{
  "http_code": 200,
  "message": "Scale measurement source setting updated successfully",
  "error_id": null,
  "data": {
    "is_automatic": false
  }
}
```

### Test Payload 3: Invalid Request (Missing Field)
```json
{}
```

**Expected Response (Error 422):**
```json
{
  "http_code": 422,
  "message": "Request invalid",
  "error_id": "VALIDATION_ERROR",
  "data": {
    "is_automatic": [
      "The is_automatic field is required."
    ]
  }
}
```

### Test Payload 4: Invalid Request (Wrong Type)
```json
{
  "is_automatic": "true"
}
```

**Expected Response (Error 422):**
```json
{
  "http_code": 422,
  "message": "Request invalid",
  "error_id": "VALIDATION_ERROR",
  "data": {
    "is_automatic": [
      "The is_automatic field must be true or false."
    ]
  }
}
```

### Test Payload 5: Invalid Request (Not Boolean)
```json
{
  "is_automatic": 1
}
```

**Expected Response (Error 422):**
```json
{
  "http_code": 422,
  "message": "Request invalid",
  "error_id": "VALIDATION_ERROR",
  "data": {
    "is_automatic": [
      "The is_automatic field must be true or false."
    ]
  }
}
```

### Test Cases

**1. Set to Automatic**
- **Payload:** `{"is_automatic": true}`
- **Expected:** Setting updated to true, response confirms the update

**2. Set to Manual**
- **Payload:** `{"is_automatic": false}`
- **Expected:** Setting updated to false, response confirms the update

**3. Verify Persistence**
- **Steps:**
  1. POST with `{"is_automatic": true}`
  2. GET the setting
  3. **Expected:** GET returns `{"is_automatic": true}`

**4. Toggle Setting**
- **Steps:**
  1. POST with `{"is_automatic": true}`
  2. POST with `{"is_automatic": false}`
  3. GET the setting
  4. **Expected:** GET returns `{"is_automatic": false}`

**5. Unauthorized Access**
- **Headers:** No Authorization header
- **Expected:** 401 Unauthorized

---

## Postman Collection Example

### Collection: Scale Measurement Source

#### Request 1: Get Source Setting
```
GET {{base_url}}/api/v1/scale-measurement/source
Authorization: Bearer {{token}}
```

#### Request 2: Set to Automatic
```
POST {{base_url}}/api/v1/scale-measurement/source
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "is_automatic": true
}
```

#### Request 3: Set to Manual
```
POST {{base_url}}/api/v1/scale-measurement/source
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "is_automatic": false
}
```

---

## Notes

- The setting is stored in `config/scale_measurement.php`
- Default value is `false` (manual)
- The setting persists across requests
- All authenticated users can GET the setting
- All authenticated users can POST/update the setting
- The setting affects how scale measurements are taken (automatic from device vs manual input)
