<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Model;

use Magento\Checkout\Model\Session;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedExceptionFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Vault\Model\ResourceModel\PaymentToken as PaymentTokenResourceModel;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Payout\Payment\Api\Client as PayoutClient;
use Payout\Payment\Logger\Logger;

/* Payout Api */

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Payout extends AbstractMethod
{
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
    protected $_configType = 'Payout\Payment\Model\Config';

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
    protected $_config;

    /**
     * Payment additional information key for payment action
     *
     * @var string
     */
    protected $_isOrderPaymentActionKey = 'is_order_action';

    /**
     * Payment additional information key for number of used authorizations
     *
     * @var string
     */
    protected $_authorizationCountKey = 'authorization_count';

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var UrlInterface
     */
    protected $_formKey;

    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @var LocalizedExceptionFactory
     */
    protected $_exception;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var BuilderInterface
     */
    protected $transactionBuilder;
    protected $creditCardTokenFactory;
    protected $paymentTokenRepository;

    /**
     * @var PaymentTokenManagementInterface
     */
    protected $paymentTokenManagement;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var Payment
     */
    protected $payment;

    /**
     * @var PaymentTokenResourceModel
     */
    protected $paymentTokenResourceModel;

    /**
     * Logging instance
     * @var Logger
     */
    protected $_payoutlogger;


    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param ConfigFactory $configFactory
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param CartFactory $cartFactory
     * @param Session $checkoutSession
     * @param LocalizedExceptionFactory $exception
     * @param TransactionRepositoryInterface $transactionRepository
     * @param BuilderInterface $transactionBuilder
     * @param AbstractResource $resource
     * @param AbstractDb $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        ConfigFactory $configFactory,
        Logger $payoutlogger,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        Session $checkoutSession,
        LocalizedExceptionFactory $exception,
        TransactionRepositoryInterface $transactionRepository,
        BuilderInterface $transactionBuilder,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        CreditCardTokenFactory $CreditCardTokenFactory,
        PaymentTokenRepositoryInterface $PaymentTokenRepositoryInterface,
        PaymentTokenManagementInterface $paymentTokenManagement,
        EncryptorInterface $encryptor,
        PaymentTokenResourceModel $paymentTokenResourceModel,
        array $data = []
    ) {
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

        $this->_storeManager             = $storeManager;
        $this->_urlBuilder               = $urlBuilder;
        $this->_formKey                  = $formKey;
        $this->_checkoutSession          = $checkoutSession;
        $this->_exception                = $exception;
        $this->transactionRepository     = $transactionRepository;
        $this->transactionBuilder        = $transactionBuilder;
        $this->creditCardTokenFactory    = $CreditCardTokenFactory;
        $this->paymentTokenRepository    = $PaymentTokenRepositoryInterface;
        $this->paymentTokenManagement    = $paymentTokenManagement;
        $this->encryptor                 = $encryptor;
        $this->paymentTokenResourceModel = $paymentTokenResourceModel;

        $parameters = ['params' => [$this->_code]];

        $this->_config       = $configFactory->create($parameters);
        $this->_payoutlogger = $payoutlogger;
    }

    /**
     * Store setter
     * Also updates store ID in config object
     *
     * @param Store|int $store
     *
     * @return $this
     */
    public function setStore($store)
    {
        $this->setData('store', $store);

        if (null === $store) {
            $store = $this->_storeManager->getStore()->getId();
        }
        $this->_config->setStoreId(is_object($store) ? $store->getId() : $store);

        return $this;
    }

    /**
     * Check whether payment method can be used
     *
     * @param CartInterface|Quote|null $quote
     *
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        return parent::isAvailable($quote) && $this->_config->isMethodAvailable();
    }

    public function getApiUrl()
    {
        return "https://sandbox.payout.one";
    }

    /**
     * This is where we compile data posted by the form to Payout
     * @return array
     */
    public function getStandardCheckoutFormFields()
    {
        $order = $this->_checkoutSession->getLastRealOrder();
        $pre   = __METHOD__ . ' : ';

        $clientId = $this->getConfigData('payout_id');
        $secret   = $this->getConfigData('encryption_key');

        $config = array(
            'client_id'     => $clientId,
            'client_secret' => $secret,
            'sandbox'       => true
        );

        $payout = new PayoutClient($config);

        $externalId = $order->getIncrementId();

        $checkout_data = array(
            'amount'       => $order->getGrandTotal(),
            'currency'     => $order->getOrderCurrencyCode(),
            'customer'     => [
                'first_name' => $order->getCustomerFirstname(),
                'last_name'  => $order->getCustomerLastname(),
                'email'      => $order->getCustomerEmail()
            ],
            'external_id'  => $order->getIncrementId(),
            'redirect_url' => $this->_urlBuilder->getUrl(
                    'Payout/redirect/order',
                    array('_secure' => true)
                ) . '?form_key=' . $this->_formKey->getFormKey() . '&gid=' . $order->getRealOrderId(),
        );


        $this->_payoutlogger->info("***********************Sending Order Data to Payout*************************");
        $this->_payoutlogger->info(json_encode($checkout_data));

        $response = $payout->createCheckout($checkout_data);

        $this->_payoutlogger->info("***********************Token Validation from Payout*************************");

        $this->_payoutlogger->info(json_encode($response));

        return $response->checkout_url;
    }

    /**
     * getTotalAmount
     */
    public function getTotalAmount($order)
    {
        if ($this->getConfigData('use_store_currency')) {
            $price = $this->getNumberFormat($order->getGrandTotal());
        } else {
            $price = $this->getNumberFormat($order->getBaseGrandTotal());
        }

        return $price;
    }

    /**
     * getNumberFormat
     */
    public function getNumberFormat($number)
    {
        return number_format($number, 2, '.', '');
    }

    /**
     * getPaidSuccessUrl
     */
    public function getPaidSuccessUrl()
    {
        return $this->_urlBuilder->getUrl('Payout/redirect/success', array('_secure' => true));
    }

    public function getOrderPlaceRedirectUrl()
    {
        return $this->_urlBuilder->getUrl('Payout/redirect');
    }

    /**
     * Checkout redirect URL getter for onepage checkout (hardcode)
     *
     * @return string
     * @see Quote\Payment::getCheckoutRedirectUrl()
     * @see \Magento\Checkout\Controller\Onepage::savePaymentAction()
     */
    public function getCheckoutRedirectUrl()
    {
        return $this->_urlBuilder->getUrl('Payout/redirect');
    }

    /**
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return $this
     */
    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);

        return parent::initialize($paymentAction, $stateObject);
    }

    /*
     * called dynamically by checkout's framework.
     */

    /**
     * getPaidNotifyUrl
     */
    public function getPaidNotifyUrl()
    {
        return $this->_urlBuilder->getUrl('Payout/notify', array('_secure' => true));
    }

    public function curlPost($url, $fields)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, count($fields));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    /**
     * @return mixed
     */
    protected function getStoreName()
    {
        return $this->_scopeConfig->getValue(
            'general/store_information/name',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Place an order with authorization or capture action
     *
     * @param Payment $payment
     * @param float $amount
     *
     * @return $this
     */
    protected function _placeOrder(Payment $payment, $amount)
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
    }

    /**
     * Get transaction with type order
     *
     * @param OrderPaymentInterface $payment
     *
     * @return false|TransactionInterface
     */
    protected function getOrderTransaction($payment)
    {
        return $this->transactionRepository->getByTransactionType(
            Transaction::TYPE_ORDER,
            $payment->getId(),
            $payment->getOrder()->getId()
        );
    }

}
