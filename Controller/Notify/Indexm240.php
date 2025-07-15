<?php
/*
 * Copyright (c) 2020 PayOut (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * Magento v2.3.0+ implement CsrfAwareActionInterface but not earlier versions
 */

namespace Payout\Payment\Controller\Notify;

use Exception;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use Payout\Payment\Controller\AbstractPayout;
use Payout\Payment\Model\Config;

class Indexm240 extends AbstractPayout implements CsrfAwareActionInterface
{
    /**
     * @var PageFactory
     */
    protected PageFactory $resultPageFactory;

    /**
     * Execute
     */
    public function execute(): \Magento\Framework\View\Result\Page
    {
        $notification = file_get_contents('php://input');
        //$post = json_encode($_POST);
        //$get = json_encode($_GET);
        $pre = __METHOD__ . " : ";
        //$this->_payoutlogger->info('I did Indexm230');
        // $this->_payoutlogger->info($post);
        $this->_payoutlogger->info($notification);
        $this->_logger->error($pre . "Logger notify from background123");


        $page_object = $this->pageFactory->create();

        try {
            $this->_initCheckout();
        } catch (LocalizedException $e) {
            $this->_logger->error($pre . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
            $this->_redirect('checkout/cart');
        } catch (Exception $e) {
            $this->_logger->error($pre . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, __('We can\'t start PayOut Checkout.'));
            $this->_redirect('checkout/cart');
        }

        return $page_object;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        // TODO: Implement createCsrfValidationException() method.
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

}
