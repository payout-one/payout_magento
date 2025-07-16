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

/**
 * Payout common payment info block
 * Uses default templates
 */
class Info extends \Magento\Payment\Block\Info
{
    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array   $data = [],
    )
    {
        parent::__construct($context, $data);
    }

}
