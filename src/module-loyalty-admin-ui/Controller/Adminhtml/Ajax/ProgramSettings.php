<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Controller\Adminhtml\Ajax;

use Leat\Loyalty\Model\Connector;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Exceptions\LoyaltyRequestException;

class ProgramSettings extends Action
{
    /**
     * @param Context $context
     * @param Connector $connector
     * @param StoreManagerInterface $storeManager
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        protected Connector $connector,
        protected StoreManagerInterface $storeManager,
        protected JsonFactory $resultJsonFactory,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute the AJAX request
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        try {
            $scope = $this->getRequest()->getParam('scope', 'default');
            $scopeId = $this->getRequest()->getParam('scope_id', 0);
            if ($scope === 'default') {
                $store = null;
            } elseif ($scope === 'website') {
                $store = (int) $this->storeManager->getWebsite($scopeId)?->getDefaultStore()->getId();
            } else {
                $store = (int) $scopeId;
            }

            $apiData = $this->fetchDataFromApi($store);

            $this->saveConfigValue($apiData, $scope, $scopeId);

            return $result->setData([
                'success' => true,
                'data' => $apiData,
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch data from the external API
     *
     * @return string
     * @throws AuthenticationException
     * @throws PiggyRequestException
     */
    private function fetchDataFromApi(int|null $storeId)
    {
        $client = $this->connector->getConnection($storeId);
        return $client->loyaltyProgram->get()->getCustomCreditName();
    }

    /**
     * Save the fetched data to the configuration
     *
     * @param string $value
     */
    private function saveConfigValue($value, $scope, $scopeId)
    {
        $this->_objectManager->get('Magento\Config\Model\ResourceModel\Config')
            ->saveConfig('leat/credits/label', $value, $scope, $scopeId);
    }
}
