<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Transaction;

use Leat\Loyalty\Model\Connector;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Piggy\Api\Exceptions\PiggyRequestException;

abstract class AbstractTransactions
{
    protected const int PAGE_SIZE = 100;
    protected const string CREDIT_RECEPTION_TYPE = 'credit_reception';

    public const string AUTOMATIONS_CHANNEL = 'AUTOMATIONS';
    public const string WIDGET_CHANNEL = 'WIDGET';
    public const string OAUTH_API_CHANNEL = 'OAUTH_API';

    public const string CURRENT_YEAR = 'current_year';
    public const string LAST_YEAR = 'last_year';

    public function __construct(
        protected Connector $connector
    ) {
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
     * @return array[]
     * @throws \Magento\Framework\Exception\AuthenticationException
     * @throws \Loyalty\Api\Exceptions\LoyaltyRequestException
     */
    public function getTransactions(array $data = [], callable $callback = null): array
    {
        $page = 1;
        $transactions = [];
        while (true) {
            $pageTransactions = $this->getTransactionsForPage($data, $page);
            $transactions = array_merge($pageTransactions, $transactions);
            $page++;

            if (count($pageTransactions) < self::PAGE_SIZE) {
                break;
            }
        }

        return is_callable($callback) ? $callback($pageTransactions) : $pageTransactions;
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

    /**
     * @param Customer|CustomerInterface|null $customer
     * @param int $page
     * @return null[]|\Piggy\Api\Models\Loyalty\Receptions\BaseReception[]|null
     * @throws AuthenticationException
     * @throws PiggyRequestException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    abstract protected function getTransactionsForPage(array $data = [], int $page = 0): array|null;
}
