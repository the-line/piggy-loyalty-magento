<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    protected const string XML_PATH_PIGGY_IS_ENABLED = 'leat/general/is_enabled';
    protected const string XML_PATH_PIGGY_ORDER_EXPORT_ENABLED = 'leat/order/order_export_enabled';
    protected const string XML_PATH_PIGGY_PENDING_PAYMENT_EXPORT = 'leat/order/allow_pending';

    protected const string XML_PATH_PIGGY_CUSTOMER_GROUP_MAPPING = 'leat/general/customer_group_mapping';

    protected const string XML_PATH_PIGGY_PERSONAL_ACCESS_TOKEN = 'leat/connection/personal_access_token';
    protected const string XML_PATH_PIGGY_CLIENT_SHOP_UUID = 'leat/connection/shop_uuid';

    protected const string XML_PATH_PIGGY_QUEUE_ALERT_TO = 'leat/queue/alert_to';
    protected const string XML_PATH_PIGGY_CALLS_PER_SECOND = 'leat/queue/calls_per_second';
    protected const string XML_GENERAL_CONTACT_NAME = 'trans_email/ident_general/name';
    protected const string XML_GENERAL_CONTACT_EMAIL = 'trans_email/ident_general/email';

    protected const XML_PATH_PIGGY_CREDITS_LABEL = 'leat/credits/label';
    protected const XML_PATH_PIGGY_CREDITS_SHOW_CART = 'leat/credits/show_on_cart_page';
    protected const XML_PATH_PIGGY_CREDITS_SHOW_CHECKOUT_SUCCESS = 'leat/credits/show_on_checkout_success_page';
    protected const XML_RAF_ENABLE = 'leat/refer_a_friend/enabled';
    protected const XML_RAF_WIDGET_HEADING = 'leat/refer_a_friend/widget_heading';
    protected const XML_RAF_SECTION_TITLE = 'leat/refer_a_friend/section_title';
    protected const XML_RAF_SECTION_SUBTITLE = 'leat/refer_a_friend/section_subtitle';
    protected const XML_RAF_SHARE_MESSAGE = 'leat/refer_a_friend/share_message';
    protected const XML_RAF_EMAIL_SUBJECT = 'leat/refer_a_friend/email_subject';
    protected const XML_RAF_ENABLED_ICONS = 'leat/refer_a_friend/enabled_icons';

    // Activity Log Configuration Constants
    protected const XML_ACTIVITY_LOG_WIDGET_HEADING = 'leat/activity_log/widget_heading';

    // Your Coupons Configuration Constants
    protected const XML_YOUR_COUPONS_WIDGET_HEADING = 'leat/coupons/widget_heading';

    // Rewards Configuration Constants
    protected const XML_REWARDS_WIDGET_HEADING = 'leat/rewards/widget_heading';

    // Referral Popup Configuration Constants
    protected const XML_RAF_POPUP_TITLE = 'leat/refer_a_friend/popup_title';
    protected const XML_RAF_POPUP_SUBTITLE = 'leat/refer_a_friend/popup_subtitle';
    protected const XML_RAF_POPUP_MESSAGE = 'leat/refer_a_friend/popup_message';
    protected const XML_RAF_POPUP_BUTTON_TEXT = 'leat/refer_a_friend/popup_button_text';
    protected const XML_RAF_POPUP_SUCCESS_MESSAGE = 'leat/refer_a_friend/popup_success_message';
    protected const XML_RAF_POPUP_DISCOUNT_AMOUNT = 'leat/refer_a_friend/popup_discount_amount';

    // Prepaid Balance Configuration Constants
    protected const XML_PREPAID_BALANCE_ENABLED = 'leat/prepaid_balance/enabled';
    protected const XML_PREPAID_BALANCE_TITLE = 'leat/prepaid_balance/title';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        protected ScopeConfigInterface $scopeConfig,
    ) {
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function getIsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PIGGY_IS_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get if orders should be exported to Leat.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function getIsOrderExportEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PIGGY_ORDER_EXPORT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Allow these payment methods to export to Leat when status is 'pending'
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPendingPaymentOrderExport(int $storeId): array
    {
        $config = $this->scopeConfig->getValue(
            self::XML_PATH_PIGGY_PENDING_PAYMENT_EXPORT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($config) {
            if (is_string($config)) {
                return array_unique(explode(',', $config));
            }
            return $config;
        }

        return [];
    }

    /**
     * Get a list of Customer Group IDs that are allowed to use Leat
     *
     * @param int|null $storeId
     * @return array<int>
     */
    public function getCustomerGroupMapping(?int $storeId = null): array
    {
        if (!$this->getIsEnabled()) {
            return [];
        }

        $config = $this->scopeConfig->getValue(
            self::XML_PATH_PIGGY_CUSTOMER_GROUP_MAPPING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($config) {
            if (is_string($config)) {
                $config = array_unique(explode(',', $config));
            } elseif (!is_array($config)) {
                return [];
            }

            return array_map('intval', $config);
        }

        return [];
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getPersonalAccessToken(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PIGGY_PERSONAL_ACCESS_TOKEN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getShopUuid(?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_PIGGY_CLIENT_SHOP_UUID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value !== null ? (string) $value : null;
    }

    /**
     * When Leat Connector causes an error, it will email these configured email addresses
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getAlertTo(): array
    {
        $config = $this->scopeConfig->getValue(
            self::XML_PATH_PIGGY_QUEUE_ALERT_TO
        );

        if ($config) {
            if (is_string($config)) {
                return array_unique(explode(',', $config));
            }
            return $config;
        }

        return [];
    }

    /**
     * Get calls per second
     *
     * @return float
     */
    public function getCallsPerSecond(): float
    {
        return (float) $this->scopeConfig->getValue(
            self::XML_PATH_PIGGY_CALLS_PER_SECOND
        );
    }

    /**
     * Get general contact name
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getGeneralContactName(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_GENERAL_CONTACT_NAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get general contact email address
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getGeneralContactEmailAddress(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_GENERAL_CONTACT_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param mixed|null $storeId
     * @return string|null
     */
    public function getCreditName(mixed $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PIGGY_CREDITS_LABEL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param mixed|null $storeId
     * @return bool
     */
    public function isShowOnCartPage(mixed $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PIGGY_CREDITS_SHOW_CART,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param mixed|null $storeId
     * @return bool
     */
    public function isShowOnCheckoutSuccessPage(mixed $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PIGGY_CREDITS_SHOW_CHECKOUT_SUCCESS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isReferAFriendEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_RAF_ENABLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Refer a Friend widget heading
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getReferAFriendWidgetHeading(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_RAF_WIDGET_HEADING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Refer a Friend section title
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getReferAFriendSectionTitle(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_RAF_SECTION_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Refer a Friend section subtitle
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getReferAFriendSectionSubtitle(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_RAF_SECTION_SUBTITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Refer a Friend share message
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getReferAFriendShareMessage(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_RAF_SHARE_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Refer a Friend email subject
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getReferAFriendEmailSubject(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_RAF_EMAIL_SUBJECT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Refer a Friend enabled icons
     *
     * @param int|null $storeId
     * @return array
     */
    public function getReferAFriendEnabledIcons(?int $storeId = null): array
    {
        $config = $this->scopeConfig->getValue(
            self::XML_RAF_ENABLED_ICONS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($config) {
            if (is_string($config)) {
                return array_unique(explode(',', $config));
            }
            return (array)$config;
        }

        return ['copy', 'twitter', 'whatsapp', 'email', 'sms'];
    }

    /**
     * Get Referral Popup title
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReferralPopupTitle(?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_RAF_POPUP_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return (string) ($value ?: __('We have a discount for you'));
    }

    /**
     * Get Referral Popup subtitle
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReferralPopupSubtitle(?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_RAF_POPUP_SUBTITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return (string) ($value ?: __('Get %1 on your first purchase!', $this->getReferralPopupDiscountAmount($storeId)));
    }

    /**
     * Get Referral Popup message
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReferralPopupMessage(?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_RAF_POPUP_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return (string) ($value ?: __('Enter your email below and we will send you the discount code.'));
    }

    /**
     * Get Referral Popup button text
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReferralPopupButtonText(?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_RAF_POPUP_BUTTON_TEXT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return (string) ($value ?: __('Get discount'));
    }

    /**
     * Get Referral Popup success message
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReferralPopupSuccessMessage(?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_RAF_POPUP_SUCCESS_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return (string) ($value ?: __('Thank you! Your discount code will be sent to your email.'));
    }

    /**
     * Get Referral Popup discount amount
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReferralPopupDiscountAmount(?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_RAF_POPUP_DISCOUNT_AMOUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return (string) ($value ?: __('50% off'));
    }

    /**
     * Check if prepaid balance feature is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isPrepaidBalanceEnabled(?int $storeId = null): bool
    {
        return $this->getIsEnabled() && $this->scopeConfig->isSetFlag(
            self::XML_PREPAID_BALANCE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get prepaid balance section title
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPrepaidBalanceTitle(?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PREPAID_BALANCE_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return (string) ($value ?: __('Use Your Prepaid Balance'));
    }

    /**
     * Get Activity Log widget heading
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getActivityLogWidgetHeading(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_ACTIVITY_LOG_WIDGET_HEADING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Your Coupons widget heading
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getYourCouponsWidgetHeading(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_YOUR_COUPONS_WIDGET_HEADING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Rewards widget heading
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getRewardsWidgetHeading(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_REWARDS_WIDGET_HEADING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
