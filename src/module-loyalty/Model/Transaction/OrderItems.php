<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Transaction;

use Leat\LoyaltyAsync\Model\Queue\Type\Contact\Credit\Transaction;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Piggy\Api\Exceptions\PiggyRequestException;
use Piggy\Api\Models\Loyalty\Receptions\DigitalRewardReception;

class OrderItems extends AbstractBalance
{
    /**
     * Add class filter to function call if not provided
     *
     * @param Customer|CustomerInterface $customer
     * @param callable|null $callback
     * @return array|DigitalRewardReception[]
     * @throws AuthenticationException
     * @throws PiggyRequestException
     */
    public function getLoyaltyTransactions(Customer|CustomerInterface $customer, callable $callback = null): array
    {
        return parent::getLoyaltyTransactions($customer, $callback ?? static::getTransactionFilter());
    }

    /**
     * Get the filter for the transactions
     * - filters out the transactions from the widget, automations and oauth api
     *
     * @return callable
     */
    protected static function getTransactionFilter(): callable
    {
        return function (array $transactions) {
            $transactions = array_filter($transactions, function ($transaction) {
                $channel = $transaction->getChannel();
                return $channel === AbstractBalance::OAUTH_API_CHANNEL;
            });

            $mappedTransactions = [];
            array_walk($transactions, function ($transaction) use (&$mappedTransactions) {
                if (!method_exists($transaction, 'getAttributes')) {
                    return;
                }

                $orderIncrementId = $transaction->getAttributes()[Transaction::INTERNAL_INCREMENT_ID_NAME] ?? '';
                $orderItemId = $transaction->getAttributes()[Transaction::INTERNAL_ORDER_ITEM_ID_NAME] ?? '';
                if (!empty($orderIncrementId) && !empty($orderItemId)) {
                    $mappedTransactions[$orderItemId] = $orderIncrementId;
                }
            });

            return $mappedTransactions;
        };
    }
}
