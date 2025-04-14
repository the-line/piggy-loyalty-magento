<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Model\Connector;

use Leat\AsyncQueue\Model\Queue\Request\GenericType;

class ConnectorPool
{
    /**
     * @var array
     */
    private array $connectors;

    /**
     * @param array $connector
     */
    public function __construct(array $connector = [])
    {
        $this->connectors = $connector;
    }

    /**
     * @return AbstractConnector[]
     */
    public function getConnectors(): array
    {
        return $this->connectors;
    }

    /**
     * Retrieve the connector object belonging to the connector code
     *
     * @param string $connectorCode
     * @return AbstractConnector|null
     */
    public function getConnector(string $connectorCode): ?AbstractConnector
    {
        $connectorPool = $this->getConnectors();
        $type = $connectorPool[$connectorCode] ?? null;

        if ($type instanceof GenericType) {
            $type->unpack([]);
        }

        return $type;
    }
}
