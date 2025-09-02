<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;

class GiftCards extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Leat_LoyaltyAdminUI::order/view/gift_cards.phtml';
    
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;
    
    /**
     * @var AppliedGiftCardRepositoryInterface
     */
    private $appliedGiftCardRepository;
    
    /**
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param AppliedGiftCardRepositoryInterface $appliedGiftCardRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        AppliedGiftCardRepositoryInterface $appliedGiftCardRepository,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        $this->appliedGiftCardRepository = $appliedGiftCardRepository;
        parent::__construct($context, $data);
    }
    
    /**
     * Get current order
     *
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    public function getOrder()
    {
        return $this->orderRepository->get($this->getOrderId());
    }
    
    /**
     * Get current order ID
     *
     * @return int
     */
    public function getOrderId()
    {
        return (int)$this->getRequest()->getParam('order_id');
    }
    
    /**
     * Get applied gift cards for the current order
     *
     * @return \Leat\Loyalty\Api\Data\AppliedGiftCardInterface[]
     */
    public function getAppliedGiftCards()
    {
        return $this->appliedGiftCardRepository->getByOrderId($this->getOrderId());
    }
    
    /**
     * Get total gift card amount
     *
     * @return float
     */
    public function getTotalGiftCardAmount()
    {
        $total = 0;
        foreach ($this->getAppliedGiftCards() as $giftCard) {
            $total += (float)$giftCard->getAppliedAmount();
        }
        return $total;
    }
    
    /**
     * Format price
     *
     * @param float $price
     * @return string
     */
    public function formatPrice($price)
    {
        return $this->getOrder()->formatPrice($price);
    }
}
