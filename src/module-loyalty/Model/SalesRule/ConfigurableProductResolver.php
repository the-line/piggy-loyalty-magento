<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\SalesRule;

use Leat\Loyalty\Model\Connector;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Api\ProductRepositoryInterface;

class ConfigurableProductResolver
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param Configurable $configurableType
     * @param Connector $leatConnector
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Configurable $configurableType,
        private readonly Connector $leatConnector
    ) {
    }

    /**
     * Get configurable product data for a simple product
     *
     * @param ProductInterface $simpleProduct
     * @return array|null ['parent_product' => ProductInterface, 'super_attribute' => array]
     */
    public function getConfigurableProductData(ProductInterface $simpleProduct): ?array
    {
        $logger = $this->leatConnector->getLogger('reward');
        try {
            // Check if this is a simple product
            if ($simpleProduct->getTypeId() !== 'simple') {
                return null;
            }

            // Find parent configurable products
            $parentIds = $this->configurableType->getParentIdsByChild((int)$simpleProduct->getId());
            if (empty($parentIds)) {
                return null;
            }

            // Get the first parent configurable product
            $parentId = reset($parentIds);
            $parentProduct = $this->productRepository->getById((int)$parentId);

            if ($parentProduct->getTypeId() !== 'configurable') {
                return null;
            }

            // Get the configurable attributes for this product
            $superAttributes = [];
            $configurableOptions = $this->configurableType->getConfigurableAttributesAsArray($parentProduct);

            foreach ($configurableOptions as $option) {
                $attributeId = $option['attribute_id'];
                $attributeCode = $option['attribute_code'];

                // Get the value for this attribute from the simple product
                $value = $simpleProduct->getData($attributeCode);

                // Find the option ID that matches this value
                foreach ($option['values'] as $optionValue) {
                    if ($optionValue['value_index'] == $value) {
                        $superAttributes[$attributeId] = $optionValue['value_index'];
                        break;
                    }
                }
            }

            if (empty($superAttributes)) {
                return null;
            }

            return [
                'parent_product' => $parentProduct,
                'super_attribute' => $superAttributes
            ];
        } catch (\Exception $e) {
            $logger->log(sprintf(
                'Error resolving configurable product for SKU %s: %s',
                $simpleProduct->getSku(),
                $e->getMessage()
            ));
            return null;
        }
    }
}
