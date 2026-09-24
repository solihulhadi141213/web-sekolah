# API Fasilitas

Semua endpoint menggunakan `Authorization: Bearer <token>`. Request POST/PUT menggunakan `Content-Type: application/json`. Koneksi database melalui helper `Database`; struktur data mengikuti tabel `facilities` dan `file_manager`.

| Endpoint | Method | Kegunaan |
| --- | --- | --- |
| get_fasilitas.php | GET | Daftar, pencarian, pengurutan, pagination, dan metadata gambar |
| add_fasilitas.php | POST | Tambah fasilitas, dengan atau tanpa gambar |
| update_fasilitas.php | PUT | Ubah title dan description |
| delete_fasilitas.php?id=1 | DELETE | Hapus fasilitas dan tangani file terkait |
| update_image.php | PUT | Ganti atau lepas gambar |
| update_sort_order.php | PUT | Tukar posisi UP/DOWN |

## Data dan gambar

`title` wajib berupa teks tidak kosong, maksimal 100 karakter; `description` maksimal 255 karakter. ID berupa integer positif sampai 2147483647. Tambah fasilitas tanpa gambar:

```json
{"title":"Perpustakaan","description":"Ruang membaca dan koleksi buku siswa."}
```

Dengan gambar lokal:

```json
{"title":"Perpustakaan","description":"Ruang membaca dan koleksi buku siswa.","file_source":"Local Directory","base64":"<base64 lengkap>"}
```

`file_source` juga menerima `Cloudinary` atau `Imagekit` dengan field `base64`. Untuk `External Link`, gunakan `image_url` tanpa field `base64`. URL tanpa protokol dinormalisasi menjadi HTTPS. Metadata mengikuti `Image-Metadata-Explanation.md`.

Unggahan memeriksa status storage, format, ukuran, dan isi gambar menggunakan helper bersama `ImageStorage`. File lokal disimpan di `assets/img/local_directory`; folder provider menggunakan `web_sekolah/facilities`. Jika verifikasi external link dinonaktifkan, hanya format URL dan ekstensi diperiksa, bukan isi aktual URL.

Update informasi (gambar dan urutan tidak berubah):

```json
{"id":1,"title":"Perpustakaan Baru","description":"Ruang membaca yang telah diperluas."}
```

Ganti gambar melalui `update_image.php`:

```json
{"id":1,"file_source":"Local Directory","base64":"<base64 lengkap>"}
```

Lepas gambar:

```json
{"id":1,"file_source":"DELETE","base64":""}
```

Penggantian mengikuti urutan upload baru, commit metadata serta relasi baru, lalu pembersihan file lama. Kegagalan upload/database mempertahankan gambar lama dan membersihkan upload baru bila rollback dapat dipastikan. Jika pembersihan lama gagal setelah commit, gambar baru tetap terpasang; respons HTTP 200 menyertakan `warnings` dan `old_file_deletion: cleanup_pending`.

File yang dipakai guru, fasilitas lain, atau tabel terkait tetap dipertahankan. Provider nonaktif tidak dipanggil; metadata lama tetap disimpan. External Link tidak menghapus file milik pemilik URL. Penghapusan di provider aktif yang gagal membatalkan DELETE fasilitas/DELETE gambar. Penghapusan fisik tidak dapat dipulihkan oleh rollback SQL; kegagalan commit setelah file dihapus dicatat untuk rekonsiliasi.

## Daftar dan urutan

Contoh:

`get_fasilitas.php?limit=12&page=1&order_by=sort_order&short_by=ASC&keyword=perpustakaan`

- `limit`: 1-100, default 12; `page`: default 1.
- `order_by`: id, title, description, atau sort_order.
- `short_by`: ASC/DESC (nama parameter mengikuti API guru).
- `keyword`: mencari id, title, description; karakter %, _, ! diperlakukan sebagai teks biasa.
- Respons memuat `data`, `pagination`, dan `filter`. Setiap fasilitas memiliki `id_file_manager`, `file_source`, objek `file_metadata`, dan `image_url` turunan. URL file lokal relatif terhadap root instalasi sehingga mengikuti domain saat ini. Tanpa gambar, field gambar bernilai null.

Ubah urutan:

```json
{"id":2,"sort_order":"UP"}
```

Nilai arah UP/DOWN. Endpoint menukar posisi dengan tetangga terdekat, termasuk jika ada celah nomor. Posisi pertama tidak bisa UP dan posisi terakhir tidak bisa DOWN (409). Urutan 0 didukung sesuai default schema facilities; urutan null/negatif atau duplikat yang terlibat ditolak. Data baru dimulai pada urutan terbesar + 1 (minimal 1 jika tabel kosong).

## Pengujian

`php tests/fasilitas_test.php`

Menggunakan tabel MySQL sementara sesuai SQL proyek dan file lokal khusus pengujian. Provider Cloudinary/Imagekit disimulasikan sehingga tidak mengubah aset akun sungguhan. Helper `TeacherImageStorage` tetap tersedia dan menggunakan implementasi bersama untuk menjaga kompatibilitas API guru.
