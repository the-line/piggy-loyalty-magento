<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Model\Connector;

use Leat\Loyalty\Model\Logger;
use Leat\Loyalty\Model\LoggerFactory;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\FlagManager;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

abstract class AbstractConnector implements ConnectorInterface
{
    /**
     * Default log file paths
     */
    protected const string LOG_FILE = "leat/async_queue.log";
    protected const string DEBUG_LOG_FILE = "leat/async_queue_debug.log";

    /**
     * Flag for tracking when error emails were last sent
     */
    protected const string ERROR_MAIL_LAST_SENT = "leat_async_queue_error_last_sent";

    /**
     * Email template identifier used for error notifications
     */
    protected const string ERROR_EMAIL_TEMPLATE = 'async_queue_error_mail';

    /**
     * Log file naming templates
     */
    protected const string SUFFIX_LOG_FILE_TEMPLATE = "leat/%s/%s.log";
    protected const string SUFFIX_DEBUG_LOG_FILE_TEMPLATE = "leat/%s/%s_debug.log";
    protected const string SUFFIX_TIMESTAMPED_LOG_FILE_TEMPLATE = "leat/%s/%s/%s.log";
    protected const string SUFFIX_TIMESTAMPED_DEBUG_LOG_FILE_TEMPLATE = "leat/%s/%s/%s_debug.log";

    /**
     * @var array Cache of purpose-specific loggers
     */
    protected array $purposeLoggers = [];

    /**
     * @var Logger Main logger instance
     */
    protected Logger $logger;

    /**
     * Constructor
     *
     * @param FlagManager $flagManager
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param LoggerFactory $loggerFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        protected FlagManager $flagManager,
        protected TransportBuilder $transportBuilder,
        protected StateInterface $inlineTranslation,
        protected LoggerFactory $loggerFactory,
        protected StoreManagerInterface $storeManager,
    ) {
        $this->logger = $loggerFactory->create()
            ->setFilename(static::LOG_FILE)
            ->setDebugFilename(static::DEBUG_LOG_FILE);
    }

    /**
     * Get a connection to the service
     *
     * @param int|null $storeId
     * @return mixed
     * @throws AuthenticationException
     */
    abstract public function getConnection(int $storeId = null): mixed;

    /**
     * Get a logger for a specific purpose.
     * - If no purpose is given, the default logger is returned.
     *
     * @param string|null $purpose
     * @param bool $timestamped
     * @return Logger
     */
    public function getLogger(?string $purpose = null, bool $timestamped = false): Logger
    {
        if ($purpose === null) {
            return $this->logger;
        }

        if (!isset($this->purposeLoggers[$purpose])) {
            if ($timestamped) {
                $this->purposeLoggers[$purpose] = $this->loggerFactory->create()
                    ->setFilename(sprintf(
                        static::SUFFIX_TIMESTAMPED_LOG_FILE_TEMPLATE,
                        get_class($this),
                        $purpose,
                        date('Y-m-d_H-i-s')
                    ))
                    ->setDebugFilename(sprintf(
                        static::SUFFIX_TIMESTAMPED_DEBUG_LOG_FILE_TEMPLATE,
                        get_class($this),
                        $purpose,
                        date('Y-m-d_H-i-s')
                    ));
            } else {
                $this->purposeLoggers[$purpose] = $this->loggerFactory->create()
                    ->setFilename(sprintf(static::SUFFIX_LOG_FILE_TEMPLATE, get_class($this), $purpose))
                    ->setDebugFilename(sprintf(static::SUFFIX_DEBUG_LOG_FILE_TEMPLATE, get_class($this), $purpose));
            }
        }

        return $this->purposeLoggers[$purpose];
    }

    /**
     * Get the general contact email address for sending error emails
     *
     * @return string|null
     */
    abstract protected function getGeneralContactEmailAddress(): ?string;

    /**
     * Get the general contact name for sending error emails
     *
     * @return string|null
     */
    abstract protected function getGeneralContactName(): ?string;

    /**
     * Get the list of email addresses to alert on error
     *
     * @return array
     */
    abstract protected function getAlertToEmails(): array;

    /**
     * Send an email about being unable to connect to the connector.
     *
     * @return void
     */
    protected function sendErrorEmail(): void
    {
        if (!$this->canSendErrorMail()) {
            return;
        }

        $this->inlineTranslation->suspend();

        try {
            $transportBuilder = $this->transportBuilder
                ->setTemplateIdentifier(static::ERROR_EMAIL_TEMPLATE)
                ->setTemplateOptions([
                    'area' => Area::AREA_ADMINHTML,
                    'store' => Store::DEFAULT_STORE_ID
                ])
                ->setFromByScope(
                    [
                        'email'=> $this->getGeneralContactEmailAddress(),
                        'name'=> $this->getGeneralContactName()
                    ],
                    Store::DEFAULT_STORE_ID
                )->setTemplateVars([]);

            foreach ($this->getAlertToEmails() as $email) {
                $transportBuilder->addTo($email);
            }

            $transport = $transportBuilder->getTransport();
            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->logger->log('Error while trying to send alert email found: ' . $e->getmessage());
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    /**
     * @return bool
     */
    public function canSendErrorMail(): bool
    {
        $lastSent = $this->flagManager->getFlagData($this->getFlag()) ?? '';
        $lastSentTimestamp = strtotime($lastSent);
        if ($lastSent && $lastSentTimestamp > strtotime('-1 hour', $lastSentTimestamp)) {
            return false;
        }

        $this->flagManager->saveFlag($this->getFlag(), date('Y-m-d H:i:s'));

        return true;
    }

    /**
     * Return the flag name for the last sent email.
     *
     * @param string $prefix (as we are using static, we cannot include it in the method signature)
     * @return string
     */
    public function getFlag(string $prefix = ''): string
    {
        $prefix = $prefix ?: static::ERROR_MAIL_LAST_SENT;
        return $prefix . '_' . get_class($this);
    }

    /**
     * Return the current store ID.
     *
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrentStoreId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }
}
