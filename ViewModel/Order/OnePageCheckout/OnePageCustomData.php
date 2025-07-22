<?php

namespace Payout\Payment\ViewModel\Order\OnePageCheckout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Payout\Payment\Model\Payout;
use Payout\Payment\ViewModel\Order\AbstractCustomData;

class OnePageCustomData extends AbstractCustomData
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param Payout $paymentMethod
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        Payout          $paymentMethod,
    )
    {
        $order = $checkoutSession->getLastRealOrder();
        parent::__construct($paymentMethod, $order);
    }
}
