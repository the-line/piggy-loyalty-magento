<?php
/**
 * GiftcardTransaction
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Type\Giftcard;

use Leat\AsyncQueue\Model\Connector\ConnectorPool;
use Leat\AsyncQueue\Model\Job;
use Leat\AsyncQueue\Model\Request;
use Leat\AsyncQueue\Service\JobDigest;
use Leat\Loyalty\Helper\GiftcardHelper;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Leat\Loyalty\Model\Transaction\Giftcard\GiftcardTransactionHash;
use Leat\Loyalty\Model\Transaction\LoyaltyTransactionHash;
use Leat\Loyalty\Model\Transaction\LoyaltyTransactionOrderItems;
use Leat\LoyaltyAsync\Model\Connector\AsyncConnector;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\Credit\Transaction;
use Magento\Store\Model\StoreManagerInterface;

class GiftcardTransaction extends Transaction
{
    protected const string TYPE_CODE = 'giftcard_transaction';

    public const string DATA_GIFTCARD_UUID_KEY = 'giftcard_uuid';
    public const string DATA_GIFTCARD_HASH_KEY = 'giftcard_hash';
    public const string DATA_AMOUNT_KEY = 'giftcard_amount';

    public const string GIFTCARD_IS_NOT_UPGRADEABLE_ERROR = 'giftcard is not upgradeable';

    public function __construct(
        protected GiftcardResource $giftcardResource,
        protected GiftcardHelper $giftcardHelper,
        protected GiftcardTransactionHash $giftcardTransactionHash,
        LoyaltyTransactionOrderItems $orderItems,
        LoyaltyTransactionHash $transactionHash,
        Config $config,
        ContactResource $contactResource,
        ConnectorPool $connectorPool,
        StoreManagerInterface $storeManager,
        AsyncConnector $connector,
        array $data = []
    ) {
        parent::__construct(
            $orderItems,
            $transactionHash,
            $config,
            $contactResource,
            $connectorPool,
            $storeManager,
            $connector,
            $data
        );
    }

    /**
     * @inheritDoc
     */
    protected function execute(): mixed
    {
        if (!$this->getData(JobDigest::PREVIOUS_RESULT)) {
            throw new \InvalidArgumentException('No giftcard UUID supplied by previous request');
        }
        $this->setData(self::DATA_GIFTCARD_UUID_KEY, $this->getData(JobDigest::PREVIOUS_RESULT));
        // Always set the giftcard UUID as result to pass it to the next request
        $this->getRequest()->setResult($this->getData(self::DATA_GIFTCARD_UUID_KEY));

        if ($skipResult = $this->skipTransaction(
            Transaction::INTERNAL_TRANSACTION_HASH,
            $this->getRequest()->getTransactionHash()
        )) {
            $this->getLogger('skipped-transactions')->log(sprintf(
                'Skipping transaction | %s ',
                $skipResult
            ));
            $this->getRequest()->setLatestFailReason($skipResult);
            return null;
        }

        $result = null;
        try {
            $result = $this->giftcardResource->createGiftcardTransaction(
                $this->getData(self::DATA_GIFTCARD_UUID_KEY),
                $this->getData(self::DATA_AMOUNT_KEY),
                [
                    self::INTERNAL_TRANSACTION_HASH => $this->getRequest()->getTransactionHash(),
                    self::INTERNAL_ORDER_ITEM_ID_NAME => $this->getData(self::DATA_ORDER_ITEM_ID_KEY),
                    self::INTERNAL_INCREMENT_ID_NAME => $this->getData(self::DATA_INCREMENT_ID_KEY)
                ],
                $this->getData($this->getStoreId())
            );
        } catch (\Exception $e) {
            $this->getLogger('giftcard-transaction')->log(sprintf(
                'Error creating giftcard transaction | %s | %s',
                $e->getMessage(),
                $e->getTraceAsString()
            ));
            if (str_contains($e->getMessage(), self::GIFTCARD_IS_NOT_UPGRADEABLE_ERROR)) {
                $this->getRequest()->setLatestFailReason($e->getMessage());
            } else {
                throw new \RuntimeException('Error creating giftcard transaction', 0, $e);
            }
        }

        return $result;
    }

    /**
     * Set request as always valid
     *
     * @param Job $job
     * @param Request $request
     * @return bool
     */
    public function isRequestCustomerValid(Job $job, Request $request): bool
    {
        return true;
    }

    /**
     * Fetch the transaction hashes for a contact
     * - If the transactions are already fetched, return the cached transactions
     *
     * @param string $customerId
     * @return array
     * @throws \Magento\Framework\Exception\AuthenticationException
     * @throws \Piggy\Api\Exceptions\PiggyRequestException
     */
    protected function getTransactionHashes(): array
    {
        $giftcardUUID = $this->getData(self::DATA_GIFTCARD_UUID_KEY);
        if (!isset($this->cachedTransactionHashes[$giftcardUUID])) {
            $this->cachedTransactionHashes[$giftcardUUID] = $this->giftcardTransactionHash->getTransactions([
                'giftcard_uuid' => $giftcardUUID,
                'store_id' => $this->getStoreId()
            ]);
        }

        return $this->cachedTransactionHashes[$giftcardUUID];
    }
}
