<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 * 
 * Released under the GNU General Public License
 */
namespace Payout\Payment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Locale\ResolverInterface;
use Payout\Payment\Helper\Data as PayoutHelper;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\RequestInterface;

class PayoutConfigProvider implements ConfigProviderInterface
{
    /**
     * @var ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var \Magento\Customer\Helper\Session\CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var string[]
     */
    protected $methodCodes = [
        Config::METHOD_CODE,
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];
	
	/**
     * @var PaygateHelper
     */
    protected $payoutHelper;
	
    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;
	
	/**
     * @var Repository
     */
    protected $assetRepo;
	
	/**
     * @var UrlInterface
     */
    protected $urlBuilder;
	
	/**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param ConfigFactory $configFactory
     * @param ResolverInterface $localeResolver
     * @param CurrentCustomer $currentCustomer
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        ConfigFactory $configFactory,
        ResolverInterface $localeResolver,
        CurrentCustomer $currentCustomer,
        PaymentHelper $paymentHelper,
		PayoutHelper $payoutHelper,
		Repository $assetRepo,
		UrlInterface $urlBuilder,
        RequestInterface $request
    ) {
        $this->_logger = $logger;
        $pre           = __METHOD__ . ' : ';
        $this->_logger->debug( $pre . 'bof' );

        $this->localeResolver  = $localeResolver;
        $this->config          = $configFactory->create();
        $this->currentCustomer = $currentCustomer;
        $this->paymentHelper   = $paymentHelper;
		$this->assetRepo = $assetRepo;
		$this->urlBuilder = $urlBuilder;
		$this->request = $request;
		$this->payoutHelper   = $payoutHelper;

        foreach ( $this->methodCodes as $code ) {
            $this->methods[$code] = $this->paymentHelper->getMethodInstance( $code );
        }

        $this->_logger->debug( $pre . 'eof and this  methods has : ', $this->methods );
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
		$pre = __METHOD__ . ' : ';
		
       
        $this->_logger->debug( $pre . 'bof' );
        $config = [
            'payment' => [
                'payout' => [
                    'paymentAcceptanceMarkSrc'  => $this->config->getPaymentMarkImageUrl(),
                    'paymentAcceptanceMarkHref' => $this->config->getPaymentMarkWhatIsPayout()
                ],
            ],
        ];

        foreach ( $this->methodCodes as $code ) {
            if ( $this->methods[$code]->isAvailable() ) {
                $config['payment']['payout']['redirectUrl'][$code]          = $this->getMethodRedirectUrl( $code );
                $config['payment']['payout']['billingAgreementCode'][$code] = $this->getBillingAgreementCode( $code );

            }
        }
        $this->_logger->debug( $pre . 'eof', $config );
        return $config;
    }
	
	/**
     * Retrieve url of a view file
     *
     * @param string $fileId
     * @param array $params
     * @return string
     */
    public function getViewFileUrl($fileId, array $params = [])
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);
            return $this->assetRepo->getUrlWithParams($fileId, $params);
        } catch (LocalizedException $e) {
            $this->_logger->critical($e);
            return $this->urlBuilder->getUrl('', ['_direct' => 'core/index/notFound']);
        }
    }
	
    /**
     * Return redirect URL for method
     *
     * @param string $code
     * @return mixed
     */
    protected function getMethodRedirectUrl( $code )
    {
        $pre = __METHOD__ . ' : ';
        $this->_logger->debug( $pre . 'bof' );

        $methodUrl = $this->methods[$code]->getCheckoutRedirectUrl();

        $this->_logger->debug( $pre . 'eof' );
        return $methodUrl;
    }

    /**
     * Return billing agreement code for method
     *
     * @param string $code
     * @return null|string
     */
    protected function getBillingAgreementCode( $code )
    {

        $pre = __METHOD__ . ' : ';
        $this->_logger->debug( $pre . 'bof' );

        $customerId = $this->currentCustomer->getCustomerId();
        $this->config->setMethod( $code );

        $this->_logger->debug( $pre . 'eof' );

        // Always return null
        return $this->payoutHelper->shouldAskToCreateBillingAgreement( $this->config, $customerId );
    }
}
