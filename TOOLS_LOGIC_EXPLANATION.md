# Tools/Alat Ukur - Logic & Flow Explanation

## 📖 Konsep Dasar

### Apa itu Tools/Alat Ukur?
Tools adalah perangkat fisik yang digunakan untuk melakukan pengukuran pada product. Contoh:
- Digital Caliper (untuk ukur diameter, ketebalan)
- Micrometer (untuk ukur dimensi kecil)
- Optical Sensor (sensor optik untuk deteksi)
- Laser Scanner (untuk scanning 3D)

---

## 🔑 Konsep Penting: Model vs IMEI

### Tool Model
- **Model** adalah **tipe/jenis** alat ukur
- Contoh: "Mitutoyo CD-6", "Keyence LK-G5001"
- 1 model bisa punya **banyak unit fisik** yang sama

### IMEI (International Mobile Equipment Identity)
- **IMEI** adalah **serial number unik** untuk setiap unit fisik
- Setiap alat ukur punya IMEI sendiri (unique)
- Walaupun model sama, IMEI harus berbeda

### Contoh Konkret:
```
Lab punya 3 Digital Caliper dengan model yang sama:

Model: "Mitutoyo CD-6"  (ini tipe alatnya)
├── Unit 1: IMEI "MIT-CD6-001" (alat fisik #1)
├── Unit 2: IMEI "MIT-CD6-002" (alat fisik #2)
└── Unit 3: IMEI "MIT-CD6-003" (alat fisik #3)

Semua 3 unit ini:
- Model sama: "Mitutoyo CD-6"
- IMEI berbeda (karena ini unit fisik yang berbeda)
- Bisa punya tanggal kalibrasi berbeda
- Bisa punya status berbeda (active/inactive)
```

---

## 🎯 Flow Lengkap: Dari Create Product → Measurement

### **PHASE 1: Setup Tools** (dilakukan Admin)

Admin menambahkan tools ke sistem:

```
Tool 1:
├── tool_name: "Digital Caliper Lab 1"
├── tool_model: "Mitutoyo CD-6"
├── tool_type: MECHANICAL
├── imei: "MIT-CD6-001"
├── last_calibration: 2025-01-15
├── next_calibration: 2026-01-15 (auto-calculated)
└── status: ACTIVE

Tool 2:
├── tool_name: "Digital Caliper Lab 2"
├── tool_model: "Mitutoyo CD-6"  (model sama!)
├── tool_type: MECHANICAL
├── imei: "MIT-CD6-002"  (IMEI beda!)
├── last_calibration: 2025-02-20
├── next_calibration: 2026-02-20
└── status: ACTIVE

Tool 3:
├── tool_name: "Digital Caliper Lab 3"
├── tool_model: "Mitutoyo CD-6"  (model sama!)
├── tool_type: MECHANICAL
├── imei: "MIT-CD6-003"  (IMEI beda!)
├── last_calibration: 2024-10-01
├── next_calibration: 2025-10-01
└── status: INACTIVE  (lagi maintenance)
```

---

### **PHASE 2: Create Product** (User pilih Model)

Saat user membuat product, dia pilih **measurement source** = TOOL:

#### Step 1: Get Tool Models
```http
GET /api/v1/tools/models
```

**Response:**
```json
{
  "data": [
    {
      "tool_model": "Mitutoyo CD-6",
      "tool_type": "MECHANICAL",
      "imei_count": 2  // Hanya 2 karena yang 1 INACTIVE
    }
  ]
}
```

**Perhatikan:**
- ✅ Hanya **1 model** yang muncul: "Mitutoyo CD-6"
- ✅ Walaupun ada 3 unit, yang muncul **hanya ACTIVE** (2 unit)
- ✅ Unit INACTIVE tidak dihitung

#### Step 2: User Pilih Model & Create Product
```http
POST /api/v1/products

{
  "basic_info": {
    "product_name": "Product A"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "Thickness Measurement",
        "name_id": "THICKNESS_A",
        "sample_amount": 10,
        "source": "TOOL",  // ← source dari tool
        "source_tool_model": "Mitutoyo CD-6",  // ← simpan MODEL
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      }
    }
  ]
}
```

**Yang Disimpan di Product:**
```json
{
  "setup": {
    "source": "TOOL",
    "source_tool_model": "Mitutoyo CD-6"  // Hanya model, BUKAN IMEI!
  }
}
```

**Kenapa hanya simpan Model?**
- Product adalah **template/blueprint**
- Saat measurement nanti, user bisa **pilih unit mana** (IMEI mana) yang dipakai
- Fleksibel: bisa pakai MIT-CD6-001 atau MIT-CD6-002

---

### **PHASE 3: Melakukan Measurement** (User pilih IMEI)

Saat user mau melakukan pengukuran untuk product tersebut:

#### Step 1: System Baca Product Config
```
Product config:
└── measurement_points[0]
    └── setup
        ├── source: "TOOL"
        └── source_tool_model: "Mitutoyo CD-6"
```

#### Step 2: Get Available Tools by Model
```http
GET /api/v1/tools/by-model?tool_model=Mitutoyo%20CD-6
```

**Response:**
```json
{
  "data": {
    "tool_model": "Mitutoyo CD-6",
    "tools": [
      {
        "id": 1,
        "tool_name": "Digital Caliper Lab 1",
        "imei": "MIT-CD6-001",
        "last_calibration": "2025-01-15",
        "next_calibration": "2026-01-15"
      },
      {
        "id": 2,
        "tool_name": "Digital Caliper Lab 2",
        "imei": "MIT-CD6-002",
        "last_calibration": "2025-02-20",
        "next_calibration": "2026-02-20"
      }
      // Tool 3 tidak muncul karena INACTIVE
    ]
  }
}
```

#### Step 3: Frontend Tampilkan Dropdown IMEI
```
User melihat dropdown:
┌─────────────────────────────────────────┐
│ Pilih Alat Ukur:                        │
├─────────────────────────────────────────┤
│ ○ MIT-CD6-001 (Lab 1) - Next: 2026-01  │
│ ● MIT-CD6-002 (Lab 2) - Next: 2026-02  │ ← User pilih ini
└─────────────────────────────────────────┘
```

**User pilih IMEI "MIT-CD6-002"** (tool_id = 2)

#### Step 4: Save Measurement dengan tool_id
```http
POST /api/v1/measurements

{
  "measurement_id": 123,
  "tool_id": 2,  // ← ID dari tool yang dipilih (MIT-CD6-002)
  "thickness_type": "THICKNESS_A",
  "value": 2.55,
  "sequence": 1
}
```

**Yang Tersimpan di measurement_items:**
```sql
INSERT INTO measurement_items (
  measurement_id,
  tool_id,  -- 2 (reference ke tools table)
  thickness_type,
  value,
  sequence
) VALUES (123, 2, 'THICKNESS_A', 2.55, 1);
```

---

## 🔄 Kenapa Pakai 2 Step (Model → IMEI)?

### Alasan Design:

#### 1. **Product = Template/Blueprint**
```
Product adalah "resep" pengukuran, bukan eksekusi aktual.
Jadi cukup simpan "pakai alat model apa", bukan "pakai alat unit mana".
```

#### 2. **Fleksibilitas saat Measurement**
```
Hari Senin: Pakai MIT-CD6-001 (karena Lab 1 kosong)
Hari Selasa: Pakai MIT-CD6-002 (karena MIT-001 sedang dipakai)

Product tetap sama, tapi unit yang dipakai bisa berbeda.
```

#### 3. **Tracking & History**
```
Dengan simpan tool_id di measurement_items, kita tahu:
- Measurement ini pakai alat unit mana
- Tanggal kalibrasi alat saat itu
- Bisa trace jika ada masalah kualitas
```

---

## 🎯 Contoh Real Case

### Skenario: Pabrik Mengukur Ketebalan Tube

#### Setup Awal (Admin):
```
Ada 3 Digital Caliper di lab:
1. MIT-CD6-001 - Kalibrasi: 2025-01-15 (ACTIVE)
2. MIT-CD6-002 - Kalibrasi: 2025-03-01 (ACTIVE)
3. MIT-CD6-003 - Kalibrasi: 2024-06-01 (INACTIVE - expired)
```

#### Create Product:
```
Product: "Tube VO 1.7mm"
└── Measurement Point: "Ketebalan"
    └── Source: TOOL
        └── Model: "Mitutoyo CD-6"
```

#### Pengukuran Hari 1 (Shift Pagi):
```
Operator: Budi
1. Buka product "Tube VO 1.7mm"
2. Lihat dropdown IMEI:
   - MIT-CD6-001 ✓
   - MIT-CD6-002 ✓
3. Pilih: MIT-CD6-001 (karena paling dekat)
4. Ukur 10 sample
5. Save → measurement_items.tool_id = 1
```

#### Pengukuran Hari 1 (Shift Sore):
```
Operator: Ani
1. Buka product yang sama
2. Lihat dropdown IMEI yang sama
3. Pilih: MIT-CD6-002 (MIT-001 sedang dipakai Budi)
4. Ukur 10 sample
5. Save → measurement_items.tool_id = 2
```

#### Pengukuran Hari 2:
```
Operator: Citra
1. Buka product yang sama
2. Lihat dropdown hanya 1 IMEI:
   - MIT-CD6-002 ✓
   (MIT-001 tidak muncul karena admin set INACTIVE untuk maintenance)
3. Pilih: MIT-CD6-002
4. Ukur 10 sample
5. Save → measurement_items.tool_id = 2
```

---

## 📊 Database Structure & Relations

### Tables:

#### 1. `tools` table
```sql
id | tool_name         | tool_model      | imei          | status
1  | Caliper Lab 1     | Mitutoyo CD-6   | MIT-CD6-001   | ACTIVE
2  | Caliper Lab 2     | Mitutoyo CD-6   | MIT-CD6-002   | ACTIVE
3  | Caliper Lab 3     | Mitutoyo CD-6   | MIT-CD6-003   | INACTIVE
```

#### 2. `products` table
```sql
id | measurement_points (JSON)
1  | [
     {
       "setup": {
         "source": "TOOL",
         "source_tool_model": "Mitutoyo CD-6"  // ← Simpan MODEL
       }
     }
   ]
```

#### 3. `measurement_items` table
```sql
id | measurement_id | tool_id | value
1  | 123            | 1       | 2.55  // ← Pakai tool_id=1 (MIT-001)
2  | 123            | 1       | 2.56
3  | 124            | 2       | 2.54  // ← Pakai tool_id=2 (MIT-002)
4  | 124            | 2       | 2.57
```

---

## 🔧 Auto-Calculate Next Calibration

### Logic:

```php
// Saat CREATE tool
if (last_calibration) {
    next_calibration = last_calibration + 1 year
}

// Saat UPDATE tool
if (last_calibration berubah) {
    next_calibration = last_calibration + 1 year
}
```

### Contoh:

#### Create:
```json
POST /api/v1/tools
{
  "last_calibration": "2025-01-15"
}

Response:
{
  "last_calibration": "2025-01-15",
  "next_calibration": "2026-01-15"  // ← Auto calculated!
}
```

#### Update:
```json
PUT /api/v1/tools/1
{
  "last_calibration": "2025-06-20"
}

Response:
{
  "last_calibration": "2025-06-20",
  "next_calibration": "2026-06-20"  // ← Auto updated!
}
```

---

## 🚦 Status Management

### ACTIVE vs INACTIVE

#### ACTIVE:
- ✅ Muncul di `/tools/models`
- ✅ Muncul di `/tools/by-model`
- ✅ Bisa dipilih saat create product
- ✅ Bisa dipilih saat measurement

#### INACTIVE:
- ❌ Tidak muncul di `/tools/models`
- ❌ Tidak muncul di `/tools/by-model`
- ❌ Tidak bisa dipilih saat create product
- ❌ Tidak bisa dipilih saat measurement
- ✅ Data tetap ada di database
- ✅ History measurement tetap tersimpan

### Use Case INACTIVE:

1. **Maintenance/Kalibrasi:**
   ```
   Tool sedang dikirim untuk kalibrasi → set INACTIVE
   Setelah kalibrasi selesai → update last_calibration & set ACTIVE
   ```

2. **Rusak:**
   ```
   Tool rusak → set INACTIVE
   Tool diperbaiki → set ACTIVE
   ```

3. **Expired Calibration:**
   ```
   next_calibration sudah lewat → set INACTIVE
   Setelah kalibrasi ulang → update dates & set ACTIVE
   ```

---

## 🔍 Query Logic

### Get Tool Models (untuk dropdown di create product)

```php
Tool::active()  // Hanya ACTIVE
    ->select('tool_model', 'tool_type')
    ->distinct()  // Unique models
    ->get()
    ->groupBy('tool_model')  // Group by model
```

**Result:**
```
Mitutoyo CD-6 → 2 units (MIT-001, MIT-002)
Keyence LK-G5001 → 1 unit (KEY-001)
// MIT-003 tidak muncul karena INACTIVE
```

### Get Tools by Model (untuk dropdown IMEI di measurement)

```php
Tool::active()  // Hanya ACTIVE
    ->where('tool_model', $model)
    ->orderBy('imei')
    ->get()
```

**Result untuk "Mitutoyo CD-6":**
```
1. MIT-CD6-001 (Lab 1)
2. MIT-CD6-002 (Lab 2)
// MIT-CD6-003 tidak muncul karena INACTIVE
```

---

## 🎓 Summary

### Key Points:

1. **Model vs IMEI:**
   - Model = Tipe alat (1 model bisa banyak unit)
   - IMEI = Serial number unik per unit

2. **2-Step Selection:**
   - Create Product → Pilih **Model**
   - Measurement → Pilih **IMEI** (unit spesifik)

3. **Status Control:**
   - ACTIVE → Bisa dipilih
   - INACTIVE → Tersimpan tapi tidak bisa dipilih

4. **Auto-Calibration:**
   - next_calibration = last_calibration + 1 year

5. **Flexibility:**
   - Product = template (simpan model)
   - Measurement = eksekusi (simpan tool_id/IMEI)

### Flow Singkat:
```
1. Admin add tools dengan IMEI unique
2. User create product → pilih MODEL
3. User measurement → pilih IMEI dari model tersebut
4. System save tool_id di measurement_items
5. Bisa trace: measurement ini pakai alat unit mana
```

---

## 💡 Benefits Design Ini

1. **Fleksibel:** Product tidak terikat ke 1 unit alat spesifik
2. **Traceable:** Tahu measurement pakai alat unit mana
3. **Maintainable:** Bisa set INACTIVE tanpa hapus data
4. **Scalable:** Bisa tambah unit baru tanpa ubah product
5. **Audit-able:** History lengkap per measurement

---

Semoga penjelasan ini memperjelas logic fitur Tools! 🎯

