<?php
/*
 * Copyright (c) 2020 PayOut (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Controller\Notify;

use Payout\Payment\Controller\AbstractPayout;

class Indexm220 extends AbstractPayout
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * Config method type
     *
     * @var string
     */
    protected $_configMethod = \Payout\Payment\Model\Config::METHOD_CODE;

    /**
     * Execute
     */
    public function execute()
    {
		$post = json_encode($_POST);
		$get = json_encode($_GET);
		$pre = __METHOD__ . " : ";
		 $this->_payoutlogger->info('I did Indexm230');
		 $this->_payoutlogger->info($post);
		 $this->_payoutlogger->info($get);
		$this->_logger->error( $pre . "Logger notify from background" );
		die('Indexm220');
        $pre = __METHOD__ . " : ";

        $page_object = $this->pageFactory->create();

        try {
            $this->_initCheckout();
        } catch ( \Magento\Framework\Exception\LocalizedException $e ) {
            $this->_logger->error( $pre . $e->getMessage() );
            $this->messageManager->addExceptionMessage( $e, $e->getMessage() );
            $this->_redirect( 'checkout/cart' );
        } catch ( \Exception $e ) {
            $this->_logger->error( $pre . $e->getMessage() );
            $this->messageManager->addExceptionMessage( $e, __( 'We can\'t start PayOut Checkout.' ) );
            $this->_redirect( 'checkout/cart' );
        }

        return $page_object;
    }

}
