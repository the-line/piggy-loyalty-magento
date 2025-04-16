<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Widget;

use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Exception\NoContactException;
use Leat\Loyalty\Model\Config as LeatConfig;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\LoyaltyFrontend\Block\GenericWidgetBlock;
use Leat\Loyalty\Model\Config;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class ReferAFriend extends GenericWidgetBlock
{
    /**
     * @var string
     */
    protected $_template = 'Leat_LoyaltyFrontend::widget/refer_a_friend.phtml';

    /**
     * @var string
     */
    protected string $defaultId = 'leat-refer';

    /**
     * @var string
     */
    protected string $defaultCssClass = 'leat-refer-a-friend';

    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected Config                $config,
        protected Session               $customerSession,
        protected ContactResource       $contactResource,
        protected Connector             $connector,
        protected RequestTypePool       $requestTypePool,
        Context                         $context,
        protected LeatConfig $leatConfig,
        array $data = []
    ) {
        parent::__construct(
            $storeManager,
            $config,
            $customerSession,
            $this->contactResource,
            $connector,
            $this->requestTypePool,
            $context
        );
    }

    /**
     * Get widget heading
     *
     * @return string
     */
    public function getWidgetHeading(): string
    {
        return (string) ($this->getData('widget_heading') ?: $this->leatConfig->getReferAFriendWidgetHeading() ?: __('Refer a friend'));
    }

    /**
     * Get section title
     *
     * @return string
     */
    public function getSectionTitle(): string
    {
        return (string) ($this->getData('section_title') ?: $this->leatConfig->getReferAFriendSectionTitle() ?: __('Refer a friend'));
    }

    /**
     * Get section subtitle
     *
     * @return string
     */
    public function getSectionSubtitle(): string
    {
        return (string) ($this->getData('section_subtitle') ?: $this->leatConfig->getReferAFriendSectionSubtitle() ?: __('Invite a friend and get rewards'));
    }

    /**
     * Get share message
     *
     * @return string
     */
    public function getShareMessage(): string
    {
        return (string) ($this->getData('share_message') ?: $this->leatConfig->getReferAFriendShareMessage() ?: __('Get a discount on your first purchase!'));
    }

    /**
     * Get email subject
     *
     * @return string
     */
    public function getEmailSubject(): string
    {
        return (string) ($this->getData('email_subject') ?: $this->leatConfig->getReferAFriendEmailSubject() ?: __('I have a discount for you'));
    }

    /**
     * Get referral URL
     *
     * @return string
     */
    public function getReferralUrl(): string
    {
        $fallback = '';
        try {
            if (!$this->customerSession->isLoggedIn()) {
                return '';
            }

            // Get customer's contact UUID from Leat
            $contact = $this->getContactForCustomer();

            // Generate referral URL with the contact UUID
            $storeUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
            $referralCode = $contact->getCurrentValues()['referral_code'] ?? null;
            if (!$referralCode) {
                return $fallback;
            }
            return $storeUrl . '?___referral-code=' . $referralCode;
        } catch (NoContactException | LocalizedException | NoSuchEntityException $e) {
            return $fallback;
        }
    }

    /**
     * Check if icon is enabled
     *
     * @param string $type
     * @return bool
     */
    public function isIconEnabled(string $type): bool
    {
        $widgetEnabledIcons = $this->getData('enabled_icons');
        if ($widgetEnabledIcons) {
            $enabledIconsArray = explode(',', $widgetEnabledIcons);
        } else {
            $enabledIconsArray = $this->leatConfig->getReferAFriendEnabledIcons();
        }
        return in_array($type, $enabledIconsArray);
    }

    /**
     * Get icon options for configuration
     *
     * @return array
     */
    public function getIconOptions(): array
    {
        return [
            'copy' => __('Copy'),
            'twitter' => __('Twitter/X'),
            'whatsapp' => __('WhatsApp'),
            'email' => __('Email'),
            'sms' => __('SMS')
        ];
    }

    /**
     * Get URL encoded share message
     *
     * @return string
     */
    public function getEncodedShareMessage(): string
    {
        return urlencode($this->getShareMessage());
    }

    /**
     * Get URL encoded referral URL
     *
     * @return string
     */
    public function getEncodedReferralUrl(): string
    {
        return urlencode($this->getReferralUrl());
    }
}
