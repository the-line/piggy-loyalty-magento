<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Loyalty;

use Leat\Loyalty\Model\Client;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\BlockFactory;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class Navigation extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Leat_LoyaltyFrontend::loyalty/navigation.phtml';

    /**
     * @var bool|null
     */
    protected ?bool $show = null;

    public function __construct(
        Template\Context $context,
        protected BlockFactory $blockFactory,
        protected Repository $assetRepository,
        protected Config $config,
        protected StoreManagerInterface $storeManager,
        protected Connector $connector,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Check if the navigation should be displayed
     *
     * @return bool
     */
    public function show(): bool
    {
        if (!isset($this->show)) {
            try {
                $this->show = $this->config->getIsEnabled($this->getStoreId()) && $this->getClient();
            } catch (\Throwable $e) {
                $this->show = false;
            }
        }

        return $this->show;
    }

    /**
     * Get the Leat Client object
     *
     * @return Client|null
     */
    protected function getClient(): ?Client
    {
        try {
            return $this->connector->getConnection();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get all navigation items ordered by sort_order
     *
     * @return array
     */
    public function getNavigationItems(): array
    {
        $items = $this->getData('navigation_items') ?? [];

        // Sort by sort_order
        uasort($items, function ($a, $b) {
            return ($a['sort_order'] ?? 999) <=> ($b['sort_order'] ?? 999);
        });

        return $items;
    }

    /**
     * Render a navigation item by code
     *
     * @param string $code
     * @return string
     * @throws LocalizedException
     */
    public function renderNavigationItem(string $code): string
    {
        $items = $this->getData('navigation_items') ?? [];

        if (!isset($items[$code])) {
            return '';
        }

        $itemData = $items[$code];

        // Create the NavItem block with all required data
        $navItemBlock = $this->blockFactory->createBlock(
            NavItem::class,
            [
                'data' => [
                    'code' => $code,
                    'position' => $this->getData('item_position') ?: 0,
                    'is_active' => $this->getData('item_position') === 1, // First item is active by default
                    'label' => $itemData['label'] ?? '',
                    'section_id' => $itemData['section_id'] ?? ('leat-' . $code)
                ]
            ]
        );

        return $navItemBlock->toHtml();
    }


    /**
     * Get the store ID for the current store
     *
     * @return int
     */
    protected function getStoreId(): int
    {
        try {
            return (int)$this->storeManager->getStore()->getId();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
