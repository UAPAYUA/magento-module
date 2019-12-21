<?php
namespace UaPayPayment\UaPay\Model\Api;

class Payment
{
	const wrLog = false;

	const METHOD_POST = 'POST';
	const METHOD_GET = 'GET';
	const METHOD_PUT = 'PUT';

	const SYSTYPE_P2P = 'P2P';
	const SYSTYPE_ECOM = 'ECOM';

	const OPERATION_PAY = 'PAY';
	const OPERATION_HOLD = 'HOLD';
	const OPERATION_SUBSCRIBE = 'SUBSCRIBE';

	const STATUS_FINISHED = 'FINISHED'; // 	Платіж завершено успішно, гроші відправлено одержувачу
	const STATUS_HOLDED = 'HOLDED'; 	// 	Необхідно підтвердження. Для завершення списання коштів потрібно виконати підтвердження.
	const STATUS_CANCELED = 'CANCELED'; // 	Процес оплати не завершений та платіж був відхилений (обірвалося з'єднання, платіж зупинений на проміжному етапі з вини платника).
	const STATUS_REVERSED = 'REVERSED'; // 	Платіж повернуто, кошти повернулися відправнику.
	const STATUS_REJECTED = 'REJECTED'; // 	Платіж не відбувся з технічних причин.
	const STATUS_NEED_CONFIRM = 'NEEDS_CONFIRMATION'; // 	Платіж очікує підтвердження (лукап або 3ds)
	const STATUS_PENDING = 'PENDING'; 	// 	Платіж знаходиться в стані оплати (проміжний статус)

	private $headers = [];
	private $payload = [];

	private $apiURL = 'https://api.uapay.ua/';
	private $apiTestURL = 'https://api.demo.uapay.ua/';

	private $clientId;
	private $secretKey;
	private $JWTEncoder;

	public $messageError = '';
	public $messageShotError = '';

	function __construct($clientId = null, $secretKey = null, $testMode = null)
	{
		$this->clientId = trim($clientId);
		$this->secretKey = trim($secretKey);

		$this->JWTEncoder = new Jweety\JWTEncoder($this->secretKey);
		if(!is_null($testMode)){
			$this->testMode($testMode);
		}
	}

	public function testMode($is = true)
	{
		if(boolval($is)){
			$this->apiURL = $this->apiTestURL;
		}

		return $this;
	}

	public function setLocale($locale = '')
	{
		$this->payload['params']['locale'] = strval(trim($locale));
	}

	public function setPaymentId($paymentId = '')
	{
		$this->payload['params']['paymentId'] = strval(trim($paymentId));
	}

	public function setInvoiceId($invoiceId = '')
	{
		$this->payload['params']['invoiceId'] = strval(trim($invoiceId));
	}

	public function setAmount($amount = 0)
	{
		$this->payload['params']['amount'] = self::formattedAmount($amount);
	}

	public function setParamSystemType($type = '')
	{
		if(in_array($type, [self::SYSTYPE_ECOM, self::SYSTYPE_P2P])){
			$this->payload['params']['systemType'] = $type;
		} else {
			$this->payload['params']['systemType'] = self::SYSTYPE_P2P;
		}
	}

	public function setDataTypeOperation($type = '')
	{
		if(in_array($type, [self::OPERATION_PAY, self::OPERATION_HOLD, self::OPERATION_SUBSCRIBE])) {
			$this->payload['data']['type'] = strval($type);
		} else {
			$this->payload['data']['type'] = strval(self::OPERATION_PAY);
		}
	}

	public function setDataInvoice($invoiceId = '')
	{
		$this->payload['data']['invoiceId'] = strval(trim($invoiceId));
	}

	public function setDataOrderId($id = 0)
	{
		$this->payload['data']['externalId'] = strval(trim($id));
	}

	public function setDataAmount($amount = 0)
	{
		$this->payload['data']['amount'] = self::formattedAmount($amount);
	}

	public function setDataDescription($description)
	{
		$this->payload['data']['description'] = strval(trim($description));
	}

	public function setDataEmail($email)
	{
		$this->payload['data']['email'] = strval(trim($email));
	}

	public function setDataRedirectUrl($redirectUrl)
	{
		$this->payload['data']['redirectUrl'] = strval(trim($redirectUrl));
	}
	public function setDataCallbackUrl($callbackUrl)
	{
		$this->payload['data']['callbackUrl'] = strval(trim($callbackUrl));
	}

	public function setDataReusability($isReusability = 1)
	{
		$this->payload['data']['reusability'] = boolval($isReusability);
	}

	public function setDataRecurringInterval($recurringInterval = 1)
	{
		$this->payload['data']['callbackrecurringInterval'] = intval($recurringInterval);
	}

	public function setDataExpiresAt($expiresAt = 1)
	{
		$this->payload['data']['expiresAt'] = intval($expiresAt);
	}

	public function setDataCardToId($cardToId)
	{
		$this->payload['data']['cardTo']['id'] = strval(trim($cardToId));
	}

	/**
	 * @param string $operation
	 * @return array|bool|mixed|object
	 */
	public function createInvoice($operation = self::OPERATION_PAY)
	{
		$session = $this->createSession();
		if($session === true){
			$this->setParamSystemType(self::SYSTYPE_ECOM);
			$this->setDataTypeOperation($operation);
			$result = $this->request('api/invoicer/invoices/create');

			return $result;
		}

		return $session;
	}
	/**
	 * @return array|bool|mixed|object
	 */
	public function createPayment()
	{
		$session = $this->createSession();
		if($session === true){
			$result = $this->request('api/invoicer/payments/create');

			return $result;
		}

		return $session;
	}

	public function getDataInvoice($id)
	{
		$session = $this->createSession();
		if($session === true) {
			$this->setParamId($id);
			$result = $this->request('api/invoicer/invoices/show');

			return $result;
		}

		return $session;
	}

	public function getDataPayment($id)
	{
		$session = $this->createSession();
		if($session === true) {
			$this->setPaymentId($id);
			$result = $this->request('api/invoicer/payments/show');

			return $result;
		}

		return $session;
	}

	/**
	 * method called after operation type HOLD
	 * @return array|bool|mixed|object|string
	 */
	public function confirmPayment()
	{
		$session = $this->createSession();
		if($session === true){
			$result = $this->request('api/invoicer/payments/complete');

			return $result;
		}

		return $session;
	}

	/**
	 * method called after HOLD
	 * @return array|bool|mixed|object
	 */
	public function cancelPayment()
	{
		$session = $this->createSession();
		if($session === true){
			$result = $this->request('api/invoicer/payments/cancel');

			return $result;
		}

		return $session;
	}

	/**
	 * method called after payment
	 * @return array|bool|mixed|object
	 */
	public function refundPayment()
	{
		$session = $this->createSession();
		if($session === true){
			$result = $this->request('api/invoicer/payments/reverse');

			return $result;
		}

		return $session;
	}

	public function createAuthCard($cardNumber = '', $expireAt = '')
	{
		$payload['params']['sessionId'] = $this->getParamSessionId();
		$payload['data']['pan'] = $cardNumber;
		$payload['data']['expiresAt'] = $expireAt;

		$result = $this->request('api/cards/create', $payload);
		if(!empty($result['status']) && !empty($result['data']['id'])) {
			return $result['data']['id'];
		}

		return false;
	}

	private function setParamSessionId($id = '')
	{
		$this->payload['params']['sessionId'] = strval(trim($id));
	}

	private function setParamId($id = '')
	{
		$this->payload['params']['id'] = strval(trim($id));
	}

	public function getParamSessionId()
	{
		return !empty($this->payload['params']['sessionId'])? $this->payload['params']['sessionId'] : false;
	}

	private function createSession()
	{
		if(!empty($this->getParamSessionId())){
			return true;
		}
		$payload['params']['clientId'] = $this->clientId;
		$session = $this->request('api/sessions/create', $payload);
		if(!empty($session['status']) && !empty($session['id'])) {
			$this->setParamSessionId($session['id']);

			return true;
		}

		return $session;
	}

	public static function formattedAmount($number = 0)
	{
		$amount = is_string($number)? str_replace(',', '.', $number) : $number;

		$amount = abs((int)(floatval($amount) * 100));

		return $amount;
	}

	private function formalizeErrorMessage($data = [])
	{
		$result = '';
		if(!empty($data['code']) || !empty($data['message'])){
			$msgType = !empty($data['code'])? ucfirst($data['code']) . ': ' : '';
			$msg = !empty($data['message'])?  [$msgType . $data['message']] : [];
			$this->messageShotError = !empty($data['message'])? $data['message'] : '';

			if(!empty($data['fields']['params'])) {
				$fields_params = $data['fields']['params'];
				foreach ($fields_params as $field => $value) {
					$msg[] = 'params.' . $field . ': ' . $value;
				}
			}
			if(!empty($data['fields']['data'])) {
				$fields_params = $data['fields']['data'];
				foreach ($fields_params as $field => $value) {
					$msg[] = 'data.' . $field . ': ' . $value;
				}
			}
			$result = implode(', ', $msg);
		}

		return $result;
	}

	public function getPaymentFromStatus($data = [], $status)
	{
		$payment = [];
		if(!empty($data)){
			foreach($data as $item){
				if($item['paymentStatus'] == $status){
					$payment = $item;
					break;
				}
			}
		}

		return $payment;
	}

	private function generateSign($params)
	{
		return $this->JWTEncoder->stringify($params);
	}

	public function parseSign($str)
	{
		return $this->JWTEncoder->parse($str);
	}

	public function resetParams()
	{
		$this->payload = [];
	}

	private function setHeader($header)
	{
		if(!in_array($header, $this->headers)) {
			$this->headers[] = $header;
		}
	}

	public function getHeaders()
	{
		return $this->headers;
	}

	/**
	 * @param $uri
	 * @param array $payload
	 * @param string $method
	 * @return array|bool|mixed|object
	 */
	private function request($uri, $payload = [], $method = self::METHOD_POST)
	{
		$url = $this->apiURL . $uri;

		$params = !empty($payload)? $payload : (!empty($this->payload)? $this->payload : []);

//		$fileReqError = __DIR__ . '/outputError.log';
//		$fileHeaders = __DIR__ . '/outputHeader.log';

		$this->setHeader('Content-Type: application/json');

		$params['iat'] = time();
		$params['token'] = $this->generateSign($params);

		$ch = curl_init();
		if($method === self::METHOD_POST) {
			$data = json_encode($params);

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		} elseif($method === self::METHOD_GET) {
			$data = !empty($params)? '?' . http_build_query($params) : '';

			curl_setopt($ch, CURLOPT_URL, $url . $data);
		} else {
			curl_setopt($ch, CURLOPT_URL, $url);
		}

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());

//		curl_setopt($ch, CURLOPT_VERBOSE, true);
//		curl_setopt($ch, CURLOPT_STDERR, fopen($fileReqError, 'w+'));
//		curl_setopt($ch, CURLOPT_WRITEHEADER, fopen($fileHeaders, 'w+'));

		$serverResponse = curl_exec($ch);
		$httpInfo = curl_getinfo($ch);
		$errNum = curl_errno($ch);
		$errMsg = curl_error($ch);

		curl_close($ch);
		// for check request
		self::writeLog($url, '', '', 1);
		self::writeLog(['headers' => $this->headers]);
		self::writeLog($params, 'params');
		self::writeLog(['http_info' => $httpInfo, 'errNum' => $errNum]);

		if($errMsg) {
			self::writeLog($errMsg, 'Error Message');
		}
		self::writeLog($serverResponse, 'response');

		if($errNum && empty($serverResponse)) {
			$this->messageError = $errMsg;
			return false;
		} else {
			$result = json_decode($serverResponse, true) ? json_decode($serverResponse, true) : $serverResponse;

			if(!empty($result['error'])){
				$this->messageError = $this->formalizeErrorMessage($result['error']);
			}

			if(!empty($result['data']['token'])) {
				$parsed = $this->parseSign($result['data']['token']);
				unset($result['data']);
				self::writeLog($parsed, 'parse token');
				$result = array_merge($result, $parsed);
			}

			return !empty($this->messageError)? false : $result;
		}
	}
	/**
	 * @param $data
	 * @param string $flag
	 * @param string $filename
	 * @param bool|true $append
	 */
	static function writeLog($data, $flag = '', $filename = '', $append = true)
	{
		if(self::wrLog) {
			$filename = !empty($filename) ? strval($filename) : 'resultRequest';

			if(is_string($data)){
				$data = json_decode($data)? json_decode($data, 1) : $data;
			}

			$date = (new \DateTime())->format('Y-m-d H:i:s.u');
			file_put_contents(__DIR__ . "/{$filename}.log", "\n\n{$date} - {$flag}\n" .
				(is_array($data) ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $data)
				, ($append ? FILE_APPEND : 0)
			);
		}
	}

}