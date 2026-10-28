<?php

namespace App\Services;

use MyFatoorah\Library\API\Payment\MyFatoorahPayment;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentStatus;

class MyFatoorahService
{
    protected MyFatoorahPayment $mf;

    private $is_test;

    private $config;

    public function __construct()
    {
        $this->is_test = config('services.myfatoorah.is_test');
        $this->config = [
            'apiKey' => $this->is_test ? config('services.myfatoorah.test_key') : config('services.myfatoorah.live_key'),
            'vcCode' => 'KWT',
            'isTest' => $this->is_test,
        ];
        $this->mf = new MyFatoorahPayment($this->config);
    }

    public function initiatePayment(float|int $amount, array $customer_data, ?string $order_id = null): array
    {
        $postFields = [
            'InvoiceValue' => $amount,
            'CustomerName' => $customer_data['name'],
            'MobileCountryCode' => '+965',
            'CustomerMobile' => $customer_data['mobile'],
            'CustomerEmail' => $customer_data['email'],
            'DisplayCurrencyIso' => 'KWD',
            'CallBackUrl' => config('services.myfatoorah.callback_url'),
            'ErrorUrl' => config('services.myfatoorah.callback_url'),
            'Language' => strtoupper(app()->getLocale()) ?? 'EN',
            'CustomerReference' => $order_id,
        ];

        $data = $this->mf->getInvoiceURL($postFields, $this->is_test, $order_id);

        return $data;
    }

    public function getPaymentStatus(string $value, string $key = 'PaymentId'): array
    {
        $mfObj = new MyFatoorahPaymentStatus($this->config);
        $response = $mfObj->getPaymentStatus($value, $key);
        $response = json_encode($response);
        $response = json_decode($response, true);
        $response['InvoiceTransactions'] = $response['InvoiceTransactions'][0];
        unset($response['InvoiceTransactions'][0]);

        return $response;
    }
}
