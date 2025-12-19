<?php

namespace Getnet\Payments\Api;

/**
 * Interface ServiceInterface.
 */
interface ServiceInterface
{
    /**
     * @return array
     */
    public function notify(): array;
}
