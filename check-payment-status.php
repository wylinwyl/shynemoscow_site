<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Логирование для отладки
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_status_debug.log');

require_once 'vendor/autoload.php';

use YooKassa\Client;
use YooKassa\Request\Payments\PaymentsRequest;

$shopId = '1055647';
$secretKey = 'live_gkpVoJMfLw3a5wMMV0lA1y7jADfxrOj_DXWwKdRqc5w';

$client = new Client();
$client->setAuth($shopId, $secretKey);

$paymentId = $_GET['paymentId'] ?? '';
$idempotenceKey = $_GET['idempotenceKey'] ?? '';

if (!$paymentId && !$idempotenceKey) {
    http_response_code(400);
    echo json_encode(['error' => 'Не указан ни Payment ID, ни Idempotence Key']);
    error_log("Не указан ни Payment ID, ни Idempotence Key");
    exit;
}

try {
    if ($paymentId) {
        // Если передан paymentId, напрямую запрашиваем статус платежа
        error_log("Проверка статуса для Payment ID: $paymentId");
        $payment = $client->getPaymentInfo($paymentId);
        $status = $payment->getStatus();
        error_log("Payment ID: $paymentId, Status: $status");
        echo json_encode([
            'status' => $status,
            'paymentId' => $paymentId
        ]);
    } else {
        // Если передан idempotenceKey, ищем платеж по metadata
        error_log("Поиск платежа по Idempotence Key: $idempotenceKey");
        
        // Запрашиваем список платежей
        $request = PaymentsRequest::builder()
            ->setLimit(100) // Ограничение на количество платежей
            ->build();
        
        $payments = $client->getPayments($request);
        $payment = null;

        // Ищем платеж с нужным idempotenceKey в metadata
        foreach ($payments->getItems() as $p) {
            $metadata = $p->getMetadata();
            if (isset($metadata['idempotenceKey']) && $metadata['idempotenceKey'] === $idempotenceKey) {
                $payment = $p;
                break;
            }
        }

        if (!$payment) {
            http_response_code(404);
            echo json_encode(['error' => 'Платеж не найден']);
            error_log("Платеж с Idempotence Key $idempotenceKey не найден");
            exit;
        }

        $paymentId = $payment->getId();
        $status = $payment->getStatus();
        error_log("Payment ID: $paymentId, Status: $status");
        echo json_encode([
            'status' => $status,
            'paymentId' => $paymentId
        ]);
    }
} catch (Exception $e) {
    error_log("Error checking payment status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>