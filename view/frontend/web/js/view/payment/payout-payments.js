/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 * 
 * Released under the GNU General Public License
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component,
              rendererList
    ) {
        'use strict';

        rendererList.push(
            {
                type: 'payout',
                component: 'Payout_Payment/js/view/payment/method-renderer/payout-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);