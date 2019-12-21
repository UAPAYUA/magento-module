<?php
namespace UaPayPayment\UaPay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;

use UaPayPayment\UaPay\Model\UaPay;

class OrderStatusAfterCapture implements ObserverInterface
{
	public function __construct(UaPay $modelUaPay)
	{
		$this->modelUaPay = $modelUaPay;
	}

	public function execute(Observer $observer)
	{
		$invoice = $observer->getInvoice();
		if($invoice instanceof Order\Invoice) {
			$order = $invoice->getOrder();
			if($order->getPayment()->getMethod() == UaPay::CODE) {
//				if ($order->getState() != Order::STATE_COMPLETE || $order->getState() != Order::STATE_COMPLETE) {
//					$order->setState(Order::STATE_COMPLETE);
//					$order->setStatus(Order::STATE_COMPLETE);
//					$order->save();
//				}
			}
		}
	}
}