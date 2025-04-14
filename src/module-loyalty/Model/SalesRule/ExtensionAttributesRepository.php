<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\SalesRule;

use Leat\Loyalty\Model\ResourceModel\SalesRule\ExtensionAttributes as ResourceModel;
use Leat\Loyalty\Model\SalesRule\ExtensionAttributesFactory;
use Magento\Framework\Exception\NoSuchEntityException;

class ExtensionAttributesRepository
{
    /**
     * @var ResourceModel
     */
    private $resource;

    /**
     * @var ExtensionAttributesFactory
     */
    private $factory;

    /**
     * @param ResourceModel $resource
     * @param ExtensionAttributesFactory $factory
     */
    public function __construct(
        ResourceModel $resource,
        ExtensionAttributesFactory $factory
    ) {
        $this->resource = $resource;
        $this->factory = $factory;
    }

    /**
     * Get extension attributes by rule ID
     *
     * @param int $ruleId
     * @return ExtensionAttributes
     * @throws NoSuchEntityException
     */
    public function getByRuleId(int $ruleId): ExtensionAttributes
    {
        $data = $this->resource->getByRuleId($ruleId);

        if (!$data) {
            // Create a new instance if it doesn't exist
            $extensionAttributes = $this->factory->create();
            $extensionAttributes->setRuleId($ruleId);
            return $extensionAttributes;
        }

        $extensionAttributes = $this->factory->create();
        $extensionAttributes->setData($data);

        return $extensionAttributes;
    }

    /**
     * Save extension attributes
     *
     * @param ExtensionAttributes $extensionAttributes
     * @return ExtensionAttributes
     */
    public function save(ExtensionAttributes $extensionAttributes): ExtensionAttributes
    {
        $this->resource->save($extensionAttributes);
        return $extensionAttributes;
    }

    /**
     * Get sales rule ID by reward UUID
     *
     * @param string $rewardUuid
     * @return int|null
     */
    public function getSalesRuleIdByRewardUuid(string $rewardUuid): ?int
    {
        return $this->resource->getSalesRuleIdByRewardUuid($rewardUuid);
    }
}
