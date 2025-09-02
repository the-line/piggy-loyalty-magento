<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Widget;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Leat\Loyalty\Model\Config;

class GiftcardBalance extends Template
{
    protected $_template = 'Leat_LoyaltyFrontend::widget/giftcard_balance.phtml';

    /**
     * @param Context $context
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Check if the gift card balance check feature should be displayed.
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isEnabled(): bool
    {
        $storeId = (int)$this->_storeManager->getStore()->getId();
        return $this->config->isGiftcardEnabled($storeId) && $this->config->isGiftcardBalanceCheckEnabled($storeId);
    }

    /**
     * Get the URL for the balance check AJAX controller.
     *
     * @return string
     */
    public function getBalanceCheckUrl(): string
    {
        return $this->getUrl('leat/loyalty/checkGiftcardBalance');
    }

    /**
     * Override _toHtml to prevent rendering if disabled
     *
     * @return string
     * @throws NoSuchEntityException
     */
    protected function _toHtml()
    {
        if (!$this->isEnabled()) {
            return '';
        }

        return parent::_toHtml();
    }
}
