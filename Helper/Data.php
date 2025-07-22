<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Psr\Log\LoggerInterface;

/**
 * Payout Data helper
 */
class Data extends AbstractHelper
{
    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected \Magento\Payment\Helper\Data $_paymentData;
    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @param Context $context
     * @param \Magento\Payment\Helper\Data $paymentData
     */
    public function __construct(
        Context                      $context,
        \Magento\Payment\Helper\Data $paymentData,
    )
    {
        $this->_logger = $context->getLogger();

        $pre = __METHOD__ . " : ";

        $this->_paymentData = $paymentData;

        parent::__construct($context);
        $this->_logger->debug($pre . 'eof');
    }
}
