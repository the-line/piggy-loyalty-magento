<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Adminhtml\Order\Invoice\Totals;

use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;

/**
 * Admin invoice totals leat balance block
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
        $source = $parent->getSource();

        // Try to get the order
        $order = null;
        if ($source && method_exists($source, 'getOrder')) {
            $order = $source->getOrder();
        }

        if (!$order instanceof Order) {
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
            'sort_order' => 750
        ]);

        $parent->addTotalBefore($total, 'grand_total');

        return $this;
    }
}
