<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    // Generic Magento email / Email name xml paths
    protected const XML_GENERAL_CONTACT_NAME = 'trans_email/ident_general/name';
    protected const XML_GENERAL_CONTACT_EMAIL = 'trans_email/ident_general/email';

    protected const XML_PATH_ASYNC_QUEUE_ALERT_TO = 'async_queue/general/alert_to';

    public function __construct(
        protected ScopeConfigInterface $scopeConfig,
    ) {
    }

    /**
     * Get general contact name
     *
     * @param string|null $websiteId
     * @return string|null
     */
    public function getGeneralContactName(?string $websiteId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_GENERAL_CONTACT_NAME,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    /**
     * Get general contact email address
     *
     * @param string|null $websiteId
     * @return string|null
     */
    public function getGeneralContactEmailAddress(?string $websiteId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_GENERAL_CONTACT_EMAIL,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    /**
     * When Async Queue causes an error, it will email these configured email addresses
     *
     * @param string|null $websiteId
     * @return array
     */
    public function getAlertTo(?string $websiteId = null): array
    {
        $config = $this->scopeConfig->getValue(
            self::XML_PATH_ASYNC_QUEUE_ALERT_TO,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );

        if ($config) {
            if (is_string($config)) {
                return array_unique(explode(',', $config));
            }
            return $config;
        }

        return [];
    }
}
