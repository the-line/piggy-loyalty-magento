<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Controller\Adminhtml\Sync;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\AttributeResource;
use Leat\LoyaltyAdminUI\Service\SyncValidator;
use Leat\LoyaltyAsync\Cron\Data\CategoryExport;
use Leat\LoyaltyAsync\Cron\Data\ProductExport;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\FlagManager;

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
        protected SyncValidator $syncValidator,
        protected Connector $leatConnector,
        protected FlagManager $flagManager
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

            // trigger exporters
            $this->triggerDataExport($storeId, CategoryExport::LAST_RUN_FLAG);
            $this->triggerDataExport($storeId, ProductExport::LAST_RUN_FLAG);

            // Use the existing AttributeResource to sync attributes
            $this->attributeResource->syncTransactionAttributes($storeId);
            $this->attributeResource->syncCustomAttributes($storeId);

            $syncResult = [
                'success' => true,
                'message' => __(
                    "Successfully synchronized attribute data with Leat." .
                    "<br/> <span class=\"datetime-msg\">Category and product export in progress, refresh page to see status.</span>"
                )
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
     * @param int $storeId
     * @return void
     */
    protected function triggerDataExport(int $storeId, string $flag): void {
        $shopUUID = $this->leatConnector->getConfig()->getShopUuid($storeId);
        $this->flagManager->saveFlag(sprintf($flag, $shopUUID), [
            'time' => 0,
            'success' => -1
        ]);
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
