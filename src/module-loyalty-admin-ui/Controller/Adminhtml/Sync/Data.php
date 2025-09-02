<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Controller\Adminhtml\Sync;

use Leat\Loyalty\Model\ResourceModel\Loyalty\AttributeResource;
use Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config\Sync;
use Leat\LoyaltyAdminUI\Service\SyncValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class Data extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'Leat_Loyalty::config';

    public function __construct(
        Context $context,
        protected JsonFactory $resultJsonFactory,
        protected AttributeResource $attributeResource,
        protected SyncValidator $syncValidator
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

            // Use the existing AttributeResource to sync attributes
            $this->attributeResource->syncTransactionAttributes($storeId);
            $this->attributeResource->syncCustomAttributes($storeId);

            $syncResult = [
                'success' => true,
                'message' => __('Successfully synchronized data with Leat')
            ];

            // Run validation to confirm everything is ready
            $validationMessage = $this->syncValidator->validateSyncStatus($storeId);
            $syncResult['validation_passed'] = ($validationMessage === null);
            if (!$syncResult['validation_passed']) {
                $syncResult['validation_message'] = (string) $validationMessage;
            }

            return $result->setData($syncResult);
        } catch (LocalizedException $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Error syncing data: %1', $e->getMessage())
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => __('An unexpected error occurred: %1', $e->getMessage())
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
