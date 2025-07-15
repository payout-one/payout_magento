<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Controller;

use Magento\Checkout\Controller\Express\RedirectLoginInterface;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\Action as AppAction;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\Generic;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url\Helper;
use Magento\Framework\Url\Helper\Data;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
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

/**
 * Checkout Controller
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractPayoutm240 extends AppAction implements RedirectLoginInterface, CsrfAwareActionInterface
{
    /**
     * Internal cache of checkout models
     *
     * @var array
     */
    protected array $_checkoutTypes = [];

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
     * @var Helper|Data
     */
    protected Helper|Data $_urlHelper;

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
     * @var Magento\Sales\Model\Order\Payment\Transaction\Builder|Builder $_transactionBuilder
     */
    protected Magento\Sales\Model\Order\Payment\Transaction\Builder|Builder $_transactionBuilder;

    /**
     * @var DateTime
     */
    protected DateTime $_date;

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param \Magento\Customer\Model\Session $customerSession
     * @param Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param Generic $payoutSession
     * @param Data $urlHelper
     * @param Url $customerUrl
     * @param TransactionFactory $transactionFactory
     * @param Payout $paymentMethod
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Magento\Customer\Model\Session $customerSession,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        Generic $payoutSession,
        Data $urlHelper,
        Url $customerUrl,
        LoggerInterface $logger,
        Logger $payoutlogger,
        TransactionFactory $transactionFactory,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        Payout $paymentMethod,
        UrlInterface $urlBuilder,
        OrderRepositoryInterface $orderRepository,
        StoreManagerInterface $storeManager,
        OrderSender $OrderSender,
        DateTime $date,
        CollectionFactory $orderCollectionFactory,
        Builder $_transactionBuilder
    ) {
        $pre = __METHOD__ . " : ";

        $this->_logger = $logger;

        $this->_payoutlogger = $payoutlogger;

        $this->_logger->debug($pre . 'bof');

        $this->_customerSession        = $customerSession;
        $this->_checkoutSession        = $checkoutSession;
        $this->_orderFactory           = $orderFactory;
        $this->payoutSession           = $payoutSession;
        $this->_urlHelper              = $urlHelper;
        $this->_customerUrl            = $customerUrl;
        $this->pageFactory             = $pageFactory;
        $this->_invoiceService         = $invoiceService;
        $this->invoiceSender           = $invoiceSender;
        $this->OrderSender             = $OrderSender;
        $this->_transactionFactory     = $transactionFactory;
        $this->_paymentMethod          = $paymentMethod;
        $this->_urlBuilder             = $urlBuilder;
        $this->orderRepository         = $orderRepository;
        $this->_storeManager           = $storeManager;
        $this->_date                   = $date;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_transactionBuilder     = $_transactionBuilder;

        parent::__construct($context);

        $parameters    = ['params' => [$this->_configMethod]];
        $this->_config = $this->_objectManager->create($this->_configType, $parameters);

        $this->_logger->debug($pre . 'eof');
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
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
     * @param string $field i.e payout_id, test_mode
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
     * Redirect to login page
     *
     * @return void
     */
    public function redirectLogin(): void
    {
        $this->_actionFlag->set('', 'no-dispatch', true);
        $this->_customerSession->setBeforeAuthUrl($this->_redirect->getRefererUrl());
        $this->getResponse()->setRedirect(
            $this->_urlHelper->addRequestParam($this->_customerUrl->getLoginUrl(), ['context' => 'checkout'])
        );
    }

    /**
     * Instantiate
     *
     * @return void
     * @throws LocalizedException
     */
    protected function _initCheckout(): void
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
        $this->_order = $this->_checkoutSession->getLastRealOrder();

        if ( ! $this->_order->getId()) {
            $this->getResponse()->setStatusHeader(404, '1.1', 'Not found');
            throw new LocalizedException(__('We could not find "Order" for processing'));
        }

        if ($this->_order->getState() != Order::STATE_PENDING_PAYMENT) {
            $this->_order->setState(
                Order::STATE_PENDING_PAYMENT
            )->save();
        }

        if ($this->_order->getQuoteId()) {
            $this->_checkoutSession->setPayoutQuoteId($this->_checkoutSession->getQuoteId());
            $this->_checkoutSession->setPayoutSuccessQuoteId($this->_checkoutSession->getLastSuccessQuoteId());
            $this->_checkoutSession->setPayoutRealOrderId($this->_checkoutSession->getLastRealOrderId());
            $this->_checkoutSession->getQuote()->setIsActive(false)->save();
        }

        $this->_logger->debug($pre . 'eof');
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
     */
    protected function _getQuote(): bool|Quote
    {
        if ( ! $this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }

        return $this->_quote;
    }

}
