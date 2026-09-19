<?php
    // Halaman default ketika URL tidak menentukan nama halaman.
    $defaultPage = [
        'file'        => '_Page/Beranda/Beranda.php',
        'title'       => 'MI Plus Annur Kuningan | Profil & Penerimaan Murid Baru',
        'description' => 'Kenali MI Plus Annur Kuningan di Lebakwangi: profil madrasah, program pendidikan, guru, fasilitas, kegiatan siswa, dan informasi penerimaan murid baru.',
        'class'       => '',
    ];

    // Daftarkan halaman baru di sini beserta file konten dan metadatanya.
    // Nama route mendukung susunan bertingkat, misalnya 'Berita/Detail'.
    $routes = [
        'Beranda' => $defaultPage,

        'Guru' => [
            'file'        => '_Page/Guru/Guru.php',
            'title'       => 'Guru & Tenaga Pendidikan | MI Plus Annur Kuningan',
            'description' => 'Kenali guru dan tenaga pendidikan MI Plus Annur Kuningan.',
            'class'       => 'teachers-page',
        ],

        'Testimonial' => [
            'file'        => '_Page/Testimonial/Testimonial.php',
            'title'       => 'Testimonial | MI Plus Annur Kuningan',
            'description' => 'Cerita dan pengalaman keluarga MI Plus Annur Kuningan.',
            'class'       => 'teachers-page testimonials-page',
        ],
    ];

    // Utamakan PATH_INFO (index.php/Guru); query route tetap didukung.
    $requestedPage = $_SERVER['PATH_INFO'] ?? '';

    if ($requestedPage === '' || $requestedPage === '/') {
        $requestedPage = $_GET['route'] ?? '';
    }

    // Siapkan nilai awal sebelum memilih halaman dan status respons.
    $pageFile = '';
    $route    = '';
    $page     = null;
    $status   = 200;

    // Nama halaman harus berupa teks, bukan parameter array.
    if (!is_string($requestedPage)) {
        $status = 400;
    } else {
        $route = trim($requestedPage, '/');

        if ($route === '') {
            // Tanpa nama halaman: tampilkan Beranda default.
            $route = 'Beranda';
            $page  = $defaultPage;
        } elseif (isset($routes[$route])) {
            // Nama halaman ditentukan: pilih dari daftar route yang tersedia.
            $page = $routes[$route];
        } else {
            $status = 404;
        }
    }

    // Pastikan file halaman terdaftar tersedia sebelum dimuat oleh index.php.
    if ($page !== null && !is_file(dirname(__DIR__) . '/' . $page['file'])) {
        $status = 404;
        $page   = null;
    }

    // Permintaan tidak valid atau halaman tidak tersedia memakai halaman error.
    if ($page === null) {
        $page = [
            'file'        => '_Page/Error/PageNotFound.php',
            'title'       => ($status === 400 ? 'Permintaan tidak valid' : 'Halaman tidak ditemukan') . ' | MI Plus Annur Kuningan',
            'description' => 'Halaman yang diminta tidak tersedia.',
            'class'       => '',
        ];
    }

    // Kirim status HTTP sebelum HTML, lalu sediakan data untuk template halaman.
    http_response_code($status);

    $pageFile        = $page['file'];
    $pageTitle       = $page['title'];
    $pageDescription = $page['description'] ?? '';
    $pageClass       = $page['class'] ?? '';
