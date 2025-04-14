<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\ResourceModel;

use Leat\AsyncQueue\Api\Data\JobInterface;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\ContactCreate;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class JobQueries
{
    protected AdapterInterface $connection;

    public function __construct(
        protected ResourceConnection $resource
    ) {
        $this->connection = $this->resource->getConnection();
    }

    /**
     * Check if the current customer has any subsequent jobs that have a contact create request
     *
     * @param JobInterface $job
     * @return bool
     */
    public function hasContactCreateRequest(JobInterface $job): bool
    {
        if ($job->getRelationId() === null) {
            return false;
        }

        $select = $this->resource->getConnection()->select()->from(
            ['pir' => $this->resource->getConnection()->getTableName('async_queue_request')],
            'request_id'
        )->joinInner(
            ['pij' => $this->resource->getConnection()->getTableName('async_queue_job')],
            'pir.job_id = pij.job_id',
            []
        )->where(
            'pij.relation_id = ?',
            $job->getRelationId()
        )->where(
            'pir.type_code = ?',
            ContactCreate::getTypeCode()
        )->where(
            'pir.is_synced = ?',
            false
        )->where(
            'pij.job_id != ?',
            $job->getId()
        );

        return !empty($this->resource->getConnection()->fetchAll($select));
    }

    /**
     * Check if the customer does not already have a pending creation request
     *
     * @param $customerId
     * @return bool
     */
    public function hasCreateJob($customerId): bool
    {
        $select = $this->connection->select()->from(
            ['pir' => $this->connection->getTableName('async_queue_request')],
            'pir.request_id'
        )->joinInner(
            ['pij' => $this->connection->getTableName('async_queue_job')],
            'pir.job_id = pij.job_id'
        )->where(
            'pij.relation_id = ?',
            $customerId
        )->where(
            'pir.is_synced = ?',
            false
        )->where(
            'type_code = ?',
            ContactCreate::getTypeCode()
        );

        return !empty($this->connection->fetchAll($select));
    }
}
