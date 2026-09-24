<?php

require_once __DIR__ . '/ImageStorage.php';

/** Tetap kompatibel dengan endpoint dan pemanggil helper gambar guru. */
class TeacherImageStorage extends ImageStorage
{
    public function __construct(array $config)
    {
        parent::__construct($config, 'teachers');
    }
}
