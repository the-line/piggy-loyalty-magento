<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Controller\Loyalty;

use Leat\Loyalty\Model\Config;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

class Index implements HttpGetActionInterface
{
    public function __construct(
        protected Config $config,
        protected RedirectFactory $redirectFactory,
        protected PageFactory $resultPageFactory,
        protected ForwardFactory $forwardFactory,
        protected Session $customerSession,
        protected UrlInterface $url,
        protected StoreManagerInterface $storeManager,
        protected ResultFactory $resultFactory
    ) {
    }

    /**
     * Route user to loyalty dashboard page
     * - Redirects to login page if not logged in
     *
     * @return ResultInterface|ResponseInterface|Forward|Redirect
     * @throws NotFoundException
     */
    public function execute()
    {
        if (!$this->config->getIsEnabled()) {
            $resultForward = $this->forwardFactory->create();
            return $resultForward->forward('noroute');
        }

        if (!$this->customerSession->isLoggedIn()) {
            // Get URL of the login page
            $loginUrl = $this->url->getUrl('customer/account/login');

            // Save requested URL for redirect after login
            $this->customerSession->setBeforeAuthUrl(
                $this->url->getUrl('*/*/index')
            );

            // Redirect to login page
            $resultRedirect = $this->redirectFactory->create();
            $resultRedirect->setUrl($loginUrl);
            return $resultRedirect;
        }

        // Customer is logged in, show the loyalty dashboard
        return $this->resultPageFactory->create();
    }
}
