# DELETE delete_teachers.php?id=1

Gunakan header `Authorization: Bearer <token>`. ID harus berupa bilangan bulat positif sesuai kolom INT UNSIGNED. ID tidak ditemukan menghasilkan HTTP 404.

Endpoint memeriksa guru, relasi `file_manager`, metadata, dan referensi file dari tabel lain sebelum menghapus.

| Kondisi | File fisik | Catatan file_manager |
| --- | --- | --- |
| Guru tanpa file | Tidak ada operasi | Tidak ada operasi |
| File digunakan data lain | Dipertahankan | Dipertahankan |
| Local Directory aktif | Dihapus dari assets/img/local_directory | Dihapus |
| Cloudinary/Imagekit aktif | Dihapus menggunakan public_id/file_id | Dihapus |
| Storage nonaktif | Tidak ada pemanggilan API/penghapusan lokal | Dipertahankan agar aset tetap tercatat |
| External Link | Tidak menghapus file milik pemilik URL | Dihapus |

File lokal yang sudah hilang dan respons not-found provider dianggap sudah terhapus. Metadata tidak valid pada storage aktif menghasilkan HTTP 409. Kegagalan provider menghasilkan HTTP 502; data guru dan catatan file dipertahankan melalui rollback.

Respons sukses HTTP 200 memuat `data.id`, `data.id_file_manager`, `data.file_deletion`, dan `data.file_metadata_deleted`. Status file: `no_file`, `deleted`, `not_found`, `external_link`, `skipped_inactive`, atau `retained_shared`.

Transaksi menjamin konsistensi perubahan database, tetapi tidak bisa mengembalikan file yang telah dihapus. Jika file berhasil dihapus lalu commit database gagal, endpoint mengembalikan error dan mencatat ID untuk rekonsiliasi; permintaan dapat dicoba kembali setelah memeriksa kondisi database. Timeout provider juga dapat memiliki hasil penghapusan yang belum pasti.

Pengujian: `php tests/delete_teachers_test.php`. Hanya tabel sementara dan file lokal khusus pengujian yang digunakan; panggilan Cloudinary dan Imagekit disimulasikan.
