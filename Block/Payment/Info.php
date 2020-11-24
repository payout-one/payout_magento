<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 * 
 * Released under the GNU General Public License
 */
namespace Payout\Payment\Block\Payment;

/**
 * Payout common payment info block
 * Uses default templates
 */
class Info extends \Magento\Payment\Block\Info
{
    /**
     * @var \Payout\Payment\Model\InfoFactory
     */
    protected $_PayoutInfoFactory;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param \Payout\Payment\Model\InfoFactory $PayoutInfoFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Model\Config $paymentConfig,
        \Payout\Payment\Model\InfoFactory $PayoutInfoFactory,
        array $data = []
    ) {
        $this->_PayoutInfoFactory = $PayoutInfoFactory;
        parent::__construct( $context, $data );
    }

}
