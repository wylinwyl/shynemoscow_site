<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Включим логирование для отладки
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/orders_debug.log');

try {
    // Проверяем метод запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed', 405);
    }

    // Получаем входные данные
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('Empty request body', 400);
    }

    // Проверяем JSON
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg(), 400);
    }

    // Создаем папку для заказов, если ее нет
    if (!file_exists(__DIR__.'/orders')) {
        mkdir(__DIR__.'/orders', 0755, true);
    }

    // Генерируем уникальный ID заказа
    $orderId = 'order_' . uniqid() . '_' . time();
    $filename = __DIR__.'/orders/' . $orderId . '.json';

    // Сохраняем данные заказа
    file_put_contents($filename, json_encode($data, JSON_UNESCAPED_UNICODE));

    // Логируем успешное сохранение
    error_log("Order saved: " . $orderId);

    // Возвращаем ответ
    echo json_encode([
        'status' => 'success',
        'orderId' => $orderId
    ]);

} catch (Exception $e) {
    // Логируем ошибку
    error_log("Error saving order: " . $e->getMessage());

    // Возвращаем ошибку
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>