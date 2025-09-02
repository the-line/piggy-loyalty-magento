<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Sales\Order\Totals;

use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Adds leat prepaid balance total to order totals block
 */
class LeatGiftcard extends Template
{
    /**
     * @param Context $context
     * @param AppliedGiftCardRepositoryInterface $appliedGiftCardRepository
     * @param array $data
     */
    public function __construct(
        Context                                              $context,
        private readonly AppliedGiftCardRepositoryInterface  $appliedGiftCardRepository,
        array                                                $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Initialize leat balance total
     *
     * @return $this
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $order = $parent->getOrder();

        if (!$order) {
            return $this;
        }

        $giftcardAmount = 0.0;
        $cards = $this->appliedGiftCardRepository->getByOrderId((int) $order->getId());

        if (empty($cards)) {
            return $this;
        }

        foreach ($cards as $card) {
            $giftcardAmount += $card->getBaseAppliedAmount();
        }

        if ($giftcardAmount <= 0) {
            return $this;
        }

        $total = new DataObject([
            'code' => 'leat_loyalty_giftcard',
            'strong' => true,
            'value' => -$giftcardAmount,
            'base_value' => -$giftcardAmount,
            'label' => __('Gift Card Amount'),
            'sort_order' => 450,
            'is_formated' => false,
            'area' => 'footer'
        ]);

        $parent->addTotal($total, 'subtotal');

        return $this;
    }
}
