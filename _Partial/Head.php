<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="<?= $escape($pageDescription) ?>"/>
    <title><?= $escape($pageTitle) ?></title>
    <meta name="robots" content="<?= $pageFile === '_Page/Error/PageNotFound.php' ? 'noindex, follow' : 'index, follow, max-image-preview:large' ?>" />
    <meta name="theme-color" content="#2f1b77" />
    <meta property="og:type" content="website" />
    <meta property="og:locale" content="id_ID" />
    <meta property="og:site_name" content="MI Plus Annur Kuningan" />
    <meta property="og:title" content="<?= $escape($pageTitle) ?>" />
    <meta property="og:description" content="<?= $escape($pageDescription) ?>" />
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:title" content="<?= $escape($pageTitle) ?>" />
    <meta name="twitter:description" content="<?= $escape($pageDescription) ?>" />

    <!-- Gaya awal tersedia sebelum CSS eksternal selesai dimuat. -->
    <style>
        html.page-loading { overflow: hidden; }
        html.page-loading body { visibility: hidden; }
        html.page-loading::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 2147483646;
            background: #faf8ff;
        }
        html.page-loading::after {
            content: "";
            position: fixed;
            top: calc(50% - 48px);
            left: calc(50% - 20px);
            z-index: 2147483647;
            width: 40px;
            height: 40px;
            box-sizing: border-box;
            border: 3px solid #ded7ef;
            border-top-color: #2f1b77;
            border-radius: 50%;
            animation: page-loading-spin .8s linear infinite;
        }
        .page-loading-status { display: none; }
        html.page-loading .page-loading-status {
            display: block;
            visibility: visible;
            position: fixed;
            top: 50%;
            left: 0;
            right: 0;
            z-index: 2147483647;
            margin: 16px 0 0;
            text-align: center;
            color: #2f1b77;
            font: 500 15px/1.5 system-ui, sans-serif;
        }
        @keyframes page-loading-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) {
            html.page-loading::after { animation: none; }
        }
    </style>
    <script>
        (function () {
            'use strict';

            const root = document.documentElement;
            const preloaderDelay = 500; // Jeda penutup setelah halaman siap, dalam milidetik.
            let finished = false;

            function revealPage() {
                if (finished) return;
                finished = true;
                window.clearTimeout(fallbackTimer);
                root.classList.remove('page-loading');
                document.getElementById('pageLoadingStatus')?.remove();
            }

            // Aktifkan penutup hanya jika JavaScript berjalan.
            const fallbackTimer = window.setTimeout(revealPage, 10000);
            root.classList.add('page-loading');

            // Dipanggil setelah seluruh script selesai dimuat atau dicoba ulang.
            window.finishPageLoading = function () {
                const fontsReady = document.fonts ? document.fonts.ready : Promise.resolve();
                fontsReady.catch(function () {}).then(function () {
                    // Beri browser waktu menyelesaikan tata letak slider dan font.
                    window.requestAnimationFrame(function () {
                        window.requestAnimationFrame(function () {
                            window.setTimeout(revealPage, preloaderDelay);
                        });
                    });
                });
            };

            // Jangan kembalikan penutup saat halaman dipulihkan melalui tombol Back.
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) revealPage();
            });
        })();
    </script>

    <link rel="preconnect" href="https://6aa97c5b9422e77b387ff09b.imgix.net" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&family=Space+Grotesk:wght@500;600&display=swap" rel="stylesheet" />

    <link rel="icon" href="<?= $escape($assetUrl('assets/img/favicon/favicon.ico')) ?>" sizes="any" />
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $escape($assetUrl('assets/img/favicon/favicon-32x32.png')) ?>" />
    <link rel="icon" type="image/png" sizes="16x16" href="<?= $escape($assetUrl('assets/img/favicon/favicon-16x16.png')) ?>" />
    <link rel="apple-touch-icon" sizes="180x180" href="<?= $escape($assetUrl('assets/img/favicon/apple-touch-icon.png')) ?>" />
    <link rel="manifest" href="<?= $escape($assetUrl('assets/img/favicon/site.webmanifest')) ?>" />

    <link rel="stylesheet" href="<?= $escape($assetUrl('node_modules/bootstrap/dist/css/bootstrap.min.css?v=2')) ?>" />
    <link rel="stylesheet" href="<?= $escape($assetUrl('node_modules/bootstrap-icons/font/bootstrap-icons.css?v=2')) ?>" />
    <link rel="stylesheet" href="<?= $escape($assetUrl('node_modules/swiper/swiper-bundle.min.css?v=2')) ?>" />
    <link rel="stylesheet" href="<?= $escape($assetUrl('assets/fonts/site-fonts.css?v=1')) ?>" />
    <link rel="stylesheet" href="<?= $escape($assetUrl('assets/css/style.css?v=22')) ?>" />
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "School",
            "name": "MI Plus Annur Kuningan",
            "alternateName": "MI Plus An-Nur",
            "description": "Kenali MI Plus Annur Kuningan di Lebakwangi: profil madrasah, program pendidikan, guru, fasilitas, kegiatan siswa, dan informasi penerimaan murid baru.",
            "telephone": "+62232878112",
            "email": "info@mi-plus-annur.sch.id",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "Jl. Buahgmana No.234, Manggari, Lebakwangi",
                "addressLocality": "Kabupaten Kuningan",
                "addressRegion": "Jawa Barat",
                "postalCode": "45574",
                "addressCountry": "ID"
            },
            "sameAs": [
                "https://www.instagram.com/mi_plusannur/",
                "https://www.facebook.com/p/Mi-Plus-An-Nur-Full-Day-School-100057043797029/",
                "https://miplusannur.wordpress.com/",
                "https://www.youtube.com/@miplusan-nurkuningan2513",
                "https://www.tiktok.com/@miplusannur"
            ]
        }
    </script>
</head>
