# 📖 Dokumentasi REST API - Virtual Guide Pekon Margodadi

Dokumentasi resmi endpoint REST API untuk Backend Virtual Guide & Portal Layanan Publik serta Potensi UMKM Pekon Margodadi.

---

## 🌐 Base URL
```http
http://localhost:8000
```
*Header default untuk seluruh request:*
```http
Accept: application/json
Content-Type: application/json
```

---

## 📑 Daftar Isi
1. [Status API](#1-status-api)
2. [Global Search (Homepage Search Bar)](#2-global-search-homepage-search-bar)
3. [Layanan Publik & Administrasi Pekon](#3-layanan-publik--administrasi-pekon)
4. [Potensi UMKM Pekon Margodadi](#4-potensi-umkm-pekon-margodadi)
5. [Autentikasi Operator & Aparatur Pekon](#5-autentikasi-operator--aparatur-pekon)

---

## 1. Status API

### `GET /`
Mengecek apakah server REST API berjalan dengan baik.

- **Autentikasi:** Tidak Perlu (Publik)
- **Response Success (200 OK):**
```json
{
  "name": "Chatbot Margodadi API",
  "status": "API is running",
  "version": "1.0.0"
}
```

---

## 2. Global Search (Homepage Search Bar)

### `GET /api/search`
Pencarian lintas modul (Layanan Publik dan Potensi UMKM) secara bersamaan untuk komponen search bar beranda.

- **Autentikasi:** Tidak Perlu (Publik)
- **Query Parameters:**
  | Parameter | Tipe | Default | Keterangan |
  |---|---|---|---|
  | `q` / `search` | `string` | *(wajib)* | Kata kunci pencarian |
  | `type` | `string` | `all` | Pilihan: `all`, `public_services`, `umkms` |
  | `limit` | `integer` | `10` | Jumlah maksimal data per kategori (max: 50) |

- **Contoh Request:**
```http
GET /api/search?q=usaha&type=all&limit=5
```

- **Response Success (200 OK):**
```json
{
  "status": "success",
  "query": "usaha",
  "total_results": 2,
  "data": {
    "public_services": [
      {
        "service_id": 1,
        "title": "Surat Keterangan Usaha (SKU)",
        "slug": "surat-keterangan-usaha-sku",
        "category_badge": "SURAT_PENGANTAR",
        "sub_category_badge": "Perekonomian & UMKM",
        "description": "Surat keterangan resmi dari Pekon Margodadi...",
        "sla_duration": "1 Hari Kerja (Maksimal 24 Jam)",
        "cost_info": "Gratis (Rp 0)",
        "officer_in_charge": "Loket Kasi Pelayanan Pekon Margodadi",
        "download_url": "https://margodadi.desa.id/formulir/sku-template.pdf",
        "tags": ["sku", "usaha", "umkm", "kur"],
        "category": {
          "category_id": 2,
          "name": "Usaha & Perekonomian",
          "slug": "usaha-perekonomian",
          "domain": "PUBLIC_SERVICE",
          "icon": "store"
        }
      }
    ],
    "umkms": [
      {
        "umkm_id": 1,
        "reg_number": "MKD-UMKM-001",
        "name": "Keripik Pisang Margodadi Berkibar",
        "sub_title": "Camilan Gurih Renyah Khas Pekon Margodadi",
        "owner_name": "Ibu Siti Munawaroh",
        "phone": "081278901234",
        "wa_number": "6281278901234",
        "address": "RT 03 Dusun 1 Pekon Margodadi, Kec. Ambarawa, Pringsewu",
        "description": "Produsen keripik pisang aneka rasa...",
        "banner_image_url": "https://images.unsplash.com/photo-1555396273-367ea4eb4db5",
        "legal_certification": "P-IRT & Sertifikasi Halal Kemenag",
        "group_name": "KWT Melati Indah Margodadi",
        "category": {
          "category_id": 4,
          "name": "Kuliner & Olahan Pangan",
          "slug": "kuliner-olahan-pangan",
          "domain": "UMKM",
          "icon": "utensils"
        },
        "products_count": 2
      }
    ]
  },
  "counts": {
    "public_services": 1,
    "umkms": 1
  }
}
```

---

## 3. Layanan Publik & Administrasi Pekon

### 3.1. List Layanan Publik (Search, Filter, Sort & Pagination)
`GET /api/public-services`

- **Autentikasi:** Tidak Perlu (Publik)
- **Query Parameters:**
  | Parameter | Tipe | Contoh | Keterangan |
  |---|---|---|---|
  | `search` / `q` | `string` | `sku` | Mencari judul, deskripsi, dasar hukum, tags |
  | `category` | `string`/`int` | `administrasi-kependudukan` | Filter slug / ID kategori |
  | `badge` | `string` | `SURAT_PENGANTAR` | Filter badge (`SURAT_PENGANTAR`, `ADMINISTRASI`, `FASILITAS`) |
  | `sub_badge` | `string` | `Kependudukan` | Filter sub-kategori badge |
  | `sort` | `string` | `latest` | Pilihan: `latest`, `oldest`, `title_asc`, `title_desc` |
  | `per_page` | `integer` | `10` | Limit per halaman (default: 10, max: 50) |

- **Contoh Request:**
```http
GET /api/public-services?search=sku&sort=latest&per_page=10
```

- **Response Success (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "service_id": 1,
      "title": "Surat Keterangan Usaha (SKU)",
      "slug": "surat-keterangan-usaha-sku",
      "category_badge": "SURAT_PENGANTAR",
      "sub_category_badge": "Perekonomian & UMKM",
      "description": "Surat keterangan resmi dari Pekon Margodadi...",
      "sla_duration": "1 Hari Kerja (Maksimal 24 Jam)",
      "cost_info": "Gratis (Rp 0)",
      "officer_in_charge": "Loket Kasi Pelayanan Pekon Margodadi",
      "download_url": "https://margodadi.desa.id/formulir/sku-template.pdf",
      "tags": ["sku", "usaha", "umkm"],
      "category": {
        "category_id": 2,
        "name": "Usaha & Perekonomian",
        "slug": "usaha-perekonomian",
        "domain": "PUBLIC_SERVICE",
        "icon": "store"
      },
      "created_at": "2026-10-07T12:00:00+00:00",
      "updated_at": "2026-10-07T12:00:00+00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 1,
    "last_page": 1,
    "has_more_pages": false
  },
  "filters_applied": {
    "search": "sku",
    "sort": "latest"
  }
}
```

---

### 3.2. Detail Layanan Publik by Slug / ID
`GET /api/public-services/{slug}`

- **Autentikasi:** Tidak Perlu (Publik)
- **Contoh Request:**
```http
GET /api/public-services/surat-keterangan-usaha-sku
```
*(Bisa juga menggunakan ID numerik, misal: `/api/public-services/1`)*

- **Response Success (200 OK):**
```json
{
  "status": "success",
  "data": {
    "service_id": 1,
    "title": "Surat Keterangan Usaha (SKU)",
    "slug": "surat-keterangan-usaha-sku",
    "category_badge": "SURAT_PENGANTAR",
    "sub_category_badge": "Perekonomian & UMKM",
    "description": "Surat keterangan resmi dari Pekon Margodadi yang menyatakan kepemilikan usaha aktif warga...",
    "legal_basis": "Peraturan Desa Margodadi No. 03 Tahun 2023 tentang Pelayanan Administrasi Usaha Desa",
    "requirements": [
      "Fotokopi KTP Pemohon (wajib berdomisili Margodadi)",
      "Fotokopi Kartu Keluarga (KK)",
      "Surat Pengantar dari Ketua RT setempat",
      "Foto dokumentasi tempat usaha / foto produk"
    ],
    "steps": [
      {
        "step": 1,
        "name": "Pengantar RT",
        "desc": "Meminta surat pengantar dari Ketua RT tempat tinggal",
        "time": "15 Menit",
        "icon": "user-check"
      },
      {
        "step": 2,
        "name": "Verifikasi Loket",
        "desc": "Menyerahkan berkas persyaratan ke Loket Pelayanan Balai Pekon",
        "time": "10 Menit",
        "icon": "file-text"
      }
    ],
    "sla_duration": "1 Hari Kerja (Maksimal 24 Jam)",
    "cost_info": "Gratis (Rp 0)",
    "officer_in_charge": "Loket Kasi Pelayanan Pekon Margodadi",
    "download_url": "https://margodadi.desa.id/formulir/sku-template.pdf",
    "tags": ["sku", "usaha", "umkm", "kur"],
    "is_active": true,
    "category": {
      "category_id": 2,
      "name": "Usaha & Perekonomian",
      "slug": "usaha-perekonomian",
      "domain": "PUBLIC_SERVICE",
      "icon": "store"
    },
    "created_at": "2026-10-07T12:00:00+00:00",
    "updated_at": "2026-10-07T12:00:00+00:00"
  }
}
```

- **Response Error (404 Not Found):**
```json
{
  "status": "error",
  "message": "Layanan publik tidak ditemukan atau sedang tidak aktif."
}
```

---

### 3.3. Daftar Kategori Layanan Publik
`GET /api/public-services/categories`

Mengambil kategori khusus domain `PUBLIC_SERVICE` beserta total layanan aktif di setiap kategori.

- **Autentikasi:** Tidak Perlu (Publik)
- **Response Success (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "category_id": 1,
      "name": "Administrasi & Kependudukan",
      "slug": "administrasi-kependudukan",
      "domain": "PUBLIC_SERVICE",
      "icon": "id-card",
      "description": "Layanan pembuatan surat pengantar KTP, KK, dan Domisili.",
      "is_active": true,
      "public_services_count": 1
    }
  ]
}
```

---

## 4. Potensi UMKM Pekon Margodadi

### 4.1. List UMKM (Search, Filter, Sorting & Pagination)
`GET /api/umkms`

- **Autentikasi:** Tidak Perlu (Publik)
- **Query Parameters:**
  | Parameter | Tipe | Contoh | Keterangan |
  |---|---|---|---|
  | `search` / `q` | `string` | `pisang` | Cari nama UMKM, pemilik, deskripsi, sertifikasi, alamat |
  | `category` | `string`/`int` | `kuliner-olahan-pangan` | Filter berdasarkan slug atau ID kategori |
  | `sort` | `string` | `a-z` | Opsi: `a-z`, `z-a`, `terbaru`, `terlama` |
  | `per_page` | `integer` | `10` | Limit data per halaman (max: 50) |

- **Contoh Request:**
```http
GET /api/umkms?search=pisang&sort=a-z&per_page=10
```

- **Response Success (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "umkm_id": 1,
      "reg_number": "MKD-UMKM-001",
      "name": "Keripik Pisang Margodadi Berkibar",
      "sub_title": "Camilan Gurih Renyah Khas Pekon Margodadi",
      "owner_name": "Ibu Siti Munawaroh",
      "phone": "081278901234",
      "wa_number": "6281278901234",
      "address": "RT 03 Dusun 1 Pekon Margodadi, Kec. Ambarawa, Pringsewu",
      "description": "Produsen keripik pisang aneka rasa berbahan baku pisang kepok lokal...",
      "banner_image_url": "https://images.unsplash.com/photo-1555396273-367ea4eb4db5",
      "legal_certification": "P-IRT & Sertifikasi Halal Kemenag",
      "group_name": "KWT Melati Indah Margodadi",
      "group_location": "Balai Dusun 1 Margodadi",
      "category": {
        "category_id": 4,
        "name": "Kuliner & Olahan Pangan",
        "slug": "kuliner-olahan-pangan",
        "domain": "UMKM",
        "icon": "utensils"
      },
      "products_count": 2,
      "last_verified_at": "2026-10-07T12:00:00+00:00",
      "created_at": "2026-10-07T12:00:00+00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 1,
    "last_page": 1,
    "has_more_pages": false
  },
  "filters_applied": {
    "search": "pisang",
    "sort": "a-z"
  }
}
```

---

### 4.2. Detail UMKM by ID / No. Registrasi
`GET /api/umkms/{id}`

- **Autentikasi:** Tidak Perlu (Publik)
- **Parameter Path:** `{id}` dapat berupa `umkm_id` angka (`1`) atau `reg_number` (`MKD-UMKM-001`).
- **Contoh Request:**
```http
GET /api/umkms/MKD-UMKM-001
```

- **Response Success (200 OK):**
```json
{
  "status": "success",
  "data": {
    "umkm_id": 1,
    "reg_number": "MKD-UMKM-001",
    "name": "Keripik Pisang Margodadi Berkibar",
    "sub_title": "Camilan Gurih Renyah Khas Pekon Margodadi",
    "owner_name": "Ibu Siti Munawaroh",
    "phone": "081278901234",
    "wa_number": "6281278901234",
    "address": "RT 03 Dusun 1 Pekon Margodadi, Kec. Ambarawa, Pringsewu",
    "description": "Produsen keripik pisang aneka rasa...",
    "history": "Berdiri sejak tahun 2019...",
    "banner_image_url": "https://images.unsplash.com/photo-1555396273-367ea4eb4db5",
    "gallery_urls": [
      "https://images.unsplash.com/photo-1555396273-367ea4eb4db5",
      "https://images.unsplash.com/photo-1540420773420-3366772f4999"
    ],
    "legal_certification": "P-IRT & Sertifikasi Halal Kemenag",
    "legal_number": "P-IRT 2151810010045-26",
    "production_capacity": "800 Pouch / Bulan",
    "capacity_note": "Siap melayani pesanan souvenir hajatan",
    "group_name": "KWT Melati Indah Margodadi",
    "group_location": "Balai Dusun 1 Margodadi",
    "map_title": "Rumah Produksi Keripik Pisang",
    "map_address": "Jl. Poros Desa No. 12 Dusun 1 Margodadi",
    "map_url": "https://maps.google.com/?q=Margodadi+Pringsewu",
    "is_active": true,
    "last_verified_at": "2026-10-07T12:00:00+00:00",
    "category": {
      "category_id": 4,
      "name": "Kuliner & Olahan Pangan",
      "slug": "kuliner-olahan-pangan",
      "domain": "UMKM",
      "icon": "utensils"
    },
    "products": [
      {
        "product_id": 1,
        "umkm_id": 1,
        "name": "Keripik Pisang Coklat Lumer 200g",
        "description": "Keripik pisang kepok renyah berlapis coklat leleh premium...",
        "price": "Rp 18.000",
        "image_url": "https://images.unsplash.com/photo-1555396273-367ea4eb4db5",
        "is_active": true
      },
      {
        "product_id": 2,
        "umkm_id": 1,
        "name": "Keripik Pisang Gurih Manis Original 250g",
        "description": "Varian original gurih dan manis renyah alami.",
        "price": "Rp 15.000",
        "image_url": "https://images.unsplash.com/photo-1555396273-367ea4eb4db5",
        "is_active": true
      }
    ],
    "created_at": "2026-10-07T12:00:00+00:00",
    "updated_at": "2026-10-07T12:00:00+00:00"
  }
}
```

---

### 4.3. Daftar Kategori UMKM
`GET /api/umkms/categories`

Mengambil kategori khusus domain `UMKM` beserta jumlah UMKM aktif di masing-masing kategori (`umkms_count`).

- **Autentikasi:** Tidak Perlu (Publik)
- **Response Success (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "category_id": 4,
      "name": "Kuliner & Olahan Pangan",
      "slug": "kuliner-olahan-pangan",
      "domain": "UMKM",
      "icon": "utensils",
      "description": "Produk kuliner, camilan khas...",
      "is_active": true,
      "umkms_count": 1
    },
    {
      "category_id": 6,
      "name": "Kerajinan & Produk Kreatif",
      "slug": "kerajinan-kreatif",
      "domain": "UMKM",
      "icon": "gift",
      "description": "Kerajinan anyaman bambu...",
      "is_active": true,
      "umkms_count": 1
    }
  ]
}
```

---

## 5. Autentikasi Operator & Aparatur Pekon

Modul autentikasi token menggunakan **Laravel Sanctum** untuk akun aparatur pekon/operator back-office.

---

### 5.1. Registrasi Operator Baru
`POST /api/auth/register`

- **Request Body:**
```json
{
  "name": "Aparatur Pekon",
  "email": "operator@margodadi.desa.id",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "081234567890",
  "role": "OPERATOR"
}
```

- **Response Success (201 Created):**
```json
{
  "status": "success",
  "message": "Operator registered successfully",
  "data": {
    "operator": {
      "operator_id": 1,
      "name": "Aparatur Pekon",
      "email": "operator@margodadi.desa.id",
      "role": "OPERATOR",
      "status": "OFFLINE",
      "is_active": true
    },
    "access_token": "1|AbCdEf123456...",
    "token_type": "Bearer"
  }
}
```

---

### 5.2. Login Operator
`POST /api/auth/login`

- **Request Body:**
```json
{
  "email": "operator@margodadi.desa.id",
  "password": "password123"
}
```

- **Response Success (200 OK):**
```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "operator": {
      "operator_id": 1,
      "name": "Aparatur Pekon",
      "email": "operator@margodadi.desa.id",
      "role": "OPERATOR",
      "status": "OFFLINE"
    },
    "access_token": "2|XyZ987654321...",
    "token_type": "Bearer"
  }
}
```

- **Response Error (401 Unauthorized):**
```json
{
  "status": "error",
  "message": "The provided credentials do not match our records."
}
```

---

### 5.3. Profil Operator Login (Me)
`GET /api/auth/me`

- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)
- **Response Success (200 OK):**
```json
{
  "status": "success",
  "data": {
    "operator": {
      "operator_id": 1,
      "name": "Aparatur Pekon",
      "email": "operator@margodadi.desa.id",
      "phone": "081234567890",
      "role": "OPERATOR",
      "status": "OFFLINE",
      "is_active": true
    }
  }
}
```

---

### 5.4. Logout Operator
`POST /api/auth/logout`

- **Autentikasi:** Wajib (`Authorization: Bearer <access_token>`)
- **Response Success (200 OK):**
```json
{
  "status": "success",
  "message": "Successfully logged out"
}
```

---

## ⚡ Error Response Format Terstandar

### 422 Unprocessable Entity (Validasi Input Gagal)
```json
{
  "message": "The email field is required.",
  "errors": {
    "email": [
      "The email field is required."
    ]
  }
}
```

### 401 Unauthorized (Token Tidak Valid / Tidak Ada)
```json
{
  "message": "Unauthenticated."
}
```

### 404 Not Found (Data Tidak Ditemukan)
```json
{
  "status": "error",
  "message": "Layanan publik tidak ditemukan atau sedang tidak aktif."
}
```
