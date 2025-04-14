<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\ResourceModel\QuoteItem\ExtensionAttributes;

use Leat\Loyalty\Model\QuoteItem\ExtensionAttributes as Model;
use Leat\Loyalty\Model\ResourceModel\QuoteItem\ExtensionAttributes as ResourceModel;
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
