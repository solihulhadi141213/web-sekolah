# PUT update_teachers_image.php

Header: `Authorization: Bearer <token>` dan `Content-Type: application/json`.

Hapus gambar:

```json
{"id":5,"file_source":"DELETE","base64":""}
```

Ganti gambar:

```json
{"id":5,"file_source":"Local Directory","base64":"<base64 lengkap>"}
```

Sumber baru juga mendukung `Cloudinary` dan `Imagekit` dengan `base64`, serta `External Link` dengan `image_url` (tanpa field base64). Validasi gambar, ukuran, format, URL, dan status storage memakai `TeacherImageStorage` seperti endpoint add.

Urutan penggantian:

1. Validasi guru dan keberadaan metadata lama.
2. Unggah gambar baru tanpa menghapus gambar lama.
3. Kunci guru dan periksa apakah referensi gambar berubah sejak request dimulai. Konflik menghasilkan 409 dan unggahan baru dibersihkan.
4. Catat file baru dan ubah hanya `teachers.id_file_manager` dalam satu transaksi.
5. Setelah commit berhasil, bersihkan file dan metadata lama jika tidak dipakai data lain dan storage aktif.

Jika upload atau transaksi pembaruan gagal, relasi serta gambar lama dipertahankan dan unggahan baru dibersihkan selama rollback dapat dipastikan. Hasil commit yang tidak pasti dicatat untuk rekonsiliasi; aset baru tidak dihapus secara membabi buta.

Jika gambar baru sudah terpasang tetapi pembersihan lama gagal, respons tetap HTTP 200 dengan `warnings` dan `old_file_deletion: cleanup_pending`. Metadata lama dipertahankan untuk rekonsiliasi; jangan mengulang unggahan hanya untuk membersihkan file lama.

Untuk DELETE, provider aktif dihapus sebelum relasi dilepas. Jika provider gagal, perubahan database dibatalkan. Jika tidak ada gambar, DELETE tetap sukses. Storage nonaktif, file bersama, atau aset lama yang menjadi target External Link baru dipertahankan. External Link hanya dihapus catatannya; file milik pemilik URL tidak dihapus.

Respons memuat `data.id`, `id_file_manager`, `file_source`, `file_metadata`, `previous_id_file_manager`, `old_file_deletion`, `old_file_metadata_deleted`, serta `warnings`. Tiga field file baru bernilai null untuk DELETE. Informasi guru dan sort_order tidak diubah.

Transaksi database tidak dapat mengembalikan file yang sudah dihapus. Kegagalan commit setelah DELETE atau timeout provider memerlukan pemeriksaan status data/file, sebagaimana endpoint delete_teachers.

Pengujian: `php tests/update_teachers_image_test.php`. Menggunakan tabel sementara, file lokal khusus uji, dan simulasi provider. Tidak melakukan unggahan/penghapusan di akun Cloudinary atau Imagekit sungguhan.
