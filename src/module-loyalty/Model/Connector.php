<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use Leat\AsyncQueue\Model\Connector\AbstractConnector;
use Leat\Loyalty\Model\LoggerFactory;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\FlagManager;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Exceptions\PiggyRequestException;

class Connector extends AbstractConnector
{
    protected const string LOG_FILE = "leat/loyalty_connector.log";
    protected const string DEBUG_LOG_FILE = "leat/loyalty_connector_debug.log";
    protected const string ERROR_MAIL_LAST_SENT = "leat_loyalty_connector_error_last_sent";
    protected const string ERROR_EMAIL_TEMPLATE = 'async_queue_error_mail';

    /**
     * @var Client[]
     */
    protected array $clients = [];

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var RateLimiterInterface
     */
    protected RateLimiterInterface $rateLimiter;

    public function __construct(
        Config $config,
        FlagManager $flagManager,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        LoggerFactory $loggerFactory,
        StoreManagerInterface $storeManager,
        RateLimiterInterface $rateLimiter
    ) {
        $this->config = $config;
        $this->rateLimiter = $rateLimiter;
        parent::__construct($flagManager, $transportBuilder, $inlineTranslation, $loggerFactory, $storeManager);
    }

    /**
     * @param int|null $storeId
     * @return Client
     * @throws AuthenticationException
     * @throws NoSuchEntityException
     */
    public function getConnection(int $storeId = null, bool $test = false): Client
    {
        $storeId = $storeId ?? $this->getCurrentStoreId();
        if (!isset($this->clients[$storeId])) {
            try {
                $this->clients[$storeId] = $this->getLeatClient($storeId);
            } catch (\Throwable $error) {
                $this->getLogger()->log("Failed to retrieve access token with the following error: \n$error");
                if (!$test) {
                    $this->sendErrorEmail();
                }
                throw new AuthenticationException(
                    __(
                        "Failed to retrieve access token from Leat, %1 %2",
                        $error->getResponse()->getStatusCode(),
                        $error->getResponse()->getReasonPhrase()
                    )
                );
            }
        }

        return $this->clients[$storeId];
    }

    /**
     * @param int $storeId
     * @return Client
     * @throws PiggyRequestException
     * @throws LocalizedException
     */
    private function getLeatClient(int $storeId): Client
    {
        $client = new Client(
            $storeId,
            $this->config,
            $this->rateLimiter
        );

        if (!$client->ping()) {
            throw new LocalizedException(__("Failed to authenticate with Leat using Personal Access Token"));
        }

        return $client;
    }

    /**
     * Get the general contact email address for sending error emails
     *
     * @return string|null
     */
    protected function getGeneralContactEmailAddress(): ?string
    {
        return $this->config->getGeneralContactEmailAddress();
    }

    /**
     * Get the general contact name for sending error emails
     *
     * @return string|null
     */
    protected function getGeneralContactName(): ?string
    {
        return $this->config->getGeneralContactName();
    }

    /**
     * Get the list of email addresses to alert on error
     *
     * @return array
     * @throws NoSuchEntityException
     */
    protected function getAlertToEmails(): array
    {
        return $this->config->getAlertTo();
    }

    /**
     * Get Leat configuration
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }
}
