<?php
namespace UaPayPayment\UaPay\Model\Api;


class ResponseObject
{
	private $data = [];

	public function __construct($data = [])
	{
		$this->data = $data;
	}

	private function get($key = null)
	{
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	public function getData()
	{
		return $this->data;
	}

	public function setData($data = [])
	{
		$this->data = $data;
	}

	public function reset()
	{
		$this->data = [];
	}

}