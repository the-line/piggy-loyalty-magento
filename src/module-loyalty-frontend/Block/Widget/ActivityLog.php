<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Widget;

use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Exception\NoContactException;
use Leat\Loyalty\Model\Config as LeatConfig;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\AttributeResource;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\Loyalty\Model\Transaction\LoyaltyTransactionOrderItems;
use Leat\LoyaltyFrontend\Block\GenericWidgetBlock;
use Leat\Loyalty\Model\Config;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Block\BlockInterface;
use Piggy\Api\Exceptions\PiggyRequestException;
use Piggy\Api\Models\Loyalty\Receptions\CreditReception;
use Piggy\Api\Models\Loyalty\Receptions\DigitalRewardReception;
use Piggy\Api\Models\Loyalty\Receptions\PhysicalRewardReception;

class ActivityLog extends GenericWidgetBlock
{
    public const string DASHBOARD_CHANNEL = 'BUSINESS_DASHBOARD';

    /**
     * @var string
     */
    protected $_template = 'Leat_LoyaltyFrontend::widget/activity_log.phtml';

    /**
     * @var string
     */
    protected string $defaultId = 'leat-activity';

    /**
     * @var string
     */
    protected string $defaultCssClass = 'leat-activity-log';

    /**
     * @var array
     */
    protected array $transactionCache = [];

    public function __construct(
        StoreManagerInterface                  $storeManager,
        Config                                 $config,
        Session                                $customerSession,
        ContactResource                        $contactResource,
        Connector                              $connector,
        RequestTypePool                        $requestTypePool,
        Context                                $context,
        protected LeatConfig                   $leatConfig,
        protected LoyaltyTransactionOrderItems $orderItems,
        protected TimezoneInterface            $timezone,
        protected AttributeResource            $attributeResource,
        array                                  $data = []
    ) {
        parent::__construct(
            $storeManager,
            $config,
            $customerSession,
            $contactResource,
            $connector,
            $requestTypePool,
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
        return (string) ($this->getData('widget_heading') ?: $this->leatConfig->getActivityLogWidgetHeading() ?: __('Your Activity'));
    }

    /**
     * Get transactions for current customer
     *
     * @return array
     * @throws PiggyRequestException
     */
    /**
     * Create a safe transaction filter callback that checks for method existence
     *
     * @return callable
     */
    public function getTransactionFilter(): callable
    {
        return function (array $transactions) {
            return $transactions;
        };
    }

    public function getCustomerTransactions(): array
    {
        if (!empty($this->transactionCache)) {
            return $this->transactionCache;
        }

        if (!$this->customerSession->isLoggedIn()) {
            return [];
        }

        try {
            $customer = $this->customerSession->getCustomer();
            $transactions = $this->orderItems->getTransactions([
                'customer' => $customer
            ], $this->getTransactionFilter());

            // Group transactions by order increment ID
            $groupedTransactions = [];
            foreach ($transactions as $transaction) {
                $attributes = method_exists($transaction, 'getAttributes') ? $transaction->getAttributes() : [];
                $incrementId = $attributes['increment_id'] ?? '';

                if (!empty($incrementId)) {
                    if (!isset($groupedTransactions[$incrementId])) {
                        $groupedTransactions[$incrementId] = [];
                    }
                    $groupedTransactions[$incrementId][] = $transaction;
                } else {
                    // Non-order Magento transactions like reward redemptions, Shopify transactions, etc.
                    $groupedTransactions["non_order_{$transaction->getUuid()}"] = [$transaction];
                }
            }

            // Process each group into a single activity item
            $processedTransactions = [];
            foreach ($groupedTransactions as $groupId => $transactionGroup) {
                $processedTransactions[] = $this->processTransactionGroup($groupId, $transactionGroup);
            }

            // Sort by date, newest first
            usort($processedTransactions, function ($a, $b) {
                return $b['timestamp'] <=> $a['timestamp'];
            });

            $this->transactionCache = $processedTransactions;
            return $processedTransactions;
        } catch (NoContactException|LocalizedException|NoSuchEntityException|AuthenticationException $e) {
            return [];
        }
    }

    /**
     * Process a group of transactions into a single activity item
     *
     * @param string $groupId
     * @param array $transactions
     * @return array
     */
    public function processTransactionGroup(string $groupId, array $transactions): array
    {
        $isOrder = !str_contains($groupId, 'non_order_');
        $firstTransaction = $transactions[0] ?? null;

        if (!$firstTransaction) {
            return [];
        }

        $createdAt = $firstTransaction->getCreatedAt();
        $points = 0;
        foreach ($transactions as $transaction) {
            $points += $transaction->getCredits() ?? 0;
        }

        $description = $this->getTransactionDescription($transactions[0], $isOrder);
        $incrementId = $isOrder ? $groupId : '';
        return [
            'action' => $description,
            'points' => $points,
            'date' => $this->formatTransactionDate($createdAt),
            'order_id' => $incrementId,
            'timestamp' => $createdAt->getTimestamp()
        ];
    }

    /**
     * Get a description for the transaction group
     *
     * @param DigitalRewardReception|PhysicalRewardReception|CreditReception $transactions
     * @param bool $isOrder
     * @return string
     */
    public function getTransactionDescription(mixed $transaction, bool $isOrder): string
    {
        if ($isOrder) {
            return (string) __('For every 100€ spent, earn 10 %1', $this->getCreditName());
        }

        if (isset($transaction)) {
            switch (true) {
                case $transaction instanceof DigitalRewardReception:
                    if (method_exists($transaction, 'getDigitalReward')) {
                        return (string) __('Reward redeemed: %1', $transaction->getDigitalReward()->getTitle());
                    }

                    return (string) __('Digital Reward redemption');
                case $transaction instanceof PhysicalRewardReception:
                    if (method_exists($transaction, 'getReward')) {
                        return (string) __('Reward redeemed: %1', $transaction->getReward()->getTitle());
                    }

                    return (string) __('Physical Reward redemption');
                case $transaction instanceof CreditReception:
                    if ($transaction->getChannel() === self::DASHBOARD_CHANNEL) {
                        return (string) __('Lucky~ %1 granted via Loyalty Dashboard', $this->getCreditName());
                    }

                    return (string) __('Prepaid Balance Reception');
                default:
                    return (string) __('%1 Transaction', $this->getCreditName());
            }
        }

        return (string) __("Unknown Transaction");
    }

    /**
     * Format date according to Magento's AbstractBlock compatibility
     *
     * @param \DateTime|string|int|null $date
     * @param int $format
     * @param bool $showTime
     * @param string|null $timezone
     * @return string
     */
    public function formatDate($date = null, $format = \IntlDateFormatter::MEDIUM, $showTime = false, $timezone = null): string
    {
        if ($date instanceof \DateTime) {
            return parent::formatDate($date, $format, $showTime, $timezone);
        }

        return parent::formatDate($date, $format, $showTime, $timezone);
    }

    /**
     * Format transaction date
     *
     * @param \DateTime $date
     * @return string
     */
    public function formatTransactionDate(\DateTime $date): string
    {
        return $this->formatDate($date, \IntlDateFormatter::MEDIUM, false);
    }

    /**
     * Format points value with sign
     *
     * @param int $points
     * @return string
     */
    public function formatPoints(int $points): string
    {
        return $points >= 0 ? "+{$points}" : (string)$points;
    }

    /**
     * @return string
     */
    public function getCreditName(): string
    {
        return $this->config->getCreditName() ?: (string) __('Points');
    }
}
