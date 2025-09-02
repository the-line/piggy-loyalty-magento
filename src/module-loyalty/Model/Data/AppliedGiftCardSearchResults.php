<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Data;

use Leat\Loyalty\Api\Data\AppliedGiftCardSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Service Data Object with applied gift card search results.
 */
class AppliedGiftCardSearchResults extends SearchResults implements AppliedGiftCardSearchResultsInterface
{
}
