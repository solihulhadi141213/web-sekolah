<?php
    // Muat konfigurasi serta fungsi pembentuk URL halaman dan aset.
    $config = require __DIR__ . '/_Config/config.php';
    require __DIR__ . '/_Helper/WebRuntime.php';

    // Tentukan header cache dan halaman tujuan sebelum mengirim HTML.
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/_Helper/CacheHeaders.php';
    require __DIR__ . '/_Partial/RoutingPage.php';

    // Pada production, bandingkan isi HTML dengan cache milik browser.
    if (!$isDevelopment && http_response_code() === 200) {
        ob_start(static function ($html) {
            $etag = '"' . hash('sha256', $html) . '"';
            header('ETag: ' . $etag);

            $cachedTags = array_map('trim', explode(',', $_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));

            // Isi belum berubah: browser dapat menggunakan salinan yang tersimpan.
            if (in_array($etag, $cachedTags, true) || in_array('*', $cachedTags, true)) {
                http_response_code(304);
                return '';
            }

            return $html;
        });
    }

    // Urutan pemuatan: pustaka antarmuka terlebih dahulu, lalu perilaku situs.
    $scriptSources = [
        $assetUrl('node_modules/bootstrap/dist/js/bootstrap.bundle.min.js'),
        $assetUrl('node_modules/swiper/swiper-bundle.min.js'),
        $assetUrl('assets/js/main.js'),
    ];

    // Amankan daftar URL saat disisipkan ke dalam blok JavaScript.
    $scriptSourcesJson = json_encode(
        $scriptSources,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
    );
?>
<!DOCTYPE html>
<html lang="id">
    <?php require __DIR__ . '/_Partial/Head.php'; ?>

    <body
        data-base-url="<?= $escape($siteUrl()) ?>"
        data-environment="<?= $isDevelopment ? 'DEVELOPMENT' : 'PRODUCTION' ?>"
    >
        <p id="pageLoadingStatus" class="page-loading-status" role="status" aria-live="polite">
            Memuat Halaman
        </p>

        <?php
            // Navigasi utama dan menu untuk perangkat seluler.
            require __DIR__ . '/_Partial/Navbar.php';
        ?>

        <main id="mainContent" class="<?= $escape($pageClass) ?>" tabindex="-1">
            <?php
                // File konten sudah ditentukan oleh RoutingPage.php.
                require __DIR__ . '/' . $pageFile;
            ?>
        </main>

        <?php
            // Informasi penutup serta modal galeri dan video.
            require __DIR__ . '/_Partial/Footer.php';
            require __DIR__ . '/_Partial/Modal.php';
        ?>

        <!-- Akses cepat untuk kembali ke atas dan menghubungi sekolah. -->
        <button type="button" class="back-to-top" aria-label="Kembali ke atas">
            <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M12 19V5M5 12l7-7 7 7" />
            </svg>
        </button>

        <a
            class="whatsapp-float"
            href="https://wa.me/6289601154726"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Hubungi melalui WhatsApp"
            title="Hubungi melalui WhatsApp"
        >
            <i class="bi bi-whatsapp" aria-hidden="true"></i>
        </a>

        <!-- Muat script berurutan; ulangi maksimal dua kali jika unduhan gagal. -->
        <script>
            (function () {
                'use strict';

                const sources = <?= $scriptSourcesJson ?>;

                function loadScript(index, attempt) {
                    if (index >= sources.length) {
                        window.finishPageLoading?.();
                        return;
                    }

                    const script = document.createElement('script');
                    const url = new URL(sources[index], document.body.dataset.baseUrl);

                    if (attempt > 0) {
                        url.searchParams.set('retry', Date.now() + '-' + attempt);
                    }

                    script.src = url.href;
                    script.onload = function () {
                        loadScript(index + 1, 0);
                    };

                    script.onerror = function () {
                        script.remove();

                        if (attempt < 2) {
                            window.setTimeout(function () {
                                loadScript(index, attempt + 1);
                            }, 1000 * (attempt + 1));

                            return;
                        }

                        console.error('Gagal memuat script setelah 3 percobaan:', sources[index]);

                        // Lanjutkan agar fitur tanpa pustaka ini tetap dapat berjalan.
                        loadScript(index + 1, 0);
                    };

                    document.body.appendChild(script);
                }

                loadScript(0, 0);
            })();
        </script>
    </body>
</html>
