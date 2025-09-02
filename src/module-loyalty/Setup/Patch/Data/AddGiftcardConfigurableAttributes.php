<?php

declare(strict_types=1);

namespace Leat\Loyalty\Setup\Patch\Data;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Catalog\Model\Product;
use Magento\Swatches\Model\Swatch;

/**
 * Class AddGiftcardConfigurableAttributes
 * @package Leat\Loyalty\Setup\Patch\Data
 */
class AddGiftcardConfigurableAttributes implements DataPatchInterface
{
    public const GIFTCARD_VALUE_ATTRIBUTE_CODE = 'giftcard_value';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * AddGiftcardConfigurableAttributes constructor.
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Add giftcard_value_cents attribute
        $eavSetup->addAttribute(
            Product::ENTITY,
            self::GIFTCARD_VALUE_ATTRIBUTE_CODE,
            attr: [
                'label' => 'Giftcard Value',
                'input' => 'select',
                'frontend_input' => Swatch::SWATCH_TYPE_TEXTUAL_ATTRIBUTE_FRONTEND_INPUT,
                'type' => 'int',
                'required' => false,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => true,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'attribute_model' => Attribute::class,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'is_configurable' => true,
                'group' => 'Product Details',
                'visible' => true,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => true,
                'is_html_allowed_on_front' => true,
                'note' => 'Value of the giftcard (e.g., $25.00, €50.00)',
                'swatch_input_type' => Swatch::SWATCH_INPUT_TYPE_TEXT,
                'update_product_preview_image' => true,
                'use_product_image_for_swatch' => false
            ]
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [
            AddLeatGiftcardAttribute::class
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
