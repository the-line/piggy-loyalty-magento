<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Service;

use Leat\Loyalty\Model\ResourceModel\Loyalty\AttributeResource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\FlagManager;
use Magento\Framework\Phrase;

class SyncValidator
{
    public function __construct(
        protected FlagManager $flagManager,
        protected AttributeResource $attributeResource
    ) {
    }

    /**
     * Validate sync status and return message if action needed
     *
     * @param int|null $storeId
     * @return Phrase|null Returns a message if action needed, null if all valid
     */
    public function validateSyncStatus(?int $storeId = null): ?Phrase
    {
        try {
            $attributesValidation = $this->attributeResource->validateAttributes($storeId);

            if (!$attributesValidation['valid']) {
                $missingCount = count($attributesValidation['missing']['transaction'] ?? [])
                              + count($attributesValidation['missing']['custom'] ?? []);

                return __(
                    'Leat data synchronization needed: %1 attribute(s) missing.' .
                     ' Please click "Synchronize Now" to set up all required attributes.',
                    $missingCount
                );
            }
        } catch (LocalizedException $e) {
            return __('Validation error: %1', $e->getMessage());
        } catch (\Exception $e) {
            return __('Unexpected error during validation: %1', $e->getMessage());
        }

        return null;
    }
}
