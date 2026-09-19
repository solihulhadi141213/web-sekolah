<?php
    // Sesuaikan pesan dengan status yang ditentukan oleh routing.
    $isBadRequest = http_response_code() === 400;
    $errorCode   = $isBadRequest ? '400' : '404';
    $errorIcon   = $isBadRequest ? 'bi-exclamation-circle' : 'bi-file-earmark-x';
    $errorTitle  = $isBadRequest ? 'Permintaan tidak valid' : 'Halaman tidak ditemukan';
?>

<section class="container py-5 text-center" aria-labelledby="errorPageTitle">
    <div class="row justify-content-center py-3 py-md-5">
        <div class="col-12 col-md-9 col-lg-7">
            <!-- Ikon dekoratif; jenis kesalahan dijelaskan melalui judul dan pesan. -->
            <div class="mb-4" style="color: var(--primary); font-size: clamp(5rem, 15vw, 8rem); line-height: 1;">
                <i class="bi <?= $escape($errorIcon) ?>" aria-hidden="true"></i>
            </div>

            <p class="eyebrow mb-3">Kesalahan <?= $escape($errorCode) ?></p>
            <h1 id="errorPageTitle" class="fw-bold mb-3"><?= $escape($errorTitle) ?></h1>

            <?php if ($isBadRequest): ?>
                <p class="text-body-secondary mb-2">
                    Kami belum dapat membuka halaman karena format alamat atau parameter yang dikirim tidak sesuai.
                </p>
                <p class="text-body-secondary mb-4">
                    Periksa kembali alamat pada browser, atau buka beranda untuk memilih halaman melalui menu navigasi.
                </p>
            <?php else: ?>
                <p class="text-body-secondary mb-2">
                    Alamat yang Anda buka belum terdaftar atau halaman yang dituju tidak tersedia.
                    Hal ini dapat terjadi karena alamat salah ketik atau tautan yang digunakan sudah tidak berlaku.
                </p>
                <p class="text-body-secondary mb-4">
                    Periksa kembali alamatnya, atau kunjungi beranda untuk menemukan informasi sekolah yang Anda cari.
                </p>
            <?php endif; ?>

            <a
                class="btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 py-3"
                href="<?= $escape($siteUrl()) ?>"
            >
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                <span>Kembali ke Beranda</span>
            </a>
        </div>
    </div>
</section>
