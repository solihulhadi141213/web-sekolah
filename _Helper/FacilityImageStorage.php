<?php

require_once __DIR__ . '/ImageStorage.php';

class FacilityImageStorage extends ImageStorage
{
    public function __construct(array $config)
    {
        parent::__construct($config, 'facilities');
    }
}
