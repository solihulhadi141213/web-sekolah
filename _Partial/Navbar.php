<a class="skip-link" href="#mainContent">Lewati ke konten utama</a>
<nav class="navbar navbar-expand-xl sticky-top custom-navbar" aria-label="Navigasi utama">
    <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $escape($pageUrl()) ?>">
        <div class="brand-mark">
        <img src="<?= $escape($assetUrl('assets/img/favicon/android-chrome-192x192.png')) ?>" alt="Logo MI Plus Annur" width="192" height="192" decoding="async" />
        </div>
        <div>
        <div class="brand-name">MI PLUS ANNUR</div>
        <small>Kuningan</small>
        </div>
    </a>

    <button
        class="navbar-toggler"
        type="button"
        data-bs-toggle="offcanvas"
        data-bs-target="#offcanvasMenu"
        aria-controls="offcanvasMenu"
        aria-label="Buka menu navigasi"
    >
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="mainNav">
        <ul class="navbar-nav align-items-center gap-xl-1">
        <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#home' : $siteUrl('index.php#home')) ?>">Beranda</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#leadership' : $siteUrl('index.php#leadership')) ?>">Selayang Pandang</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#profile' : $siteUrl('index.php#profile')) ?>">Profil</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $escape($pageUrl('Guru')) ?>"<?= $route === 'Guru' ? ' aria-current="page"' : '' ?>>Guru</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $escape($pageUrl('Testimonial')) ?>"<?= $route === 'Testimonial' ? ' aria-current="page"' : '' ?>>Testimonial</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#student' : $siteUrl('index.php#student')) ?>">Kesiswaan</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#facility' : $siteUrl('index.php#facility')) ?>">Fasilitas</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#news' : $siteUrl('index.php#news')) ?>">Berita</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#gallery' : $siteUrl('index.php#gallery')) ?>">Galeri</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#admission' : $siteUrl('index.php#admission')) ?>">PPDB</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#contact' : $siteUrl('index.php#contact')) ?>">Kontak</a></li>
        <!-- <li class="nav-item ms-xl-1">
            <a class="btn btn-primary btn-sm rounded-pill px-3" href="<?= $escape($route === 'Beranda' ? '#admission' : $siteUrl('index.php#admission')) ?>">Daftar Sekarang</a>
        </li> -->
        </ul>
    </div>
    </div>
</nav>

<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasMenu" aria-labelledby="offcanvasMenuLabel">
    <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasMenuLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#home' : $siteUrl('index.php#home')) ?>">Beranda</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#leadership' : $siteUrl('index.php#leadership')) ?>">Selayang Pandang</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#profile' : $siteUrl('index.php#profile')) ?>">Profil</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= $escape($pageUrl('Guru')) ?>"<?= $route === 'Guru' ? ' aria-current="page"' : '' ?>>Guru</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= $escape($pageUrl('Testimonial')) ?>"<?= $route === 'Testimonial' ? ' aria-current="page"' : '' ?>>Testimonial</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#student' : $siteUrl('index.php#student')) ?>">Kesiswaan</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#facility' : $siteUrl('index.php#facility')) ?>">Fasilitas</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#news' : $siteUrl('index.php#news')) ?>">Berita</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#gallery' : $siteUrl('index.php#gallery')) ?>">Galeri</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#admission' : $siteUrl('index.php#admission')) ?>">PPDB</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= $escape($route === 'Beranda' ? '#contact' : $siteUrl('index.php#contact')) ?>">Kontak</a></li>
        </ul>
    </div>
</div>