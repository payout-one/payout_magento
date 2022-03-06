<?php

namespace Payout\Payment\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Disable extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $element->setDisabled('disabled');
        $url = $this->getBaseUrl() . 'Payout/redirect/order';
        $element->setValue($url);

        return $element->getElementHtml();
    }
}
