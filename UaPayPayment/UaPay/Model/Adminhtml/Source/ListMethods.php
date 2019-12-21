<?php
namespace UaPayPayment\UaPay\Model\Adminhtml\Source;

use UaPayPayment\UaPay\Model\Api\Payment as ApiUaPay;
class ListMethods implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        $list = [
            [
                'value' => ApiUaPay::OPERATION_PAY,
                'label' => 'PAY',
            ],
            [
                'value' => ApiUaPay::OPERATION_HOLD,
                'label' => 'HOLD',
            ],
        ];
        return $list;
    }
}
