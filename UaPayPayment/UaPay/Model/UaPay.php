<?php
namespace UaPayPayment\UaPay\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;

use Magento\Sales\Model\Order\Payment\Transaction;

use UaPayPayment\UaPay\Model\Api\Payment;
use UaPayPayment\UaPay\Model\Api\ResponseObject;


/**
 * Pay In Store payment method model
 */
class UaPay extends AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    const CODE = 'uapay';
    protected $_code = self::CODE;

    protected $_isGateway               = true;

    protected $_canAuthorize            = true;

    protected $_canCapture              = true;
    protected $_canCaptureOnce          = true;

    protected $_canVoid                 = true;

    protected $_canCancelInvoice        = true;

    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;

    protected $_transactionBuilder;
    protected $_invoiceService;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        Transaction\BuilderInterface $builderInterface,
        InvoiceService $invoiceService,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_context = $context;
        $this->_transactionBuilder = $builderInterface;
        $this->_invoiceService = $invoiceService;

        $messageManager = $this->getObjectManager()->create('Magento\Framework\App\Action\Context');
        $this->messageManager = $messageManager->getMessageManager();

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    public function createPayment(Order $order)
    {
        $uaPay = $this->getApiUapay();

        $uaPay->setDataOrderId($order->getIncrementId());
        $uaPay->setDataAmount($order->getGrandTotal());
        $uaPay->setDataDescription("Order {$order->getIncrementId()}");
//        $uaPay->setDataEmail($order->getCustomerEmail());
        $uaPay->setDataReusability(0);

        $redirectUrl = $this->getConfigCustomRedirectUrl();
        if(empty($redirectUrl)) {
            if ($order->getCustomerId())
                $redirectUrl = $this->getBaseUrl() . "sales/order/view/order_id/{$order->getId()}/";
            else
                $redirectUrl = $this->getBaseUrl() . 'checkout/onepage/success/';
        }
        $uaPay->setDataRedirectUrl($redirectUrl);
        $uaPay->setDataCallbackUrl($this->getUrlCallback($order->getIncrementId()));

        $result = $uaPay->createInvoice($this->getConfigTypeOperation());

        if(!empty($result['paymentPageUrl'])) {
            $paymentMethod = $order->getPayment();
            $paymentMethod->setLastTransId($result['id']);
            $paymentMethod->save();
            $order->setStatus(Order::STATE_PENDING_PAYMENT);
            $order->setState(Order::STATE_PENDING_PAYMENT);
            $order->save();
            return [ 'redirect' => $result['paymentPageUrl'] ];
        }
        else
            return [ 'response' => $uaPay->messageError ];
    }

    public function callback($response, OrderFactory $orderFactory)
    {
        $data = '';
        $token = '';
//        $data = file_get_contents('php://input');
//        if(!empty($data)) {
//            $ua = $this->getApiUapay();
//            $token = $ua->parseSign(json_decode($data, 1)['token']);
//        }
        Payment::writeLog([
            '$_GET' => $_GET,
            'input' => $data,
            '$token' => $token,
        ], '', 'callback');

        if(empty($response['orderId'])){
            die('Bad Request!!! LoL');
        }

        $orderId = $response['orderId'];

        $order = $orderFactory->create()->loadByIncrementId($orderId);

        if(empty($order->getId())){
            die('Bad Request!!! LoL');
        } else {
            if($order->getStatus() == Order::STATE_PROCESSING || $order->getStatus() == Order::STATE_COMPLETE){
                die('Error! Order Confirmed!');
            }
        }

        $paymentMethod = $order->getPayment();

        if($paymentMethod->getMethod() == self::CODE) {
            $invoiceId = $paymentMethod->getLastTransId();
            if(strpos($invoiceId, 'uapay') !== false){
                Payment::writeLog($invoiceId, 'Bad Request invoiceId', 'callback');
                die('Bad Request!!!');
            }

            $uaPay = $this->getApiUapay();
            $invoiceData = $uaPay->getDataInvoice($invoiceId);

            $payment = $invoiceData['payments'][0];

            Payment::writeLog($invoiceData, 'invoiceData', 'callback');

            switch ($payment['paymentStatus']){
                case Payment::STATUS_FINISHED:

                    $amountPayment = $payment['amount'];
                    $amountOrder = $order->getGrandTotal();

                    $transactionId = $this->generateTransId($orderId);
                    $paymentMethod->setLastTransId($transactionId);
                    $paymentMethod->setAmountAuthorized($amountOrder);
                    $paymentMethod->setIsTransactionClosed(1);
                    $paymentMethod->setAdditionalInformation([
                        Transaction::RAW_DETAILS => $payment,
                        'invoiceData' => $invoiceData
                    ]);

                    $transaction = $this->_transactionBuilder->setPayment($paymentMethod)
                        ->setOrder($order)
                        ->setTransactionId($transactionId)
                        ->setAdditionalInformation([
                            Transaction::RAW_DETAILS => $payment,
                            'invoiceData' => $invoiceData
                        ])
                        ->setFailSafe(true)
                        ->build(Transaction::TYPE_CAPTURE);

                    $message = __('The Captured amount is %1', $amountOrder);
                    $paymentMethod->addTransactionCommentsToOrder($transaction, $message);
                    $paymentMethod->setSkipOrderProcessing(true);
                    $paymentMethod->setParentTransactionId(null);

                    $paymentMethod->save();
                    $transaction->save();

                    $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                    $order->save();

                    // maybe trouble, run event for check status order before save
                    //file vendor/magento/module-sales/Model/ResourceModel/Order/Handler/State.php
                    // class Magento\Sales\Model\ResourceModel\Order\Handler\State

                    if($order->canInvoice()) {
                        $invoice = $this->_invoiceService->prepareInvoice($order);
                        $invoice->setTransactionId($paymentMethod->getTransactionId())
                            ->setRequestedCaptureCase(Order\Invoice::CAPTURE_ONLINE);
                        $invoice->setGrandTotal($amountOrder);
                        $invoice->setBaseGrandTotal($amountOrder);
                        $invoice->register();
                        $invoice->save();

                        // Save the invoice to the order
                        $order->addCommentToStatusHistory(__('Created Invoice #%1.', $invoice->getId()))
                            ->setIsCustomerNotified(true);

                        $order->setState(Order::STATE_COMPLETE)->setStatus(Order::STATE_COMPLETE);
                        $order->save();
                    }
                    break;
                case Payment::STATUS_HOLDED;
                    if ($payment['status'] == 'PAID') {
                        if($order->canInvoice()) {
                            $paymentMethod->setAdditionalInformation([
                                Transaction::RAW_DETAILS => $payment,
                                'original_amount_order' => $order->getGrandTotal()
                            ]);
                            $paymentMethod->setAmountAuthorized($order->getGrandTotal());
                            $paymentMethod->setIsTransactionClosed(0);

                            $transactionId = $this->generateTransId($orderId);
                            $transaction = $this->_transactionBuilder->setPayment($paymentMethod)
                                ->setOrder($order)
                                ->setTransactionId($transactionId)
                                ->setAdditionalInformation([
                                    Transaction::RAW_DETAILS => $payment,
                                    'invoiceData' => $invoiceData
                                ])
                                ->addAdditionalInformation('original_amount_order', $order->getGrandTotal())
                                ->setFailSafe(true)
                                ->build(Transaction::TYPE_AUTH);

                            $formatedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());
                            // Add transaction to payment
                            $paymentMethod->addTransactionCommentsToOrder($transaction, __('On hold amount is %1.', $formatedPrice));
                            $paymentMethod->setLastTransId($transactionId);
                            // Save payment, transaction
                            $paymentMethod->save();
                            $transaction->save();


                            $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);

                            $order->save();
                        }
                    }
                    break;
                case Payment::STATUS_CANCELED;
                case Payment::STATUS_REJECTED;

                    $order->addCommentToStatusHistory(__('UAPAY: Order not paid'));
                    $order->setStatus(Order::STATE_CANCELED);
                    $order->setState(Order::STATE_CANCELED);
                    $order->save();

                    break;
            }

            if ($invoiceData === false) {
                $order->addCommentToStatusHistory(__('UAPAY Error: ') . $uaPay->messageError);
                $order->save();
            }
        }
    }

    public function capture(InfoInterface $payment, $amount)
    {
        //after this method run event event sales_order_invoice_pay $invoice

        if ($amount <= 0) {
            $message = __('Invalid amount for capture %1.', $amount);
            $this->setToMessageManagerError($message);
            throw new \Exception($message);
        }

        if($payment->getMethod() == self::CODE) {
            $dataTransaction = $payment->getAdditionalInformation(Transaction::RAW_DETAILS);

            $transaction = $this->getObjTransaction($payment->getLastTransId());

            if ($transaction->getTxnType() == Transaction::TYPE_AUTH) {
                if (!empty($dataTransaction['paymentId'])) {
                    $uaPay = $this->getApiUaPay();

                    $uaPay->setInvoiceId($dataTransaction['invoiceId']);
                    $uaPay->setPaymentId($dataTransaction['paymentId']);

                    $result = $uaPay->confirmPayment();

                    Payment::writeLog($result, '', 'capture_after');

                    if ($result === false) {
                        $message = !empty($uaPay->messageShotError) ? $uaPay->messageShotError : $uaPay->messageError;
                        $this->setToMessageManagerError(__('UAPAY Error: ') . $message);
                        throw new \Exception();
                    }

                    if (empty($result['status'])) {
                        $message = __('Request capture was failed!!!');

                        $order = $payment->getOrder();
                        $order->addCommentToStatusHistory($message);
                        $order->save();

                        $payment->setAdditionalInformation([
                            Transaction::RAW_DETAILS => $dataTransaction,
                            'cancel' => $result
                        ]);
                        $payment->save();

                        $this->setToMessageManagerError($message);
                        throw new \Exception();
                    } else {
                        $dataTransaction = array_merge($dataTransaction, ['capture' => $result]);
                        $payment->setAdditionalInformation([
                            Transaction::RAW_DETAILS => $dataTransaction,
                            'capture' => $result
                        ]);
                        $payment->save();
                        $order = $payment->getOrder();
                        $order->setStatus(Order::STATE_COMPLETE);
                        $order->setState(Order::STATE_COMPLETE);
                        $order->save();
                    }
                }
            }
        }

        return $this;
    }

    public function void(InfoInterface $payment)
    {
        //after this method run event sales_order_payment_void', ['payment' => $this, 'invoice' => $document]

        if($payment->getMethod() == self::CODE) {
            $dataTransaction = $payment->getAdditionalInformation(Transaction::RAW_DETAILS);

            if(!empty($dataTransaction['paymentId'])) {
                $uaPay = $this->getApiUaPay();

                $uaPay->setInvoiceId($dataTransaction['invoiceId']);
                $uaPay->setPaymentId($dataTransaction['paymentId']);

                $result = $uaPay->cancelPayment();

                Payment::writeLog($result, '', 'void_after');

                if ($result === false) {
                    $message = !empty($uaPay->messageShotError)? $uaPay->messageShotError : $uaPay->messageError;

                    $this->setToMessageManagerError(__('UAPAY Error: ') . $message);
                    throw new \Exception();
                }

                if (empty($result['status'])) {
                    $message = 'Request voiding was failed!!!';

                    $order = $payment->getOrder();
                    $order->addCommentToStatusHistory($message);
                    $order->save();

                    $payment->setAdditionalInformation([
                        Transaction::RAW_DETAILS => $dataTransaction,
                        'cancel' => $result
                    ]);
                    $payment->save();

                    $this->setToMessageManagerError($message);
                    throw new \Exception();
                } else {
                    $payment->setAdditionalInformation([
                        Transaction::RAW_DETAILS => $dataTransaction,
                        'cancel' => $result
                    ]);
                    $payment->save();
                }
            }
        }

        return $this;
    }

	/**
     * Refund specified amount for payment
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Exception
     */
    public function refund(InfoInterface $payment, $amount)
    {
        // sales_order_payment_refund ['payment' => $this, 'creditmemo' => $creditmemo]
        Payment::writeLog($amount, '$amount', 'refund');

        if($payment->getMethod() == self::CODE) {
            $transaction = $this->getObjTransaction($payment->getLastTransId());
            if($transaction->getTxnType() != Transaction::TYPE_CAPTURE){
                $message = __('Not found captured UAPAY Transaction!!!');
                throw new \Exception($message);
            }

            $dataTransaction = $payment->getAdditionalInformation(Transaction::RAW_DETAILS);

            if(!empty($dataTransaction['paymentId'])) {
                $uaPay = $this->getApiUaPay();

                $uaPay->setInvoiceId($dataTransaction['invoiceId']);
                $uaPay->setPaymentId($dataTransaction['paymentId']);

                if (Payment::formattedAmount($amount) != (int)$dataTransaction['amount']) {
                    $uaPay->setDataAmount($amount);
                }

                $result = $uaPay->refundPayment();

                Payment::writeLog($result, '', 'refund_after');

                if ($result === false) {
                    $msg = !empty($uaPay->messageShotError)? $uaPay->messageShotError : $uaPay->messageError;
                    throw new \Exception(__('UAPAY Error: ') . $msg);
                }

                if (empty($result['status'])) {
                    $message = __('Request refund was failed!!!');

                    $order = $payment->getOrder();
                    $order->addCommentToStatusHistory($message);
                    $order->save();

                    throw new \Exception($message);
                } else {
                    $payment->setAdditionalInformation([
                        Transaction::RAW_DETAILS => $dataTransaction,
                        'refund' => $result
                    ]);
                    $payment->save();

                    $order = $payment->getOrder();
                    $order->setStatus(Order::STATE_CANCELED);
                    $order->setState(Order::STATE_CANCELED);
                    $order->save();
                }
            } else {
                throw new \Exception(__('Error!!! Data payment invalid'));
            }

        }

        return $this;
    }

    protected function generateTransId($id)
    {
        return $id . '-' . self::CODE;
    }

    protected function createTransaction($transactionId, $type, $response, Order $order, $isClosed = true)
    {
        $transaction = $this->_transactionBuilder->setPayment($order->getPayment()->setIsTransactionClosed($isClosed))
            ->setOrder($order)
            ->setTransactionId($transactionId)
            ->setAdditionalInformation([Transaction::RAW_DETAILS => $response])
            ->setFailSafe(true)
            ->build($type);
        $transaction->save();
    }

    public function getObjTransaction($txn_id = null)
    {
        $transaction = $this->getObjectManager()->create(Transaction::class)
            ->load($txn_id, Transaction::TXN_ID);

        return ($transaction instanceof Transaction)? $transaction : null;
    }

    public function getInfoTransaction($txn_id = null)
    {
        return new ResponseObject(
            is_object($txn_id)? $txn_id->getAdditionalInformation(Transaction::RAW_DETAILS)
                : $this->getObjTransaction($txn_id)->getAdditionalInformation(Transaction::RAW_DETAILS)
        );
    }

    public function getApiUaPay()
    {
        return new Payment($this->getConfigData('clientId'), $this->getConfigData('secretKey'), $this->getConfigData('testMode'));
    }

    public function getConfigCustomRedirectUrl()
    {
       return $this->getConfigData('customRedirectUrl');
    }

    public function getConfigTypeOperation()
    {
       return $this->getConfigData('typeOperation');
    }

    public function getBaseUrl()
    {
        $storeManager = $this->getObjectManager()->get('\Magento\Store\Model\StoreManagerInterface');

        return $storeManager->getStore()->getBaseUrl();
    }

    public function getUrlCallback($orderId = '')
    {
        return $this->getBaseUrl() . 'uapay/callback?orderId=' . $orderId;
    }

    public function getObjectManager()
    {
        return \Magento\Framework\App\ObjectManager::getInstance();
    }

    public function getBackendUser()
    {
        return $this->getObjectManager()->create('\Magento\Backend\Model\Auth\Session')->getUser();
    }

    protected function getLogger()
    {
        return $this->getContext()->getLogger();
    }

    protected function getContext()
    {
        return $this->_context;
    }

    /**
     * Get Instance of Magento global Message Manager
     * @return \Magento\Framework\Message\ManagerInterface
     */
    protected function getMessageManager()
    {
        return $this->messageManager;
    }

    protected function createSuccessMessage($text = '')
    {
        return $this->getObjectManager()->create('Magento\Framework\Message\Success', ['text' => $text]);
    }

    protected function createErrorMessage($text = '')
    {
        return $this->getObjectManager()->create('Magento\Framework\Message\Error', ['text' => $text]);
    }

    protected function setToMessageManagerError($message)
    {
        $this->getMessageManager()->addMessage($this->createErrorMessage($message));
    }

    protected function setToMessageManagerSuccess($message)
    {
        $this->getMessageManager()->addMessage($this->createSuccessMessage($message));
    }
}
