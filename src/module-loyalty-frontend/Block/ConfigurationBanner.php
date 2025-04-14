<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block;

use Leat\Loyalty\Model\Client;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class ConfigurationBanner extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Leat_LoyaltyFrontend::banner/configuration.phtml';

    /**
     * @var bool|null
     */
    protected ?bool $needsConfiguration = null;

    public function __construct(
        Context $context,
        protected Config $config,
        protected StoreManagerInterface $storeManager,
        protected Connector $connector,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Check if the banner should be shown
     * - Only show for admin users
     * - Only show if module is enabled but not correctly configured
     *
     * @return bool
     */
    public function showBanner(): bool
    {
        return $this->moduleNeedsConfiguration();
    }

    /**
     * Check if the module needs configuration
     * - Module is enabled
     * - But connection can't be established
     *
     * @return bool
     */
    protected function moduleNeedsConfiguration(): bool
    {
        if ($this->needsConfiguration === null) {
            $this->needsConfiguration = !((bool) $this->getClient());
        }

        return $this->needsConfiguration;
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
     * Get store ID for the current store
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

    /**
     * Get the admin URL for the Leat configuration
     *
     * @return string
     */
    public function getConfigurationUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit/section/leat');
    }
}
