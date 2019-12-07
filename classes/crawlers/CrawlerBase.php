<?php

class CrawlerBase
{
    protected $config;

    function __construct(array $config)
    {
        $this->config = $config;
    }
}