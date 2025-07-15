<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Block;

use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Template\Context;
use Payout\Payment\Helper\Data;
use Payout\Payment\Model\Config;
use Payout\Payment\Model\ConfigFactory;

class Form extends \Magento\Payment\Block\Form
{
    /**
     * @var string Payment method code
     */
    protected string $_methodCode = Config::METHOD_CODE;

    /**
     * @var Data
     */
    protected Data $_payoutData;

    /**
     * @var ConfigFactory
     */
    protected ConfigFactory $payoutConfigFactory;

    /**
     * @var ResolverInterface
     */
    protected ResolverInterface $_localeResolver;

    /**
     * @var ?Config
     */
    protected ?Config $_config;

    /**
     * @var bool
     */
    protected $_isScopePrivate;

    /**
     * @var CurrentCustomer
     */
    protected CurrentCustomer $currentCustomer;

    /**
     * @param Context $context
     * @param ConfigFactory $payoutConfigFactory
     * @param ResolverInterface $localeResolver
     * @param Data $payoutData
     * @param CurrentCustomer $currentCustomer
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConfigFactory $payoutConfigFactory,
        ResolverInterface $localeResolver,
        Data $payoutData,
        CurrentCustomer $currentCustomer,
        array $data = []
    ) {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
        $this->_payoutData         = $payoutData;
        $this->payoutConfigFactory = $payoutConfigFactory;
        $this->_localeResolver     = $localeResolver;
        $this->_config             = null;
        $this->_isScopePrivate     = true;
        $this->currentCustomer     = $currentCustomer;
        parent::__construct($context, $data);
        $this->_logger->debug($pre . "eof");
    }

    /**
     * Payment method code getter
     *
     * @return string
     */
    public function getMethodCode(): string
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');

        return $this->_methodCode;
    }

    /**
     * Set template and redirect message
     *
     * @return void
     */
    protected function _construct(): void
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug($pre . 'bof');
        $this->_config = $this->payoutConfigFactory->create()->setMethod($this->getMethodCode());
        parent::_construct();
    }

}
