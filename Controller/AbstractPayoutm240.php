<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Controller;

use Exception;
use Magento\Checkout\Controller\Express\RedirectLoginInterface;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Session\Generic;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url\Helper\Data;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\StoreManagerInterface;
use Payout\Payment\Logger\Logger;
use Payout\Payment\Model\Config;
use Payout\Payment\Model\Payout;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Checkout Controller
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractPayoutm240 implements ActionInterface, RedirectLoginInterface, CsrfAwareActionInterface
{
    /**
     * @var Config
     */
    protected mixed $_config;

    /**
     * @var Quote|bool
     */
    protected Quote|bool $_quote = false;

    /**
     * Config mode type
     *
     * @var string
     */
    protected string $_configType = 'Payout\Payment\Model\Config';

    /** Config method type @var string */
    protected string $_configMethod = Config::METHOD_CODE;

    /**
     * Checkout mode type
     *
     * @var string
     */
    protected string $_checkoutType;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected \Magento\Customer\Model\Session $_customerSession;

    /**
     * @var Session $_checkoutSession
     */
    protected Session $_checkoutSession;

    /**
     * @var OrderFactory
     */
    protected OrderFactory $_orderFactory;

    /**
     * @var Generic
     */
    protected Generic $payoutSession;

    /**
     * @var Data
     */
    protected Data $_urlHelper;

    /**
     * @var Url
     */
    protected Url $_customerUrl;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $_logger;

    /**
     * Logging instance
     * @var Logger
     */
    protected Logger $_payoutlogger;

    /**
     * @var  Order $_order
     */
    protected Order $_order;

    /**
     * @var PageFactory
     */
    protected PageFactory $pageFactory;

    /**
     * @var InvoiceService
     */
    protected InvoiceService $_invoiceService;

    /**
     * @var InvoiceSender
     */
    protected InvoiceSender $invoiceSender;

    /**
     * @var OrderSender
     */
    protected OrderSender $OrderSender;

    /**
     * @var TransactionFactory
     */
    protected TransactionFactory $_transactionFactory;

    /**
     * @var  StoreManagerInterface $storeManager
     */
    protected StoreManagerInterface $_storeManager;
    /**
     * @var Payout $_paymentMethod
     */
    protected Payout $_paymentMethod;

    /**
     * @var UrlInterface
     */
    protected UrlInterface $_urlBuilder;

    /**
     * @var OrderRepositoryInterface $orderRepository
     */
    protected OrderRepositoryInterface $orderRepository;

    /**
     * @var CollectionFactory $_orderCollectionFactory
     */
    protected CollectionFactory $_orderCollectionFactory;

    /**
     * @var Builder $_transactionBuilder
     */
    protected Builder $_transactionBuilder;

    /**
     * @var DateTime
     */
    protected DateTime $_date;

    /**
     * @var OrderResourceModel
     */
    protected OrderResourceModel $orderResourceModel;

    /**
     * @var QuoteResourceModel
     */
    protected QuoteResourceModel $quoteResourceModel;

    /**
     * @var ResponseInterface
     */
    protected ResponseInterface $response;

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    /**
     * @var MessageManagerInterface
     */
    protected MessageManagerInterface $messageManager;

    /**
     * @var RedirectInterface
     */
    protected RedirectInterface $redirect;

    /**
     * @var TransactionRepositoryInterface
     */
    protected TransactionRepositoryInterface $transactionRepository;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    protected OrderPaymentRepositoryInterface $orderPaymentRepository;

    /**
     * @var OrderInterface
     */
    protected OrderInterface $orderInterface;

    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    protected OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository;

    /**
     * @var CartRepositoryInterface
     */
    protected CartRepositoryInterface $quoteRepository;

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param \Magento\Customer\Model\Session $customerSession
     * @param Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param Generic $payoutSession
     * @param Data $urlHelper
     * @param Url $customerUrl
     * @param LoggerInterface $logger
     * @param Logger $payoutlogger
     * @param TransactionFactory $transactionFactory
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param Payout $paymentMethod
     * @param UrlInterface $urlBuilder
     * @param OrderRepositoryInterface $orderRepository
     * @param StoreManagerInterface $storeManager
     * @param OrderSender $OrderSender
     * @param DateTime $date
     * @param CollectionFactory $orderCollectionFactory
     * @param Builder $_transactionBuilder
     * @param OrderResourceModel $orderResourceModel
     * @param QuoteResourceModel $quoteResourceModel
     * @param TransactionRepositoryInterface $transactionRepository
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderInterface $orderInterface
     * @param OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        Context                               $context,
        PageFactory                           $pageFactory,
        \Magento\Customer\Model\Session       $customerSession,
        Session                               $checkoutSession,
        OrderFactory                          $orderFactory,
        Generic                               $payoutSession,
        Data                                  $urlHelper,
        Url                                   $customerUrl,
        LoggerInterface                       $logger,
        Logger                                $payoutlogger,
        TransactionFactory                    $transactionFactory,
        InvoiceService                        $invoiceService,
        InvoiceSender                         $invoiceSender,
        Payout                                $paymentMethod,
        UrlInterface                          $urlBuilder,
        OrderRepositoryInterface              $orderRepository,
        StoreManagerInterface                 $storeManager,
        OrderSender                           $OrderSender,
        DateTime                              $date,
        CollectionFactory                     $orderCollectionFactory,
        Builder                               $_transactionBuilder,
        OrderResourceModel                    $orderResourceModel,
        QuoteResourceModel                    $quoteResourceModel,
        TransactionRepositoryInterface        $transactionRepository,
        OrderPaymentRepositoryInterface       $orderPaymentRepository,
        OrderInterface                        $orderInterface,
        OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository,
        CartRepositoryInterface               $quoteRepository,
    )
    {
        $pre = __METHOD__ . " : ";

        $this->_logger = $logger;

        $this->_payoutlogger = $payoutlogger;

        $this->_logger->debug($pre . 'bof');

        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->payoutSession = $payoutSession;
        $this->_urlHelper = $urlHelper;
        $this->_customerUrl = $customerUrl;
        $this->pageFactory = $pageFactory;
        $this->_invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->OrderSender = $OrderSender;
        $this->_transactionFactory = $transactionFactory;
        $this->_paymentMethod = $paymentMethod;
        $this->_urlBuilder = $urlBuilder;
        $this->orderRepository = $orderRepository;
        $this->_storeManager = $storeManager;
        $this->_date = $date;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_transactionBuilder = $_transactionBuilder;

        $this->response = $context->getResponse();
        $this->objectManager = $context->getObjectManager();
        $this->messageManager = $context->getMessageManager();
        $this->redirect = $context->getRedirect();
        $this->request = $context->getRequest();

        $parameters = ['params' => [$this->_configMethod]];
        $this->_config = $this->objectManager->create($this->_configType, $parameters);

        $this->_logger->debug($pre . 'eof');
        $this->orderResourceModel = $orderResourceModel;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->transactionRepository = $transactionRepository;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderInterface = $orderInterface;
        $this->orderStatusHistoryRepository = $orderStatusHistoryRepository;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Custom getter for payment configuration
     *
     * @param string $field i.e payout_id, sandbox_mode
     *
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfigData(string $field): mixed
    {
        return $this->_paymentMethod->getConfigData($field);
    }

    /**
     * Returns before_auth_url redirect parameter for customer session
     * @return null
     */
    public function getCustomerBeforeAuthUrl(): null
    {
        return null;
    }

    /**
     * Returns a list of action flags [flag_key] => boolean
     * @return array
     */
    public function getActionFlagList(): array
    {
        return [];
    }

    /**
     * Returns login url parameter for redirect
     * @return string
     */
    public function getLoginUrl(): string
    {
        return $this->_customerUrl->getLoginUrl();
    }

    /**
     * Returns action name which requires redirect
     * @return string
     */
    public function getRedirectActionName(): string
    {
        return 'index';
    }

    /**
     * @param Order $order
     * @return void
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function _initCheckout(Order $order): void
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');

        if (empty($order->getId())) {
            $response = $this->getResponse();
            if ($response instanceof Http) {
                $response->setStatusHeader(404, '1.1', 'Not found');
            }
            throw new LocalizedException(__('We could not find "Order" for processing'));
        }

        if ($order->getState() != Order::STATE_PENDING_PAYMENT) {
            $order->setState(
                Order::STATE_PENDING_PAYMENT
            );
            $this->orderResourceModel->save($order);
        }

        if ($order->getQuoteId()) {
            $this->_checkoutSession->setPayoutQuoteId($this->_checkoutSession->getQuoteId());
            $this->_checkoutSession->setPayoutSuccessQuoteId($this->_checkoutSession->getLastSuccessQuoteId());
            $this->_checkoutSession->setPayoutRealOrderId($this->_checkoutSession->getLastRealOrderId());
            $quote = $this->_checkoutSession->getQuote();
            $quote->setIsActive(false);
            $this->quoteResourceModel->save($quote);
        }

        $this->_logger->debug($pre . 'eof');
    }

    public function createCheckout(Order $order): Page
    {
        $pre = __METHOD__ . " : ";

        $page_object = $this->pageFactory->create();

        try {
            $this->_initCheckout($order);
            $this->redirect($this->_paymentMethod->createCheckoutForLastOrder());
        } catch (Exception $e) {
            $this->_logger->error($pre . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, __('Error occurred, contact support or try again, please'));
            $this->redirectToFailureUrl($order);
        }

        return $page_object;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Payout session instance getter
     *
     * @return Generic
     */
    protected function _getSession(): Generic
    {
        return $this->payoutSession;
    }

    /**
     * Return checkout session object
     *
     * @return Session
     */
    protected function _getCheckoutSession(): Session
    {
        return $this->_checkoutSession;
    }

    /**
     * Return checkout quote object
     *
     * @return bool|Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function _getQuote(): bool|Quote
    {
        if (!$this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }

        return $this->_quote;
    }

    /**
     *
     * Set redirect into response
     * @param $path
     * @param array $arguments
     * @return ResponseInterface
     */
    protected function redirect($path, array $arguments = []): ResponseInterface
    {
        $this->redirect->redirect($this->getResponse(), $path, $arguments);
        return $this->getResponse();
    }

    /**
     * @param OrderInterface $order
     * @return void
     */
    protected function redirectToOrderDetail(OrderInterface $order): void
    {
        if ($order->getCustomerIsGuest()) {
            $this->redirect('sales/guest/view/');
        } else {
            $this->redirect("sales/order/view/order_id/{$order->getId()}/");
        }
    }

    /**
     * @param OrderInterface $order
     * @return void
     */
    protected function redirectToSuccessUrl(OrderInterface $order): void
    {
        if ($this->_checkoutSession->getLastRealOrderId() != $order->getIncrementId()) {
            $this->redirectToOrderDetail($order);
        } else {
            $this->redirectToOnePageSuccessUrl();
        }
    }

    /**
     * @param OrderInterface $order
     * @return void
     */
    protected function redirectToFailureUrl(OrderInterface $order): void
    {
        if ($this->_checkoutSession->getLastRealOrderId() != $order->getIncrementId()) {
            $this->redirectToOrderDetail($order);
        } else {
            $this->redirectToOnePageFailureUrl();
        }
    }

    /**
     * @return void
     */
    protected function redirectToOnePageSuccessUrl(): void
    {
        $this->redirectToOnePageUrl('checkout/onepage/success');
    }

    /**
     * @return void
     */
    protected function redirectToOnePageFailureUrl(): void
    {
        $this->redirectToOnePageUrl('checkout/onepage/failure');
    }

    /**
     * @param string $url
     * @return void
     */
    private function redirectToOnePageUrl(string $url): void
    {
        $this->redirect($url);
    }
}
