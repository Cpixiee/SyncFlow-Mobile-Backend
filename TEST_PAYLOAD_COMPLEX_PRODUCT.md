# 🧪 TEST PAYLOAD - Product Kompleks (FIXED)

## Endpoint: `POST /api/v1/products`

### **Header:**
```
Authorization: Bearer {your_token}
Content-Type: application/json
```

---

## **PAYLOAD 1: Product Kompleks LENGKAP (FIXED VERSION)**

```json
{
  "basic_info": {
    "product_category_id": 1,
    "product_name": "COT",
    "ref_spec_number": "YPES-11-03-009-TEST-FIXED"
  },
  "measurement_points": [
    {
      "setup": {
        "name": "INSIDE DIAMETER",
        "sample_amount": 5,
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
        "sample_amount": 5,
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
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 1,
        "tolerance_minus": 0.1,
        "tolerance_plus": 0.15
      }
    },
    {
      "setup": {
        "name": "THICKNESS B",
        "sample_amount": 5,
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
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 1,
        "tolerance_minus": 0.1,
        "tolerance_plus": 0.15
      }
    },
    {
      "setup": {
        "name": "THICKNESS C",
        "sample_amount": 5,
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
        "rule": "BETWEEN",
        "unit": "mm",
        "value": 1,
        "tolerance_minus": 0.1,
        "tolerance_plus": 0.15
      }
    },
    {
      "setup": {
        "name": "THICKNESS",
        "sample_amount": 5,
        "source": "DERIVED",
        "source_derived_name_id": "thickness_a",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg_thickness",
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
        "name": "ROOM TEMP SATU",
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
          "type": "MANUAL",
          "name": "suhu",
          "is_show": true
        },
        {
          "type": "FIXED",
          "name": "suhu_const",
          "value": 9.80665,
          "is_show": false
        },
        {
          "type": "FORMULA",
          "name": "avg_thickness",
          "formula": "=(avg(thickness_a)+avg(thickness_b)+avg(thickness_c))/3",
          "is_show": false
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "normalized",
          "formula": "=single_value/avg_thickness*5",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg",
              "formula": "=avg(single_value)",
              "is_final_value": false
            },
            {
              "name": "fix",
              "formula": "=avg*suhu_const",
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
        "name": "RESIDUAL FACTOR AFTER SATU",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FORMULA",
          "name": "avg_thickness",
          "formula": "=(avg(thickness_a)+avg(thickness_b)+avg(thickness_c))/3",
          "is_show": false
        },
        {
          "type": "FORMULA",
          "name": "room_temp_satu_avg",
          "formula": "=avg(room_temp_satu)",
          "is_show": false
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "normalized",
          "formula": "=single_value/avg_thickness*5",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg",
              "formula": "=avg(single_value)",
              "is_final_value": false
            },
            {
              "name": "fix",
              "formula": "=avg/room_temp_satu_avg*100",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Mpa%",
        "value": 90
      }
    },
    {
      "setup": {
        "name": "AFTER IMMERSION IN OIL SATU",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FORMULA",
          "name": "avg_thickness",
          "formula": "=(avg(thickness_a)+avg(thickness_b)+avg(thickness_c))/3",
          "is_show": true
        },
        {
          "type": "FORMULA",
          "name": "room_temp_satu_avg",
          "formula": "=avg(room_temp_satu)",
          "is_show": false
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "normalized",
          "formula": "=single_value/avg_thickness*5",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg",
              "formula": "=avg(single_value)",
              "is_final_value": false
            },
            {
              "name": "fix",
              "formula": "=avg/room_temp_satu_avg*100",
              "is_final_value": true
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Mpa%",
        "value": 70
      }
    },
    {
      "setup": {
        "name": "ROOM TEMP DUA",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [],
      "pre_processing_formulas": [
        {
          "name": "normalized",
          "formula": "=(single_value-20)/20*100",
          "is_show": true
        }
      ],
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
        "rule": "MIN",
        "unit": "Mpa%",
        "value": 150
      }
    },
    {
      "setup": {
        "name": "RESIDUAL FACTOR AFTER DUA",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FORMULA",
          "name": "room_temp_dua_avg",
          "formula": "=avg(room_temp_dua)",
          "is_show": false
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "normalized",
          "formula": "=(single_value-20)/20*100",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg",
              "formula": "=avg(single_value)",
              "is_final_value": true
            },
            {
              "name": "fix",
              "formula": "=avg/room_temp_dua_avg*100",
              "is_final_value": false
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Mpa%",
        "value": 70
      }
    },
    {
      "setup": {
        "name": "AFTER IMMERSION IN OIL DUA",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "SINGLE",
        "nature": "QUANTITATIVE"
      },
      "variables": [
        {
          "type": "FORMULA",
          "name": "room_temp_dua_avg",
          "formula": "=avg(room_temp_dua)",
          "is_show": false
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "normalized",
          "formula": "=(single_value-20)/20*100",
          "is_show": true
        }
      ],
      "evaluation_type": "JOINT",
      "evaluation_setting": {
        "joint_setting": {
          "formulas": [
            {
              "name": "avg",
              "formula": "=avg(single_value)",
              "is_final_value": true
            },
            {
              "name": "fix",
              "formula": "=avg/room_temp_dua_avg*100",
              "is_final_value": false
            }
          ]
        }
      },
      "rule_evaluation_setting": {
        "rule": "MIN",
        "unit": "Mpa%",
        "value": 70
      }
    },
    {
      "setup": {
        "name": "WITHSTANDING VOLTAGE",
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
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "TIDAK PECAH/RETAK"
        }
      }
    },
    {
      "setup": {
        "name": "LOW TEMP RESISTANCE",
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
          "name": "mandrel",
          "is_show": true
        }
      ],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "TIDAK PECAH/RETAK"
        }
      }
    },
    {
      "setup": {
        "name": "FLAME TEST",
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
        "unit": "detik",
        "value": 15
      }
    },
    {
      "setup": {
        "name": "HEAT RESISTANCE",
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
          "name": "mandrel",
          "is_show": true
        }
      ],
      "evaluation_type": "SKIP_CHECK",
      "evaluation_setting": {
        "qualitative_setting": {
          "label": "TIDAK PECAH/RETAK"
        }
      }
    },
    {
      "setup": {
        "name": "HEAT DEFORMATION",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE"
      },
      "pre_processing_formulas": [
        {
          "name": "selisih",
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
              "formula": "=avg(selisih)",
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
    },
    {
      "setup": {
        "name": "HEAT SHRINKAGE",
        "sample_amount": 3,
        "source": "MANUAL",
        "type": "BEFORE_AFTER",
        "nature": "QUANTITATIVE"
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
          "name": "marking",
          "is_show": true
        }
      ],
      "pre_processing_formulas": [
        {
          "name": "selisih",
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
              "formula": "=avg(selisih)",
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
  ]
}
```

---

## **Expected Response:**

```json
{
  "http_code": 201,
  "message": "Product berhasil dibuat",
  "error_id": null,
  "data": {
    "product_id": "PRD-XXXXXXXX",
    "basic_info": {
      "product_category_id": 1,
      "product_name": "COT",
      "ref_spec_number": "YPES-11-03-009-TEST-FIXED",
      "nom_size_vo": null,
      "article_code": null,
      "no_document": null,
      "no_doc_reference": null,
      "color": null,
      "size": null
    },
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
          "rule": "BETWEEN",
          "unit": "mm",
          "value": 14,
          "tolerance_minus": 0.5,
          "tolerance_plus": 1
        },
        "group_order": 0
      }
      // ... rest of measurement points dengan formula tetap ada = nya
    ],
    "measurement_groups": [],
    "product_category": {
      "id": 1,
      "name": "Tube Test"
    }
  }
}
```

---

## ✅ **Key Points to Verify:**

1. ✅ **Formula tetap ada `=` prefix** di response
2. ✅ **name_id auto-generated** dari name
3. ✅ **Product created successfully** (status 201)
4. ✅ **Semua formulas valid** dan tersimpan dengan benar

---

## 🧪 **Testing Measurement Flow:**

### **Step 1: Create Measurement**
```
POST /api/v1/product-measurement

{
  "product_id": "PRD-XXXXXXXX",  // dari response create product
  "measurement_type": "FULL_MEASUREMENT",
  "due_date": "2025-12-31"
}
```

### **Step 2: Set Batch Number**
```
POST /api/v1/product-measurement/{measurement_id}/set-batch-number

{
  "batch_number": "BATCH-TEST-001"
}
```

### **Step 3: Check Samples - Inside Diameter**
```
POST /api/v1/product-measurement/{measurement_id}/check-samples

{
  "measurement_item_name_id": "inside_diameter",
  "samples": [
    {"sample_index": 1, "single_value": 14},
    {"sample_index": 2, "single_value": 14},
    {"sample_index": 3, "single_value": 14},
    {"sample_index": 4, "single_value": 14},
    {"sample_index": 5, "single_value": 14}
  ]
}
```

**Expected Response:**
```json
{
  "http_code": 200,
  "message": "Samples processed successfully",
  "data": {
    "status": true,
    "variable_values": [],
    "samples": [
      {
        "sample_index": 1,
        "status": true,
        "single_value": 14,
        "pre_processing_formula_values": null
      }
      // ... rest of samples
    ],
    "joint_setting_formula_values": [
      {
        "name": "avg",
        "formula": "=avg(single_value)",  // ✅ dengan = prefix
        "is_final_value": true,
        "value": 14
      }
    ]
  }
}
```

### **Step 4: Check Samples - Thickness A, B, C**

Check each one sequentially, values akan disimpan di `last_check_data`.

### **Step 5: Check Samples - Room Temp Satu (with FORMULA variables)**

```json
{
  "measurement_item_name_id": "room_temp_satu",
  "samples": [
    {"sample_index": 1, "single_value": 20},
    {"sample_index": 2, "single_value": 22},
    {"sample_index": 3, "single_value": 21}
  ],
  "variable_values": [
    {"name_id": "panjang", "value": 100},
    {"name_id": "suhu", "value": 25}
  ]
}
```

**Expected:**
- `avg_thickness` variable akan di-calculate otomatis dari `thickness_a`, `thickness_b`, `thickness_c` yang sudah di-check sebelumnya
- Formula akan berjalan dengan benar karena ada measurement context

---

## 📊 **Verification Points:**

### ✅ **Create Product:**
- [ ] Product created successfully (201)
- [ ] Formula dengan `=` prefix tersimpan
- [ ] Response include `=` prefix
- [ ] name_id auto-generated

### ✅ **Check Samples:**
- [ ] Simple measurement works (inside_diameter)
- [ ] Cross-reference works (thickness aggregate)
- [ ] FORMULA variables calculated correctly
- [ ] Pre-processing formulas work
- [ ] Joint formulas work
- [ ] Status evaluation correct (OK/NG)

---

**🎉 Ready to Test in Postman!**


