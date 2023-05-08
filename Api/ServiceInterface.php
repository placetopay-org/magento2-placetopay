<?php

namespace PlacetoPay\Payments\Api;

/**
 * Interface ServiceInterface.
 */
interface ServiceInterface
{
    public function notify(): array;
}
