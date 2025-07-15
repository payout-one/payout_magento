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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\LocalizedExceptionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Vault\Model\ResourceModel\PaymentToken as PaymentTokenResourceModel;
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
    protected CreditCardTokenFactory $creditCardTokenFactory;
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
     * @param CreditCardTokenFactory $CreditCardTokenFactory
     * @param PaymentTokenRepositoryInterface $PaymentTokenRepositoryInterface
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param EncryptorInterface $encryptor
     * @param PaymentTokenResourceModel $paymentTokenResourceModel
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context                              $context,
        Registry                             $registry,
        ExtensionAttributesFactory           $extensionFactory,
        AttributeValueFactory                $customAttributeFactory,
        Data                                 $paymentData,
        ScopeConfigInterface                 $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        ConfigFactory                        $configFactory,
        Logger                               $payoutlogger,
        StoreManagerInterface                $storeManager,
        UrlInterface                         $urlBuilder,
        FormKey                              $formKey,
        Session                              $checkoutSession,
        LocalizedExceptionFactory            $exception,
        TransactionRepositoryInterface       $transactionRepository,
        BuilderInterface                     $transactionBuilder,
        CreditCardTokenFactory               $CreditCardTokenFactory,
        PaymentTokenRepositoryInterface      $PaymentTokenRepositoryInterface,
        PaymentTokenManagementInterface      $paymentTokenManagement,
        EncryptorInterface                   $encryptor,
        PaymentTokenResourceModel            $paymentTokenResourceModel,
        AbstractResource                     $resource = null,
        AbstractDb                           $resourceCollection = null,
        array                                $data = []
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
        $this->creditCardTokenFactory = $CreditCardTokenFactory;
        $this->paymentTokenRepository = $PaymentTokenRepositoryInterface;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->encryptor = $encryptor;
        $this->paymentTokenResourceModel = $paymentTokenResourceModel;

        $parameters = ['params' => [$this->_code]];

        $this->_config = $configFactory->create($parameters);
        $this->_payoutlogger = $payoutlogger;
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
     * This is where we compile data posted by the form to Payout
     * @return string
     * @throws LocalizedException
     * @throws Exception
     */
    public function createCheckout(): string
    {
        $order = $this->_checkoutSession->getLastRealOrder();

        $clientId = $this->getConfigData('payout_id');
        $secret = $this->getConfigData('encryption_key');
        $testMode = $this->getConfigData('test_mode');

        $config = array(
            'client_id' => $clientId,
            'client_secret' => $secret,
            'sandbox' => (bool)$testMode
        );

        $payout = new PayoutClient($config);

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
            'idempotency_key' => $order->getPayment()->getEntityId(),
        );


        $this->_payoutlogger->info("***********************Sending Order Data to Payout*************************");
        $this->_payoutlogger->info(json_encode($checkout_data));

        $response = $payout->createCheckout($checkout_data);

        $orderPayment = $order->getPayment();
        if ($orderPayment instanceof Payment) {
            $orderPayment
                ->setLastTransId($response->id)
                ->setTransactionId($response->id)
                ->setAdditionalInformation(
                    [Transaction::RAW_DETAILS =>
                        [
                            'checkout_id' => $response->id,
                            'order_id' => $order->getId(),
                            'external_id' => $response->external_id,
                            'amount' => $response->amount,
                            'currency' => $response->currency,
                            'customer_email' => $response->customer->email,
                            'first_name' => $response->customer->first_name,
                            'last_name' => $response->customer->last_name,
                            'nonce' => $response->nonce,
                            'signature' => $response->signature,
                            'raw_data' => json_encode($response),
                            'source' => 'checkout_response',
                        ]
                    ]
                )
                ->save();
        }

        $this->_payoutlogger->info("***********************Token Validation from Payout*************************");

        $this->_payoutlogger->info(json_encode($response));

        return $response->checkout_url;
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
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return AbstractMethod
     */
    public function initialize($paymentAction, $stateObject): AbstractMethod
    {
        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
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
