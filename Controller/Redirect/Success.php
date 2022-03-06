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
use Payout\Payment\Controller\AbstractPayout;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Success extends AbstractPayout
{
    /**
     * Execute on Payout/redirect/success
     */
    public function execute()
    {
        $notification = json_decode(file_get_contents('php://input'));

        $this->_payoutlogger->info("*************************Payout Notify Response*************************");
        $this->_payoutlogger->info(json_encode($notification));
        if ( ! isset($notification->external_id)) {
            return;
        }


        $external_id = $notification->external_id;

        $order = $this->getOrderByIncrementId($external_id);

        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');

        $page_object = $this->pageFactory->create();
        $baseurl     = $this->_storeManager->getStore()->getBaseUrl();
        try {
            if (isset($notification->data->status)) {
                $status = $notification->data->status;
                if ($status == "succeeded") {
                    $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
                    if ($this->getConfigData('Successful_Order_status') != "") {
                        $status = $this->getConfigData('Successful_Order_status');
                    }
                    $message = __(
                        'Redirect Response, Transaction has been approved: Payout_Checkout_Id: "%1"',
                        $notification->data->id
                    );

                    $order->addStatusHistoryComment(__($message))->save();


                    $model                  = $this->_paymentMethod;
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
                    $this->createTransaction($order, $notification);
                    $order->setState($status)->setStatus($status)->save();
                } else {
                    $this->_order->addStatusHistoryComment(
                        __(
                            'Redirect Response, Transaction has been declined, Payout_Checkout_Id: ' . $notification->data->id
                        )
                    )->setIsCustomerNotified(false);
                    $order->cancel()->save();
                    // Save Transaction Response
                    $this->createTransaction($order, $notification);
                }
            }
        } catch (LocalizedException $e) {
            // Save Transaction Response
            $this->createTransaction($order, $notification);
            $this->_logger->error($pre . $e->getMessage());
        }

        return;
    }

    public function createTransaction($order = null, $paymentData)
    {
        $PayoutResponse = array(
            'order_id'       => $paymentData->data->external_id,
            'amount'         => $paymentData->data->amount,
            'currency'       => $paymentData->data->currency,
            'customer_email' => $paymentData->data->customer->email,
            'first_name'     => $paymentData->data->customer->first_name,
            'last_name'      => $paymentData->data->customer->last_name,
            'checkout_id'    => $paymentData->data->id,
            'failure_reason' => $paymentData->data->payment->failure_reason,
            'payment_method' => $paymentData->data->payment->payment_method,
            'nonce'          => $paymentData->nonce,
            'signature'      => $paymentData->signature,
            'type'           => $paymentData->type
        );

        $checkoutId = $paymentData->data->id;
        try {
            //get payment object from order object
            $payment = $order->getPayment();
            $payment->setLastTransId($checkoutId)
                    ->setTransactionId($checkoutId)
                    ->setAdditionalInformation(
                        [Transaction::RAW_DETAILS => $PayoutResponse]
                    );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $message = __('The authorized amount is %1.', $formatedPrice);
            //get the object of builder class
            $trans       = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
                                 ->setOrder($order)
                                 ->setTransactionId($checkoutId)
                                 ->setAdditionalInformation(
                                     [Transaction::RAW_DETAILS => $PayoutResponse]
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

            return $transaction->save()->getTransactionId();
        } catch (Exception $e) {
            //log errors here
        }
    }

    public function getOrderByIncrementId($incrementId)
    {
        $objectManager = ObjectManager::getInstance();
        $order         = $objectManager->get('\Magento\Sales\Model\Order')->loadByIncrementId($incrementId);

        return $order;
    }
}
