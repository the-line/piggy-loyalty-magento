<?php
/**
 * TransactionHash
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\Loyalty\Model\Transaction;

use Leat\LoyaltyAsync\Model\Queue\Type\Contact\Credit\Transaction;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;

class LoyaltyTransactionHash extends LoyaltyTransactionOrderItems
{
    /**
     * Add class filter to function call if not provided
     *
     * @param Customer|CustomerInterface $customer
     * @param callable|null $callback
     * @param null $transactionType
     * @param string $contactUuid
     * @return array
     * @throws \Magento\Framework\Exception\AuthenticationException
     * @throws \Piggy\Api\Exceptions\PiggyRequestException
     */
    public function getTransactions(array $data = [], callable $callback = null, $transactionType = null): array
    {
        return parent::getTransactions($data, $callback ?? static::getTransactionFilter());
    }

    /**
     * Get the filter for the transactions
     * - Only returns a list of transaction hashes from the API
     *
     * @return callable
     */
    protected static function getTransactionFilter(): callable
    {
        return function (array $transactions) {
            $transactions = array_filter($transactions, function ($transaction) {
                $channel = $transaction->getChannel();
                return $channel === AbstractTransactions::OAUTH_API_CHANNEL;
            });

            $mappedTransactions = [];
            array_walk($transactions, function ($transaction) use (&$mappedTransactions) {
                $transactionHash = $transaction->getAttributes()[Transaction::INTERNAL_TRANSACTION_HASH] ?? '';
                if (!empty($transactionHash)) {
                    $mappedTransactions[] = $transactionHash;
                }
            });

            return $mappedTransactions;
        };
    }
}
