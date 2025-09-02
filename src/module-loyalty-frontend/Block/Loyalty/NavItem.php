<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Loyalty;

use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class NavItem extends Template
{
    /**
     * Default template
     *
     * @var string
     */
    protected $_template = 'Leat_LoyaltyFrontend::loyalty/nav-item/generic.phtml';

    /**
     * @var string
     */
    protected string $code = '';

    /**
     * @var int
     */
    protected int $position = 0;

    /**
     * @var bool
     */
    protected bool $isActive = false;

    /**
     * @var string
     */
    protected string $label = '';

    /**
     * @var string
     */
    protected string $sectionId = '';

    /**
     * @var array
     */
    protected array $iconMapping = [
        'balance' => 'balance.svg',
        'coupons' => 'coupons.svg',
        'rewards' => 'rewards.svg',
        'refer' => 'refer.svg',
        'activity' => 'activity.svg',
        'giftcard' => 'giftcard.svg',
    ];

    /**
     * Constructor
     *
     * @param Context $context
     * @param Repository $assetRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        protected Repository $assetRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);

        // Set properties from data
        $this->code = (string)($data['code'] ?? '');
        $this->position = (int)($data['position'] ?? 0);
        $this->isActive = (bool)($data['is_active'] ?? false);
        $this->label = (string)($data['label'] ?? '');
        $this->sectionId = (string)($data['section_id'] ?? '');
    }

    /**
     * Get the navigation item code
     *
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Get the navigation item position
     *
     * @return int
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Check if the item is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Get the navigation item label
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label ?: __(ucfirst($this->code));
    }

    /**
     * Get the section ID for navigation
     *
     * @return string
     */
    public function getSectionId(): string
    {
        return $this->sectionId ?: 'leat-' . $this->code;
    }

    /**
     * Get the URL to the SVG icon for this navigation item
     *
     * @return string
     */
    public function getIconUrl(): string
    {
        $iconFile = $this->iconMapping[$this->code] ?? '';

        if (empty($iconFile)) {
            return '';
        }

        try {
            $asset = $this->assetRepository->createAsset(
                'Leat_LoyaltyFrontend::images/nav-icons/' . $iconFile,
                ['area' => 'frontend']
            );
            return $asset->getUrl();
        } catch (\Exception $e) {
            return '';
        }
    }
}
