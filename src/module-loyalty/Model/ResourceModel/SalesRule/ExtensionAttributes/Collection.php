<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\ResourceModel\SalesRule\ExtensionAttributes;

use Leat\Loyalty\Model\SalesRule\ExtensionAttributes as Model;
use Leat\Loyalty\Model\ResourceModel\SalesRule\ExtensionAttributes as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
