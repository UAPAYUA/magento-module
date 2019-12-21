<?php
namespace UaPayPayment\UaPay\Controller\Callback;

use \Magento\Framework\App\CsrfAwareActionInterface;
use \Magento\Framework\App\Request\InvalidRequestException;
use \Magento\Framework\App\RequestInterface;
use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\App\Request\Http;
use \Magento\Sales\Model\OrderFactory;
use \UaPayPayment\UaPay\Model\UaPay;


class Index extends Action implements CsrfAwareActionInterface
{
	protected $_context;
	protected $_request;
	protected $_orderFactory;
	protected $_paymentModel;

	public function __construct(
		Context $context,
		Http $request,
		OrderFactory $orderFactory,
		UaPay $paymentModel
	)
	{
		$this->_context = $context;
		$this->_request = $request;
		$this->_orderFactory = $orderFactory;
		$this->_paymentModel = $paymentModel;

		parent::__construct($context);
	}

	public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

	public function validateForCsrf(RequestInterface $request): ?bool
    {
		return true;
	}

	public function execute()
	{
		$this->_paymentModel->callback($this->_request->getParams(), $this->_orderFactory);
	}

	protected function getOrderFactory()
	{
		return $this->_orderFactory;
	}

	protected function getContext()
	{
		return $this->_context;
	}

	protected function getObjectManager()
	{
		return $this->_objectManager;
	}
}