<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Type\Contact\Order\Return;

use Leat\LoyaltyAsync\Model\Queue\Type\LeatGenericType;

class CreateAndProcess extends LeatGenericType
{
    protected const string TYPE_CODE = 'return_create_and_process';
    public const string DATA_RETURN_KEY = 'return';

    protected function execute(): mixed
    {
        $client = $this->getClient();
        return $client->orderReturns->createAndProcess(
            $this->getData(self::DATA_RETURN_KEY)
        );
    }
}
