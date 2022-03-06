<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Controller;

if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface")) {
    class_alias('Payout\Payment\Controller\AbstractPayoutm230', 'Payout\Payment\Controller\AbstractPayout');
} else {
    class_alias('Payout\Payment\Controller\AbstractPayoutm220', 'Payout\Payment\Controller\AbstractPayout');
}
