# API Aktivitas

Mengelola tabel `student_activities`, tanpa file gambar. Semua endpoint memerlukan `Authorization: Bearer <token>`. POST/PUT menggunakan `Content-Type: application/json`. OPTIONS mengembalikan 204.

| Endpoint | Method | Fungsi |
| --- | --- | --- |
| `get_aktivitas.php` | GET | Daftar, filter kategori, pencarian, pagination |
| `add_aktivitas.php` | POST | Tambah aktivitas |
| `update_aktivitas.php` | PUT | Ubah kategori, judul, dan deskripsi |
| `delete_aktivitas.php?id=1` | DELETE | Hapus aktivitas |
| `update_sort_order.php` | PUT | Tukar posisi UP/DOWN dalam kategori yang sama |

## Validasi

- `category`: wajib, salah satu `organisasi`, `ekstrakurikuler`, `prestasi`.
- `title`: wajib berupa teks tidak kosong, maksimal 100 karakter Unicode.
- `description`: wajib berupa teks tidak kosong, maksimal 65535 **byte UTF-8**, sesuai kapasitas kolom TEXT. Karakter multibyte menggunakan lebih dari satu byte.
- Teks dipangkas spasi di awal/akhir sebelum disimpan. Null, array, dan tipe non-string ditolak untuk field teks.
- `id`: integer positif sampai 2147483647 untuk PUT; DELETE menerima parameter query id dengan batas sama.
- POST/PUT data utama menolak field tambahan, termasuk field gambar dan sort_order.
- Validasi gagal menghasilkan 400, Content-Type salah 415, data tidak ditemukan 404, dan konflik urutan 409. JWT mengikuti helper bersama proyek.

## Tambah

```json
{"category":"ekstrakurikuler","title":"Pramuka","description":"Kegiatan pengembangan kemandirian dan kerja sama siswa."}
```

Urutan otomatis terbesar + 1 **dalam kategori yang dipilih**, mulai dari 1 jika kategori kosong. Respons sukses 201, dengan id, category, title, description, dan sort_order.

## Update

```json
{"id":1,"category":"ekstrakurikuler","title":"Pramuka Sekolah","description":"Kegiatan Pramuka setiap pekan."}
```

Keempat field wajib. Kategori tetap mempertahankan urutan lama; perubahan kategori memindahkan aktivitas ke urutan terakhir kategori tujuan. Nomor kategori lama tidak dipadatkan. Payload identik tetap sukses.

## Daftar

```text
get_aktivitas.php?category=ekstrakurikuler&limit=12&page=1&order_by=sort_order&short_by=ASC&keyword=pramuka
```

- `category` opsional; kosong menampilkan seluruh kategori.
- `limit`: 1–100, default 12; `page`: integer positif, default 1.
- `order_by`: id, category, title, description, sort_order; default sort_order.
- `short_by`: ASC/DESC, default ASC, mengikuti ejaan API Teachers.
- Keyword mencari id, category, title, description. `%`, `_`, `!` diperlakukan literal.
- Respons memuat `data`, `pagination`, dan `filter`, termasuk kategori yang digunakan.

## Ubah urutan

```json
{"id":2,"sort_order":"UP"}
```

Kirim ke `update_sort_order.php`; DOWN untuk turun. Posisi ditukar dengan tetangga terdekat pada kategori yang sama dalam satu transaksi. Celah urutan didukung. Posisi pertama tidak bisa UP dan terakhir tidak bisa DOWN (409), meskipun ada data di kategori lain. Urutan 0 didukung sesuai schema; null, negatif, dan duplikat yang terlibat ditolak.

## Pengujian

`php tests/aktivitas_test.php`

Menggunakan tabel MySQL sementara sesuai schema proyek; tidak mengubah aktivitas asli. Mencakup CRUD, mandatory field, batas Unicode/TEXT, pagination, kategori, perpindahan kategori, dan batas pertukaran urutan.
