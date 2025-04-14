<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Model\Queue\Request;

class RequestTypePool
{
    /**
     * @var array
     */
    private array $requestType;

    /**
     * @param array $requestType
     */
    public function __construct(array $requestType = [])
    {
        $this->requestType = $requestType;
    }

    /**
     * @return array
     */
    public function getRequestTypes(): array
    {
        return $this->requestType;
    }

    /**
     * Retrieve an empty request type object to aid with creation of requests
     * - If retrieving a type to immediately call the execute function without making use of the Queue or JobBuilder,
     *   remember to set the store id on the request type object. This might be required for some requests.
     *
     * @param string $typeCode
     * @return GenericType|null
     */
    public function getRequestType(string $typeCode): ?GenericType
    {
        $typePool = $this->getRequestTypes();
        $type = $typePool[$typeCode] ?? null;

        if ($type instanceof GenericType) {
            $type->unpack([]);
        }

        return $type;
    }
}
