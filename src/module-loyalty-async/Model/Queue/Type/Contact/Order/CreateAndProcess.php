<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Type\Contact\Order;

use Leat\LoyaltyAsync\Model\Queue\Type\LeatGenericType;

class CreateAndProcess extends LeatGenericType
{
    protected const string TYPE_CODE = 'create_and_process';
    public const string DATA_ORDER_KEY = 'order';

    protected function execute(): mixed
    {
        $client = $this->getClient();
        return $client->orders->createAndProcess(
            $this->getData(self::DATA_ORDER_KEY)
        );
    }
}
