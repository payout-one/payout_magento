<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 * 
 * Released under the GNU General Public License
 */
namespace Payout\Payment\Model;

use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\Data\Form\FormKey;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Magento\Vault\Model\ResourceModel\PaymentToken as PaymentTokenResourceModel;

/* Payout Api */
use Payout\Payment\Api\Client as PayoutClient;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Payout extends \Magento\Payment\Model\Method\AbstractMethod
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
     * @var \Payout\Payment\Model\Config $config
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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_formKey;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Framework\Exception\LocalizedExceptionFactory
     */
    protected $_exception;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var Transaction\BuilderInterface
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
     * @var \Payout\Payment\Logger\Logger
     */
    protected $_payoutlogger;
	

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Payout\Payment\Model\ConfigFactory $configFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Data\Form\FormKey $formKey
     * @param \Payout\Payment\Model\CartFactory $cartFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Exception\LocalizedExceptionFactory $exception
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param Transaction\BuilderInterface $transactionBuilder
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct( \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        ConfigFactory $configFactory,
		\Payout\Payment\Logger\Logger $payoutlogger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Exception\LocalizedExceptionFactory $exception,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
		CreditCardTokenFactory $CreditCardTokenFactory,
		PaymentTokenRepositoryInterface $PaymentTokenRepositoryInterface,
		PaymentTokenManagementInterface $paymentTokenManagement,
        EncryptorInterface $encryptor,
		PaymentTokenResourceModel $paymentTokenResourceModel,
        array $data = [] ) {
        parent::__construct( $context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data );
		
        $this->_storeManager         = $storeManager;
        $this->_urlBuilder           = $urlBuilder;
        $this->_formKey              = $formKey;
        $this->_checkoutSession      = $checkoutSession;
        $this->_exception            = $exception;
        $this->transactionRepository = $transactionRepository;
        $this->transactionBuilder    = $transactionBuilder;
        $this->creditCardTokenFactory    = $CreditCardTokenFactory;
        $this->paymentTokenRepository    = $PaymentTokenRepositoryInterface;
		$this->paymentTokenManagement = $paymentTokenManagement;
        $this->encryptor = $encryptor;
		$this->paymentTokenResourceModel = $paymentTokenResourceModel;

        $parameters = ['params' => [$this->_code]];

        $this->_config = $configFactory->create( $parameters );
		$this->_payoutlogger = $payoutlogger;

    }

    /**
     * Store setter
     * Also updates store ID in config object
     *
     * @param \Magento\Store\Model\Store|int $store
     *
     * @return $this
     */
    public function setStore( $store )
    {
        $this->setData( 'store', $store );

        if ( null === $store ) {
            $store = $this->_storeManager->getStore()->getId();
        }
        $this->_config->setStoreId( is_object( $store ) ? $store->getId() : $store );

        return $this;
    }

    /**
     * Whether method is available for specified currency
     *
     * @param string $currencyCode
     *
     * @return bool
     */
    public function canUseForCurrency( $currencyCode )
    {
        return $this->_config->isCurrencyCodeSupported( $currencyCode );
    }

    /**
     * Payment action getter compatible with payment model
     *
     * @see \Magento\Sales\Model\Payment::place()
     * @return string
     */
    public function getConfigPaymentAction()
    {
        return $this->_config->getPaymentAction();
    }

    /**
     * Check whether payment method can be used
     *
     * @param \Magento\Quote\Api\Data\CartInterface|Quote|null $quote
     *
     * @return bool
     */
    public function isAvailable( \Magento\Quote\Api\Data\CartInterface $quote = null )
    {
        return parent::isAvailable( $quote ) && $this->_config->isMethodAvailable();
    }

    /**
     * @return mixed
     */
    protected function getStoreName()
    {

        $storeName = $this->_scopeConfig->getValue(
            'general/store_information/name',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $storeName;
    }

    /**
     * Place an order with authorization or capture action
     *
     * @param Payment $payment
     * @param float $amount
     *
     * @return $this
     */
    protected function _placeOrder( Payment $payment, $amount )
    {

        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );

    }
	
	public function getApiUrl(){
		return "https://sandbox.payout.one";
	}

    /**
     * This is where we compile data posted by the form to Payout
     * @return array
     */
    public function getStandardCheckoutFormFields()
    {
		$order = $this->_checkoutSession->getLastRealOrder();
        $pre = __METHOD__ . ' : ';
		
		$clientId = $this->getConfigData( 'payout_id' );
		$secret = $this->getConfigData( 'encryption_key' );
		
		$config = array(
			'client_id' => $clientId,
			'client_secret' => $secret,
			'sandbox' => true
		);

		$payout = new PayoutClient($config);
		
		$externalId = $order->getIncrementId();
		
		$checkout_data = array(
			'amount' => $order->getGrandTotal(),
			'currency' => "EUR",//$order->getOrderCurrencyCode(),
			'customer' => [
				'first_name' => $order->getCustomerFirstname(),
				'last_name' => $order->getCustomerLastname(),
				'email' =>  $order->getCustomerEmail()
			],
			'external_id' => $order->getIncrementId(),
			'redirect_url' => $this->_urlBuilder->getUrl( 'Payout/redirect/order', array( '_secure' => true ) ) . '?form_key=' . $this->_formKey->getFormKey().'&gid='.$order->getRealOrderId(),
		);
		
		
		$this->_payoutlogger->info("***********************Sending Order Data to Payout*************************");
		$this->_payoutlogger->info(json_encode($checkout_data));
		
		$response = $payout->createCheckout($checkout_data);
		
		$this->_payoutlogger->info("***********************Token Validation from Payout*************************");
		
		$this->_payoutlogger->info(json_encode($response));
		
		$checkoutUrl = $response->checkout_url;
		header("Location: $checkoutUrl"); 
		exit(0);
    }
	
	
    /**
     * getTotalAmount
     */
    public function getTotalAmount( $order )
    {
        if ( $this->getConfigData( 'use_store_currency' ) ) {
            $price = $this->getNumberFormat( $order->getGrandTotal() );
        } else {
            $price = $this->getNumberFormat( $order->getBaseGrandTotal() );
        }

        return $price;
    }

    /**
     * getNumberFormat
     */
    public function getNumberFormat( $number )
    {
        return number_format( $number, 2, '.', '' );
    }

    /**
     * getPaidSuccessUrl
     */
    public function getPaidSuccessUrl()
    {
        return $this->_urlBuilder->getUrl( 'Payout/redirect/success', array( '_secure' => true ) );
    }

    /**
     * Get transaction with type order
     *
     * @param OrderPaymentInterface $payment
     *
     * @return false|\Magento\Sales\Api\Data\TransactionInterface
     */
    protected function getOrderTransaction( $payment )
    {
        return $this->transactionRepository->getByTransactionType( Transaction::TYPE_ORDER, $payment->getId(), $payment->getOrder()->getId() );
    }

    /*
     * called dynamically by checkout's framework.
     */
    public function getOrderPlaceRedirectUrl()
    {
        return $this->_urlBuilder->getUrl( 'Payout/redirect' );

    }
    /**
     * Checkout redirect URL getter for onepage checkout (hardcode)
     *
     * @see \Magento\Checkout\Controller\Onepage::savePaymentAction()
     * @see Quote\Payment::getCheckoutRedirectUrl()
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return $this->_urlBuilder->getUrl( 'Payout/redirect' );
    }

    /**
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return $this
     */
    public function initialize( $paymentAction, $stateObject )
    {
        $stateObject->setState( \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT );
        $stateObject->setStatus( 'pending_payment' );
        $stateObject->setIsNotified( false );

        return parent::initialize( $paymentAction, $stateObject );

    }

    /**
     * getPaidNotifyUrl
     */
    public function getPaidNotifyUrl()
    {
        return $this->_urlBuilder->getUrl( 'Payout/notify', array( '_secure' => true ) );
    }

    public function curlPost( $url, $fields )
    {
        $curl = curl_init( $url );
        curl_setopt( $curl, CURLOPT_POST, count( $fields ) );
        curl_setopt( $curl, CURLOPT_POSTFIELDS, $fields );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
        $response = curl_exec( $curl );
        curl_close( $curl );
        return $response;
    }
	
}