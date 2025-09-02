<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Transaction;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\CustomerContactLink;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\Credit\Transaction;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Piggy\Api\Exceptions\PiggyRequestException;
use Piggy\Api\Models\Loyalty\Receptions\DigitalRewardReception;

class LoyaltyTransactionOrderItems extends AbstractTransactions
{
    public function __construct(
        protected Connector $connector,
        protected CustomerContactLink $customerContactLink
    ) {
        parent::__construct($connector);
    }

    /**
     * Add class filter to function call if not provided
     *
     * @param Customer|CustomerInterface $customer
     * @param callable|null $callback
     * @return array|DigitalRewardReception[]
     * @throws AuthenticationException
     * @throws PiggyRequestException
     */
    public function getTransactions(array $data = [], callable $callback = null): array
    {
        return parent::getTransactions($data, $callback ?? static::getTransactionFilter());
    }

    /**
     * Get the balance for a given customer by leat transactions
     *  - returns the total balance for the current and last year
     *
     * @param $contactUuid
     * @return int[]
     * @throws AuthenticationException
     * @throws PiggyRequestException
     */
    public function getBalances(Customer|CustomerInterface $customer): array
    {
        $creditReceptions = $this->getTransactions([
            'customer' => $customer
        ], static::getTransactionFilter());

        list($currentYearTransactions, $lastYearTransactions) = $this->getSortedCreditReceptions($creditReceptions);

        $currentYearTotal = $this->getTotalBalanceForCreditReceptions($currentYearTransactions);
        $lastYearTotal = $this->getTotalBalanceForCreditReceptions($lastYearTransactions);

        return [
            self::CURRENT_YEAR => $currentYearTotal,
            self::LAST_YEAR => $lastYearTotal
        ];
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
                return $channel === AbstractTransactions::OAUTH_API_CHANNEL;
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

    /**
     * @param array $data
     * @param int $page
     * @return array|null[]|\Piggy\Api\Models\Loyalty\Receptions\BaseReception[]|null
     * @throws AuthenticationException
     * @throws PiggyRequestException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getTransactionsForPage(array $data = [], int $page = 0): array|null
    {
        $customer = $data['customer'] ?? null;
        if ((!$customer instanceof CustomerInterface || $customer instanceof Customer)
            && $customer->getId() === null
        ) {
            return null;
        }

        return $this->connector->getConnection((int)$customer->getStoreId())?->loyaltyTransactions->list(
            $page,
            $this->customerContactLink->getContactUuid($customer->getId()),
            type: self::CREDIT_RECEPTION_TYPE,
            limit: self::PAGE_SIZE
        );
    }
}
