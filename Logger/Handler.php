<?php


namespace PlacetoPay\Payments\Logger;


class Handler extends  \Magento\Framework\Logger\Handler\Base
{
    protected $fileName = '/var/log/placetopay/info.log';
    protected $loggerType = \Monolog\Logger::INFO;
}