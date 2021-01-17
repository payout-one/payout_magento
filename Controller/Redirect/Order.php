<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */
namespace Payout\Payment\Controller\Redirect;

use Magento\Framework\Controller\ResultFactory;
use Payout\Payment\Api\Client as PayoutClient;

class Order extends \Payout\Payment\Controller\AbstractPayout
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
		$params = $this->getRequest()->getParams();
		
		$orderId = $params['gid'];
		$order = $this->getOrderByIncrementId($orderId);

		$page_object = $this->pageFactory->create();
		$order = $this->getOrderByIncrementId($orderId);
		if($order->getStatus() == "Pending Payment"){
			$this->_redirect( 'checkout/onepage/failure' );
		}else{
			$this->_redirect( 'checkout/onepage/success' );
		}

        return $page_object;
    }
	
	public function getOrder( $id )
    {
        return $this->orderRepository->get( $id );
    }
	
	public function getOrderByIncrementId($incrementId){
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$order = $objectManager->get('\Magento\Sales\Model\Order')->loadByIncrementId($incrementId);
		return $order;
	}
    
}
