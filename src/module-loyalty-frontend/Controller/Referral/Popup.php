<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Controller\Referral;

use Leat\Loyalty\Model\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

class Popup implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param PageFactory $resultPageFactory
     * @param RawFactory $resultRawFactory
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly PageFactory $resultPageFactory,
        private readonly RawFactory $resultRawFactory,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Get popup HTML
     *
     * @return Raw
     */
    public function execute(): Raw
    {
        $resultRaw = $this->resultRawFactory->create();
        $referralCode = $this->request->getParam('referral_code');

        if (!$referralCode) {
            return $resultRaw->setContents('');
        }

        $storeId = (int)$this->storeManager->getStore()->getId();

        // Use isIsolated to create a minimal layout without full page structure
        // This is more efficient for AJAX responses that only need specific content
        $page = $this->resultPageFactory->create(false, ['isIsolated' => true]);
        $block = $page->getLayout()->createBlock(
            \Magento\Framework\View\Element\Template::class,
            'referral_popup_content'
        )->setTemplate('Leat_LoyaltyFrontend::loyalty/popup/refer_a_friend.phtml')
            ->setReferralCode($referralCode)
            ->setData('popup_title', $this->config->getReferralPopupTitle($storeId))
            ->setData('popup_subtitle', $this->config->getReferralPopupSubtitle($storeId))
            ->setData('popup_message', $this->config->getReferralPopupMessage($storeId))
            ->setData('popup_button_text', $this->config->getReferralPopupButtonText($storeId))
            ->setData('popup_success_message', $this->config->getReferralPopupSuccessMessage($storeId))
            ->setData('popup_discount_amount', $this->config->getReferralPopupDiscountAmount($storeId));

        return $resultRaw->setContents($block->toHtml());
    }
}
