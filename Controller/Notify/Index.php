<?php
/*
 * Copyright (c) 2020 PayOut (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * Magento v2.3.0+ implement CsrfAwareActionInterface but not earlier versions
 */

namespace Payout\Payment\Controller\Notify;

/**
 * Check for existence of CsrfAwareActionInterface - only v2.3.0+
 */
if ( interface_exists( "Magento\Framework\App\CsrfAwareActionInterface" ) ) {
    class_alias( 'Payout\Payment\Controller\Notify\Indexm230', 'Payout\Payment\Controller\Notify\Index' );
} else {
    class_alias( 'Payout\Payment\Controller\Notify\Indexm220', 'Payout\Payment\Controller\Notify\Index' );
}
