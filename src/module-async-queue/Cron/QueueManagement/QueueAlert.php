<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Cron\QueueManagement;

use Leat\AsyncQueue\Model\Config;
use Leat\Loyalty\Model\Logger;
use Leat\Loyalty\Model\LoggerFactory;
use Leat\AsyncQueue\Api\RequestRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\Store;

class QueueAlert
{
    private const LOG_FILENAME = 'leat/async_queue_cron.log';

    /**
     * @var Logger
     */
    private Logger $logger;

    public function __construct(
        protected RequestRepositoryInterface $requestRepository,
        protected SearchCriteriaBuilder $searchCriteriaBuilder,
        protected DateTime $dateTime,
        protected TransportBuilder $transportBuilder,
        protected StateInterface $inlineTranslation,
        protected Config $config,
        LoggerFactory $loggerFactory
    ) {
        $this->logger = $loggerFactory->create()
            ->setFilename(self::LOG_FILENAME)
            ->setDebugFilename(self::LOG_FILENAME);
    }

    /**
     * Alert site manager of requests that have failed to export in the last week.
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(): void
    {
        $requestItems = $this->requestRepository->getList(
            $this->searchCriteriaBuilder->addFilter(
                'is_synced',
                false
            )->addFilter(
                'attempt',
                1,
                'gteq'
            )->create()
        )->getItems();

        $rows = [];
        foreach ($requestItems as $requestItem) {
            $cells = [];
            $cells[] = sprintf('<td>%s</td>', $requestItem->getRequestId());
            $cells[] = sprintf('<td>%s</td>', $requestItem->getTypeCode());
            $cells[] = sprintf('<td>%s</td>', $requestItem->getRelationId());
            $cells[] = sprintf('<td>%s</td>', $requestItem->getWebsiteId());
            $cells[] = sprintf('<td>%s</td>', $requestItem->getCreatedAt());
            $cells[] = sprintf('<td>%s</td>', $requestItem->getUpdatedAt());
            $cells[] = sprintf('<td>%s</td>', $requestItem->getAttempt());
            $cells[] = sprintf('<td>%s</td>', current(explode(PHP_EOL, $requestItem->getLatestFailReason() ?? '')) ?? 'N/A');
            $cells = implode('', $cells);
            $rows[] = sprintf('<tr>%s</tr>', $cells);
        }
        $rows = implode('', $rows);
        $tbody = sprintf('<tbody>%s</tbody>', $rows);

        $errorCount = count($requestItems);
        if ($errorCount > 0) {
            $this->sendEmail($errorCount, $tbody);
        }
    }

    /**
     * Send an email about the amount of errors found to the address configured in the admin.
     *
     * @param $errorCount
     * @param $tbody
     * @return void
     */
    protected function sendEmail($errorCount, $tbody): void
    {
        $this->inlineTranslation->suspend();

        try {
            $transportBuilder = $this->transportBuilder
                ->setTemplateIdentifier('async_queue_error_mail')
                ->setTemplateVars(['error_count' => $errorCount])
                ->setTemplateOptions([
                    'area' => Area::AREA_ADMINHTML,
                    'store' => Store::DEFAULT_STORE_ID
                ])
                ->setFromByScope(
                    [
                        'email'=> $this->config->getGeneralContactEmailAddress(),
                        'name'=> $this->config->getGeneralContactName()
                    ],
                    Store::DEFAULT_STORE_ID
                );

            foreach ($this->config->getAlertTo() as $email) {
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
}
