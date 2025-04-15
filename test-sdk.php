<?php
require 'vendor/autoload.php';

$client = new \YooKassa\Client();
$client->setAuth(1055647, 'live_gkpVoJMfLw3a5wMMV0lA1y7jADfxrOj_DXWwKdRqc5w');

try {
    $payment = $client->createPayment([
        'amount' => [
            'value' => 1.00,
            'currency' => 'RUB'
        ],
        'confirmation' => [
            'type' => 'redirect',
            'return_url' => 'https://shynemoscow.ru/success.html'
        ],
        'description' => 'Тестовый платеж',
        'receipt' => [
            'customer' => [
                'email' => 'test@example.com'
            ],
            'items' => [
                [
                    'description' => 'Тестовый товар',
                    'quantity' => 1,
                    'amount' => [
                        'value' => 1.00,
                        'currency' => 'RUB'
                    ],
                    'vat_code' => 1
                ]
            ]
        ]
    ], uniqid());
    
    echo "Работает! Payment ID: ".$payment->getId();
} catch (\Exception $e) {
    echo "Ошибка: ".$e->getMessage();
}
?>