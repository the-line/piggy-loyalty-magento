<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Piggy\Api\Exceptions\MaintenanceModeException;
use Piggy\Api\Exceptions\PiggyRequestException;
use Piggy\Api\Http\BaseClient;
use Piggy\Api\Http\Responses\Response;
use Piggy\Api\Http\Traits\SetsOAuthResources as OAuthResources;

class Client extends BaseClient
{
    use OAuthResources;

    /**
     * @var RateLimiter
     */
    private RateLimiter $rateLimiter;

    /**
     * @var int
     */
    private int $storeId;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param int $storeId
     * @param Config $config
     * @param RateLimiter|null $rateLimiter
     * @param ClientInterface|null $client
     */
    public function __construct(
        int $storeId,
        Config $config,
        ?RateLimiter $rateLimiter = null,
        ?ClientInterface $client = null
    ) {
        $this->storeId = $storeId;
        $this->config = $config;
        $this->rateLimiter = $rateLimiter ?? new RateLimiter($config);

        parent::__construct($client);

        $this->setAccessToken($this->config->getPersonalAccessToken($storeId));
    }

    /**
     * Ping the API to check connection
     *
     * @return Response
     * @throws \Exception
     */
    public function ping(): Response
    {
        try {
            return $this->get("/api/v3/oauth/clients");
        } catch (\Error $e) {
            // Convert PHP Error to Exception to avoid issues with type compatibility
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string $accessToken
     * @return $this
     */
    public function setAccessToken(string $accessToken): self
    {
        $this->addHeader("Authorization", "Bearer $accessToken");
        return $this;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $queryOptions
     * @return Response
     * @throws PiggyRequestException
     * @throws GuzzleException
     * @throws MaintenanceModeException
     */
    public function request(string $method, string $endpoint, $queryOptions = []): Response
    {
        // Apply rate limiting before making the request
        $this->rateLimiter->limit();

        return parent::request($method, $endpoint, $queryOptions);
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return $this->storeId;
    }
}
