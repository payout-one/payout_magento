<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 * 
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Block\Payment;

class Request extends \Magento\Framework\View\Element\Template
{

    /** 
     * @var \Payout\Payment\Model\Payout $_paymentMethod
     */
    protected $_paymentMethod;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /** 
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory  $readFactory
     */
    protected $readFactory;

    /**
     * @var \Magento\Framework\Module\Dir\Reader $reader
     */
    protected $reader;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory
     * @param \Magento\Framework\Module\Dir\Reader $reader
     * @param \Payout\Payment\Model\Payout $paymentMethod
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory,
        \Magento\Framework\Module\Dir\Reader $reader,
        \Payout\Payment\Model\Payout $paymentMethod,
        array $data = []
    ) {
        $this->_orderFactory    = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct( $context, $data );
        $this->_isScopePrivate = true;
        $this->readFactory     = $readFactory;
        $this->reader          = $reader;
        $this->_paymentMethod  = $paymentMethod;
    }

    public function _prepareLayout()
    {
        $this->setMessage( 'Redirecting to Payout' )
            ->setId( 'payout_checkout' )
            ->setName( 'payout_checkout' )
            ->setFormMethod( 'POST' )
            ->setFormAction( 'https://payout.one/payment/redirect' )
            ->setFormData( $this->_paymentMethod->getStandardCheckoutFormFields() )
            ->setSubmitForm( '<script type="text/javascript">document.getElementById( "payout_checkout" ).submit();</script>' );

        return parent::_prepareLayout(); // TODO: Change the autogenerated stub
    }

}
