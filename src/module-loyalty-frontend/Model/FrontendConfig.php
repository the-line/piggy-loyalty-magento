<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Model;

use Leat\Loyalty\Model\Config;
use Magento\Store\Model\ScopeInterface;

class FrontendConfig extends Config
{
    protected const string XML_PATH_PIGGY_ADD_ON_CREATE = 'leat/general/add_on_create';

    /**
     * @param $storeId
     * @return bool
     */
    public function getIsAddOnCreate(mixed $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PIGGY_ADD_ON_CREATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
