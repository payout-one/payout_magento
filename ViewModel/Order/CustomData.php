<?php

namespace Payout\Payment\ViewModel\Order;

use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Payout\Payment\Model\Payout;

class CustomData extends AbstractCustomData
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param RequestInterface $request
     * @param Payout $paymentMethod
     * @param Registry $registry // using deprecated registry, it is still used as i see, didn't find proper replacement
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        RequestInterface         $request,
        Payout                   $paymentMethod,
        Registry                 $registry
    )
    {
        $orderId = $request->getParam('order_id');
        if (!empty($orderId)) {
            $order = $orderRepository->get($orderId);
        } else {
            $registryOrder = $registry->registry('current_order');
            $order = isset($registryOrder) && $registryOrder instanceof OrderInterface ? $registryOrder : null;
        }
        parent::__construct($paymentMethod, $order);
    }
}
