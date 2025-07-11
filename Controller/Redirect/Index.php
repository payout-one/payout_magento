<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Controller\Redirect;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Payout\Payment\Controller\AbstractPayout;
use Payout\Payment\Model\Config;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Index extends AbstractPayout
{
    /**
     * @var PageFactory
     */
    protected PageFactory $resultPageFactory;

    /**
     * Config method type
     *
     * @var string
     */
//    protected string $_configMethod = Config::METHOD_CODE;

    /**
     * Execute
     */
    public function execute(): Page
    {
        $pre = __METHOD__ . " : ";

        $page_object = $this->pageFactory->create();

        try {
            $this->_initCheckout();
        } catch (LocalizedException $e) {
            $this->_logger->error($pre . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
            $this->_redirect('checkout/cart');
        } catch (Exception $e) {
            $this->_logger->error($pre . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, __('We can\'t start Payout Checkout.'));
            $this->_redirect('checkout/cart');
        }

        $block = $page_object->getLayout()
            ->getBlock('Payout')
            ->setPaymentFormData($order ?? null);

        return $page_object;
    }

}
