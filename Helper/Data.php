<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Helper;

use Magento\Framework\App\Config\BaseFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use Payout\Payment\Model\ConfigFactory;
use Psr\Log\LoggerInterface;

/**
 * Payout Data helper
 */
class Data extends AbstractHelper
{
    /**
     * Cache for shouldAskToCreateBillingAgreement()
     *
     * @var bool
     */
    protected static bool $_shouldAskToCreateBillingAgreement = false;

    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected \Magento\Payment\Helper\Data $_paymentData;
    /**
     * @var LoggerInterface
     */
    protected $_logger;
    /**
     * @var array
     */
    private array $methodCodes;
    /**
     * @var ConfigFactory|BaseFactory
     */
    private ConfigFactory|BaseFactory $configFactory;

    /**
     * @param Context $context
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param BaseFactory $configFactory
     * @param array $methodCodes
     */
    public function __construct(
        Context $context,
        \Magento\Payment\Helper\Data $paymentData,
        BaseFactory $configFactory,
        array $methodCodes
    ) {
        $this->_logger = $context->getLogger();

        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof, methodCodes is : ', $methodCodes);

        $this->_paymentData  = $paymentData;
        $this->methodCodes   = $methodCodes;
        $this->configFactory = $configFactory;

        parent::__construct($context);
        $this->_logger->debug($pre . 'eof');
    }

    /**
     * Check whether customer should be asked confirmation whether to sign a billing agreement
     * should always return false.
     *
     * @return bool
     */
    public function shouldAskToCreateBillingAgreement(): bool
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . "bof");
        $this->_logger->debug($pre . "eof");

        return self::$_shouldAskToCreateBillingAgreement;
    }

    /**
     * Retrieve available billing agreement methods
     *
     * @param bool|int|string|Store|null $store
     * @param Quote|null $quote
     *
     * @return MethodInterface[]
     */
    public function getBillingAgreementMethods(Store|bool|int|string $store = null, Quote|null $quote = null): array
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
        $result = [];
        foreach ($this->_paymentData->getStoreMethods($store, $quote) as $method) {
            if ($method instanceof MethodInterface) {
                $result[] = $method;
            }
        }
        $this->_logger->debug($pre . 'eof | result : ', $result);

        return $result;
    }

}
