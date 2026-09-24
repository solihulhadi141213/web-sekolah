# API Testimonials

Mengikuti pola Teachers dan Fasilitas. Data memakai tabel `testimonials` dan `file_manager`. Semua endpoint memerlukan `Authorization: Bearer <token>`. POST/PUT memakai `Content-Type: application/json`; OPTIONS mengembalikan 204.

| Endpoint | Method | Fungsi |
| --- | --- | --- |
| `get_testimonials.php` | GET | Daftar, pencarian, pagination, metadata dan URL gambar |
| `add_testimonials.php` | POST | Tambah testimonial dengan gambar opsional |
| `update_testimonials.php` | PUT | Ubah nama orang tua dan kutipan |
| `delete_testimonials.php?id=1` | DELETE | Hapus testimonial dan tangani file terkait |
| `update_image.php` | PUT | Ganti atau lepas gambar |
| `update_sort_order.php` | PUT | Tukar posisi UP/DOWN |

## Tambah testimonial

`parent_name` wajib berupa teks tidak kosong, maksimal 100 karakter. `quote` wajib berupa teks tidak kosong, maksimal 65535 byte UTF-8 sesuai kapasitas kolom TEXT. `sort_order` otomatis terbesar + 1, dimulai dari 1 jika tabel kosong.

Tanpa gambar:

```json
{"parent_name":"Siti Nurhasanah","quote":"Anak kami semakin semangat belajar."}
```

Field gambar juga boleh kosong atau null:

```json
{"parent_name":"Siti Nurhasanah","quote":"Anak kami semakin semangat belajar.","file_source":"","base64":""}
```

Jika seluruh field gambar kosong, tidak ada file yang dibuat dan `id_file_manager`, `file_source`, serta `file_metadata` bernilai null. Sumber valid yang dikirim tanpa isi gambar juga dianggap tanpa unggahan. Field gambar non-string selain null dan sumber tidak dikenal ditolak.

Dengan gambar:

```json
{"parent_name":"Siti Nurhasanah","quote":"Anak kami semakin semangat belajar.","file_source":"Local Directory","base64":"<base64 lengkap gambar>"}
```

`Local Directory`, `Cloudinary`, dan `Imagekit` memakai `base64`. `External Link` memakai `image_url`:

```json
{"parent_name":"Siti Nurhasanah","quote":"Anak kami semakin semangat belajar.","file_source":"External Link","image_url":"https://example.com/foto.jpg"}
```

Gambar yang diisi wajib menyertakan `file_source` dan lolos validasi helper `ImageStorage`. File lokal berada di `assets/img/local_directory`; folder provider `web_sekolah/testimonials`. Jika verifikasi External Link nonaktif, hanya format URL dan ekstensi diperiksa. Metadata mengikuti `Image-Metadata-Explanation.md`.

## Daftar

```text
get_testimonials.php?limit=12&page=1&order_by=sort_order&short_by=ASC&keyword=belajar
```

- `limit`: 1–100, default 12; `page`: integer positif, default 1.
- `order_by`: `id`, `parent_name`, `quote`, `sort_order`.
- `short_by`: ASC/DESC, default ASC.
- `keyword`: mencari id, nama, dan kutipan; `%`, `_`, `!` diperlakukan literal.

Respons berisi `data`, `pagination`, dan `filter`. Tiap item memuat `id`, `parent_name`, `quote`, `sort_order`, `id_file_manager`, `file_source`, objek `file_metadata`, dan `image_url`. URL lokal mengikuti root instalasi saat ini; tanpa gambar, field gambar bernilai null.

## Ubah teks

```json
{"id":1,"parent_name":"Siti Nurhasanah","quote":"Terima kasih atas pendampingan belajar anak kami."}
```

Hanya ketiga field tersebut diterima dan wajib diisi. ID harus integer positif sampai 2147483647. Gambar dan urutan tidak diubah.

## Ganti atau lepas gambar

Kirim ke `update_image.php`:

```json
{"id":1,"file_source":"Local Directory","base64":"<base64 lengkap gambar>"}
```

```json
{"id":1,"file_source":"External Link","image_url":"https://example.com/foto-baru.jpg"}
```

Untuk mengosongkan gambar yang sudah tersimpan, gunakan aksi eksplisit:

```json
{"id":1,"file_source":"DELETE","base64":""}
```

Gambar baru diunggah sebelum transaksi. File lama dibersihkan setelah relasi baru di-commit. Kegagalan pembersihan setelah commit menghasilkan respons sukses dengan `warnings` dan `old_file_deletion: cleanup_pending`. Konflik penggantian bersamaan menghasilkan 409. File bersama tetap dipertahankan, termasuk yang dipakai testimonial lain, guru, fasilitas, galeri, dan hero.

Storage nonaktif tidak dipanggil dan metadata dipertahankan. External Link tidak menghapus aset pemilik URL. Kegagalan penghapusan provider aktif membatalkan DELETE. File fisik yang telah dihapus tidak dapat dipulihkan oleh rollback SQL; kegagalan commit setelah penghapusan dicatat untuk rekonsiliasi.

## Urutan

```json
{"id":2,"sort_order":"UP"}
```

Kirim ke `update_sort_order.php`; gunakan DOWN untuk turun. Pertukaran mengikuti tetangga terdekat, termasuk nomor bercelah. Posisi pertama tidak bisa UP dan terakhir tidak bisa DOWN (409). Urutan 0 didukung; null, negatif, dan duplikat yang terlibat ditolak.

## Pengujian

`php tests/testimonials_test.php`

Menggunakan tabel MySQL sementara dan file lokal khusus pengujian. Provider Cloudinary/Imagekit disimulasikan. Mencakup gambar opsional, batas teks UTF-8, CRUD, pagination, pencarian, urutan, rollback, konflik gambar, dan perlindungan file bersama.
