<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\QuoteItem;

use Leat\Loyalty\Model\QuoteItem\ExtensionAttributes;
use Leat\Loyalty\Model\ResourceModel\QuoteItem\ExtensionAttributes as ExtensionAttributesResource;
use Leat\Loyalty\Model\QuoteItem\ExtensionAttributesFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class ExtensionAttributesRepository
{
    /**
     * @var ExtensionAttributesResource
     */
    private $resource;

    /**
     * @var ExtensionAttributesFactory
     */
    private $extensionAttributesFactory;

    /**
     * @param ExtensionAttributesResource $resource
     * @param ExtensionAttributesFactory $extensionAttributesFactory
     */
    public function __construct(
        ExtensionAttributesResource $resource,
        ExtensionAttributesFactory $extensionAttributesFactory
    ) {
        $this->resource = $resource;
        $this->extensionAttributesFactory = $extensionAttributesFactory;
    }

    /**
     * Save extension attributes
     *
     * @param ExtensionAttributes $extensionAttributes
     * @return ExtensionAttributes
     * @throws CouldNotSaveException
     */
    public function save(ExtensionAttributes $extensionAttributes): ExtensionAttributes
    {
        try {
            $this->resource->save($extensionAttributes);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__(
                'Could not save quote item extension attributes: %1',
                $e->getMessage()
            ));
        }
        return $extensionAttributes;
    }

    /**
     * Get extension attributes by item ID
     *
     * @param int $itemId
     * @return ExtensionAttributes
     * @throws NoSuchEntityException
     */
    public function getByItemId(int $itemId): ExtensionAttributes
    {
        $extensionAttributes = $this->extensionAttributesFactory->create();
        $data = $this->resource->getByItemId($itemId);

        if ($data === null) {
            throw new NoSuchEntityException(__('Quote item extension attributes with item_id "%1" does not exist.', $itemId));
        }

        $extensionAttributes->setData($data);
        return $extensionAttributes;
    }

    /**
     * Delete extension attributes
     *
     * @param ExtensionAttributes $extensionAttributes
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(ExtensionAttributes $extensionAttributes): bool
    {
        try {
            $this->resource->delete($extensionAttributes);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__(
                'Could not delete quote item extension attributes: %1',
                $e->getMessage()
            ));
        }
        return true;
    }

    /**
     * Delete extension attributes by item ID
     *
     * @param int $itemId
     * @return bool
     */
    public function deleteByItemId(int $itemId): bool
    {
        return (bool)$this->resource->deleteByItemId($itemId);
    }
}
