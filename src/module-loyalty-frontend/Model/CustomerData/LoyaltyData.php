<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Model\CustomerData;

use Leat\Loyalty\Model\AppliedCouponsManager;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\LoyaltyFrontend\Block\Widget\ActivityLog;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\BlockFactory;

class LoyaltyData implements SectionSourceInterface
{
    protected const array DEFAULT_RETURN = [
        'points' => 0,
        'hasContact' => false,
        'appliedCoupons' => [],
        'transactions' => []
    ];

    /**
     * @var \Leat\LoyaltyFrontend\Block\Widget\ActivityLog|null
     */
    protected $activityLogBlock;

    public function __construct(
        protected CustomerSession $customerSession,
        protected ContactResource $contactResource,
        protected AppliedCouponsManager $appliedCouponsManager,
        protected BlockFactory $blockFactory
    ) {
        // Create ActivityLog block instance
        try {
            $this->activityLogBlock = $this->blockFactory->createBlock(
                ActivityLog::class,
                [
                    'data' => []
                ]
            );
        } catch (\Throwable $e) {
            // If block creation fails, we'll handle it in getTransactionsData
            $this->activityLogBlock = null;
        }
    }

    /**
     * Get customer data for the Leat section
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getSectionData(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return self::DEFAULT_RETURN;
        }

        $customerId = (int)$this->customerSession->getCustomerId();

        try {
            if (!$this->contactResource->hasContactUuid($customerId)) {
                return self::DEFAULT_RETURN;
            }

            $contact = $this->contactResource->getCustomerContact($customerId);
            $points = $contact->getCreditBalance()->getBalance() ?? 0;

            // Get applied coupons from the quote
            $appliedCoupons = $this->appliedCouponsManager->getAllAppliedCoupons(true);
            $prepaidBalance = $contact->getPrepaidBalance()->getBalanceInCents() ?? 0;

            // Get transaction data for activity log
            $transactions = $this->getTransactionsData();

            return [
                'points' => $points,
                'hasContact' => true,
                'appliedCoupons' => $appliedCoupons,
                'prepaidBalance' => $prepaidBalance / 100,
                'transactions' => $transactions
            ];
        } catch (\Throwable $e) {
            return self::DEFAULT_RETURN;
        }
    }

    /**
     * Get transaction data for the activity log
     *
     * @return array
     */
    private function getTransactionsData(): array
    {
        try {
            // Check if ActivityLog block was successfully created
            if ($this->activityLogBlock === null) {
                return [];
            }

            // Use the ActivityLog widget to get the transactions
            return $this->activityLogBlock->getCustomerTransactions();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
