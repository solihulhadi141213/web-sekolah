<?php

require_once __DIR__ . '/ImageStorage.php';

class ArticleImageStorage extends ImageStorage
{
    public function __construct(array $config)
    {
        parent::__construct($config, 'articles');
    }
}
