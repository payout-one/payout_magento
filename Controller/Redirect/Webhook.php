<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Controller\Redirect;

use Exception;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Payout\Payment\Api\Client;
use Payout\Payment\Controller\AbstractPayout;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Webhook extends AbstractPayout
{
    /**
     * Execute on Payout/redirect/webhook
     * @returns ResultInterface|ResponseInterface
     */
    public function execute(): ResultInterface|ResponseInterface
    {
        $webhookData = json_decode(file_get_contents('php://input'));

        $this->_payoutlogger->info("*************************Payout Webhook Response*************************");
        $this->_payoutlogger->info(json_encode($webhookData));

        $payoutSecret = $this->getConfigData('encryption_key');

        if (!isset($webhookData->external_id)) {
            $this->_payoutlogger->error(__('Webhook error') . ': ' . __('There is no external id'));
            return $this->getResponse();
        }

        if (empty($payoutSecret)) {
            $this->_payoutlogger->error(__('Webhook error') . ': ' . __('Payout secret is not filled in configuration, can not verify signature'));
            return $this->getResponse();
        }

        if (
            !Client::verifySignature(
                [
                    $webhookData->external_id,
                    $webhookData->type,
                    $webhookData->nonce,
                ],
                $payoutSecret,
                $webhookData->signature
            )
        ) {
            $this->_payoutlogger->error(__('Webhook error') . ': ' . __('Signature is not valid'));
            return $this->getResponse();
        }

        $external_id = $webhookData->external_id;

        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');

        try {
            $order = $this->getOrderByPaymentId($external_id);
            if (!isset($order) || $order->getId() == null) {
                $this->_payoutlogger->error(__('Webhook error') . ': ' . __('Order not found for external id (payment id)') . ': ' . $external_id);
                return $this->getResponse();
            }
            if (!isset($webhookData->data->status)) {
                $this->_payoutlogger->error(__('Webhook error') . ': ' . __('checkout status not set in webhook'));
                return $this->getResponse();
            }
            if ($webhookData->data->status != "succeeded") {
                $this->_payoutlogger->info(__('Webhook info') . ': ' . __('checkout status is not succeeded') . ' (' . $webhookData->data->status . '), ' . __('skipping'));
                return $this->getResponse();
            }
            if ($order->getPayment()->getMethod() != "payout") {
                $this->_payoutlogger->error(__('Webhook error') . ': ' . __('Payment method in order is not Payout'));
                return $this->getResponse();
            }

            $typeOrderTransactions = $this->_paymentMethod->getTypeOrderCheckoutTransactionsForOrder($order, (int)$webhookData->data->id);
            if (empty($typeOrderTransactions)) {
                $this->_payoutlogger->error(__('Webhook error') . ': ' . __('Can\'t find order transaction for checkout'));
                return $this->getResponse();
            }

            if (!empty($this->_paymentMethod->getTypeCaptureCheckoutTransactionsForOrder($order, (int)$webhookData->data->id))) {
                $this->_payoutlogger->info(__('Webhook info') . ': ' . __('Success webhook for this checkout was already processed'));
                return $this->getResponse();
            }

            $this->_paymentMethod->createSuccessfulPayoutPaymentCapture(
                $order,
                [
                    'checkout_id' => $webhookData->data->id,
                    'order_id' => $order->getId(),
                    'external_id' => $webhookData->external_id,
                    'amount' => $webhookData->data->amount,
                    'currency' => $webhookData->data->currency,
                    'customer_email' => $webhookData->data->customer->email,
                    'first_name' => $webhookData->data->customer->first_name,
                    'last_name' => $webhookData->data->customer->last_name,
                    'failure_reason' => $webhookData->data->payment->failure_reason,
                    'payment_method' => $webhookData->data->payment->payment_method,
                    'nonce' => $webhookData->nonce,
                    'signature' => $webhookData->signature,
                    'type' => $webhookData->type,
                    'status' => $webhookData->data->status,
                    'raw_data' => json_encode($webhookData),
                    'source' => 'webhook',
                ],
            );
        } catch (Exception $e) {
            $this->_logger->error($pre . $e->getMessage() . ' trace: ' . $e->getTraceAsString());
        }
        return $this->getResponse();
    }

    /**
     * @param $paymentId
     * @return \Magento\Sales\Model\Order|null
     */
    private function getOrderByPaymentId($paymentId): \Magento\Sales\Model\Order|null
    {
        try {
            return $this->orderPaymentRepository->get($paymentId)->getOrder();
        } catch (Exception) {
            return null;
        }
    }
}
