<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Loyalty;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;

class Index extends Template
{
    public function __construct(
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Initialize blocks and sections
     *
     * @return $this
     * @throws LocalizedException
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->initializeBlocks();
        return $this;
    }

    /**
     * Initialize widget blocks and set up sections
     *
     * @return void
     * @throws LocalizedException
     */
    protected function initializeBlocks(): void
    {
        $sections = [];

        // Initialize each section from the config
        foreach ($this->getSectionConfig() as $code => $config) {
            // Skip if section is not enabled
            if (!($config['enabled'] ?? true)) {
                continue;
            }

            $block = $this->getLayout()->createBlock($config['class'] ?? Template::class);
            $content = $block->toHtml();

            if (!empty($content)) {
                $sections[$code] = [
                    'content' => $content,
                    'visible' => true,
                    'id' => $config['id'],
                    'label' => $config['label'] ?? ($block->getWidgetHeading() ?? ucfirst($code)),
                    'sort_order' => $config['sort_order'] ?? 999
                ];
            }
        }

        // Sort sections by sort order
        uasort($sections, function ($a, $b) {
            return ($a['sort_order'] ?? 999) <=> ($b['sort_order'] ?? 999);
        });

        $this->setData('sections', $sections);
    }

    /**
     * Get navigation block HTML
     *
     * @return string
     * @throws LocalizedException
     */
    public function getNavigationHtml(): string
    {
        $navigationBlock = $this->getLayout()->createBlock(Navigation::class);

        // Pass only the necessary data for navigation
        $navigationItems = [];
        foreach ($this->getSections() as $code => $section) {
            $navigationItems[$code] = [
                'code' => $code,
                'label' => $section['label'],
                'sort_order' => $section['sort_order'],
                'section_id' => $section['id']
            ];
        }

        $navigationBlock->setData('navigation_items', $navigationItems);
        return $navigationBlock->toHtml();
    }

    /**
     * Get sections data
     *
     * @return array
     */
    public function getSections(): array
    {
        return $this->getData('sections') ?? [];
    }

    /**
     * Get the section configuration
     *
     * @return array
     */
    public function getSectionConfig(): array
    {
        return $this->getData('section_config') ?? [];
    }
}
