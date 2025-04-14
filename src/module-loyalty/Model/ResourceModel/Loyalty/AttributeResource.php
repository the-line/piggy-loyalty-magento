<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\ResourceModel\Loyalty;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Enum\CustomAttributeTypes;
use Piggy\Api\Models\CustomAttributes\CustomAttribute;

class AttributeResource extends AbstractLeatResource
{
    protected const string LOGGER_PURPOSE = 'attributes';
    protected const string DEFAULT_GROUP_NAME = 'Magento';

    /**
     * @var array
     */
    protected array $attributeList = [];

    /**
     * Transaction attributes
     */
    private const array TRANSACTION_ATTRIBUTES = [
        'increment_id' => [
            'type' => CustomAttributeTypes::TEXT,
            'label' => 'Increment ID',
            'description' => 'The Magento order increment ID of the order',
            'options' => null
        ],
        'order_item_id' => [
            'type' => CustomAttributeTypes::TEXT,
            'label' => 'Order ID',
            'description' => 'The Magento order item ID of the order item',
            'options' => null
        ],
        'product_name' => [
            'type' => CustomAttributeTypes::TEXT,
            'label' => 'Product Name',
            'description' => 'The name of the product sold',
            'options' => null
        ],
        'brand' => [
            'type' => CustomAttributeTypes::TEXT,
            'label' => 'Brand',
            'description' => 'The brand of the product sold',
            'options' => null
        ],
        'sku' => [
            'type' => CustomAttributeTypes::TEXT,
            'label' => 'SKU',
            'description' => 'The SKU of the product sold',
            'options' => null
        ],
        'quantity' => [
            'type' => CustomAttributeTypes::NUMBER,
            'label' => 'Quantity',
            'description' => 'The quantity of the order item',
            'options' => null
        ],
        'row_total' => [
            'type' => CustomAttributeTypes::NUMBER,
            'label' => 'Row Total',
            'description' => 'Row total of the order item',
            'options' => null
        ]
    ];

    /**
     * Reward attributes
     */
    private const array REWARD_ATTRIBUTES = [];

    /**
     * Sync all transaction attributes
     *
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    public function syncTransactionAttributes(?int $storeId = null): array
    {
        return $this->syncAttributes('transaction', self::TRANSACTION_ATTRIBUTES, $storeId);
    }

    /**
     * Sync all reward attributes
     *
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    public function syncRewardAttributes(?int $storeId = null): array
    {
        return $this->syncAttributes('reward', self::REWARD_ATTRIBUTES, $storeId);
    }

    /**
     * Sync attributes for an entity type
     *
     * @param string $entityCode
     * @param array $attributes
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    private function syncAttributes(string $entityCode, array $attributes, ?int $storeId = null): array
    {
        return $this->executeApiRequest(
            function () use ($entityCode, $attributes, $storeId) {
                $currentAttributes = $this->getCurrentAttributes($entityCode, $storeId);

                foreach ($attributes as $name => $data) {
                    if ($entityCode === 'transaction') {
                        if (!isset($currentAttributes[$name])) {
                            $this->getLogger()->log("Missing attribute $name for $entityCode, creating");
                            $this->createCustomTransactionAttribute($name, $data, $storeId);
                        } else {
                            $this->getLogger()->log("Attribute $name for $entityCode exists, skipping");
                        }
                    } else {
                        if (!isset($currentAttributes[$name])) {
                            $this->createCustomAttribute($entityCode, $name, $data, $storeId);
                        } else {
                            // TODO: Update attribute if needed
                            $this->getLogger()->log("Attribute $name for $entityCode exists, skipping");
                        }
                    }
                }

                return $this->getCurrentAttributes($entityCode, $storeId);
            },
            "Error syncing $entityCode attributes"
        );
    }

    /**
     * Get current attributes for an entity
     *
     * @param string $entityCode
     * @param int|null $storeId
     * @return array Associative array with attribute name as key and attribute object as value
     * @throws LocalizedException
     */
    public function getCurrentAttributes(string $entityCode, ?int $storeId = null): array
    {
        return $this->executeApiRequest(
            function () use ($entityCode, $storeId) {
                $client = $this->getClient($storeId);
                if ($entityCode === 'transaction') {
                    $result = $client->loyaltyTransactionAttributes->list();
                } else {
                    $result = $client->customAttributes->list([
                        'entity' => $entityCode
                    ]);
                }

                $currentAttributes = [];
                foreach ($result as $attribute) {
                    $currentAttributes[$attribute->getName()] = $attribute;
                }

                return $currentAttributes;
            },
            "Error retrieving $entityCode attributes"
        );
    }

    /**
     * Create an attribute
     *
     * @param string $entityCode
     * @param string $attributeName
     * @param array $data
     * @param int|null $storeId
     * @return CustomAttribute
     * @throws LocalizedException
     */
    public function createCustomAttribute(string $entityCode, string $attributeName, array $data, ?int $storeId = null): CustomAttribute
    {
        return $this->executeApiRequest(
            function () use ($entityCode, $attributeName, $data, $storeId) {
                $client = $this->getClient($storeId);
                $this->getLogger()->log("$attributeName attribute data: " . json_encode($data));

                return $client->customAttributes->create(
                    $entityCode,
                    $attributeName,
                    $data['label'],
                    $data['type'],
                    $data['options'] ?? null,
                    $data['description'] ?? null,
                    $data['group_name'] ?? self::DEFAULT_GROUP_NAME
                );
            },
            "Error creating attribute $attributeName for $entityCode"
        );
    }

    /**
     * Update an attribute
     *
     * @param string $entityCode
     * @param string $attributeName
     * @param array $data
     * @param int|null $storeId
     * @return CustomAttribute
     * @throws LocalizedException
     */
    public function updateCustomAttribute(string $entityCode, string $attributeName, array $data, ?int $storeId = null): CustomAttribute
    {
        return $this->executeApiRequest(
            function () use ($entityCode, $attributeName, $data, $storeId) {
                $client = $this->getClient($storeId);
                $this->getLogger()->log("$attributeName attribute data: " . json_encode($data));

                return $client->customAttributes->update(
                    $attributeName,
                    $entityCode,
                    $data['label'],
                    $data['options'] ?? null,
                    $data['description'] ?? null,
                    $data['group_name'] ?? self::DEFAULT_GROUP_NAME
                );
            },
            "Error updating attribute $attributeName for $entityCode"
        );
    }


    /**
     * Set transaction attributes
     *
     * @param string $attributeName
     * @param array $data
     * @param int|null $storeId
     * @return mixed
     * @throws LocalizedException
     */
    public function createCustomTransactionAttribute(string $attributeName, array $data, ?int $storeId = null): mixed
    {
        return $this->executeApiRequest(
            function () use ($attributeName, $data, $storeId) {
                $client = $this->getClient($storeId);

                // string $name, string $dataType, ?string $label = null, ?string $description = null, ?array $options = null
                return $client->loyaltyTransactionAttributes->create(
                    $attributeName,
                    $data['type'],
                    $data['label'] ?? null,
                    $data['description'] ?? null,
                    $data['options'] ?? null
                );
            },
            'Error setting transaction attributes'
        );
    }

    /**
     * Get contact attributes
     *
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     */
    public function getFormattedContactAttributes(?int $storeId = null): array
    {
        if (empty($this->attributeList)) {
            $cacheKey = $this->getCacheIdentifier('contact_attributes');
            if (!($result = $this->loadCache($cacheKey))) {
                $result = [];
                $client = $this->getClient($storeId);
                $attributes = $client->contactAttributes->list();

                foreach ($attributes as $key => $attribute) {
                    $result[$attribute->getName()] = $key;
                }

                try {
                    $this->saveToCache($result, $cacheKey);
                } catch (NoSuchEntityException $e) {
                    // Do nothing on cache save failure
                }
            }

            $this->attributeList = $result;
        }

        return $this->attributeList;
    }

    /**
     * Validate whether all required attributes exist
     *
     * @param int $storeId
     * @return array Returns validation result ['valid' => bool, 'missing' => array]
     * @throws LocalizedException
     */
    public function validateAttributes(int $storeId): array
    {
        $result = [
            'valid' => true,
            'missing' => [
                'transaction' => [],
                'reward' => []
            ]
        ];

        try {
            $currentTransactionAttributes = $this->getCurrentAttributes('transaction', $storeId);
            foreach (array_keys(self::TRANSACTION_ATTRIBUTES) as $attrName) {
                if (!isset($currentTransactionAttributes[$attrName])) {
                    $result['missing']['transaction'][] = $attrName;
                    $result['valid'] = false;
                }
            }

            $currentRewardAttributes = $this->getCurrentAttributes('reward', $storeId);
            if (!empty(self::REWARD_ATTRIBUTES)) {
                foreach (array_keys(self::REWARD_ATTRIBUTES) as $attrName) {
                    if (!isset($currentRewardAttributes[$attrName])) {
                        $result['missing']['reward'][] = $attrName;
                        $result['valid'] = false;
                    }
                }
            }
        } catch (\Exception $e) {
            throw new LocalizedException(__('Error validating attributes: %1', $e->getMessage()));
        }

        return $result;
    }
}
