<?php


declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Type\Contact\Credit;

use Leat\AsyncQueue\Model\Connector\ConnectorPool;
use Leat\AsyncQueue\Model\Request;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\Loyalty\Model\Transaction\LoyaltyTransactionOrderItems;
use Leat\Loyalty\Model\Transaction\LoyaltyTransactionHash;
use Leat\LoyaltyAsync\Model\Connector\AsyncConnector;
use Leat\LoyaltyAsync\Model\Queue\Type\ContactType;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Exceptions\PiggyRequestException;
use Piggy\Api\Models\Loyalty\Receptions\CreditReception;

abstract class Transaction extends ContactType
{
    protected const string TYPE_CODE = 'credit_transaction';

    public const string DEFAULT_UNIT_NAME = 'purchase_amount';

    public const string DATA_CREDITS_KEY = 'credits';
    public const string DATA_ADDITIONAL_CREDITS_KEY = 'additional_credits';
    public const string DATA_SKU_KEY = 'sku';
    public const string DATA_PRODUCT_NAME_KEY = 'product_name';
    public const string DATA_ROW_TOTAL_KEY = 'row_total';
    public const string DATA_BRAND_KEY = 'brand';
    public const string DATA_INCREMENT_ID_KEY = 'increment_id';
    public const string DATA_ORDER_ITEM_ID_KEY = 'order_item_id';
    public const string DATA_UNIT_NAME_KEY = 'unit_name';
    public const string DATA_TRANSACTION_NOTE_KEY = 'transaction_note';


    public const string INTERNAL_SKU_NAME = 'sku';
    public const string INTERNAL_BRAND_NAME = 'brand';
    public const string INTERNAL_ADDITIONAL_CREDITS_NAME = 'additional_credits';
    public const string INTERNAL_PRODUCT_NAME_NAME = 'product_name';
    public const string INTERNAL_ROW_TOTAL_NAME = 'row_total';
    public const string INTERNAL_INCREMENT_ID_NAME = 'increment_id';
    public const string INTERNAL_ORDER_ITEM_ID_NAME = 'order_item_id';

    public const string INTERNAL_TRANSACTION_HASH = 'transaction_hash';
    public const string INTERNAL_TRANSACTION_NOTE = 'transaction_note';

    /**
     * Cached transaction hashes for a customer
     * @var array
     */
    protected array $cachedTransactionHashes = [];

    /**
     * Mapped transactions for a customer
     * - key: order_item_id
     * - value: order_increment_id
     *
     * @var array
     */
    protected array $transactions = [];

    public function __construct(
        protected LoyaltyTransactionOrderItems $orderItems,
        protected LoyaltyTransactionHash       $transactionHash,
        Config                                 $config,
        ContactResource                        $contactResource,
        ConnectorPool                          $connectorPool,
        StoreManagerInterface                  $storeManager,
        AsyncConnector                         $connector,
        array                                  $data = []
    ) {
        parent::__construct($config, $contactResource, $connectorPool, $storeManager, $connector, $data);
    }

    /**
     * @inheritDoc
     */
    protected function execute(): mixed
    {
        $job = $this->getJob();
        $contactUuid = $this->contactResource->getContactUuid((int) $job->getRelationId());
        if (!$contactUuid) {
            // No contact to subscribe.
            return null;
        }

        if ($skipResult = $this->skipTransaction(
            Transaction::INTERNAL_ORDER_ITEM_ID_NAME,
            $this->getData(self::DATA_ORDER_ITEM_ID_KEY)
        )) {
            $this->getLogger('skipped-transactions')->log(sprintf(
                'Skipping transaction | %s ',
                $skipResult
            ));
            $this->getRequest()->setLatestFailReason($skipResult);
            return null;
        }

        $client = $this->getClient();
        $shopUuid = $this->config->getShopUuid();
        return $client->creditReceptions->create(
            $contactUuid,
            $shopUuid,
            (float) $this->getData(self::DATA_ROW_TOTAL_KEY),
            null,
            null,
            $this->getData(self::DATA_UNIT_NAME_KEY) ?? self::DEFAULT_UNIT_NAME,
            null,
            [
                self::INTERNAL_SKU_NAME => $this->getData(self::DATA_SKU_KEY),
                self::INTERNAL_ROW_TOTAL_NAME => $this->getData(self::DATA_ROW_TOTAL_KEY),
                self::INTERNAL_PRODUCT_NAME_NAME => $this->getData(self::DATA_PRODUCT_NAME_KEY),
                self::INTERNAL_BRAND_NAME => $this->getData(self::DATA_BRAND_KEY),
                self::INTERNAL_INCREMENT_ID_NAME => $this->getData(self::DATA_INCREMENT_ID_KEY),
                self::INTERNAL_ORDER_ITEM_ID_NAME => $this->getData(self::DATA_ORDER_ITEM_ID_KEY),
                self::INTERNAL_ADDITIONAL_CREDITS_NAME => $this->getData(self::DATA_ADDITIONAL_CREDITS_KEY),
                self::INTERNAL_TRANSACTION_HASH => $this->getRequest()->getTransactionHash(),
                self::INTERNAL_TRANSACTION_NOTE => $this->getData(self::DATA_TRANSACTION_NOTE_KEY),
            ]
        );
    }

    /**
     * @param string $contactUuid
     * @param string|null $attribute
     * @param string|null $attributeValue
     * @return bool|string
     * @throws AuthenticationException
     * @throws PiggyRequestException
     */
    public function skipTransaction(
        ?string $attribute = null,
        ?string $attributeValue = null
    ): bool|string {
        switch ($attribute) {
            case self::INTERNAL_ORDER_ITEM_ID_NAME:
                $orderItemTransactionMapping = $this->getCustomerTransactionOrderItemIds();
                if (isset($orderItemTransactionMapping[$attributeValue])) {
                    return sprintf(
                        'Transaction with order item id %s already exists',
                        $attributeValue
                    );
                }
                return false;
            case self::INTERNAL_TRANSACTION_HASH:
                $transactionHashes = $this->getTransactionHashes();
                if (in_array($attributeValue, $transactionHashes, true)) {
                    return sprintf(
                        'Transaction with hash %s already exists',
                        $attributeValue
                    );
                }
                return false;
            default:
                return false;
        }
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
        $job = $this->getJob();
        $customer = $this->contactResource->getCustomer((int) $job->getRelationId());
        $contactUuid = $this->contactResource->getContactUuid((int) $customer->getId());
        if (!isset($this->cachedTransactionHashes[$contactUuid])) {
            $this->cachedTransactionHashes[$contactUuid] = $this->transactionHash->getTransactions([
                'customer' => $customer
            ]);
        }

        return $this->cachedTransactionHashes[$contactUuid];
    }

    /**
     * Fetch the transaction order item ids for a contact
     * - If the transactions are already fetched, return the cached transactions
     *
     * @return array
     * @throws \Magento\Framework\Exception\AuthenticationException
     * @throws \Loyalty\Api\Exceptions\LoyaltyRequestException
     */
    protected function getCustomerTransactionOrderItemIds(): array
    {
        $job = $this->getJob();
        $customer = $this->contactResource->getCustomer((int) $job->getRelationId());
        $contactUuid = $this->contactResource->getContactUuid((int) $customer->getId());
        if (!isset($this->transactions[$contactUuid])) {
            $this->transactions[$contactUuid] = $this->orderItems->getTransactions([
                'customer' => $customer
            ]);
        }

        return $this->transactions[$contactUuid];
    }
}
