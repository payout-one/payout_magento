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
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Session\Generic;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url\Helper\Data;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\StoreManagerInterface;
use Payout\Payment\Api\Client;
use Payout\Payment\Controller\AbstractPayout;
use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction as TransactionResourceModel;
use Payout\Payment\Logger\Logger;
use Payout\Payment\Model\Payout;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Webhook extends AbstractPayout
{
    /**
     * @var TransactionResourceModel
     */
    private TransactionResourceModel $transactionResourceModel;

    public function __construct(
        Context                         $context,
        PageFactory                     $pageFactory,
        \Magento\Customer\Model\Session $customerSession,
        Session                         $checkoutSession,
        OrderFactory                    $orderFactory,
        Generic                         $payoutSession,
        Data                            $urlHelper,
        Url                             $customerUrl,
        LoggerInterface                 $logger,
        Logger                          $payoutlogger,
        TransactionFactory              $transactionFactory,
        InvoiceService                  $invoiceService,
        InvoiceSender                   $invoiceSender,
        Payout                          $paymentMethod,
        UrlInterface                    $urlBuilder,
        OrderRepositoryInterface        $orderRepository,
        StoreManagerInterface           $storeManager,
        OrderSender                     $OrderSender,
        DateTime                        $date,
        CollectionFactory               $orderCollectionFactory,
        Builder                         $_transactionBuilder,
        TransactionResourceModel        $transactionResourceModel,
        OrderResourceModel              $orderResourceModel,
        QuoteResourceModel              $quoteResourceModel,
    )
    {
        parent::__construct($context, $pageFactory, $customerSession, $checkoutSession, $orderFactory, $payoutSession, $urlHelper, $customerUrl, $logger, $payoutlogger, $transactionFactory, $invoiceService, $invoiceSender, $paymentMethod, $urlBuilder, $orderRepository, $storeManager, $OrderSender, $date, $orderCollectionFactory, $_transactionBuilder, $orderResourceModel, $quoteResourceModel);

        $this->transactionResourceModel = $transactionResourceModel;
    }

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
            $this->_payoutlogger->error("Webhook error: There is no external id");
            return $this->getResponse();
        }

        if (empty($payoutSecret)) {
            $this->_payoutlogger->error("Webhook error: Payout secret is not filled in configuration, can not verify signature");
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
            $this->_payoutlogger->error("Webhook error: Signature is not valid");
            return $this->getResponse();
        }

        $external_id = $webhookData->external_id;

        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');

        try {
            $order = $this->getOrderByPaymentId($external_id);
            if (!isset($order) || $order->getId() == null) {
                $this->_payoutlogger->error("Webhook error: Order not found for external id (payment id): " . $external_id);
                return $this->getResponse();
            }
            if (!isset($webhookData->data->status)) {
                $this->_payoutlogger->error("Webhook error: checkout status not set in webhook");
                return $this->getResponse();
            }
            if ($webhookData->data->status != "succeeded") {
                $this->_payoutlogger->info("Webhook info: checkout status is not succeeded (" . $webhookData->data->status . "), skipping");
                return $this->getResponse();
            }
            if ($order->getPayment()->getMethod() != "payout") {
                $this->_payoutlogger->error("Webhook error: Payment method in order is not Payout");
                return $this->getResponse();
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
                    return $this->getResponse();
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
            $transaction = $this->objectManagerInterface->create('Magento\Framework\DB\Transaction')
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
        } catch (Exception $e) {
            $this->_logger->error($pre . $e->getMessage());
        }
        return $this->getResponse();
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
                ->build(TransactionInterface::TYPE_CAPTURE);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();

            $this->transactionResourceModel->save($transaction);
        } catch (Exception $e) {
            $this->_logger->error(__METHOD__ . " : Error creating payment transaction: " . $e->getMessage());
        }
    }

    private function getOrderByPaymentId($paymentId)
    {
        $objectManager = ObjectManager::getInstance();
        $payment = $objectManager->create('Magento\Sales\Model\Order\PaymentFactory')->create()->load($paymentId);

        return $payment->getOrder();
    }
}
