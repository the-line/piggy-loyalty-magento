<?php

namespace Leat\AsyncQueue\Model\Connector;

use Leat\Loyalty\Model\Logger;

interface ConnectorInterface
{
    /**
     * Return a connection to the service that this connector is for
     *
     * @return mixed
     */
    public function getConnection(int $storeId = null): mixed;

    /**
     * Retrieve a logger instance to use for logging when using this connector
     *
     * @param string|null $purpose
     * @param bool $timestamped
     * @return Logger
     */
    public function getLogger(?string $purpose = null, bool $timestamped = false): Logger;
}
