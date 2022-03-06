<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Block\Payment;

use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\Config;
use Payout\Payment\Model\InfoFactory;

/**
 * Payout common payment info block
 * Uses default templates
 */
class Info extends \Magento\Payment\Block\Info
{
    /**
     * @var InfoFactory
     */
    protected $_PayoutInfoFactory;

    /**
     * @param Context $context
     * @param Config $paymentConfig
     * @param InfoFactory $PayoutInfoFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $paymentConfig,
        InfoFactory $PayoutInfoFactory,
        array $data = []
    ) {
        $this->_PayoutInfoFactory = $PayoutInfoFactory;
        parent::__construct($context, $data);
    }

}
