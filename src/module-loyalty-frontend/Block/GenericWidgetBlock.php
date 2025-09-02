<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block;

use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Model\Logger;
use Leat\Loyalty\Model\Client;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\Loyalty\Model\Config;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Block\BlockInterface;
use Piggy\Api\Models\Contacts\Contact;

abstract class GenericWidgetBlock extends Template implements BlockInterface
{
    protected const string LOGGER_PURPOSE = 'widget';

    /**
     * @var bool|null
     */
    protected ?bool $show = null;

    /**
     * Default widget ID (for anchor navigation)
     *
     * @var string
     */
    protected string $defaultId = '';

    /**
     * Default CSS class for the widget
     *
     * @var string
     */
    protected string $defaultCssClass = '';

    /**
     * Position in the loyalty page (set by the Index block)
     *
     * @var int
     */
    protected int $position = 0;

    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected Config                $config,
        protected Session               $customerSession,
        protected ContactResource       $contactResource,
        protected Connector             $connector,
        protected RequestTypePool       $requestTypePool,
        Context                         $context,
    ) {
        parent::__construct($context);
    }

    /**
     * Check if the widget should be shown
     * - Check if Leat is enabled
     * - Check if the customer is logged in
     * - Check if the customer has a Leat UUID
     *
     * @return bool
     */
    public function show(): bool
    {
        if (!isset($this->show)) {
            try {
                $this->show = $this->isLeatEnabled() && $this->enabledForCustomer() && $this->getClient();
            } catch (\Throwable $e) {
                $this->show = false;
            }
        }

        return $this->show;
    }


    /**
     * Get the label for this widget
     *
     * @return string
     */
    abstract public function getWidgetHeading(): string;

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function isLeatEnabled(): bool
    {
        return $this->config->getIsEnabled($this->getStoreId());
    }

    /**
     * @return bool
     */
    protected function enabledForCustomer(): bool
    {
        try {
            if (!$this->customerSession->isLoggedIn()) {
                return false;
            }

            $hasUUID = $this->contactResource->hasContactUuid((int) $this->customerSession->getCustomerId());
            $customerGroupId = $this->getCustomerGroupId() ?? 0;
            return $hasUUID && in_array($customerGroupId, $this->config->getCustomerGroupMapping());
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function getCustomerGroupId(): int
    {
        return (int) $this->customerSession->getCustomerGroupId();
    }

    /**
     * @return string|null
     */
    protected function getContactUUIDForCustomer(): ?string
    {
        return $this->contactResource->getContactUuid((int) $this->customerSession->getCustomerId());
    }

    /**
     * @return ?Contact
     */
    protected function getContactForCustomer(): ?Contact
    {
        return $this->contactResource->getCustomerContact((int) $this->customerSession->getCustomerId());
    }

    /**
     * Get the Leat Client object
     * - If the client can't be authenticated, return null
     *
     * @return Client
     * @throws AuthenticationException
     */
    protected function getClient(): ?Client
    {
        try {
            return $this->getConnector()->getConnection();
        } catch (AuthenticationException $e) {
            return null;
        }
    }

    /**
     * Return Leat Connector object
     *
     * @return Connector
     */
    private function getConnector(): Connector
    {
        return $this->connector;
    }

    /**
     * Get the store id for the current store
     *
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getStoreId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getShopUUID(): string
    {
        return $this->config->getShopUUID($this->getStoreId());
    }

    /**
     * @return Logger
     */
    protected function getLogger(): Logger
    {
        return $this->getConnector()->getLogger(static::LOGGER_PURPOSE);
    }

    /**
     * Get widget ID (for anchor navigation)
     *
     * @return string
     */
    public function getWidgetId(): string
    {
        return $this->getData('widget_id') ?? $this->defaultId;
    }

    /**
     * Get CSS classes for the widget
     *
     * @return string
     */
    public function getWidgetCssClass(): string
    {
        $classes = [];

        // Add default class
        if (!empty($this->defaultCssClass)) {
            $classes[] = $this->defaultCssClass;
        }

        // Add custom class if provided
        $customClass = $this->getData('css_class');
        if (!empty($customClass)) {
            $classes[] = $customClass;
        }

        // Add position class if set
        if (($this->getPosition() ?? 0) > 0) {
            $classes[] = 'section-position-' . $this->position;
        }

        return implode(' ', $classes);
    }
}
