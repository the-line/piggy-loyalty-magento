<?php


declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Type\Contact\Credit;

use Leat\AsyncQueue\Model\Connector\ConnectorPool;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\Loyalty\Model\Transaction\OrderItems;
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

    public const string INTERNAL_SKU_NAME = 'sku';
    public const string INTERNAL_BRAND_NAME = 'brand';
    public const string INTERNAL_ADDITIONAL_CREDITS_NAME = 'additional_credits';
    public const string INTERNAL_PRODUCT_NAME_NAME = 'product_name';
    public const string INTERNAL_ROW_TOTAL_NAME = 'row_total';
    public const string INTERNAL_INCREMENT_ID_NAME = 'increment_id';
    public const string INTERNAL_ORDER_ITEM_ID_NAME = 'order_item_id';

    /**
     * Mapped transactions for a customer
     * - key: order_item_id
     * - value: order_increment_id
     *
     * @var array
     */
    protected array $transactions = [];

    public function __construct(
        protected OrderItems $orderItems,
        Config $config,
        ContactResource $contactResource,
        ConnectorPool $connectorPool,
        StoreManagerInterface $storeManager,
        AsyncConnector $connector,
        array $data = []
    ) {
        parent::__construct($config, $contactResource, $connectorPool, $storeManager, $connector, $data);
    }

    /**
     * @inheritDoc
     */
    protected function execute(): ?CreditReception
    {
        $job = $this->getJob();
        $contactUuid = $this->contactResource->getContactUuid((int) $job->getRelationId());
        if (!$contactUuid) {
            // No contact to subscribe.
            return null;
        }

        if ($this->skipTransaction($this->getData(self::DATA_ORDER_ITEM_ID_KEY))) {
            $this->getLogger('skipped-transactions')->log(sprintf(
                'Skipping transaction for #%s - order item id: %s - Contact %s',
                $this->getData(self::DATA_INCREMENT_ID_KEY),
                $this->getData(self::DATA_ORDER_ITEM_ID_KEY),
                $contactUuid
            ));
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
                self::INTERNAL_ADDITIONAL_CREDITS_NAME => $this->getData(self::DATA_ADDITIONAL_CREDITS_KEY)
            ]
        );
    }

    /**
     * @param $orderItemId
     *
     * @return bool
     * @throws AuthenticationException
     * @throws PiggyRequestException
     */
    protected function skipTransaction($orderItemId): bool
    {
        $orderItemTransactionMapping = $this->getCustomerTransactionOrderItemIds();

        return isset($orderItemTransactionMapping[$orderItemId]);
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
            $this->transactions = [];
            $this->transactions[$contactUuid] = $this->orderItems->getLoyaltyTransactions($customer);
        }

        return $this->transactions[$contactUuid];
    }
}
