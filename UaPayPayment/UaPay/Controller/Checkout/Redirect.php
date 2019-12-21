<?php
namespace UaPayPayment\UaPay\Controller\Checkout;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\App\Request\Http;
use \Magento\Checkout\Model\Session;
use \Magento\Sales\Model\OrderFactory;
use \Magento\Framework\View\Result\PageFactory;
use \UaPayPayment\UaPay\Model\UaPay;

class Redirect extends Action
{
    protected $_pageFactory;

    protected $_context;
    protected $_request;
    protected $_checkoutSession;
    protected $_orderFactory;

    protected $_paymentModel;

    public function __construct(
        Context $context,
        Http $request,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        PageFactory $pageFactory,
        UaPay $paymentModel
    ){
        $this->_context = $context;
        $this->_request = $request;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->_pageFactory = $pageFactory;
        $this->_paymentModel = $paymentModel;

        parent::__construct($context);
    }

    public function execute()
    {
        return $this->_pageFactory->create();
    }

    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    protected function getOrderFactory()
    {
        return $this->_orderFactory;
    }

    protected function getContext()
    {
        return $this->_context;
    }
}