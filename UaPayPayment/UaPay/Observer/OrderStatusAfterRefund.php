<?php
namespace UaPayPayment\UaPay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;

use UaPayPayment\UaPay\Model\Api;
use UaPayPayment\UaPay\Model\UaPay;

class OrderStatusAfterRefund implements ObserverInterface
{
	public function __construct(UaPay $modelUaPay)
	{
		$this->modelUaPay = $modelUaPay;
	}

	public function execute(Observer $observer)
	{
		$payment = $observer->getPayment();
		if($payment instanceof Order\Payment && $payment->getMethod() == UaPay::CODE) {
			$order = $payment->getOrder();
			if($order->getState() != Order::STATE_CANCELED || $order->getStatus() != Order::STATE_CANCELED) {

				$order->setState(Order::STATE_CANCELED);
				$order->setStatus(Order::STATE_CANCELED);
				$order->save();
			}
		}
	}
}