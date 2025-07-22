<?php
/*
 * Copyright (c) 2025 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Controller\Redirect;

use Exception;
use Magento\Framework\View\Result\Page;
use Magento\Sales\Model\Order;
use Payout\Payment\Controller\AbstractPayout;
use Payout\Payment\Model\Payout;

class Repeat extends AbstractPayout
{
    /**
     * Execute
     */
    public function execute(): Page
    {
        $pre = __METHOD__ . " : ";

        $page_object = $this->pageFactory->create();

        $transactionId = $this->request->getParam('transaction_id');
        $orderId = $this->request->getParam('order_id');
        $orderProtectCode = $this->request->getParam('order_protect_code');

        if (empty($transactionId) && empty($orderId)) {
            $message = __('Both order id and transaction id are not provided') . ', ' . __('can\'t repeat checkout');
            $this->_payoutlogger->error($pre . $message);
            $this->messageManager->addErrorMessage($message);
            $this->redirect('checkout/cart');
            return $page_object;
        }

        if (empty($orderProtectCode)) {
            $message = __('No order protect code provided') . ', ' . __('can\'t repeat checkout');
            $this->_payoutlogger->error($pre . $message);
            $this->messageManager->addErrorMessage($message);
            $this->redirect('checkout/cart');
            return $page_object;
        }

        if (!empty($orderId)) {
            try {
                $order = $this->orderRepository->get($orderId);
            } catch (Exception $e) {
                $this->_logger->error($pre . $e->getMessage());
                $this->messageManager->addExceptionMessage($e, __('Order not found') . ', ' . __('can\'t repeat checkout'));
                $this->redirect('checkout/cart');
                return $page_object;
            }
        } else {
            try {
                $transaction = $this->transactionRepository->get((int)$transactionId);
            } catch (Exception $e) {
                $this->_logger->error($pre . $e->getMessage());
                $this->messageManager->addExceptionMessage($e, __('Transaction not found') . ', ' . __('can\'t repeat checkout'));
                $this->redirect('checkout/cart');
                return $page_object;
            }
            $order = $transaction->getOrder();
        }

        if (!isset($order) || !($order instanceof Order)) {
            $message = __('Order not loaded correctly') . ', ' . __('can\'t repeat checkout');
            $this->_payoutlogger->error($pre . $message);
            $this->messageManager->addErrorMessage($message);
            $this->redirect('checkout/cart');
            return $page_object;
        }

        $dbOrderProtectCode = $order->getProtectCode();
        if (empty($dbOrderProtectCode) || (string)$orderProtectCode !== $dbOrderProtectCode) {
            $message = __('Can\'t verify repeat request') . ', ' . __('protect code is not available or not matching');
            $this->_payoutlogger->error($pre . $message);
            $this->messageManager->addErrorMessage($message);
            $this->redirect('checkout/cart');
            return $page_object;
        }

        if (!empty($orderId)) {
            return $this->createCheckout($order);
        }

        try {
            /** @noinspection PhpUndefinedVariableInspection */ // transaction is set in else block for sure
            $retrievedCheckout = $this->_paymentMethod->retrieveCheckout($transaction);
            if (isset ($retrievedCheckout) && $retrievedCheckout->status == Payout::CHECKOUT_STATE_SUCCEEDED) {
                $this->_paymentMethod->createSuccessfulPayoutPaymentCapture(
                    $order,
                    $this->_paymentMethod->createRawDetailsInfoDataForCheckoutResponse($retrievedCheckout, $order->getId()),
                );
                $message = __('Payment transaction') . ' ' . $transaction->getId() . ' - ' . __('checkout changed its status to successful in meantime');
                $messageUser = __('Payment transaction') . ' - ' . __('checkout changed its status to successful in meantime');
                $this->_payoutlogger->notice($pre . $message);
                $this->messageManager->addNoticeMessage($messageUser);
                $this->redirectToSuccessUrl($order);
                return $page_object;
            }
            $this->redirect(
                isset($retrievedCheckout)
                    ? $retrievedCheckout->checkout_url
                    : $transaction->getAdditionalInformation('raw_details_info')['checkout_url']
            );
        } catch (Exception $e) {
            $this->_logger->error($pre . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, __('Error occurred, contact support or try again, please'));
            $this->redirectToFailureUrl($order);
        }
        return $page_object;
    }
}
