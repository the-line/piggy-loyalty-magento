<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Service;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Piggy\Api\Exceptions\PiggyRequestException;

class ConnectionTester
{
    public function __construct(
        protected Config $config,
        protected Connector $connector
    ) {
    }

    /**
     * Test the Leat connection
     *
     * @param int $storeId
     * @return array
     */
    public function testConnection(int $storeId): array
    {
        // Check if required configuration is set
        $personalAccessToken = $this->config->getPersonalAccessToken($storeId);
        $shopUuid = $this->config->getShopUuid($storeId);

        if (empty($personalAccessToken) || empty($shopUuid)) {
            return [
                'success' => false,
                'message' => __('Missing configuration: Please set Personal Access Token and Shop UUID')
            ];
        }

        try {
            // Try to get a connection and ping the Leat API
            $client = $this->connector->getConnection($storeId, true);
            $response = $client->ping();
            if ($response->getData()) {
                return [
                    'success' => true,
                    'message' => __(
                        "Successfully connected to Leat API.\n Company: %1, ID: %2",
                        $response->getData()->company,
                        $response->getData()->id
                    ),
                    'datetime' => date('Y-m-d H:i:s')
                ];
            }

            return [
                'success' => false,
                'message' => __('Connection test failed: Unexpected response from API')
            ];
        } catch (NoSuchEntityException $e) {
            return [
                'success' => false,
                'message' => __('Store not found: %1', $e->getMessage())
            ];
        } catch (AuthenticationException $e) {
            return [
                'success' => false,
                'message' => __('Authentication failed: %1', $e->getMessage())
            ];
        } catch (\Error $e) {
            return [
                'success' => false,
                'message' => __('PHP Internal error: %1', $e->getMessage())
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => __('Unexpected error: %1', $e->getMessage())
            ];
        }
    }
}
