<?php
// config/payment_config.php
return [
    'vnpay' => [
        'vnp_TmnCode' => 'YOUR_TMN_CODE',
        'vnp_HashSecret' => 'YOUR_HASH_SECRET',
        'vnp_Url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'vnp_ReturnUrl' => 'http://localhost/vnpay_return.php'
    ],
    'momo' => [
        'partnerCode' => 'YOUR_PARTNER_CODE',
        'accessKey' => 'YOUR_ACCESS_KEY',
        'secretKey' => 'YOUR_SECRET_KEY',
        'endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create'
    ]
];