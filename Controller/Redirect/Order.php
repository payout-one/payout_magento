<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Controller\Redirect;

use Magento\Framework\View\Result\Page;
use Magento\Sales\Api\Data\OrderInterface;
use Payout\Payment\Controller\AbstractPayout;

class Order extends AbstractPayout
{
    /**
     * Execute
     */
    public function execute(): Page
    {
        $params = $this->request->getParams();

        $orderId = (string)$params['gid'];

        $page_object = $this->pageFactory->create();
        $order = $this->getOrderByIncrementId($orderId);

        if ($this->_paymentMethod->isAtLeastOneCheckoutInOrderSucceeded($order)) {
            $this->redirectToSuccessUrl($order);
        } else {
            $this->redirectToFailureUrl($order);
        }

        return $page_object;
    }

    public function getOrderByIncrementId(string $incrementId): OrderInterface
    {
        return $this->orderInterface->loadByIncrementId($incrementId);
    }

}
