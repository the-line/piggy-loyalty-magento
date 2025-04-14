<?php

declare(strict_types=1);

namespace Leat\Loyalty\Setup\Patch\Data;

use Magento\Eav\Model\Config;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Customer\Model\Customer;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class AddContactUuidCustomerAttribute implements DataPatchInterface, PatchRevertableInterface
{
    public const ATTRIBUTE_CODE = 'contact_uuid';

    /**
     * @var EavSetupFactory
     */
    protected $eavSetupFactory;

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var AttributeRepositoryInterface
     */
    private AttributeRepositoryInterface $attributeRepository;

    /**
     * @var Config
     */
    protected $eavConfig;

    /**
     * Constructor
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param AttributeRepositoryInterface $attributeRepository
     * @param EavSetupFactory $eavSetupFactory
     * @param Config $eavConfig
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        AttributeRepositoryInterface $attributeRepository,
        EavSetupFactory $eavSetupFactory,
        Config $eavConfig
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->attributeRepository = $attributeRepository;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attributeSetId = $eavSetup->getDefaultAttributeSetId(Customer::ENTITY);
        $attributeGroupId = $eavSetup->getDefaultAttributeGroupId(Customer::ENTITY, $attributeSetId);

        $eavSetup->addAttribute(
            Customer::ENTITY,
            self::ATTRIBUTE_CODE,
            [
                'label' => 'Contact Uuid',
                'input' => 'text',
                'type' => 'varchar',
                'source' => '',
                'required' => false,
                'position' => 333,
                'visible' => true,
                'system' => false,
                'is_used_in_grid' => false,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => false,
                'is_searchable_in_grid' => true,
                'backend' => ''
            ]
        );

        $attribute = $this->eavConfig->getAttribute(Customer::ENTITY, self::ATTRIBUTE_CODE);
        $attribute->addData([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId
        ]);

        $this->attributeRepository->save($attribute);

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->removeAttribute(Customer::ENTITY, self::ATTRIBUTE_CODE);

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }
}
