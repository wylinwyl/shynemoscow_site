<?php
require_once __DIR__ . '/vendor/autoload.php';

use YooKassa\Client;

// Включаем буферизацию вывода
ob_start();

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/payment_debug.log');

try {
    // Проверка метода запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed', 405);
    }

    // Получаем и валидируем входные данные
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('Empty request body', 400);
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg(), 400);
    }

    // Валидация обязательных полей
    $required = ['amount', 'email', 'items'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field", 400);
        }
    }

    // Инициализация клиента
    $client = new Client();
    $client->setAuth('1055647', 'live_7e2eLPplW9Iwm4ER3DdUwM_MR-iwsCOimNXFNsi4heA');

    // Подготовка товаров для чека
    $items = [];
    foreach ($data['items'] as $item) {
        $amountValue = number_format((float)$item['amount']['value'], 2, '.', '');
        if ($amountValue <= 0) {
            throw new Exception("Invalid item price: {$item['amount']['value']}", 400);
        }
        
        $items[] = [
            'description' => mb_substr($item['description'] ?? 'Товар', 0, 128),
            'quantity' => (float)($item['quantity'] ?? 1),
            'amount' => [
                'value' => $amountValue,
                'currency' => $item['amount']['currency'] ?? 'RUB'
            ],
            'vat_code' => (int)($item['vat_code'] ?? 1),
            'payment_mode' => $item['payment_mode'] ?? 'full_payment',
            'payment_subject' => $item['payment_subject'] ?? 'commodity'
        ];
    }

    // Создание платежа
    $idempotenceKey = uniqid('', true);
    $payment = $client->createPayment([
        'amount' => [
            'value' => number_format((float)$data['amount']['value'], 2, '.', ''),
            'currency' => $data['amount']['currency'] ?? 'RUB'
        ],
        'payment_method_data' => [
            'type' => 'bank_card'
        ],
        'confirmation' => [
            'type' => 'redirect',
            'return_url' => 'https://shynemoscow.ru/main.html?payment_id=' . $idempotenceKey
        ],
        'description' => $data['description'] ?? 'Оплата заказа',
        'metadata' => array_merge($data['metadata'] ?? [], ['idempotenceKey' => $idempotenceKey]),
        'receipt' => [
            'customer' => [
                'email' => $data['email']
            ],
            'items' => $items
        ],
        'capture' => true
    ], $idempotenceKey);

    // Очищаем буфер и отправляем JSON
    ob_end_clean();
    echo json_encode([
        'confirmation_url' => $payment->getConfirmation()->getConfirmationUrl(),
        'payment_id' => $payment->getId(),
        'status' => 'success'
    ]);

} catch (\Exception $e) {
    // Очищаем буфер перед отправкой ошибки
    ob_end_clean();
    http_response_code($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => 'payment_error',
        'status' => 'error'
    ]);
    exit;
}
?>