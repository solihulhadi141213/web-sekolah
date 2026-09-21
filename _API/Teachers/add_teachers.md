# POST add_teachers.php

Header wajib: `Authorization: Bearer <token>` dan `Content-Type: application/json`.

Field `name`, `role`, dan `subject` wajib berupa teks tidak kosong, maksimal 100 karakter.
Tanpa gambar, jangan kirim `file_source`, `base64`, atau `image_url`.

| file_source | Field gambar | Konfigurasi |
| --- | --- | --- |
| Local Directory | base64 | local_directory_* |
| External Link | image_url | external_link_* |
| Cloudinary | base64 | cloudinary_* |
| Imagekit | base64 | imagekit_* |

- Base64 lengkap atau data URI diterima. Contoh terpotong dengan `...` tidak valid.
- Storage harus berstatus `Active`. Format dan ukuran mengikuti konfigurasi masing-masing.
- Isi gambar diperiksa dengan Fileinfo dan GD, lalu unggahan di-encode ulang. Hanya JPG/JPEG, PNG, WebP, dan GIF yang didukung jika diizinkan konfigurasi. GIF animasi menjadi gambar satu frame.
- Dimensi maksimal 6000 piksel per sisi dan 8 megapiksel total.
- File lokal disimpan di `assets/img/local_directory` dengan nama acak. Ukuran metadata dihitung dalam KB (byte / 1024).
- External Link tanpa protokol diberi awalan HTTPS. Dengan `external_link_verify=true`, isi gambar diunduh dan diperiksa (maksimal 5 MiB, tiga redirect, hanya alamat jaringan publik). Dengan `false`, tidak ada akses jaringan: hanya format URL dan ekstensi diperiksa, sehingga isi aktual URL tidak dapat dipastikan.
- Metadata mengikuti `Image-Metadata-Explanation.md`. `creat_at` dan `file_upload_at` menggunakan UTC.
- `file_manager` dan `teachers` disimpan dalam satu transaksi. Kegagalan database yang berhasil di-rollback memicu pembersihan unggahan. Jika hasil commit tidak pasti, aset dipertahankan untuk rekonsiliasi.
- `sort_order` menggunakan nilai terbesar saat ini + 1; bukan nomor urut unik untuk request paralel.

Respons sukses HTTP 201 memuat `status`, `message`, `requested_by_app`, dan `data`: identitas guru, `id_file_manager`, `sort_order`, `file_source`, serta `file_metadata`. Tanpa gambar, ketiga field file bernilai null.

Kesalahan: 400 payload/gambar tidak valid, 401 JWT, 403 storage nonaktif, 405 method, 413 ukuran, 415 tipe file/content type, 422 URL tidak dapat diakses, 500 database/konfigurasi server, 502 provider storage.

## Pengujian

Jalankan `php tests/add_teachers_test.php` dengan PHP CLI yang memiliki PDO MySQL, Fileinfo, GD, dan cURL serta Composer dependencies. Pengujian memakai koneksi dari konfigurasi dengan tabel sementara berdasarkan `DB/web_sekolah.sql`; tabel aplikasi tidak diubah. File lokal uji dibersihkan. Cloudinary, Imagekit, dan unduhan eksternal memakai respons simulasi; pengujian ini tidak memverifikasi kredensial atau koneksi provider sungguhan.
