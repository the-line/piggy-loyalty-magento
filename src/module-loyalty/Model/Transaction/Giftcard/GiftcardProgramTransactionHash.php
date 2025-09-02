<?php
/**
 * TransactionHash
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\Loyalty\Model\Transaction\Giftcard;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Transaction\AbstractTransactions;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\Credit\Transaction;
use Leat\LoyaltyAsync\Model\Queue\Type\Giftcard\GiftcardCreate;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Exceptions\PiggyRequestException;

class GiftcardProgramTransactionHash extends AbstractTransactions
{
    public function __construct(
        Connector $connector,
        protected StoreManagerInterface $storeManager,
        protected Config $config,
    ) {
        parent::__construct($connector);
    }

    /**
     * Add class filter to function call if not provided
     *
     * @param Customer|CustomerInterface $customer
     * @param callable|null $callback
     * @param string $contactUuid
     * @return array
     * @throws \Magento\Framework\Exception\AuthenticationException
     * @throws \Piggy\Api\Exceptions\PiggyRequestException
     */
    public function getTransactions(array $data = [], callable $callback = null): array
    {
        try {
            return parent::getTransactions($data, $callback ?? static::getTransactionFilter());
        } catch (\Exception $e) {
            $this->connector->getLogger('giftcard-transaction')->log(sprintf(
                'Error retrieving giftcard transactions | %s | %s',
                $e->getMessage(),
                $e->getTraceAsString()
            ));
            return [];
        }
    }

    /**
     * Get the filter for the transactions
     * - Only returns a list of transaction hashes from the API
     *
     * @param string|null $giftcardUUID - For filtering transactions by giftcard hash
     * @return callable
     */
    protected static function getTransactionFilter(string $giftcardUUID = null): callable
    {
        return function (array $transactions) use ($giftcardUUID) {
            $mappedTransactions = [];
            array_walk($transactions, function ($transaction) use (&$mappedTransactions, $giftcardUUID) {
                if (!method_exists($transaction, 'getAttributes')) {
                    return;
                }
                $transactionHash = $transaction->getAttributes()[Transaction::INTERNAL_TRANSACTION_HASH] ?? '';
                if (!empty($transactionHash)) {
                    if ($giftcardUUID && $transaction->getUUID() !== $giftcardUUID) {
                        return;
                    }
                    $mappedTransactions[] = $transactionHash;
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
        $storeId = $data['store_id'] ?? $this->storeManager->getStore()->getId();
        $giftcardProgramUUID = $data[GiftcardCreate::DATA_GIFTCARD_PROGRAM_UUID]
            ?? $this->config->getGiftcardProgramUUID($storeId);
        return $this->connector->getConnection($storeId)?->giftcardTransactions->list(
            $giftcardProgramUUID,
            $page,
            limit: self::PAGE_SIZE
        );
    }
}
