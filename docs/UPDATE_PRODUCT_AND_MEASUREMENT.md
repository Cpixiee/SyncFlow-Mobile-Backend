# Update Product & Product Measurement (SKIP_CHECK)

Tanggal: 2026-01-20  
Scope: API v1 — Product & Product Measurement

Dokumen ini menjelaskan:
- Perbaikan response **GET** detail product measurement untuk item **SKIP_CHECK**
- Penyamaan behavior **PUT update product** agar payload-nya konsisten dengan **create product**
- Cara pakai + langkah testing manual (Postman/Insomnia/cURL)

---

## 1) GET `/api/v1/product-measurement/{msr_id}` — SKIP_CHECK `status` harus `null`

### Latar belakang
Item measurement dengan `evaluation_type = "SKIP_CHECK"` artinya **tidak dilakukan evaluasi OK/NG**. Karena tidak ada evaluasi, statusnya **bukan false/true**, tapi **`null`**.

### Problem sebelumnya
Pada beberapa data (legacy), field `status` di level measurement item masih muncul `false`, sehingga terbaca seperti NG—padahal item tersebut SKIP_CHECK.

### Behavior baru (expected)
Untuk setiap measurement item yang pada konfigurasi product-nya bertipe:
- `evaluation_type: "SKIP_CHECK"`

maka pada response **GET product measurement detail**:
- `measurement_results[].status` **selalu `null`**

Catatan:
- Ini berlaku **meskipun** data lama tersimpan `false` di database.
- `samples[]` tetap dikembalikan seperti biasa (nilai ukur tetap ada), hanya **item-level status** yang dipastikan `null`.

### Dampak ke consumer (FE/mobile)
- Jangan interpret `null` sebagai error.
- `null` pada SKIP_CHECK berarti: **“skip evaluasi”** (bukan TODO dan bukan NG).

---

## 2) PUT `/api/v1/products/{product_id}` — Update product bisa pakai payload “create product”

### Latar belakang
Product menyimpan definisi `measurement_points` (setup, variables, formulas, evaluation, rules). Tim butuh capability untuk **edit product** sepenuhnya, termasuk:
- measurement points
- variables (MANUAL/FIXED/FORMULA)
- pre-processing formulas
- joint formulas
- derived source
- item SKIP_CHECK / JOINT / PER_SAMPLE

### Problem sebelumnya
Ada perbedaan rule validasi antara create vs update yang menyebabkan payload yang sama:
- Lolos saat create
- Error saat update

### Behavior baru (expected)
Endpoint update product sekarang menerima payload `measurement_points` yang konsisten dengan create product, termasuk:
- `evaluation_type`: `PER_SAMPLE`, `JOINT`, `SKIP_CHECK`
- `evaluation_setting`:
  - Boleh kosong/nullable untuk `SKIP_CHECK`
  - Harus sesuai untuk `PER_SAMPLE` dan `JOINT`
- `rule_evaluation_setting`:
  - Umumnya wajib untuk QUANTITATIVE
  - Untuk `SKIP_CHECK` boleh tidak ada / null
- `variables` dan `pre_processing_formulas` dan `joint_setting.formulas` dapat ikut diupdate
- `name_id` seperti `scrape_1`, `scrape_2`, dll dapat ikut diupdate sesuai rules sistem

Catatan penting:
- Update `measurement_points` akan mengganti definisi measurement point product sesuai payload yang dikirim.
- Jika payload mengubah struktur item yang dipakai oleh formula/derived, pastikan dependencies tetap valid.

---

## 3) Cara pakai (ringkas)

### A. Cek konfigurasi product
1. Panggil **GET** `/api/v1/products/{product_id}`
2. Pastikan measurement point yang dimaksud memiliki:
   - `setup.name_id` sesuai target (contoh: `diameter_wire_x`)
   - `evaluation_type` sesuai (contoh: `SKIP_CHECK`)

### B. Cek detail measurement (validasi fix SKIP_CHECK)
1. Panggil **GET** `/api/v1/product-measurement/{msr_id}`
2. Cari item di `measurement_results[]` yang `measurement_item_name_id`-nya sama (contoh: `diameter_wire_x`)
3. Expected:
   - `status` di item tersebut adalah **`null`**

### C. Update product (edit measurement points)
1. Siapkan payload update product dengan struktur yang sama seperti create:
   - `basic_info`
   - `measurement_points` lengkap (setup, evaluation_type, evaluation_setting jika perlu, rule_evaluation_setting jika perlu, variables, formulas, dll)
2. Panggil **PUT** `/api/v1/products/{product_id}`
3. Expected:
   - Response sukses dan `measurement_points` tersimpan sesuai payload.

---

## 4) Contoh payload JSON untuk PUT update product

Catatan:
- Endpoint: **PUT** `/api/v1/products/{product_id}`
- Payload ini untuk **update product config** (bukan product measurement).
- `measurement_points` boleh berisi SKIP_CHECK (tanpa `evaluation_setting` dan tanpa `rule_evaluation_setting`).

### A. Template minimal (struktur dasar)

```json
{
  "basic_info": {
    "product_category_id": 2,
    "product_name": "CIVUS",
    "product_spec_name": "CIVUS 0.75 G",
    "ref_spec_number": null,
    "nom_size_vo": null,
    "article_code": "1801R704060",
    "no_document": "QAA/4-2033/2012",
    "no_doc_reference": "YPES-11-01-254",
    "color": "G",
    "size": "0.75"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "JUMLAH CORE",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "jumlah_core",
        "sample_amount": 5
      },
      "group_name": null,
      "group_order": 1,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "core",
        "value": 11,
        "tolerance_plus": 0,
        "tolerance_minus": 0
      }
    },
    {
      "setup": {
        "name": "DIAMETER WIRE X",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "diameter_wire_x",
        "sample_amount": 5
      },
      "group_name": null,
      "group_order": 2,
      "evaluation_type": "SKIP_CHECK"
    }
  ]
}
```

### B. Contoh payload lengkap (sesuai kasus CIVUS yang dipakai untuk create & update)

```json
{
  "basic_info": {
    "product_category_id": 2,
    "product_name": "CIVUS",
    "product_spec_name": "CIVUS 0.75 G",
    "ref_spec_number": null,
    "nom_size_vo": null,
    "article_code": "1801R704060",
    "no_document": "QAA/4-2033/2012",
    "no_doc_reference": "YPES-11-01-254",
    "color": "G",
    "size": "0.75"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "JUMLAH CORE",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "jumlah_core",
        "sample_amount": 5
      },
      "group_name": null,
      "group_order": 1,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "core",
        "value": 11,
        "tolerance_plus": 0,
        "tolerance_minus": 0
      }
    },
    {
      "setup": {
        "name": "DIAMETER STRAND",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "diameter_strand",
        "sample_amount": 5
      },
      "group_name": null,
      "group_order": 2,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 1.02,
        "tolerance_plus": 0.01,
        "tolerance_minus": 0.01
      }
    },
    {
      "setup": {
        "name": "TEBAL MIN INSULT",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "tebal_min_insult",
        "sample_amount": 5
      },
      "group_name": null,
      "group_order": 3,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0.16
      }
    },
    {
      "setup": {
        "name": "RATA RATA INSULT",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "rata_rata_insult",
        "sample_amount": 5
      },
      "group_name": null,
      "group_order": 4,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "mm",
        "value": 0.2
      }
    },
    {
      "setup": {
        "name": "LEBAR STRIPPING",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "lebar_stripping",
        "sample_amount": 5
      },
      "group_name": null,
      "group_order": 5,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 0.5,
        "tolerance_plus": 0.4,
        "tolerance_minus": 0.4
      }
    },
    {
      "setup": {
        "name": "DIAMETER WIRE X",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "diameter_wire_x",
        "sample_amount": 5
      },
      "group_name": null,
      "group_order": 6,
      "evaluation_type": "SKIP_CHECK"
    },
    {
      "setup": {
        "name": "DIAMETER WIRE Y",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "diameter_wire_y",
        "sample_amount": 5
      },
      "group_name": null,
      "group_order": 7,
      "evaluation_type": "SKIP_CHECK"
    },
    {
      "setup": {
        "name": "rata_rata_diameter",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "name_id": "rata_rata_diameter",
        "sample_amount": 0
      },
      "group_name": null,
      "group_order": 8,
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "final",
              "formula": "=(avg(diameter_wire_x)+avg(diameter_wire_y))/2",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 1.46,
        "tolerance_plus": 0.12,
        "tolerance_minus": 0.06
      }
    },
    {
      "setup": {
        "name": "BERAT",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "berat",
        "sample_amount": 5
      },
      "group_name": null,
      "group_order": 9,
      "evaluation_type": "SKIP_CHECK"
    },
    {
      "setup": {
        "name": "TENSILE FORCE",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "DERIVED",
        "name_id": "tensile_force",
        "sample_amount": 5,
        "source_derived_name_id": "berat"
      },
      "variables": [
        {
          "name": "cross_section",
          "type": "FORMULA",
          "formula": "=((rata_rata_diameter.final*rata_rata_diameter.final)-(avg(diameter_strand)*avg(diameter_strand)))*0.7854",
          "is_show": true
        }
      ],
      "group_name": null,
      "group_order": 10,
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "rata_rata_tensile_force",
              "formula": "=avg(tensile_force_normalized)",
              "is_final_value": true
            }
          ]
        }
      },
      "pre_processing_formulas": [
        {
          "name": "tensile_force_normalized",
          "formula": "=(single_value/cross_section)*9.80665",
          "is_show": true
        }
      ],
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Mpa",
        "value": 15.7
      }
    },
    {
      "setup": {
        "name": "ELONGATION VALUE",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "elongation_value",
        "sample_amount": 5
      },
      "group_name": null,
      "group_order": 11,
      "evaluation_type": "SKIP_CHECK"
    },
    {
      "setup": {
        "name": "ELONGATION PERCENTAGE",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "DERIVED",
        "name_id": "elongation_percentage",
        "sample_amount": 5,
        "source_derived_name_id": "elongation_value"
      },
      "variables": [
        {
          "name": "cross_section",
          "type": "FORMULA",
          "formula": "=((rata_rata_diameter.final*rata_rata_diameter.final)-(avg(diameter_strand)*avg(diameter_strand)))*0.7854",
          "is_show": true
        }
      ],
      "group_name": null,
      "group_order": 12,
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "elongation_average",
              "formula": "=avg(elongation_normalized)",
              "is_final_value": true
            }
          ]
        }
      },
      "pre_processing_formulas": [
        {
          "name": "elongation_normalized",
          "formula": "=((single_value-50)/50)",
          "is_show": true
        }
      ],
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "%",
        "value": 125
      }
    },
    {
      "setup": {
        "name": "DIELETRIC TEST IN WATER",
        "nature": "QUALITATIVE",
        "name_id": "dieletric_test_in_water",
        "sample_amount": 1
      },
      "group_name": null,
      "group_order": 13,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        },
        "qualitative_setting": {
          "label": "TAHAN SELAMA 30 MENIT DENGAN 1Kv"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Boolean",
        "value": 1
      }
    },
    {
      "setup": {
        "name": "HEAT DEFORMATION",
        "nature": "QUALITATIVE",
        "name_id": "heat_deformation",
        "sample_amount": 1
      },
      "group_name": null,
      "group_order": 14,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        },
        "qualitative_setting": {
          "label": "TAHAN SELAMA 1 MENIT DENGAN 1kV"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Boolean",
        "value": 1
      }
    },
    {
      "setup": {
        "name": "TIGHTNESS STRENGHT",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "tightness_strenght",
        "sample_amount": 3
      },
      "group_name": null,
      "group_order": 15,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "N",
        "value": 20,
        "tolerance_plus": 30,
        "tolerance_minus": 15
      }
    },
    {
      "setup": {
        "name": "LOW TEMPERATURE WINDING",
        "nature": "QUALITATIVE",
        "name_id": "low_temperature_winding",
        "sample_amount": 1
      },
      "group_name": null,
      "group_order": 16,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        },
        "qualitative_setting": {
          "label": "TIDAK ADA GORESAN, PECAH, SPARK"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Boolean",
        "value": 1
      }
    },
    {
      "setup": {
        "name": "IMPACT",
        "nature": "QUALITATIVE",
        "name_id": "impact",
        "sample_amount": 1
      },
      "variables": [],
      "group_name": null,
      "group_order": 17,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        },
        "qualitative_setting": {
          "label": "TAHAN SELAMA 1 MENIT DENGAN 1kV"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Boolean",
        "value": 1
      }
    },
    {
      "setup": {
        "name": "SCRAPE 1",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "scrape_1",
        "sample_amount": 3
      },
      "group_name": null,
      "group_order": 18,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "count",
        "value": 150
      }
    },
    {
      "setup": {
        "name": "SCRAPE 2",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "scrape_2",
        "sample_amount": 3
      },
      "group_name": null,
      "group_order": 19,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "count",
        "value": 150
      }
    },
    {
      "setup": {
        "name": "LONG TERM AGING",
        "nature": "QUALITATIVE",
        "name_id": "long_term_aging",
        "sample_amount": 1
      },
      "group_name": null,
      "group_order": 20,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        },
        "qualitative_setting": {
          "label": "TIDAK ADA GORESAN, PECAH, SPARK"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Boolean",
        "value": 1
      }
    },
    {
      "setup": {
        "name": "SHORT TERM AGING",
        "nature": "QUALITATIVE",
        "name_id": "short_term_aging",
        "sample_amount": 1
      },
      "group_name": null,
      "group_order": 21,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        },
        "qualitative_setting": {
          "label": "TIDAK ADA GORESAN, PECAH, SPARK"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Boolean",
        "value": 1
      }
    },
    {
      "setup": {
        "name": "THERMAL OVERLOAD",
        "nature": "QUALITATIVE",
        "name_id": "thermal_overload",
        "sample_amount": 1
      },
      "group_name": null,
      "group_order": 22,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        },
        "qualitative_setting": {
          "label": "TIDAK ADA GORESAN, PECAH, SPARK"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Boolean",
        "value": 1
      }
    },
    {
      "setup": {
        "name": "SHRINKAGE TEST",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "shrinkage_test",
        "sample_amount": 3
      },
      "group_name": null,
      "group_order": 23,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": false,
          "pre_processing_formula_name": "selisih_shrinkage_test"
        }
      },
      "pre_processing_formulas": [
        {
          "name": "selisih_shrinkage_test",
          "formula": "=before-after",
          "is_show": true
        }
      ],
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "mm",
        "value": 2
      }
    },
    {
      "setup": {
        "name": "FLAME TEST",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "flame_test",
        "sample_amount": 5
      },
      "group_name": null,
      "group_order": 24,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "dtk",
        "value": 70
      }
    },
    {
      "setup": {
        "name": "CONDUCTOR RESISTANCE",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "conductor_resistance",
        "sample_amount": 3
      },
      "variables": [
        {
          "name": "suhu",
          "type": "MANUAL",
          "is_show": true
        },
        {
          "name": "suhu_variable",
          "type": "MANUAL",
          "is_show": true
        }
      ],
      "group_name": null,
      "group_order": 25,
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "rata_rata_r20",
              "formula": "=avg(r20)*1000",
              "is_final_value": true
            }
          ]
        }
      },
      "pre_processing_formulas": [
        {
          "name": "r20",
          "formula": "=single_value*suhu_variable/1000",
          "is_show": false
        }
      ],
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "q/m",
        "value": 24.7
      }
    },
    {
      "setup": {
        "name": "CONDUCTOR ELONGATION",
        "type": "SINGLE",
        "nature": "QUANTITATIVE",
        "source": "MANUAL",
        "name_id": "conductor_elongation",
        "sample_amount": 3
      },
      "group_name": null,
      "group_order": 26,
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "per_sample_setting": {
          "is_raw_data": true
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "%",
        "value": 20
      }
    }
  ]
}
```

---

## 5) Test payload (contoh skenario)

Kamu bisa pakai payload yang sama seperti saat create product, lalu kirim ke PUT update product.

### Skenario minimal yang wajib lolos
- Ada beberapa item `PER_SAMPLE` quantitative + `rule_evaluation_setting`
- Ada beberapa item `SKIP_CHECK` tanpa `rule_evaluation_setting` (atau boleh null)
- Ada item `JOINT` dengan `evaluation_setting.joint_setting.formulas`
- Ada item `DERIVED` dengan `source_derived_name_id`

### Checklist hasil test
- **PUT update product**: tidak error validasi hanya karena beda rule dari create
- **GET product detail**: measurement points sesuai update
- **GET product-measurement detail**:
  - Untuk item SKIP_CHECK → `status: null`
  - Untuk item non-SKIP_CHECK → status mengikuti evaluasi (true/false/null sesuai data)

---

## 6) Notes untuk QA/Tim
- Kalau menemukan error validasi pada PUT update product:
  - Catat `error_id` dan field mana yang ditolak
  - Pastikan dependency formula mengacu pada item yang sudah ada/urutannya valid
- Untuk SKIP_CHECK:
  - `status: null` adalah behavior yang benar
  - Nilai sample tetap boleh ada, karena SKIP_CHECK hanya “skip evaluasi”, bukan “skip input”

