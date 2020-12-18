<?php

namespace PlacetoPay\Payments\Helper;

use Magento\Framework\Serialize\Serializer\Json;
use PlacetoPay\Payments\Logger\Logger;

/**
 * Class PlacetoPayLogger.
 */
class PlacetoPayLogger
{
    /**
     * @var Logger
     */
    protected $_logger;

    /**
     * @var Json
     */
    protected $_json;

    /**
     * PlacetoPayLogger constructor.
     * @param Logger $logger
     * @param Json $json
     */
    public function __construct(Logger $logger, Json $json)
    {
        $this->_logger = $logger;
        $this->_json = $json;
    }

    /**
     * @param object $class
     * @param string $type
     * @param string $message
     * @param array $context
     */
    public function log(object $class, string $type, string $message, array $context = [])
    {
        $this->_logger->$type(get_class($class) . ' - ' . $message . ' ' . $this->_json->serialize($context));
    }
}
