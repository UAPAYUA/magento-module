<?php
namespace UaPayPayment\UaPay\Block\Widget\Button;

use Magento\Backend\Block\Widget\Button\Toolbar as ToolbarContext;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Backend\Block\Widget\Button\ButtonList;
use UaPayPayment\UaPay\Model as UaPayPayment;
use Magento\Sales\Model\Order;

class Toolbar
{
	/**
	 * @param ToolbarContext $toolbar
	 * @param AbstractBlock $context
	 * @param ButtonList $buttonList
	 * @return array
	 */
	public function beforePushButtons(
		ToolbarContext $toolbar,
		\Magento\Framework\View\Element\AbstractBlock $context,
		\Magento\Backend\Block\Widget\Button\ButtonList $buttonList
	) {
		if (!$context instanceof \Magento\Sales\Block\Adminhtml\Order\View) {
			return [$context, $buttonList];
		}

		$order = $context->getOrder();
		$orderStatus = $order->getStatus();
		$paymentMethod = $order->getPayment();

		if ($paymentMethod->getMethod() == UaPayPayment\UaPay::CODE) {
			if($orderStatus == Order::STATE_PENDING_PAYMENT) {
				$buttonList->remove('order_invoice');
			}
			if($orderStatus == Order::STATE_PENDING_PAYMENT || $orderStatus == Order::STATE_PROCESSING) {
				$buttonList->remove('order_hold');
				$buttonList->remove('order_cancel');
			}
		}

		return [$context, $buttonList];
	}
}