<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Model\ResourceModel\Job;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    /**
     * @inheritDoc
     */
    // phpcs:ignore
    protected $_idFieldName = 'job_id';

    /**
     * @inheritDoc
     */
    // phpcs:ignore
    protected function _construct()
    {
        $this->_init(
            \Leat\AsyncQueue\Model\Job::class,
            \Leat\AsyncQueue\Model\ResourceModel\Job::class
        );
    }
}
