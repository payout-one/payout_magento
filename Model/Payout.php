<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Model;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\LocalizedExceptionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Url\Decoder;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\ResourceModel\PaymentToken as PaymentTokenResourceModel;
use Payout\Payment\Api\Client as PayoutClient;
use Payout\Payment\Logger\Logger;
use Magento\Sales\Model\ResourceModel\Order\Payment as PaymentResourceModel;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Psr\Log\LogLevel;

/* Payout Api */

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Payout extends AbstractMethod
{
    public const string CHECKOUT_STATE_PROCESSING = "processing";
    public const string CHECKOUT_STATE_REQUIRES_AUTHORIZATION = "requires_authorization";
    public const string CHECKOUT_STATE_SUCCEEDED = "succeeded";
    public const string CHECKOUT_STATE_EXPIRED = "expired";

    public const string ORDER_STATE_PENDING_PAYOUT = "pending_payout";
    public const string ORDER_STATUS_PENDING_PAYOUT = "pending_payout";

    public const int AUTHENTICATE_TIMEOUT = 2;
    public const int CREATE_CHECKOUT_TIMEOUT = 5;
    public const int RETRIEVE_CHECKOUT_TIMEOUT = 5;

    /**
     * @var string
     */
    protected $_code = Config::METHOD_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'Payout\Payment\Block\Form';

    /**
     * @var string
     */
    protected $_infoBlockType = 'Payout\Payment\Block\Payment\Info';

    /**
     * @var string
     */
    protected string $_configType = 'Payout\Payment\Model\Config';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isGateway = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canOrder = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canVoid = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseInternal = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canFetchTransactionInfo = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canReviewPayment = true;

    /**
     * Website Payments Pro instance
     *
     * @var Config $config
     */
    protected Config $_config;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $_storeManager;

    /**
     * @var UrlInterface
     */
    protected UrlInterface $_urlBuilder;

    /**
     * @var UrlInterface|FormKey
     */
    protected UrlInterface|FormKey $_formKey;

    /**
     * @var Session
     */
    protected Session $_checkoutSession;

    /**
     * @var LocalizedExceptionFactory
     */
    protected LocalizedExceptionFactory $_exception;

    /**
     * @var TransactionRepositoryInterface
     */
    protected TransactionRepositoryInterface $transactionRepository;

    /**
     * @var BuilderInterface
     */
    protected BuilderInterface $transactionBuilder;
    protected PaymentTokenRepositoryInterface $paymentTokenRepository;

    /**
     * @var PaymentTokenManagementInterface
     */
    protected PaymentTokenManagementInterface $paymentTokenManagement;

    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $encryptor;

    /**
     * @var Payment
     */
    protected Payment $payment;

    /**
     * @var PaymentTokenResourceModel
     */
    protected PaymentTokenResourceModel $paymentTokenResourceModel;

    /**
     * Logging instance
     * @var Logger
     */
    protected Logger $_payoutlogger;

    /**
     * @var PaymentResourceModel
     */
    protected PaymentResourceModel $paymentResourceModel;

    /**
     * @var SearchCriteriaBuilder
     */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    protected FilterBuilder $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    protected FilterGroupBuilder $filterGroupBuilder;

    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    protected OrderPaymentRepositoryInterface $orderPaymentRepository;

    /**
     * @var OrderSender
     */
    protected OrderSender $orderSender;

    /**
     * @var InvoiceService
     */
    protected InvoiceService $invoiceService;

    /**
     * @var InvoiceSender
     */
    protected InvoiceSender $invoiceSender;

    /**
     * @var OrderRepositoryInterface $orderRepository
     */
    protected OrderRepositoryInterface $orderRepository;

    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    protected OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository;

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var EncoderInterface
     */
    protected EncoderInterface $urlEncoder;

    /**
     * @var DecoderInterface
     */
    protected DecoderInterface $urlDecoder;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param ConfigFactory $configFactory
     * @param Logger $payoutlogger
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param Session $checkoutSession
     * @param LocalizedExceptionFactory $exception
     * @param TransactionRepositoryInterface $transactionRepository
     * @param BuilderInterface $transactionBuilder
     * @param PaymentTokenRepositoryInterface $PaymentTokenRepositoryInterface
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param EncryptorInterface $encryptor
     * @param PaymentTokenResourceModel $paymentTokenResourceModel
     * @param PaymentResourceModel $paymentResourceModel
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param ResourceConnection $resourceConnection
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderSender $orderSender
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository
     * @param ObjectManagerInterface $objectManager
     * @param EncoderInterface $urlEncoder
     * @param DecoderInterface $urlDecoder
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context                               $context,
        Registry                              $registry,
        ExtensionAttributesFactory            $extensionFactory,
        AttributeValueFactory                 $customAttributeFactory,
        Data                                  $paymentData,
        ScopeConfigInterface                  $scopeConfig,
        \Magento\Payment\Model\Method\Logger  $logger,
        ConfigFactory                         $configFactory,
        Logger                                $payoutlogger,
        StoreManagerInterface                 $storeManager,
        UrlInterface                          $urlBuilder,
        FormKey                               $formKey,
        Session                               $checkoutSession,
        LocalizedExceptionFactory             $exception,
        TransactionRepositoryInterface        $transactionRepository,
        BuilderInterface                      $transactionBuilder,
        PaymentTokenRepositoryInterface       $PaymentTokenRepositoryInterface,
        PaymentTokenManagementInterface       $paymentTokenManagement,
        EncryptorInterface                    $encryptor,
        PaymentTokenResourceModel             $paymentTokenResourceModel,
        PaymentResourceModel                  $paymentResourceModel,
        SearchCriteriaBuilder                 $searchCriteriaBuilder,
        FilterBuilder                         $filterBuilder,
        FilterGroupBuilder                    $filterGroupBuilder,
        ResourceConnection                    $resourceConnection,
        OrderPaymentRepositoryInterface       $orderPaymentRepository,
        OrderSender                           $orderSender,
        InvoiceService                        $invoiceService,
        InvoiceSender                         $invoiceSender,
        OrderRepositoryInterface              $orderRepository,
        OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository,
        ObjectManagerInterface                $objectManager,
        EncoderInterface                      $urlEncoder,
        DecoderInterface                      $urlDecoder,
        AbstractResource                      $resource = null,
        AbstractDb                            $resourceCollection = null,
        array                                 $data = []
    )
    {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->_storeManager = $storeManager;
        $this->_urlBuilder = $urlBuilder;
        $this->_formKey = $formKey;
        $this->_checkoutSession = $checkoutSession;
        $this->_exception = $exception;
        $this->transactionRepository = $transactionRepository;
        $this->transactionBuilder = $transactionBuilder;
        $this->paymentTokenRepository = $PaymentTokenRepositoryInterface;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->encryptor = $encryptor;
        $this->paymentTokenResourceModel = $paymentTokenResourceModel;

        $parameters = ['params' => [$this->_code]];

        $this->_config = $configFactory->create($parameters);
        $this->_payoutlogger = $payoutlogger;
        $this->paymentResourceModel = $paymentResourceModel;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->resourceConnection = $resourceConnection;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderSender = $orderSender;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->orderRepository = $orderRepository;
        $this->orderStatusHistoryRepository = $orderStatusHistoryRepository;
        $this->objectManager = $objectManager;
        $this->urlEncoder = $urlEncoder;
        $this->urlDecoder = $urlDecoder;
    }

    /**
     * Store setter
     * Also updates store ID in config object
     *
     * @param Store|int $storeId
     *
     * @return Payout
     * @throws NoSuchEntityException
     */
    public function setStore($storeId): Payout
    {
        $this->setData('store', $storeId);

        if (null === $storeId) {
            $storeId = $this->_storeManager->getStore()->getId();
        }
        $this->_config->setStoreId(is_object($storeId) ? $storeId->getId() : $storeId);

        return $this;
    }

    /**
     * Check whether payment method can be used
     *
     * @param CartInterface|null $quote
     *
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null): bool
    {
        return parent::isAvailable($quote) && $this->_config->isMethodAvailable();
    }

    /**
     * @return string
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function createCheckoutForLastOrder(): string
    {
        return $this->createCheckout($this->_checkoutSession->getLastRealOrder());
    }

    /**
     * This is where we compile data posted by the form to Payout
     * @param Order $order
     * @return string
     * @throws LocalizedException
     * @throws AlreadyExistsException
     * @throws Exception
     */
    public function createCheckout(Order $order): string
    {
        $orderPayment = $order->getPayment();

        if (!($orderPayment instanceof Payment)) {
            throw new Exception("Order payment is not instance of Payment, can\'t create checkout.");
        }

        if (isset($orderPayment->getAdditionalInformation()['raw_details_info']['idempotency_key'])) {
            $idempotencyKey = $orderPayment->getAdditionalInformation()['raw_details_info']['idempotency_key'];
        } else {
            $idempotencyKey = $this->getIdempotencyKeyForPayment($orderPayment);
            // update orderPayment with new idempotency key in additional information
            $order->setPayment($this->orderPaymentRepository->get($orderPayment->getId()));
        }

        $payout = new PayoutClient($this->getPayoutConfigs());

        $items = [];

        foreach ($order->getAllItems() as $item) {
            if ((float)$item->getPriceInclTax() > 0) {
                $items[] = [
                    'name' => $item->getName(),
                    'unit_price' => "" . intval(round((float)$item->getPriceInclTax() * 100)),
                    'quantity' => (int)$item->getQtyOrdered(),
                ];
            }
        }

        if ((float)$order->getShippingInclTax() > 0) {
            $items[] = [
                'name' => 'shipping cost',
                'unit_price' => "" . intval(round((float)$order->getShippingInclTax() * 100)),
                'quantity' => 1,
            ];
        }

        $checkout_data = array(
            'amount' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
            'customer' => [
                'first_name' => $order->getCustomerFirstname(),
                'last_name' => $order->getCustomerLastname(),
                'email' => $order->getCustomerEmail()
            ],
            'products' => $items,
            'billing_address' => $this->createCheckoutAddress($order->getBillingAddress()),
            'shipping_address' => $this->createCheckoutAddress($order->getShippingAddress()),
            'external_id' => $order->getPayment()->getEntityId(),
            'redirect_url' => $this->_urlBuilder->getUrl(
                    'Payout/redirect/order',
                    array('_secure' => true)
                ) . '?form_key=' . $this->_formKey->getFormKey() . '&gid=' . $order->getRealOrderId(),
            'idempotency_key' => $idempotencyKey,
        );


        $this->_payoutlogger->info("***********************Sending Order Data to Payout*************************");
        $this->_payoutlogger->info(json_encode($checkout_data));

        $response = $payout->createCheckout($checkout_data);

        $typeOrderTransactions = $this->getTypeOrderCheckoutTransactionsForOrder($order, $response->id);
        if (empty($typeOrderTransactions)) {
            $this->createTransactionForCheckoutResponse(
                $order,
                $this->createRawDetailsInfoDataForCheckoutResponse($response, $order->getId()),
                TransactionInterface::TYPE_ORDER
            );
        }

        $this->_payoutlogger->info("***********************Token Validation from Payout*************************");

        $this->_payoutlogger->info(json_encode($response));

        return $response->checkout_url;
    }

    /**
     * @param mixed $response
     * @param int $orderId
     * @return array
     */
    public function createRawDetailsInfoDataForCheckoutResponse(mixed $response, int $orderId): array
    {
        return [
            'checkout_id' => $response->id,
            'order_id' => $orderId,
            'external_id' => $response->external_id,
            'amount' => $response->amount,
            'currency' => $response->currency,
            'customer_email' => $response->customer->email,
            'first_name' => $response->customer->first_name,
            'last_name' => $response->customer->last_name,
            'nonce' => $response->nonce,
            'checkout_url' => $response->checkout_url,
            'signature' => $response->signature,
            'status' => $response->status,
            'raw_data' => json_encode($response),
            'source' => 'checkout_response',
        ];
    }

    /**
     * @param Order $order
     * @param array $rawDetailsInfoData
     * @param string $type
     * @return void
     * @throws AlreadyExistsException
     * @throws Exception
     */
    private function createTransactionForCheckoutResponse(Order $order, array $rawDetailsInfoData, string $type): void
    {
        if (!in_array($type, [TransactionInterface::TYPE_ORDER, TransactionInterface::TYPE_CAPTURE])) {
            $error = __('Given transaction type is not supported') . ', ' . __('can\'t create transaction for checkout');
            $this->_payoutlogger->error($error);
            throw new Exception($error);
        }
        $checkoutId = $rawDetailsInfoData['checkout_id'];


        $transactionId = $checkoutId . ($type == TransactionInterface::TYPE_ORDER ? '-order' : '-capture');
        $orderPayment = $order->getPayment();

        if (!($orderPayment instanceof Payment)) {
            $error = __('Order payment is not of type payment') . ', ' . __('can\'t create transaction for checkout');
            $this->_payoutlogger->error($error);
            throw new Exception($error);
        }

        $transaction = $this->transactionBuilder
            ->setPayment($orderPayment)
            ->setOrder($order)
            ->setTransactionId($transactionId)
            ->setAdditionalInformation([Transaction::RAW_DETAILS => $rawDetailsInfoData])
            ->setFailSafe(true)
            ->build($type);

        $orderPayment
            ->setLastTransId($transactionId)
            ->setTransactionId($transactionId)
            ->addTransactionCommentsToOrder(
                $transaction,
                $type == TransactionInterface::TYPE_ORDER
                    ? __('Created checkout') . ': ' . $checkoutId
                    : __('The authorized amount is %1',
                    $order->getBaseCurrency()->formatTxt($order->getGrandTotal())
                )
            );

        if ($type == TransactionInterface::TYPE_CAPTURE) {
            $typeOrderTransactions = $this->getTypeOrderCheckoutTransactionsForOrder($order, $checkoutId);
            reset($typeOrderTransactions);
            $typeOrderTransaction = current($typeOrderTransactions);
            $orderPayment->setParentTransactionId($typeOrderTransaction->getTxnId());
            $transaction
                ->setParentTxnId($typeOrderTransaction->getTxnId())
                ->setParentId($typeOrderTransaction->getId());

        } else {
            $orderPayment->setParentTransactionId(null);
            $transaction->setParentTxnId(null);
        }

        $this->paymentResourceModel->save($orderPayment);
        $this->transactionRepository->save($transaction);
    }

    /**
     * @return array
     */
    private function getPayoutConfigs(): array
    {
        return array(
            'client_id' => $this->getConfigData('payout_id'),
            'client_secret' => $this->getConfigData('encryption_key'),
            'sandbox' => (bool)$this->getConfigData('sandbox_mode'),
            'internal_payout_test_override' => (bool)$this->getConfigData('internal_payout_test_override'),
        );
    }


    /**
     * @param Payment $payment
     * @return string
     * @throws Exception
     */
    private function getIdempotencyKeyForPayment(Payment $payment): string
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $row = $connection->fetchRow(
                $connection
                    ->select()
                    ->from($this->paymentResourceModel->getMainTable(), 'additional_information')
                    ->where('entity_id = ?', $payment->getId())
                    ->forUpdate()
            );

            $data = isset($row['additional_information']) ? json_decode($row['additional_information'], true) : [];

            if (isset($data['raw_details_info']['idempotency_key'])) {
                $this->_logger->log(LogLevel::INFO, __('Obtained idempotency key') . ' ' . $data['raw_details_info']['idempotency_key'] . ' ' . __('in meantime'));
                $connection->rollBack();
                return (string)$data['raw_details_info']['idempotency_key'];
            }

            $generatedIdempotencyKey = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
            $data['raw_details_info']['idempotency_key'] = $generatedIdempotencyKey;
            $connection->update(
                $this->paymentResourceModel->getMainTable(),
                ['additional_information' => json_encode($data)],
                'entity_id = ' . (int)$payment->getId(),
            );

            $connection->commit();
            return $generatedIdempotencyKey;
        } catch (Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @param TransactionInterface $transaction
     * @return mixed|null
     * @throws Exception
     */
    public function retrieveCheckout(TransactionInterface $transaction): mixed
    {
        $order = $transaction->getOrder();
        if (!isset($order) || !($order instanceof Order)) {
            throw new Exception(__('Payment transaction') . ' ' . $transaction->getId() . ' - ' . __('its order not loaded') . ', ' . __('can\'t repeat checkout for it'));
        }
        if ($transaction->getTxnType() != TransactionInterface::TYPE_ORDER) {
            throw new Exception(__('Payment transaction') . ' ' . $transaction->getId() . ' ' . __('is not of type order') . ', ' . __('can\'t repeat checkout for it'));
        }
        if (!isset($transaction->getAdditionalInformation('raw_details_info')['checkout_id'])) {
            throw new Exception(__('Payment transaction') . ' ' . $transaction->getId() . ' ' . __('has not checkout id in its additional data') . ', ' . __('can\'t repeat checkout for it'));
        }

        $checkoutId = (int)$transaction->getAdditionalInformation('raw_details_info')['checkout_id'];
        $payoutClient = new PayoutClient($this->getPayoutConfigs());

        try {
            return $payoutClient->retrieveCheckout($checkoutId);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @param Order $order
     * @param array $rawDetailsInfoData
     * @return void
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws Exception
     */
    public function createSuccessfulPayoutPaymentCapture(Order $order, array $rawDetailsInfoData): void
    {
        $checkoutId = $rawDetailsInfoData['checkout_id'];
        $status = Order::STATE_PROCESSING;
        if ($this->getConfigData('Successful_Order_status') != "") {
            $status = $this->getConfigData('Successful_Order_status');
        }
        $message = __(
            'Redirect Response, Transaction has been approved: Payout_Checkout_Id: "%1"',
            $checkoutId
        );

        $this->orderStatusHistoryRepository->save(
            $order->addCommentToStatusHistory($message)
        );

        $order_successful_email = $this->getConfigData('order_email');

        if ($order_successful_email != '0') {
            $this->orderSender->send($order);
            $this->orderStatusHistoryRepository->save(
                $order->addCommentToStatusHistory(
                    __('Notified customer about order #%1', $order->getId())
                )->setIsCustomerNotified(true)
            );
        }

        // Capture invoice when payment is successfull
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
        $invoice->register();

        // Save the invoice to the order
        $transaction = $this->objectManager->create('Magento\Framework\DB\Transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transaction->save();

        $send_invoice_email = $this->getConfigData('invoice_email');
        if ($send_invoice_email != '0') {
            $this->invoiceSender->send($invoice);
            $this->orderStatusHistoryRepository->save(
                $order->addCommentToStatusHistory(
                    __('Notified customer about invoice #%1', $invoice->getId())
                )->setIsCustomerNotified(true)
            );
        }

        // Save Transaction Response
        $this->createTransactionForCheckoutResponse($order, $rawDetailsInfoData, TransactionInterface::TYPE_CAPTURE);

        $this->orderRepository->save($order->setState($status)->setStatus($status));
    }

    /**
     * @param OrderInterface $order
     * @return array
     */
    public function getPayoutGroupedTransactionsForOrder(OrderInterface $order): array
    {
        return array_reduce(
            $this->getTransactionsForOrder($order),
            function ($grouped, $transaction) {
                $transactionCheckoutId = $this->getCheckoutIdFromTransaction($transaction);
                if (isset($transactionCheckoutId)) {
                    $grouped[$transactionCheckoutId]['checkout_id'] = $transactionCheckoutId;
                    $grouped[$transactionCheckoutId]['items'][$transaction->getTxnType()][] = $transaction;
                }
                return $grouped;
            }, []);
    }

    /**
     * @param OrderInterface $order
     * @return bool
     */
    public function isAtLeastOneCheckoutInOrderSucceeded(OrderInterface $order): bool
    {
        return !empty(array_filter(
            $this->getPayoutGroupedTransactionsForOrder($order),
            fn($transaction) => !empty($transaction['items'][TransactionInterface::TYPE_CAPTURE]),
        ));
    }

    private function getCheckoutIdFromTransaction(Transaction $transaction): ?int
    {
        if (isset($transaction->getAdditionalInformation('raw_details_info')['checkout_id'])) {
            return (int)$transaction->getAdditionalInformation('raw_details_info')['checkout_id'];
        }
        return null;
    }

    /**
     * @param OrderInterface $order
     * @param Filter[] $filters
     * @return TransactionInterface[]
     */
    public function getTransactionsForOrder(OrderInterface $order, array $filters = []): array
    {
        if ($order->getEntityId() == null || $order->getPayment() == null) {
            return [];
        }

        return $this->transactionRepository->getList(
            $this->searchCriteriaBuilder->setFilterGroups(
                array_map(
                    fn($filter) => $this->filterGroupBuilder->addFilter($filter)->create(),
                    array_merge(
                        [
                            $this->filterBuilder->setField('payment_id')->setValue($order->getPayment()->getEntityId())->create(),
                            $this->filterBuilder->setField('order_id')->setValue($order->getEntityId())->create(),
                        ],
                        $filters
                    )
                )
            )->create()
        )->getItems();
    }

    /**
     * @param OrderInterface $order
     * @param int $checkoutId
     * @return TransactionInterface[]
     */
    public function getTypeOrderCheckoutTransactionsForOrder(OrderInterface $order, int $checkoutId): array
    {
        return $this->getTransactionsForOrder(
            $order,
            [$this->filterBuilder->setField('txn_id')->setValue($checkoutId . '-order')->create()]
        );
    }

    /**
     * @param OrderInterface $order
     * @param int $checkoutId
     * @return TransactionInterface[]
     */
    public function getTypeCaptureCheckoutTransactionsForOrder(OrderInterface $order, int $checkoutId): array
    {
        return $this->getTransactionsForOrder(
            $order,
            [$this->filterBuilder->setField('txn_id')->setValue($checkoutId . '-capture')->create()]
        );
    }

    /**
     * create payout checkout address from magento address
     *
     * @param Address $address
     *
     * @return array
     */
    private function createCheckoutAddress(Address $address): array
    {
        return [
            'name' => $address->getFirstname() . ' ' . $address->getLastname(),
            'address_line_1' => $address->getStreetLine(1),
            'address_line_2' => $address->getStreetLine(2),
            'postal_code' => $address->getPostcode(),
            'country_code' => $address->getCountryId(),
            'city' => $address->getCity(),
        ];
    }

    /**
     * Checkout redirect URL getter for onepage checkout (hardcode)
     *
     * @return string
     * @see Quote\Payment::getCheckoutRedirectUrl()
     * @see \Magento\Checkout\Controller\Onepage::savePaymentAction()
     */
    public function getCheckoutRedirectUrl(): string
    {
        return $this->_urlBuilder->getUrl('Payout/redirect');
    }

    /**
     * @param Order $order
     * @param int $id
     * @param string $idType
     * @return string
     */
    public function getRepeatCheckoutUrl(OrderInterface $order, int $id, string $idType): string
    {
        return $this->_urlBuilder->getUrl(
            'Payout/redirect/repeat',
            [$idType => $id, 'order_protect_code' => $order->getProtectCode()]
        );
    }

    /**
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return AbstractMethod
     */
    public function initialize($paymentAction, $stateObject): AbstractMethod
    {
        $stateObject->setState(self::ORDER_STATE_PENDING_PAYOUT);
        $stateObject->setStatus(self::ORDER_STATUS_PENDING_PAYOUT);
        $stateObject->setIsNotified(false);

        return parent::initialize($paymentAction, $stateObject);
    }

    /**
     * @return mixed
     */
    protected function getStoreName(): mixed
    {
        return $this->_scopeConfig->getValue(
            'general/store_information/name',
            ScopeInterface::SCOPE_STORE
        );
    }
}
