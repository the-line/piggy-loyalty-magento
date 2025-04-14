<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Model\ResourceModel\Request;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    /**
     * @inheritDoc
     */
    // phpcs:ignore
    protected $_idFieldName = 'request_id';

    /**
     * @inheritDoc
     */
    // phpcs:ignore
    protected function _construct()
    {
        $this->_init(
            \Leat\AsyncQueue\Model\Request::class,
            \Leat\AsyncQueue\Model\ResourceModel\Request::class
        );
    }
}
