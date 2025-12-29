# Profile Image Upload API Documentation

## Overview
API endpoint untuk upload gambar profil user. File akan disimpan di `storage/app/public` dan dapat diakses via URL `/storage/{filename}`.

---

## Endpoint

### Upload Profile Image

**POST** `/api/v1/upload-profile-image`

**Authentication:** Required (JWT Bearer Token)

**Content-Type:** `multipart/form-data`

#### Request Body (Form Data)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `image` | File | Yes | File gambar (JPEG, PNG, GIF, WEBP) - Max 5MB |

#### Response Success (200)

```json
{
  "http_code": 200,
  "message": "Profile image uploaded successfully",
  "error_id": null,
  "data": {
    "id": 1,
    "username": "john_doe",
    "photo_url": "/storage/1703123456_abc123def456.jpg",
    "updated_at": "2025-12-18 16:30:00"
  }
}
```

#### Response Error (400/401/500)

```json
{
  "http_code": 400,
  "message": "Request invalid",
  "error_id": "VALIDATION_ERROR",
  "data": {
    "image": ["The image field is required."]
  }
}
```

---

## Use Case & Flow

### Use Case 1: User Upload Profile Image (Mobile/Flutter)

**Scenario:** User A ingin mengupload foto profil dari aplikasi Flutter

**Flow:**

1. **User melakukan login** (jika belum login)
   ```
   POST /api/v1/login
   Body: { "username": "userA", "password": "password123" }
   Response: { "data": { "token": "eyJ0eXAiOiJKV1QiLCJhbGc..." } }
   ```

2. **User memilih gambar dari gallery/kamera**
   - User membuka halaman profile
   - User tap tombol "Upload Photo" atau "Change Photo"
   - User memilih gambar dari gallery atau ambil foto dari kamera

3. **App mengupload gambar ke server**
   ```dart
   // Flutter example
   var request = http.MultipartRequest(
     'POST',
     Uri.parse('https://your-api.com/api/v1/upload-profile-image'),
   );
   
   request.headers['Authorization'] = 'Bearer ${userToken}';
   request.files.add(
     await http.MultipartFile.fromPath('image', imagePath),
   );
   
   var response = await request.send();
   var responseData = await response.stream.bytesToString();
   ```

4. **Backend memproses upload**
   - Validasi file (format, size)
   - Hapus gambar lama (jika ada)
   - Simpan file baru di `storage/app/public/{filename}`
   - Update `photo_url` di database: `/storage/{filename}`
   - Return response dengan `photo_url` baru

5. **App menerima response dan update UI**
   ```dart
   // Parse response
   var jsonResponse = json.decode(responseData);
   String photoUrl = jsonResponse['data']['photo_url']; // "/storage/1703123456_abc123.jpg"
   
   // Construct full URL
   String fullImageUrl = 'https://your-api.com$photoUrl';
   
   // Update UI - tampilkan gambar
   Image.network(fullImageUrl)
   ```

6. **App menyimpan photo_url ke state management**
   - Update user profile state
   - Cache gambar untuk offline access
   - Refresh profile page

---

### Use Case 2: User Upload Profile Image (Web)

**Scenario:** User A ingin mengupload foto profil dari website

**Flow:**

1. **User login** (jika belum login)
2. **User pilih file** via file input
3. **Submit form dengan FormData**
   ```javascript
   const formData = new FormData();
   formData.append('image', fileInput.files[0]);
   
   fetch('/api/v1/upload-profile-image', {
     method: 'POST',
     headers: {
       'Authorization': `Bearer ${token}`
     },
     body: formData
   })
   .then(res => res.json())
   .then(data => {
     const imageUrl = data.data.photo_url;
     const fullUrl = `${window.location.origin}${imageUrl}`;
     // Update UI
     document.getElementById('profile-img').src = fullUrl;
   });
   ```

---

### Use Case 3: Display Profile Image

**Scenario:** Menampilkan foto profil user di aplikasi

**Flow:**

1. **Get user profile** (sudah include photo_url)
   ```
   GET /api/v1/me
   Headers: { "Authorization": "Bearer {token}" }
   ```

2. **Response**
   ```json
   {
     "data": {
       "id": 1,
       "username": "userA",
       "photo_url": "/storage/1703123456_abc123.jpg",
       ...
     }
   }
   ```

3. **Construct full URL dan tampilkan**
   ```dart
   // Flutter
   String photoUrl = user.photoUrl; // "/storage/1703123456_abc123.jpg"
   String baseUrl = "https://your-api.com";
   String fullUrl = "$baseUrl$photoUrl";
   
   Image.network(fullUrl, 
     errorBuilder: (context, error, stackTrace) => Icon(Icons.person)
   )
   ```

   ```javascript
   // Web/React
   const photoUrl = user.photo_url; // "/storage/1703123456_abc123.jpg"
   const fullUrl = `${API_BASE_URL}${photoUrl}`;
   
   <img src={fullUrl} alt="Profile" onError={handleError} />
   ```

---

## Important Notes

### 1. Storage Link

**PENTING:** Pastikan symbolic link sudah dibuat dengan command:
```bash
php artisan storage:link
```

Ini akan membuat link dari `public/storage` → `storage/app/public`, sehingga file bisa diakses via URL `/storage/{filename}`.

**Note:** Command ini sudah ditambahkan di `deploy.sh` step 10.

### 2. URL Format

- **Backend menyimpan:** `/storage/{filename}` (relative path)
- **Frontend harus prepend base URL:**
  - Production: `https://your-api.com/storage/{filename}`
  - Development: `http://localhost:8000/storage/{filename}`

### 3. File Naming

File disimpan dengan format: `{timestamp}_{uniqid}.{extension}`
- Contoh: `1703123456_abc123def456.jpg`
- Format ini memastikan file name unik dan tidak bentrok

### 4. Old Image Cleanup

Sistem otomatis menghapus gambar lama ketika user upload gambar baru. Old image dihapus dari storage sebelum file baru disimpan.

### 5. File Validation

- **Format yang diperbolehkan:** JPEG, JPG, PNG, GIF, WEBP
- **Max size:** 5MB (5120 KB)
- **Validation error akan return 400** dengan detail error

### 6. Authentication

**Wajib:** Endpoint ini memerlukan JWT Bearer Token. User hanya bisa upload foto profil sendiri (automatically determined dari token).

---

## Example Integration (Flutter/Dart)

```dart
import 'package:http/http.dart' as http;
import 'dart:io';
import 'dart:convert';

class ProfileService {
  final String baseUrl;
  final String token;

  ProfileService(this.baseUrl, this.token);

  Future<Map<String, dynamic>> uploadProfileImage(File imageFile) async {
    try {
      var request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/api/v1/upload-profile-image'),
      );

      // Add authorization header
      request.headers['Authorization'] = 'Bearer $token';

      // Add image file
      request.files.add(
        await http.MultipartFile.fromPath('image', imageFile.path),
      );

      // Send request
      var streamedResponse = await request.send();
      var response = await http.Response.fromStream(streamedResponse);

      if (response.statusCode == 200) {
        var jsonResponse = json.decode(response.body);
        return {
          'success': true,
          'photo_url': jsonResponse['data']['photo_url'],
          'message': jsonResponse['message'],
        };
      } else {
        var errorResponse = json.decode(response.body);
        return {
          'success': false,
          'message': errorResponse['message'],
          'error_id': errorResponse['error_id'],
        };
      }
    } catch (e) {
      return {
        'success': false,
        'message': 'Network error: $e',
      };
    }
  }

  String getImageUrl(String photoUrl) {
    if (photoUrl == null || photoUrl.isEmpty) {
      return null;
    }
    
    // If already full URL, return as is
    if (photoUrl.startsWith('http://') || photoUrl.startsWith('https://')) {
      return photoUrl;
    }
    
    // Prepend base URL
    return '$baseUrl$photoUrl';
  }
}

// Usage
final service = ProfileService('https://api.example.com', userToken);
final result = await service.uploadProfileImage(selectedImageFile);

if (result['success']) {
  String photoUrl = result['photo_url']; // "/storage/1703123456_abc123.jpg"
  String fullUrl = service.getImageUrl(photoUrl); // "https://api.example.com/storage/1703123456_abc123.jpg"
  // Update UI with fullUrl
}
```

---

## Example Integration (JavaScript/React)

```javascript
const uploadProfileImage = async (imageFile, token) => {
  const formData = new FormData();
  formData.append('image', imageFile);

  try {
    const response = await fetch('https://api.example.com/api/v1/upload-profile-image', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      },
      body: formData
    });

    const data = await response.json();

    if (response.ok) {
      const photoUrl = data.data.photo_url; // "/storage/1703123456_abc123.jpg"
      const fullUrl = `https://api.example.com${photoUrl}`;
      return { success: true, photoUrl, fullUrl };
    } else {
      return { success: false, message: data.message, error: data };
    }
  } catch (error) {
    return { success: false, message: error.message };
  }
};

// Usage
const handleImageUpload = async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  const result = await uploadProfileImage(file, userToken);
  
  if (result.success) {
    setProfileImage(result.fullUrl);
    // Update UI
  } else {
    alert(`Upload failed: ${result.message}`);
  }
};
```

---

## Testing

### Manual Test via Web

**URL untuk akses test page:**
- **Production Server:** `http://103.236.140.19:2020/test-upload.html`
- **Local Development:** `http://localhost:2020/test-upload.html` (jika menggunakan docker)

**Steps:**
1. Buka browser dan akses URL di atas
2. Login dengan username & password (atau masukkan token manual)
3. Pilih file gambar
4. Klik "Upload Image"
5. Lihat hasil upload dan URL yang dihasilkan

**Note:** Port 2020 adalah port yang dikonfigurasi di docker-compose.yml untuk Apache server.

### Test via Postman/cURL

```bash
# Production Server
curl -X POST http://103.236.140.19:2020/api/v1/upload-profile-image \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "image=@/path/to/image.jpg"

# Local Development (jika menggunakan docker)
curl -X POST http://localhost:2020/api/v1/upload-profile-image \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "image=@/path/to/image.jpg"
```

---

## Related Endpoints

- **GET /api/v1/me** - Get current user profile (includes photo_url)
- **PUT /api/v1/update-user** - Update user info (can update photo_url with external URL)

---

## Error Codes

| HTTP Code | Error ID | Description |
|-----------|----------|-------------|
| 400 | VALIDATION_ERROR | File validation failed (format, size) |
| 401 | UNAUTHORIZED | Missing or invalid JWT token |
| 500 | IMAGE_UPLOAD_ERROR | Server error during upload |

