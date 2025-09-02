<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Widget\Button;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

class FetchCreditNameButton extends GenericField
{
    /**
     * @var string
     */
    protected $_template = 'Leat_LoyaltyAdminUI::system/config/fetch_credit_name_button.phtml';

    /**
     * Render the button and input field
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->setData('element_id', $element->getHtmlId());
        $this->setData('element_name', $element->getName());
        $this->setData('value', $element->getValue());
        $this->setData('html_attributes', $element->serialize($element->getHtmlAttributes()));
        return $this->_toHtml();
    }

    /**
     * Get the button HTML
     *
     * @return string
     * @throws LocalizedException
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(Button::class)
            ->setData([
                'id' => 'fetch_data_button',
                'label' => __('Fetch Data'),
            ]);
        return $button->toHtml();
    }

    /**
     * Get the AJAX URL for the button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('leat_loyalty/ajax/programSettings');
    }
}
