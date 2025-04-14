<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Config\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\DataObject;
use Magento\Payment\Model\Config;

class PaymentMethods extends DataObject implements OptionSourceInterface
{
    public function __construct(
        protected ScopeConfigInterface $appConfigScopeConfigInterface,
        protected Config $paymentModelConfig
    ) {
        parent::__construct();
    }

    /**
     * Return list of active payment methods
     * @return array
     */
    public function toOptionArray()
    {
        $payments = $this->paymentModelConfig->getActiveMethods();
        $methods = [];
        foreach ($payments as $paymentCode => $paymentModel) {
            $paymentTitle = $this->appConfigScopeConfigInterface->getValue('payment/'.$paymentCode.'/title');
            $methods[$paymentCode] = array(
                'label' => $paymentTitle,
                'value' => $paymentCode
            );
        }

        return $methods;
    }
}
