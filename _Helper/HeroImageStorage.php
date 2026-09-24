<?php

require_once __DIR__ . '/ImageStorage.php';

class HeroImageStorage extends ImageStorage
{
    public function __construct(array $config)
    {
        parent::__construct($config, 'hero_slides');
    }
}
