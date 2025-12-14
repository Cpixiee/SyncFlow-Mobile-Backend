# Master Product API Documentation

## Overview
API ini menyediakan fitur untuk membuat product dari master data yang sudah tersedia. Master products adalah template/template produk yang sudah disiapkan dan bisa digunakan untuk membuat product baru dengan cepat.

## Endpoints

### 1. GET /api/v1/products/master-products
**Purpose**: Mendapatkan daftar master products yang belum pernah dibuat menjadi product aktual.

**Authentication**: Required (semua user yang authenticated)

**Query Parameters**:
- `product_category_id` (optional, integer): Filter berdasarkan kategori product
- `query` (optional, string): Search query untuk mencari berdasarkan `product_spec_name`, `product_name`, atau `article_code`
- `page` (optional, integer, default: 1): Halaman yang ingin ditampilkan
- `limit` (optional, integer, default: 10): Jumlah item per halaman

**Response**:
```json
{
  "http_code": 200,
  "message": "Success",
  "error_id": null,
  "data": {
    "docs": [
      {
        "id": 1,
        "product_category_id": 1,
        "product_category_name": "Tube Test",
        "product_name": "COT",
        "product_spec_name": "COT B 5",
        "ref_spec_number": null,
        "nom_size_vo": null,
        "article_code": null,
        "no_document": null,
        "no_doc_reference": null,
        "color": "B",
        "size": "5"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_page": 5,
      "limit": 10,
      "total_docs": 50
    }
  }
}
```

**Notes**:
- Endpoint ini hanya mengembalikan master products yang **belum pernah dibuat** menjadi product (berdasarkan `product_spec_name`)
- Search akan mencari di `product_spec_name`, `product_name`, dan `article_code`
- Jika master product sudah pernah dibuat menjadi product, maka tidak akan muncul di list ini

**Example Request**:
```
GET /api/v1/products/master-products?product_category_id=1&query=COT&page=1&limit=10
```

---

### 2. POST /api/v1/products/from-existing
**Purpose**: Membuat product baru dari master product yang sudah ada.

**Authentication**: Required (Admin atau SuperAdmin only)

**Request Body**:
```json
{
  "master_product_id": 1,
  "measurement_points": [
    {
      "setup": {
        "name": "INSIDE DIAMETER",
        "name_id": "inside_diameter",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "PER_SAMPLE",
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 14,
        "tolerance_minus": 0.5,
        "tolerance_plus": 1
      }
    }
  ],
  "measurement_groups": [
    {
      "order": 1,
      "measurement_items": ["inside_diameter"]
    }
  ]
}
```

**Request Body Parameters**:
- `master_product_id` (required, integer): ID master product yang akan digunakan sebagai dasar
- `measurement_points` (required, array): Array measurement points (sama seperti endpoint create product biasa)
- `measurement_groups` (optional, array): Array measurement groups (sama seperti endpoint create product biasa)

**Response**:
```json
{
  "http_code": 201,
  "message": "Product berhasil dibuat dari master product",
  "error_id": null,
  "data": {
    "product_id": "PRD-97RPJRUY",
    "basic_info": {
      "product_category_id": 1,
      "product_name": "COT",
      "product_spec_name": "COT B 5",
      "ref_spec_number": null,
      "nom_size_vo": null,
      "article_code": null,
      "no_document": null,
      "no_doc_reference": null,
      "color": "B",
      "size": "5"
    },
    "measurement_points": [...],
    "measurement_groups": [...],
    "product_category": {
      "id": 1,
      "name": "Tube Test"
    }
  }
}
```

**Notes**:
- `basic_info` akan diambil dari master product yang dipilih
- User hanya perlu mengirim `measurement_points` dan `measurement_groups`
- Semua validasi measurement points tetap berlaku (sama seperti endpoint create product biasa)
- Jika product dengan `product_spec_name` yang sama sudah pernah dibuat, akan return error `PRODUCT_ALREADY_EXISTS`

**Error Responses**:
- `422`: Validation error (validation measurement points, formula, dll)
- `400`: Product dengan spesifikasi tersebut sudah pernah dibuat
- `404`: Master product tidak ditemukan

**Contoh Payload Lengkap**: Lihat file `MASTER_PRODUCT_PAYLOAD_EXAMPLES.md` untuk berbagai contoh payload yang lebih detail.

---

## Master Data Product Names

### Tube Test
Master products untuk kategori **Tube Test**:

1. COT B 5
2. COT B 7
3. COT B 10
4. COT B 13
5. COT B 15
6. COT B 19
7. COT B 22
8. COT B 25
9. COT FR 5
10. COT FR 7
11. COT FR 10
12. COT FR 13
13. COT FR 15
14. COT FR 19
15. COT FR 22
16. COT FR 25
17. COT RF R03
18. COT RF 25PL
19. CORRU Y-17
20. CORRU 09PL
21. HCOT DL 5
22. HCOT DL 7
23. HCOT DL 10
24. HCOT DL 13
25. HCOT DL 15
26. HCOT DL 19
27. HCOT DL 22
28. HCOT DL 25
29. VO B 3X4
30. VO B 4X5
31. VO B 5X6
32. VO B 6X7
33. VO B 6X8
34. VO B 7X8
35. VO B 8X9
36. VO B 8X10
37. VO B 9X10
38. VO B 9X11
39. VO B 10X11
40. VO B 10X12
41. VO B 11X12
42. VO B 12X13
43. VO B 12X14
44. VO B 13X14
45. VO B 13X15
46. VO B 14X15
47. VO B 14X16
48. VO B 16X17
49. VO B 16X18
50. VO B 18X19
51. VO B 18X20
52. VO B 32X33
53. VO B 32X34
54. VO B 19X20
55. VO B 20X21
56. VO B 20X22
57. VO B 22X23
58. VO B 22X24
59. VO B 24X25
60. VO B 24X26
61. VO B 26X27
62. VO B 26X28
63. VO B 28X29
64. VO B 28X30
65. VO B 30X31
66. VO B 30X32
67. VO HR 4X5
68. VO HR 6X7
69. VO HR 6X8
70. VO HR 7X8
71. VO HR 8X9
72. VO HR 8X10
73. VO HR 9X10
74. VO HR 10X11
75. VO HR 10X12
76. VO HR 11X12
77. VO HR 12X13
78. VO HR 12X14
79. VO HR 14X15
80. VO HR 14X16
81. VO HR 16X17
82. VO HR 16X18
83. VO HR 20X22
84. VO HR 22X24
85. VO HR 24X26
86. VO HR 26X28
87. VO HR 30X31
88. VO HR 32X34

### Wire Test Reguler
Master products untuk kategori **Wire Test Reguler**:

1. AVSSH 0.3F
2. AVSSH 0.5F
3. AVSSH 0.75F
4. AVSSH 1.25F
5. IVSSH 0.3F
6. IVSSH 0.5F
7. IVSSH 0.75F
8. IVSSH 1.25F
9. CIVUS 0.35
10. CIVUS 0.5 W
11. CIVUS 0.75
12. CIVUS 1.0
13. CIVUS 1.25
14. AC CIVUS 0.35
15. AC CIVUS 0.5
16. CAVS 0.3
17. CAVS 0.5
18. CAVS 0.85
19. CAVS 1.25
20. AC CAVS 0.3
21. AC CAVS 0.5
22. AVSS 2F
23. AVS 0.5
24. AVS 0.85
25. AVS 1.25
26. AVS 3
27. AVS 2
28. AVS 5
29. AV 8
30. AVSS 0.3F
31. AVSS 0.5F
32. AVSS 0.75F

### Shield Wire Test
Master products untuk kategori **Shield Wire Test**:

1. AVSSCS 0.3FX2
2. AVSSCS 0.3FX5
3. AVSSCS 0.3FX6
4. AVSSCS 0.5FX2
5. AVSSCS 0.5FX3
6. AVSSCS 0.5FX6
7. AVSSCS 0.75FX2
8. AVSSCS-S 0.3FX1
9. AVSSCS-S 0.3FX2
10. AVSSCS-S 0.3FX4
11. AVSSCS-S 0.5FX1
12. AVSSCS-S 0.5FX2
13. AVSSCS-S 0.5FX3
14. AVSSCS-S 0.5FX4
15. AVSSCS-S 1.25FX2
16. AVSSHCS 0.3FX2
17. AVSSHCS 0.5FX2
18. AVSSHCS 0.5FX6
19. AVSSHCS 1.25FX2
20. CAVSAS 0.3X3
21. CAVSAS-S 0.3X3
22. CAVSAS-S 0.3X4
23. CAVSAS-S 0.3X5
24. CAVSAS-S 0.5X1
25. CAVSAS-S 0.5X2
26. CAVSAS-S 0.5X3
27. CIVUSAS-S 0.35X1
28. CIVUSAS-S 0.35X3
29. CIVUSAS-S 0.35X4
30. CIVUSAS-S 0.5X3
31. CIVUSAS-S 0.5X4
32. CIVUSAS-S 0.75X2

---

## Workflow

### Flow "Add From Existing Product"

1. **User memilih method "Add From Existing Product"**
   - User akan diarahkan ke halaman untuk memilih master product

2. **User melihat list master products yang tersedia**
   - Frontend memanggil `GET /api/v1/products/master-products`
   - Dapat filter berdasarkan kategori dan search query
   - Hanya menampilkan master products yang **belum pernah dibuat** menjadi product

3. **User memilih master product**
   - User klik pada master product yang diinginkan
   - Frontend mendapatkan `master_product_id`

4. **User mengisi measurement points dan groups**
   - Frontend menampilkan form untuk mengisi `measurement_points` dan `measurement_groups`
   - `basic_info` sudah terisi otomatis dari master product

5. **User submit untuk create product**
   - Frontend memanggil `POST /api/v1/products/from-existing`
   - Mengirim `master_product_id`, `measurement_points`, dan `measurement_groups`
   - Backend akan create product dengan `basic_info` dari master product

6. **Product berhasil dibuat**
   - Product akan muncul di list products
   - Master product yang sudah dibuat tidak akan muncul lagi di list master products

---

## Database Schema

### Table: `master_products`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `product_category_id` | bigint | Foreign key ke `product_categories.id` |
| `product_name` | string | Nama produk (e.g., "COT", "AVSSH") |
| `product_spec_name` | string | Spesifikasi produk (alias) - unique |
| `ref_spec_number` | string (nullable) | Ref spec number |
| `nom_size_vo` | string (nullable) | Nom size VO |
| `article_code` | string (nullable) | Article code |
| `no_document` | string (nullable) | No document |
| `no_doc_reference` | string (nullable) | No doc reference |
| `color` | string (nullable) | Color |
| `size` | string (nullable) | Size |
| `created_at` | timestamp | Created timestamp |
| `updated_at` | timestamp | Updated timestamp |

**Indexes**:
- `product_category_id`
- `product_spec_name`
- `product_name`, `color`, `size` (composite)

---

## Seeding Master Products

Untuk populate master products ke database, jalankan:

```bash
php artisan db:seed --class=MasterProductSeeder
```

Atau jika ingin run semua seeders:

```bash
php artisan migrate:fresh --seed
```

Pastikan `ProductCategorySeeder` sudah di-run terlebih dahulu sebelum `MasterProductSeeder`.

