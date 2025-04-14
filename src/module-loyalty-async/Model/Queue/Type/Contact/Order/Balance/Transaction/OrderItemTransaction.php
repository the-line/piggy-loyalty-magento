<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Type\Contact\Order\Balance\Transaction;

use Leat\LoyaltyAsync\Model\Queue\Type\Contact\Credit\Transaction;

class OrderItemTransaction extends Transaction
{
    protected const string TYPE_CODE = 'order_item_transaction';
}
