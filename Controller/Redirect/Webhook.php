<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Controller\Redirect;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
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
     * Execute on Payout/redirect/success
     */
    public function execute(): void
    {
        $webhookData = json_decode(file_get_contents('php://input'));

        $this->_payoutlogger->info("*************************Payout Webhook Response*************************");
        $this->_payoutlogger->info(json_encode($webhookData));

        $payoutSecret = $this->getConfigData('encryption_key');

        if (!isset($webhookData->external_id)) {
            $this->_payoutlogger->error("Webhook error: There is no external id");
            return;
        }

        if (empty($payoutSecret)) {
            $this->_payoutlogger->error("Webhook error: Payout secret is not filled in configuration, can not verify signature");
            return;
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
            $this->_payoutlogger->error("Webhook error: Signature is not valid");
            return;
        }

        $external_id = $webhookData->external_id;

        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');

        try {
            $order = $this->getOrderByPaymentId($external_id);
            if (!isset($order) || $order->getId() == null) {
                $this->_payoutlogger->error("Webhook error: Order not found for external id (payment id): " . $external_id);
                return;
            }
            if (!isset($webhookData->data->status)) {
                $this->_payoutlogger->error("Webhook error: checkout status not set in webhook");
                return;
            }
            if ($webhookData->data->status != "succeeded") {
                $this->_payoutlogger->info("Webhook info: checkout status is not succeeded (" . $webhookData->data->status . "), skipping");
                return;
            }
            if ($order->getPayment()->getMethod() != "payout") {
                $this->_payoutlogger->error("Webhook error: Payment method in order is not Payout");
                return;
            }

            $objectManager = ObjectManager::getInstance();
            $transactions = $objectManager->create('\Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory')->create()->addOrderIdFilter($order->getId());
            foreach ($transactions->getItems() as $transaction) {
                $transactionAdditionalInformation = $transaction->getAdditionalInformation();
                if (
                    isset($transactionAdditionalInformation['raw_details_info']['checkout_id'])
                    && isset($transactionAdditionalInformation['raw_details_info']['source'])
                    && $transactionAdditionalInformation['raw_details_info']['checkout_id'] == $webhookData->data->id
                    && $transactionAdditionalInformation['raw_details_info']['source'] == "webhook"
                ) {
                    $this->_payoutlogger->info("Webhook info: Success webhook for this checkout was already processed");
                    return;
                }
            }

            $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
            if ($this->getConfigData('Successful_Order_status') != "") {
                $status = $this->getConfigData('Successful_Order_status');
            }
            $message = __(
                'Redirect Response, Transaction has been approved: Payout_Checkout_Id: "%1"',
                $webhookData->data->id
            );

            $order->addStatusHistoryComment(__($message))->save();


            $model = $this->_paymentMethod;
            $order_successful_email = $model->getConfigData('order_email');

            if ($order_successful_email != '0') {
                $this->OrderSender->send($order);
                $order->addStatusHistoryComment(
                    __('Notified customer about order #%1.', $order->getId())
                )->setIsCustomerNotified(true)->save();
            }

            // Capture invoice when payment is successfull
            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            $invoice->register();

            // Save the invoice to the order
            $transaction = $this->_objectManager->create('Magento\Framework\DB\Transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transaction->save();

            // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
            $send_invoice_email = $model->getConfigData('invoice_email');
            if ($send_invoice_email != '0') {
                $this->invoiceSender->send($invoice);
                $order->addStatusHistoryComment(
                    __('Notified customer about invoice #%1.', $invoice->getId())
                )->setIsCustomerNotified(true)->save();
            }

            // Save Transaction Response
            $this->createTransaction($webhookData, $order);
            $order->setState($status)->setStatus($status)->save();
        } catch (LocalizedException $e) {
            $this->_logger->error($pre . $e->getMessage());
        }
    }

    public function createTransaction($webhookData, $order = null): void
    {
        $checkoutId = $webhookData->data->id;
        $additionalData = array(
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
            'raw_data' => json_encode($webhookData),
            'source' => 'webhook',
        );

        try {
            //get payment object from order object
            $payment = $order->getPayment();
            $payment->setLastTransId($checkoutId)
                ->setTransactionId($checkoutId)
                ->setAdditionalInformation(
                    [Transaction::RAW_DETAILS => $additionalData]
                );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $message = __('The authorized amount is %1.', $formatedPrice);
            //get the object of builder class
            $trans = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($checkoutId)
                ->setAdditionalInformation(
                    [Transaction::RAW_DETAILS => $additionalData]
                )
                ->setFailSafe(true)
                //build method creates the transaction and returns the object
                ->build(Transaction::TYPE_CAPTURE);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();

            $transaction->save()->getTransactionId();
        } catch (Exception $e) {
            //log errors here
        }
    }

    private function getOrderByPaymentId($paymentId)
    {
        $objectManager = ObjectManager::getInstance();
        $payment = $objectManager->create('Magento\Sales\Model\Order\PaymentFactory')->create()->load($paymentId);

        return $payment->getOrder();
    }
}
