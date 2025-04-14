<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Model\Rule\Action;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\LayoutInterface;
use Magento\Rule\Model\Action\AbstractAction;

class AddGiftProducts extends AbstractAction
{
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    private $productRepository;

    /**
     * @param Repository $assetRepo
     * @param LayoutInterface $layout
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param array $data
     */
    public function __construct(
        Repository $assetRepo,
        LayoutInterface $layout,
        \Magento\Catalog\Model\ProductRepository $productRepository = null,
        array $data = []
    ) {
        parent::__construct($assetRepo, $layout, $data);
        $this->productRepository = $productRepository ?: ObjectManager::getInstance()
            ->get(\Magento\Catalog\Model\ProductRepository::class);
    }

    /**
     * {@inheritdoc}
     */
    public function loadAttributeOptions()
    {
        $this->setAttributeOption(['gift_skus' => __('Gift Product SKUs')]);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function loadOperatorOptions()
    {
        $this->setOperatorOption(['add_gift' => __('Add as gifts')]);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getValueElementType()
    {
        return 'text';
    }

    /**
     * Return HTML markup for gift SKUs field
     *
     * @return string
     */
    public function asHtml()
    {
        $html = $this->getTypeElement()->getHtml() . __(
            "Add the following products as gifts: %1",
            $this->getValueElement()->getHtml()
        );
        $html .= $this->getRemoveLinkHtml();
        return $html;
    }

    /**
     * Get value for rule condition
     *
     * @return string
     */
    public function getValue()
    {
        return $this->getData('value');
    }

    /**
     * Get value element type
     *
     * @return string
     */
    public function getValueElement()
    {
        $element = parent::getValueElement();
        $element->setData('class', 'admin__control-text gift-skus-input');
        $element->setData('note', __('Enter comma-separated SKUs. For configurable products, you can specify the simple product SKU.'));
        return $element;
    }
}
