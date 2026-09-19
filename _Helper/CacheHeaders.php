<?php
// Production boleh menyimpan respons, tetapi wajib validasi agar pergantian
// ke DEVELOPMENT langsung terbaca pada permintaan berikutnya.
if ($isDevelopment) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
} else {
    header('Cache-Control: public, no-cache');
    header_remove('Pragma');
    header_remove('Expires');
}
