<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Job extends AbstractDb
{
    public const TABLE_NAME = 'async_queue_job';

    /**
     * @inheritDoc
     */
    // phpcs:ignore
    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, 'job_id');
    }
}
