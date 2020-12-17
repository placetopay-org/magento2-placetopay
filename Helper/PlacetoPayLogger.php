<?php

namespace PlacetoPay\Payments\Helper;

use PlacetoPay\Payments\Logger\Logger;

class PlacetoPayLogger
{
    /**
     * @var Logger
     */
    protected $_logger;

    /**
     * PlacetoPayLogger constructor.
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->_logger = $logger;
    }

    /**
     * @param object $class
     * @param string $type
     * @param string $message
     * @param array $context
     */
    public function logger(object $class, string $type, string $message, array $context = [])
    {
        $this->_logger->$type(get_class($class) . ' - ' . $message . ' ' . json_encode($context));
    }
}
