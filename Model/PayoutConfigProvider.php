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
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\Method\AbstractMethod;
use Payout\Payment\Helper\Data as PayoutHelper;
use Psr\Log\LoggerInterface;

class PayoutConfigProvider implements ConfigProviderInterface
{
    /**
     * @var ResolverInterface
     */
    protected ResolverInterface $localeResolver;

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var CurrentCustomer
     */
    protected CurrentCustomer $currentCustomer;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $_logger;

    /**
     * @var string[]
     */
    protected array $methodCodes = [
        Config::METHOD_CODE,
    ];

    /**
     * @var AbstractMethod[]
     */
    protected array $methods = [];

    /**
     * @var PayoutHelper
     */
    protected PayoutHelper $payoutHelper;

    /**
     * @var PaymentHelper
     */
    protected PaymentHelper $paymentHelper;

    /**
     * @var Repository
     */
    protected Repository $assetRepo;

    /**
     * @var UrlInterface
     */
    protected UrlInterface $urlBuilder;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    /**
     * @param LoggerInterface $logger
     * @param ConfigFactory $configFactory
     * @param ResolverInterface $localeResolver
     * @param CurrentCustomer $currentCustomer
     * @param PaymentHelper $paymentHelper
     * @param PayoutHelper $payoutHelper
     * @param Repository $assetRepo
     * @param UrlInterface $urlBuilder
     * @param RequestInterface $request
     * @throws LocalizedException
     */
    public function __construct(
        LoggerInterface   $logger,
        ConfigFactory     $configFactory,
        ResolverInterface $localeResolver,
        CurrentCustomer   $currentCustomer,
        PaymentHelper     $paymentHelper,
        PayoutHelper      $payoutHelper,
        Repository        $assetRepo,
        UrlInterface      $urlBuilder,
        RequestInterface  $request
    )
    {
        $this->_logger = $logger;
        $pre = __METHOD__ . ' : ';
        $this->_logger->debug($pre . 'bof');

        $this->localeResolver = $localeResolver;
        $this->config = $configFactory->create();
        $this->currentCustomer = $currentCustomer;
        $this->paymentHelper = $paymentHelper;
        $this->assetRepo = $assetRepo;
        $this->urlBuilder = $urlBuilder;
        $this->request = $request;
        $this->payoutHelper = $payoutHelper;

        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $this->paymentHelper->getMethodInstance($code);
        }

        $this->_logger->debug($pre . 'eof and this  methods has : ', $this->methods);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(): array
    {
        $pre = __METHOD__ . ' : ';


        $this->_logger->debug($pre . 'bof');
        $config = [
            'payment' => [
                'payout' => [
                    'paymentAcceptanceMarkSrc' => $this->config->getPaymentMarkImageUrl(),
                    'paymentAcceptanceMarkHref' => $this->config->getPaymentMarkWhatIsPayout()
                ],
            ],
        ];

        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment']['payout']['redirectUrl'][$code] = $this->getMethodRedirectUrl($code);
            }
        }
        $this->_logger->debug($pre . 'eof', $config);

        return $config;
    }

    /**
     * Retrieve url of a view file
     *
     * @param string $fileId
     * @param array $params
     *
     * @return string
     */
    public function getViewFileUrl(string $fileId, array $params = []): string
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
     *
     * @return mixed
     */
    protected function getMethodRedirectUrl(string $code): mixed
    {
        $pre = __METHOD__ . ' : ';
        $this->_logger->debug($pre . 'bof');

        $method = $this->methods[$code];
        if ($method instanceof Payout) {
            $methodUrl = $method->getCheckoutRedirectUrl();
        } else {

            $methodUrl = "";
        }

        $this->_logger->debug($pre . 'eof');

        return $methodUrl;
    }
}
