<?php
    // Gunakan route yang sudah divalidasi oleh RoutingPage.php.
    $pageScripts = [
        'Beranda' => '_Page/Beranda/Beranda.js',
        'Guru' => '_Page/Guru/Guru.js',
        'Testimonial' => '_Page/Testimonial/Testimonial.js',
    ];

    if (isset($pageScripts[$route])) {
        echo '<script src="' . $escape($assetUrl($pageScripts[$route])) . '"></script>';
    }
