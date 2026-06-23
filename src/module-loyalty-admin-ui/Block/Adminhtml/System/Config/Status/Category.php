<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config\Status;

use Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config\AbstractStatus;
use Leat\LoyaltyAsync\Cron\Data\CategoryExport;

class Category extends AbstractStatus
{
    /**
     * @param int|null $storeId
     * @return string
     */
    protected function getStatus(?int $storeId = null): string
    {
        $shopUUID = $this->leatConnector->getConfig()->getShopUuid($storeId);
        $flagValue = $this->flagManager->getFlagData(sprintf(CategoryExport::LAST_RUN_FLAG, $shopUUID));

        if (!$flagValue || !isset($flagValue['time'])) {
            return $this->getNoFlagDataMessage();
        }

        $time = $flagValue['time'];
        $success = $flagValue['success'] ?? false;
        $flagMessage = $flagValue['message'] ?? null;

        if ($time === 0 && $success === -1) {
            return '<span class="notice-msg">' . __("Sync in progress...") . '</span>';
        }

        $message = sprintf("Last run: %s", date('Y-m-d H:i:s', $time));

        if (!$success) {
            if ($flagMessage) {
                $message .= sprintf("<br/> Error: %s", $flagMessage);
            }
            return '<span class="error-msg">' . $message . '</span>';
        }

        if ($flagMessage) {
            $message .= sprintf("<br/> %s", $flagMessage);
        }

        return '<span class="datetime-msg">' . $message . '</span>';
    }
}
