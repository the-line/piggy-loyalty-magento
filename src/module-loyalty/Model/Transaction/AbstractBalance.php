<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Transaction;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\CustomerContactLink;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Piggy\Api\Exceptions\PiggyRequestException;
use Piggy\Api\Models\Loyalty\Receptions\DigitalRewardReception;

abstract class AbstractBalance
{
    protected const PAGE_SIZE = 100;
    protected const CREDIT_RECEPTION_TYPE = 'credit_reception';

    public const AUTOMATIONS_CHANNEL = 'AUTOMATIONS';
    public const WIDGET_CHANNEL = 'WIDGET';
    public const OAUTH_API_CHANNEL = 'OAUTH_API';

    public const CURRENT_YEAR = 'current_year';
    public const LAST_YEAR = 'last_year';

    public function __construct(
        protected Connector $connector,
        protected CustomerContactLink $customerContactLink
    ) {
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
        $creditReceptions = $this->getLoyaltyTransactions($customer, static::getTransactionFilter());

        list($currentYearTransactions, $lastYearTransactions) = $this->getSortedCreditReceptions($creditReceptions);

        $currentYearTotal = $this->getTotalBalanceForCreditReceptions($currentYearTransactions);
        $lastYearTotal = $this->getTotalBalanceForCreditReceptions($lastYearTransactions);

        return [
            self::CURRENT_YEAR => $currentYearTotal,
            self::LAST_YEAR => $lastYearTotal
        ];
    }

    /**
     * Get the total balance for the array credit receptions
     * @param $creditReceptions
     * @return float
     */
    public function getTotalBalanceForCreditReceptions($creditReceptions): float
    {
        $balance = 0;
        foreach ($creditReceptions as $transaction) {
            $balance += method_exists($transaction, 'getUnitValue') ? $transaction->getUnitValue() : 0;
        }

        return $balance;
    }

    /**
     * Sort the credit receptions into current and last year
     *
     * @param $creditReceptions
     * @return array[]
     */
    protected function getSortedCreditReceptions($creditReceptions): array
    {
        $lastYearStart = strtotime(date('Y-01-01 00:00:00', strtotime('-1 year')));
        $lastYearEnd = strtotime(date('Y-12-31 23:59:59', strtotime('-1 year')));
        $currentYearStart = strtotime(date('Y-01-01 00:00:00'));
        $currentYearEnd = strtotime(date('Y-12-31 23:59:59'));

        $currentYear = [];
        $lastYear = [];
        foreach ($creditReceptions as $transaction) {
            $createdAt = $transaction->getCreatedAt()->getTimeStamp();
            if ($createdAt >= $currentYearStart && $createdAt <= $currentYearEnd) {
                $currentYear[] = $transaction;
            } elseif ($createdAt >= $lastYearStart && $createdAt <= $lastYearEnd) {
                $lastYear[] = $transaction;
            }
        }

        return [$currentYear, $lastYear];
    }

    /**
     * Return all Credit Receptions
     *
     * @param string $contactUuid
     * @param callable|null $callback
     * @return DigitalRewardReception[]
     * @throws \Magento\Framework\Exception\AuthenticationException
     * @throws \Loyalty\Api\Exceptions\LoyaltyRequestException
     */
    public function getLoyaltyTransactions(Customer|CustomerInterface $customer, callable $callback = null): array
    {
        $page = 1;
        $creditReceptions = [];
        while (true) {
            $transactions = $this->connector->getConnection((int) $customer->getStoreId())?->loyaltyTransactions->list(
                $page,
                $this->customerContactLink->getContactUuid($customer->getId()),
                type: self::CREDIT_RECEPTION_TYPE,
                limit: self::PAGE_SIZE
            );

            $creditReceptions = array_merge($transactions, $creditReceptions);
            $page++;

            if (count($transactions) < self::PAGE_SIZE) {
                break;
            }
        }

        return is_callable($callback) ? $callback($transactions) : $transactions;
    }

    /**
     * Get the filter for the transactions
     *
     * @return callable
     */
    protected static function getTransactionFilter(): callable
    {
        return function (array $transactions) {
            return array_filter($transactions, function ($transaction) {
                return $transaction->getType() === self::CREDIT_RECEPTION_TYPE;
            });
        };
    }
}
