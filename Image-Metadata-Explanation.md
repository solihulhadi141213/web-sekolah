## Standar Format JSON pada file_metadata
Pada setiap informasi metadata yang digunakan di database project ini akan selalu memuat informasi berikut berdasarkan sumber file nya (File Source)

------------------------------------------------------------
Local Directory
```json
{
    "file_name" : "example.jpg",
    "file_size" : 5000,
    "file_mime_type" : "image/jpeg",
    "file_extension" : ".jpeg",
    "file_upload_at" : "2026-09-20T03:49:15.123Z"
}
 ```
Penjelasan :
Metode 'Local Directory' merupakan file yang dikelola pada directory assets/img/local_directory. Metadata yang digunakan menyimpan informasi file.

1. file_name : Nama File Yang Disimpan (generate agar unik)
2. file_size : Ukuran file dalam kilobyte
3. file_mime_type : tipe file (contoh image/jpeg untuk gambar jpg, atau video/mp4 untuk vidio). Ini untuk memberitahu sistem bagaimana menampilkan file pada halaman html. Misalnya jika image maka tinggal menggunakan tag 'img' lalu jika vidio maka sistem tinggal menggunakan tag 'video'
4. file_extension : Memastikan extension yang digunakan benar
5. file_upload_at : waktu di upload (ISO 8601)
------------------------------------------------------------
External Link
```json
{
    "file_url" : "https://ik.imagekit.io/dhiforester/default-image.jpg",
    "file_type" : "Image"
}
 ```
Penjelasan : 

Apabila menggunakan external link, maka sistem hanya cukup tahu URL dan tipe filenya untuk memberitahu sistem bagaimana menampilkannya pada halaman html.

------------------------------------------------------------
Cloudinary
```json
{
    "url" : "https://res.cloudinary.com/dweltjc1a/image/upload/v1789758327/web_sekolah/teachers/c400191992f17ac91af04216972132cb.jpg",
    "public_id" : "web_sekolah/teachers/b60ba25684658ca6cebe3abbac772f3c",
    "format" : "jpg",
    "resource_type" : "Image",
    "file_upload_at" : "2026-09-20T03:49:15.123Z"
}
 ```
 Penjelasan : 

 Sistem dapat terintegrasi dengan platform Cloudinary. Dimana client dapat mengelola asset melalui platform Cloudinary dan sistem menyimpan metadatanya. 
 1. url : Adalah URL file yang akan digunakan
 2. public_id : Sebagai identifikasi file secara spesifik pada penyimpanan Cloudinary. Jika file akan dihapus maka ID ini digunakan untuk request penghapusan.
 3. format : tipe file yang digunakan, bisa bernilai jpeg, png, webm dll.
 4. resource_type : Untuk memberitahu sistem ini file apa dan menentukan bagaimana sistem menampilkannya pada halaman html web nantinya.
5. file_upload_at : waktu di upload (ISO 8601)
 ------------------------------------------------------------
 Imagekit
```json
{
    "url" : "https://ik.imagekit.io/dhiforester/default-image.jpg",
    "file_id" : "6aada1cdead997d09a8f230d",
    "format" : "JPG",
    "resource_type" : "Image",
    "file_upload_at" : "2026-09-20T03:49:15.123Z"
}
 ```
 Penjelasan : 

 Sistem dapat terintegrasi dengan platform Imagekit. Dimana client dapat mengelola asset melalui platform Imagekit dan sistem menyimpan metadatanya. 
 1. url : Adalah URL file yang akan digunakan
 2. file_id : Sebagai identifikasi file secara spesifik pada penyimpanan Imagekit. Jika file akan dihapus maka ID ini digunakan untuk request penghapusan.
 3. format : tipe file yang digunakan, bisa bernilai jpeg, png, webm dll.
 4. resource_type : Untuk memberitahu sistem ini file apa dan menentukan bagaimana sistem menampilkannya pada halaman html web nantinya.
 5. file_upload_at : waktu di upload (ISO 8601)