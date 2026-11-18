# 🚀 Tevara API (Laravel 11)

Selamat datang di repository backend **Tevara API**! Proyek ini dibangun menggunakan **Laravel 11** dengan menerapkan standar REST API modern (*Clean Architecture*, *Standardized JSON Envelope*, *Form Request Validation*, dan *Service Layer*).

README ini dibuat sebagai panduan belajar (*tutorial & documentation*) praktis, mulai dari cara instalasi pertama kali setelah clone, penjelasan arsitektur folder, hingga daftar perintah (*command*) Artisan yang sering digunakan sehari-hari.

---

## 📋 Daftar Isi
1. [Prasyarat Sistem](#1-prasyarat-sistem)
2. [Panduan Instalasi (Mulai dari Clone)](#2-panduan-instalasi-mulai-dari-clone)
3. [Format Response API Standar](#3-format-response-api-standar)
4. [Daftar Endpoint yang Tersedia](#4-daftar-endpoint-yang-tersedia)
5. [Struktur Folder & Alur Kode](#5-struktur-folder--alur-kode)
6. [Cheat Sheet Command Artisan & Fungsinya](#6-cheat-sheet-command-artisan--fungsinya)
7. [Menjalankan Automated Test](#7-menjalankan-automated-test)

---

## 1. Prasyarat Sistem
Pastikan komputer kamu sudah terinstall:
* **PHP** versi 8.2 atau lebih baru (`php -v`)
* **Composer** versi terbaru (`composer -v`)
* **Git**

---

## 2. Panduan Instalasi (Mulai dari Clone)

Jika kamu baru saja meng-clone project ini atau bekerja di komputer baru, ikuti urutan langkah berikut:

### Langkah 1: Clone Repository
```bash
git clone <URL_REPOSITORY_KAMU>
cd tevara-api
```

### Langkah 2: Install Dependensi PHP
```bash
composer install
```
> **Fungsi:** Mengunduh semua library dan package pihak ketiga yang dicatat di file `composer.json` ke dalam folder `vendor/`.

### Langkah 3: Siapkan File Environment (`.env`)
Salin file template `.env.example` menjadi `.env`:
```bash
# Untuk Windows (PowerShell/CMD):
copy .env.example .env

# Atau untuk Git Bash / Linux / macOS:
cp .env.example .env
```
> **Fungsi:** File `.env` menyimpan konfigurasi rahasia lokal komputer kamu (seperti koneksi database, port server, dan app key) yang tidak boleh di-push ke GitHub publik.

### Langkah 4: Generate Application Key
```bash
php artisan key:generate
```
> **Fungsi:** Membuat kunci enkripsi unik di baris `APP_KEY` pada file `.env`. Kunci ini wajib ada agar sesi, cookie, dan enkripsi data Laravel bisa bekerja dengan aman.

### Langkah 5: Jalankan Server Lokal
```bash
php artisan serve
```
> **Fungsi:** Menjalankan local development server. Secara default, API kamu sekarang sudah bisa diakses di:  
> 👉 `http://127.0.0.1:8000` (atau port yang tertera di terminal).

---

## 3. Format Response API Standar

Seluruh endpoint di project ini menggunakan trait `App\Traits\ApiResponse` agar format respon JSON selalu konsisten di sisi Frontend/Mobile:

### Response Sukses (200 / 201)
```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "admin"
    }
  ]
}
```

### Response Error / Not Found (404 / 500)
```json
{
  "success": false,
  "message": "User not found"
}
```

### Response Gagal Validasi Input (422)
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

---

## 4. Daftar Endpoint yang Tersedia

Semua endpoint aplikasi memakai prefiks `/api/v1/`. Daftar endpoint, metode HTTP, parameter, format request, autentikasi, dan contoh response yang terbaru tersedia di Swagger UI:

```text
http://127.0.0.1:8000/api/documentation
```

Jalankan `php artisan l5-swagger:generate` setelah menambah atau mengubah dokumentasi OpenAPI pada atribut controller. Spesifikasi JSON yang dihasilkan berada di `storage/api-docs/api-docs.json`; jangan mengedit file hasil generate secara manual. Pastikan dokumentasi untuk setiap route tetap cocok dengan `routes/api.php` dan aturan validasi/controller terkait.

### Alur UAT dan relasi data

- Klien pemilik proyek mengirim hasil pengujian lewat `POST /api/v1/proposals/{id}/uat`. Sistem menghitung hasil keseluruhan dari hasil setiap fitur dan menyimpan satu sesi baru.
- Pengurus membaca sesi terbaru lewat `GET /api/v1/proposals/{id}/uat`, lalu memeriksanya lewat `PATCH /api/v1/proposals/{id}/uat/{uatId}/review`.
- Jika ada fitur yang belum sesuai, Pengurus mengirim daftar `corrections`. Setiap poin menjadi satu `tasks` yang ditautkan ke `uat_items` dan anggota `project_teams` yang ditugaskan. Mahasiswa melaporkan progres melalui endpoint task yang sudah ada.
- Setelah seluruh task perbaikan disetujui melalui `PATCH /api/v1/proposals/{id}/tasks/{taskId}/review`, sesi berubah menjadi `READY_FOR_RETEST` dan Klien bisa mengirim sesi UAT berikutnya. Sesi-sesi lama tetap tersimpan.
- Jika seluruh fitur lulus, Pengurus mengirim keputusan `APPROVED`; Klien kemudian mengesahkan hasil melalui `PATCH /api/v1/proposals/{id}/uat/signoff`. Proyek baru berpindah ke status Handover setelah persetujuan Klien.

Relasi utamanya: satu `proposals` memiliki banyak `uat_results`; setiap sesi UAT memiliki banyak `uat_items`; setiap item yang perlu diperbaiki dapat memiliki `tasks`; task menunjuk ke `project_teams` untuk menentukan mahasiswa penanggung jawab. BAST tetap memakai relasi dan endpoint BAST tersendiri, dan hanya dapat disiapkan setelah sesi UAT terakhir lulus serta disahkan Klien.

---

## 5. Struktur Folder & Alur Kode

Untuk menjaga kode tetap bersih (*Separation of Concerns*), tanggung jawab dipisahkan ke beberapa folder:

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── User/
│   │       └── UserController.php    # Jembatan menerima HTTP Request & mengirim Response
│   ├── Requests/
│   │   └── User/
│   │       ├── StoreUserRequest.php  # Validasi data saat POST (wajib name, email, dll)
│   │       └── UpdateUserRequest.php # Validasi data saat PUT/PATCH
│   └── Resources/
│       └── User/
│           └── UserResource.php      # Memfilter field JSON yang boleh dilihat publik
├── Services/
│   └── UserService.php               # Tempat logika data CRUD diproses (saat ini in-memory)
└── Traits/
    └── ApiResponse.php               # Helper otomatis pembungkus JSON ($this->ok, $this->created)
```

### Alur Request dari Awal sampai Respon:
1. **Client** mengirim request ke URL (misal: `POST /api/v1/users`).
2. **`routes/api.php`** mengarahkan request ke Controller yang bertugas.
3. **`StoreUserRequest`** mencegat request lebih dulu untuk memvalidasi input. Jika ada input kosong/salah, langsung dikembalikan error `422`.
4. **`UserController`** memanggil `UserService` untuk memproses data.
5. **`UserResource`** menata data agar rapi dan aman.
6. **`ApiResponse`** membungkusnya menjadi JSON standar (`success`, `message`, `data`).

---

## 6. Cheat Sheet Command Artisan & Fungsinya

Berikut kumpulan perintah Artisan yang sering kamu gunakan saat mendevelop fitur:

### 📡 Menjalankan Server & Cek Route
* **`php artisan serve`**  
  Menjalankan local server Laravel.
* **`php artisan serve --port=8001`**  
  Menjalankan server di port tertentu jika port 8000 sedang bentrok.
* **`php artisan route:list`**  
  Melihat semua daftar URL/route yang terdaftar di aplikasi.
* **`php artisan route:list --path=api`**  
  Menyaring daftar route agar hanya menampilkan endpoint API saja.
* **`php artisan route:clear`**  
  Menghapus cache route (gunakan jika route yang baru kamu tulis tidak mau terbaca).

### 🛠️ Membuat Komponen Kode Baru
* **`php artisan make:controller NamaFolder/NamaController --api`**  
  Membuat controller khusus API (langsung berisi 5 method CRUD: `index`, `store`, `show`, `update`, `destroy`).  
  *Contoh:* `php artisan make:controller Product/ProductController --api`
* **`php artisan make:request NamaFolder/NamaRequest`**  
  Membuat Form Request untuk memvalidasi data form/JSON dari client.  
  *Contoh:* `php artisan make:request Product/StoreProductRequest`
* **`php artisan make:resource NamaFolder/NamaResource`**  
  Membuat API Resource untuk menyaring dan memformat output data JSON.  
  *Contoh:* `php artisan make:resource Product/ProductResource`
* **`php artisan make:model NamaModel -m`**  
  Membuat Model Eloquent database sekaligus file migrasinya (`-m`).

---

## 7. Menjalankan Automated Test

Aplikasi ini sudah dilengkapi dengan unit & feature test untuk memastikan seluruh endpoint berjalan tanpa error:

```bash
# Menjalankan seluruh test dalam project:
php artisan test

# Menjalankan test khusus fitur User CRUD:
php artisan test --filter=UserCrudTest
```

---

*Selamat belajar & bereksplorasi! Jangan ragu membuat branch baru untuk latihan fitur-fitur berikutnya.* 🚀
