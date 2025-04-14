<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Cron\QueueManagement;

use Leat\AsyncQueue\Api\Data\JobInterface;
use Leat\AsyncQueue\Api\JobRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class QueueCleanup
{
    private const CLEANUP_CUTOFF = '-1 month';

    /**
     * @var AdapterInterface
     */
    private AdapterInterface $connection;

    public function __construct(
        protected JobRepositoryInterface $jobRepository,
        protected SearchCriteriaBuilder $searchCriteriaBuilder,
        protected DateTime $dateTime,
        ResourceConnection $resourceConnection
    ) {
        $this->connection = $resourceConnection->getConnection();
    }

    /**
     * Clean month old records from the Async queue as long as they have successfully synced
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(): void
    {
        $timeFrom = strtotime(self::CLEANUP_CUTOFF, time());
        $dateFrom = $this->dateTime->gmtDate(null, $timeFrom);

        /** @var JobInterface[] $jobs */
        $jobs = $this->jobRepository->getList(
            $this->searchCriteriaBuilder->addFilter(
                'created_at',
                $dateFrom,
                'lteq'
            )->addFilter(
                'completed',
                true
            )->create()
        )->getItems();

        $jobIds = [];
        foreach ($jobs as $job) {
            $jobIds[] = $job->getJobId();
        }

        $this->connection->delete(
            $this->connection->getTableName('async_queue_job'),
            ['job_id in (?)' => $jobIds]
        );
    }
}
