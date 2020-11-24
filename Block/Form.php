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
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Payout\Payment\Model\Config;
use Payout\Payment\Model\Payout\Checkout;

class Form extends \Magento\Payment\Block\Form
{
    /**
     * @var string Payment method code
     */
    protected $_methodCode = Config::METHOD_CODE;

    /**
     * @var \Payout\Payment\Helper\Data
     */
    protected $_payoutData;

    /**
     * @var \Payout\Payment\Model\ConfigFactory
     */
    protected $payoutConfigFactory;

    /**
     * @var ResolverInterface
     */
    protected $_localeResolver;

    /**
     * @var \Payout\Payment\Model\Config
     */
    protected $_config;

    /**
     * @var bool
     */
    protected $_isScopePrivate;

    /**
     * @var CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @param Context $context
     * @param \Payout\Payment\Model\ConfigFactory $payoutConfigFactory
     * @param ResolverInterface $localeResolver
     * @param \Payout\Payment\Helper\Data $payoutData
     * @param CurrentCustomer $currentCustomer
     * @param array $data
     */
    public function __construct(
        Context $context,
        \Payout\Payment\Model\ConfigFactory $payoutConfigFactory,
        ResolverInterface $localeResolver,
        \Payout\Payment\Helper\Data $payoutData,
        CurrentCustomer $currentCustomer,
        array $data = []
    ) {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );
        $this->_payoutData         = $payoutData;
        $this->payoutConfigFactory = $payoutConfigFactory;
        $this->_localeResolver      = $localeResolver;
        $this->_config              = null;
        $this->_isScopePrivate      = true;
        $this->currentCustomer      = $currentCustomer;
        parent::__construct( $context, $data );
        $this->_logger->debug( $pre . "eof" );
    }

    /**
     * Set template and redirect message
     *
     * @return null
     */
    protected function _construct()
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );
        $this->_config = $this->payoutConfigFactory->create()->setMethod( $this->getMethodCode() );
        parent::_construct();
    }

    /**
     * Payment method code getter
     *
     * @return string
     */
    public function getMethodCode()
    {
        $pre = __METHOD__ . " : ";
        $this->_logger->debug( $pre . 'bof' );

        return $this->_methodCode;
    }

}
