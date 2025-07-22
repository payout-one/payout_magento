<?php

namespace Payout\Payment\ViewModel\Order;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Payout\Payment\Model\Payout;

abstract class AbstractCustomData implements ArgumentInterface
{
    /**
     * @var Payout
     */
    private Payout $paymentMethod;

    /**
     * @var OrderInterface|null
     */
    private ?OrderInterface $order;

    /**
     * @param Payout $paymentMethod
     * @param OrderInterface|null $order
     */
    public function __construct(
        Payout          $paymentMethod,
        ?OrderInterface $order,
    )
    {
        $this->paymentMethod = $paymentMethod;
        $this->order = $order;
    }

    /**
     * @return bool
     */
    private function isOrderAvailable(): bool
    {
        return isset($this->order);
    }

    /**
     * used in view/frontend/templates/order/custom.phtml
     * @return array|array[]
     */
    public function getPayoutTransactions(): array
    {
        if (!$this->isOrderAvailable() || $this->order->getPayment()->getMethod() != "payout") {
            return [];
        }

        $groupedPayoutTransactions = $this->paymentMethod->getPayoutGroupedTransactionsForOrder($this->order);
        $result = array_map(
            fn($group) => [
                'checkout_id' => $group['checkout_id'],
                'status' => !empty($group['items'][TransactionInterface::TYPE_CAPTURE])
                    ? Payout::CHECKOUT_STATE_SUCCEEDED
                    : Payout::CHECKOUT_STATE_PROCESSING,
                'order_transaction_id' => $group['items'][TransactionInterface::TYPE_ORDER][0]->getId(),
                'order_id' => $this->order->getId(),
                'repeat_checkout_url' => $this->paymentMethod->getRepeatCheckoutUrl(
                    $this->order,
                    $group['items'][TransactionInterface::TYPE_ORDER][0]->getId(),
                    'transaction_id'
                ),
            ],
            array_filter(
                $groupedPayoutTransactions,
                fn($group) => !empty($group['items'][TransactionInterface::TYPE_ORDER]),
            )
        );

        // if there are no type order transactions, checkout was not assigned to order yet -> pass order id to repeat url to create checkout
        if (empty($result)) {
            $result = [
                [
                    'checkout_id' => null,
                    'status' => Payout::CHECKOUT_STATE_PROCESSING,
                    'order_transaction_id' => null,
                    'order_id' => $this->order->getId(),
                    'repeat_checkout_url' => $this->paymentMethod->getRepeatCheckoutUrl(
                        $this->order,
                        $this->order->getId(),
                        'order_id'
                    ),
                ]
            ];
        }

        return $result;
    }
}
