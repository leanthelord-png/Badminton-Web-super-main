<?php
return [
    'currency' => 'VND',
    'currency_symbol' => '₫',
    
    'vnpay' => [
        'vnp_TmnCode' => 'YOUR_TMN_CODE',
        'vnp_HashSecret' => 'YOUR_HASH_SECRET',
        'vnp_Url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'vnp_ReturnUrl' => 'http://localhost/Badminton-Web-MySQL-test1/Badminton-Web-super-main/Badminton%20Web/Badminton%20Web/payment_callback.php?method=vnpay',
    ],
    
    'momo' => [
        'partner_code' => 'YOUR_PARTNER_CODE',
        'access_key' => 'YOUR_ACCESS_KEY',
        'secret_key' => 'YOUR_SECRET_KEY',
        'endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
    ],
    
    'zalopay' => [
        'app_id' => 'YOUR_APP_ID',
        'key1' => 'YOUR_KEY1',
        'key2' => 'YOUR_KEY2',
        'endpoint' => 'https://sandbox.zalopay.com.vn/v001/tpe/createorder',
    ],
    
    'bank_transfer' => [
        'bank_name' => 'Vietcombank',
        'account_number' => '1234567890',
        'account_name' => 'BADMINTON PRO COMPANY',
        'branch' => 'Hanoi Branch',
    ],
];