# Master Product Payload Examples

## Contoh Payload untuk Create Product dari Master Data

### ⚠️ PENTING: Endpoint yang Digunakan

**Gunakan endpoint ini:**
```
POST /api/v1/products/from-existing
```

**JANGAN gunakan endpoint ini** (yang membutuhkan `basic_info`):
```
POST /api/v1/products
```

### Perbedaan Endpoint:

1. **`POST /api/v1/products/from-existing`** ✅ (Recommended untuk master data)
   - **TIDAK perlu** mengirim `basic_info`
   - Cukup kirim `master_product_id`, `measurement_points`, dan `measurement_groups`
   - `basic_info` akan otomatis diambil dari master product

2. **`POST /api/v1/products`** ❌ (Tidak untuk master data)
   - **HARUS** mengirim `basic_info` (product_category_id, product_name, dll)
   - Untuk create product dari scratch

---

## Contoh 1: Product Tube Test (COT) - Simple

**Master Product**: COT B 5

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

---

## Contoh 2: Product Tube Test (COT) - Lengkap dengan Multiple Measurement Points

**Master Product**: COT B 5

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
    },
    {
      "setup": {
        "name": "THICKNESS A",
        "name_id": "thickness_a",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "SKIP_CHECK"
    },
    {
      "setup": {
        "name": "THICKNESS B",
        "name_id": "thickness_b",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "SKIP_CHECK"
    },
    {
      "setup": {
        "name": "THICKNESS C",
        "name_id": "thickness_c",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "SKIP_CHECK"
    },
    {
      "setup": {
        "name": "THICKNESS",
        "name_id": "thickness",
        "sample_amount": 0,
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "final",
              "formula": "=(avg(thickness_a)+avg(thickness_b)+avg(thickness_c))/3",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 1,
        "tolerance_minus": 0.1,
        "tolerance_plus": 0.15
      }
    },
    {
      "setup": {
        "name": "ROOM TEMP A",
        "name_id": "room_temp_a",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "MANUAL",
          "name": "panjang",
          "is_show": true
        },
        {
          "type": "FORMULA",
          "name": "cross_section",
          "formula": "=thickness.final*5",
          "is_show": true
        },
        {
          "type": "FIXED",
          "name": "temp_const",
          "value": 9.80665,
          "is_show": false
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "normalized",
          "formula": "=single_value/cross_section",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg",
              "formula": "=avg(normalized)",
              "is_final_value": false
            },
            {
              "name": "final",
              "formula": "=avg*temp_const",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Mpa%",
        "value": 15.7
      }
    },
    {
      "setup": {
        "name": "WITHSTANDING VOLTAGE",
        "name_id": "withstanding_voltage",
        "sample_amount": 1,
        "nature": "QUALITATIVE"
      },
      "variables": [
        {
          "type": "MANUAL",
          "name": "panjang",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "suhu",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "waktu",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "proses",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "volt",
          "is_show": true
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "Withstanding Voltage Check"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "value": true
      }
    }
  ],
  "measurement_groups": [
    {
      "order": 1,
      "measurement_items": ["inside_diameter"]
    },
    {
      "group_name": "THICKNESS",
      "order": 2,
      "measurement_items": ["thickness_a", "thickness_b", "thickness_c", "thickness"]
    },
    {
      "order": 3,
      "measurement_items": ["room_temp_a"]
    },
    {
      "order": 4,
      "measurement_items": ["withstanding_voltage"]
    }
  ]
}
```

---

## Contoh 3: Product Wire Test (AVSSH) - Simple

**Master Product**: AVSSH 0.3F

```json
{
  "master_product_id": 25,
  "measurement_points": [
    {
      "setup": {
        "name": "DIAMETER",
        "name_id": "diameter",
        "sample_amount": 5,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "PER_SAMPLE",
      "rule_evaluation_setting": {
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 0.3,
        "tolerance_minus": 0.01,
        "tolerance_plus": 0.01
      }
    },
    {
      "setup": {
        "name": "RESISTANCE",
        "name_id": "resistance",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg",
              "formula": "=avg(single_value)",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "Ohm/km",
        "value": 100
      }
    }
  ],
  "measurement_groups": [
    {
      "order": 1,
      "measurement_items": ["diameter"]
    },
    {
      "order": 2,
      "measurement_items": ["resistance"]
    }
  ]
}
```

---

## Contoh 4: Product dengan BEFORE_AFTER Type

**Master Product**: COT B 5

```json
{
  "master_product_id": 1,
  "measurement_points": [
    {
      "setup": {
        "name": "HEAT DEFORMATION",
        "name_id": "heat_deformation",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "MANUAL",
          "name": "kondisi",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "panjang",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "weigth",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "waktu",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "temperature",
          "is_show": true
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "difference",
          "formula": "=(before-after)/before*100",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg",
              "formula": "=avg(difference)",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MAX",
        "unit": "%",
        "value": 10
      }
    }
  ],
  "measurement_groups": [
    {
      "order": 1,
      "measurement_items": ["heat_deformation"]
    }
  ]
}
```

---

## Contoh 5: Product dengan QUALITATIVE Measurement Point (sample_amount = 1)

**Master Product**: COT B 5

**⚠️ PENTING: Untuk QUALITATIVE:**
- `sample_amount` **MINIMAL** `1` (bisa 1, 4, 5, 10, dll - tidak dibatasi)
- `evaluation_type` **HARUS SELALU** `PER_SAMPLE` (tidak bisa SKIP_CHECK atau JOINT)
- `rule_evaluation_setting` **HARUS ADA** (required)

```json
{
  "master_product_id": 1,
  "measurement_points": [
    {
      "setup": {
        "name": "WITHSTANDING VOLTAGE",
        "name_id": "withstanding_voltage",
        "sample_amount": 1,
        "nature": "QUALITATIVE"
      },
      "variables": [
        {
          "type": "MANUAL",
          "name": "panjang",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "suhu",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "waktu",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "proses",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "volt",
          "is_show": true
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "Withstanding Voltage Check"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "value": true
      }
    }
  ],
  "measurement_groups": [
    {
      "order": 1,
      "measurement_items": ["withstanding_voltage"]
    }
  ]
}
```

---

## Contoh 6: Product dengan QUALITATIVE Measurement Point (sample_amount = 5)

**Master Product**: COT B 5

**Contoh dengan sample_amount lebih dari 1:**

```json
{
  "master_product_id": 1,
  "measurement_points": [
    {
      "setup": {
        "name": "FLAME TEST",
        "name_id": "flame_test",
        "sample_amount": 5,
        "nature": "QUALITATIVE"
      },
      "variables": [
        {
          "type": "MANUAL",
          "name": "panjang",
          "is_show": true
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "Flame Test Check"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "value": true
      }
    },
    {
      "setup": {
        "name": "HEAT RESISTANCE",
        "name_id": "heat_resistance",
        "sample_amount": 10,
        "nature": "QUALITATIVE"
      },
      "variables": [
        {
          "type": "MANUAL",
          "name": "panjang",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "temp",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "waktu",
          "is_show": true
        },
        {
          "type": "MANUAL",
          "name": "mandrel",
          "is_show": true
        }
      ],
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "Heat Resistance Check"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "value": true
      }
    }
  ],
  "measurement_groups": [
    {
      "order": 1,
      "measurement_items": ["flame_test"]
    },
    {
      "order": 2,
      "measurement_items": ["heat_resistance"]
    }
  ]
}
```

---

## Field yang Otomatis Diambil dari Master Product

Ketika menggunakan endpoint `POST /api/v1/products/from-existing`, field berikut **tidak perlu dikirim** karena akan diambil otomatis dari master product:

- `product_category_id`
- `product_name`
- `product_spec_name`
- `ref_spec_number`
- `nom_size_vo`
- `article_code`
- `no_document`
- `no_doc_reference`
- `color`
- `size`

**User hanya perlu mengirim:**
- `master_product_id` (required)
- `measurement_points` (required)
- `measurement_groups` (optional)

---

## ⚠️ TROUBLESHOOTING: Error "basic_info.product_category_id is required"

**Jika Anda mendapat error seperti ini:**
```json
{
  "http_code": 400,
  "message": "Request invalid",
  "error_id": "VALIDATION_693E48F32F192",
  "data": {
    "basic_info.product_category_id": ["The basic info.product category id field is required."],
    "basic_info.product_name": ["The basic info.product name field is required."],
    "measurement_points": ["The measurement points field is required."]
  }
}
```

**Berarti Anda menggunakan endpoint yang SALAH!**

### Solusi:

✅ **Gunakan endpoint ini untuk create dari master data:**
```
POST /api/v1/products/from-existing
```

❌ **Jangan gunakan endpoint ini:**
```
POST /api/v1/products  (ini untuk create product biasa, HARUS ada basic_info)
```

### Payload yang Benar untuk `/products/from-existing`:

```json
{
  "master_product_id": 1,
  "measurement_points": [
    {
      "setup": {
        "name": "FLAME TEST",
        "name_id": "flame_test",
        "sample_amount": 5,
        "nature": "QUALITATIVE"
      },
      "evaluation_type": "PER_SAMPLE",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "Flame Test Check"
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "value": true
      }
    }
  ],
  "measurement_groups": [
    {
      "order": 1,
      "measurement_items": ["flame_test"]
    }
  ]
}
```

**Perhatikan:**
- ✅ Ada `master_product_id`
- ✅ Ada `measurement_points`
- ✅ Ada `measurement_groups`
- ❌ **TIDAK ada** `basic_info` (tidak perlu dikirim!)

---

## Validasi yang Tetap Berlaku

Semua validasi measurement points tetap berlaku sama seperti endpoint create product biasa:

1. **QUANTITATIVE requirements:**
   - Jika `sample_amount > 0`: `source` dan `type` required
   - Jika `sample_amount = 0`: `source` optional, `type` harus `SINGLE`

2. **QUALITATIVE requirements:**
   - `sample_amount` **MINIMAL** `1` (bisa 1, 4, 5, 10, dll - tidak dibatasi)
   - `evaluation_type` **HARUS SELALU** `PER_SAMPLE` (tidak bisa SKIP_CHECK atau JOINT)
   - `rule_evaluation_setting` **HARUS ADA** (required)

3. **BEFORE_AFTER type:**
   - `pre_processing_formulas` wajib ada

4. **Formula validation:**
   - Format formula harus valid
   - Dependencies harus terpenuhi
   - Cross-references harus valid

5. **Name uniqueness:**
   - `name_id` harus unique dalam `measurement_points`

---

## Error Responses

### 400 - Product Already Exists
```json
{
  "http_code": 400,
  "message": "Product dengan spesifikasi 'COT B 5' sudah pernah dibuat",
  "error_id": "PRODUCT_ALREADY_EXISTS",
  "data": null
}
```

### 404 - Master Product Not Found
```json
{
  "http_code": 404,
  "message": "Master product tidak ditemukan",
  "error_id": null,
  "data": null
}
```

### 422 - Validation Error
```json
{
  "http_code": 422,
  "message": "Validation failed",
  "error_id": "VALIDATION_ERROR",
  "data": {
    "measurement_points.0.setup.sample_amount": [
      "QUALITATIVE nature requires sample_amount to be at least 1"
    ]
  }
}
```

---

## Langkah-Langkah untuk Create Product dari Master Data

1. **Get list master products yang tersedia:**
   ```
   GET /api/v1/products/master-products?product_category_id=1
   ```

2. **Pilih master_product_id dari response**

3. **Buat payload dengan measurement_points dan measurement_groups**

4. **Kirim request:**
   ```
   POST /api/v1/products/from-existing
   ```

5. **Product berhasil dibuat, basic_info sudah terisi otomatis dari master product**
