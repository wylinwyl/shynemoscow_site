<?php
require_once __DIR__ . '/vendor/autoload.php';

use YooKassa\Client;

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_debug.log');

try {
    // Проверка метода запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET requests allowed', 405);
    }

    // Получаем payment_id из параметров запроса
    $paymentId = $_GET['payment_id'] ?? '';
    if (empty($paymentId)) {
        throw new Exception('Missing payment_id parameter', 400);
    }

    // Инициализация клиента YooKassa
    $client = new Client();
    $client->setAuth('1055647', 'live_7e2eLPplW9Iwm4ER3DdUwM_MR-iwsCOimNXFNsi4heA');

    // Получаем информацию о платеже
    $payment = $client->getPaymentInfo($paymentId);

    // Определяем статус платежа
    $status = $payment->getStatus();
    $response = [
        'status' => $status,
        'payment_id' => $paymentId
    ];

    // Логирование для отладки
    file_put_contents('check-payment-status.log', 
        date('[Y-m-d H:i:s]') . " Payment ID: $paymentId, Status: $status\n", 
        FILE_APPEND
    );

    echo json_encode($response);

} catch (\Exception $e) {
    http_response_code($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => 'check_payment_error',
        'status' => 'error'
    ]);
    exit;
}
?>