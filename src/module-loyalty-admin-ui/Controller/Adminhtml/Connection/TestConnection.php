<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Controller\Adminhtml\Connection;

use Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config\TestConnection as TestConnectionBlock;
use Leat\LoyaltyAdminUI\Service\ConnectionTester;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;

class TestConnection extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * Authorization level
     */
    const string ADMIN_RESOURCE = 'Leat_Loyalty::config';

    public function __construct(
        Context $context,
        protected JsonFactory $resultJsonFactory,
        protected ConnectionTester $connectionTester,
        protected StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $storeId = (int) $this->getRequest()->getParam('store', 0);
            $status = $this->connectionTester->testConnection($storeId);

            return $result->setData($status);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Error testing connection: %1', $e->getMessage())
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setData([
            'success' => false,
            'message' => __('Invalid Form Key. Please refresh the page.')
        ]);

        return new InvalidRequestException(
            $resultJson,
            [__('Invalid Form Key. Please refresh the page.')]
        );
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
