<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$requestBody = json_decode(file_get_contents('php://input'), true);

if (!$requestBody || !isset($requestBody['email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверные данные запроса']);
    exit;
}

$to = $requestBody['email'];
$subject = "Ваш заказ на SHYNEMOSCOW успешно оплачен";
$orderNumber = isset($requestBody['orderId']) ? $requestBody['orderId'] : date('YmdHis');

$itemsList = '';
foreach ($requestBody['items'] as $item) {
    $itemsList .= sprintf(
        "%s (%s, %s) - %d x %d ₽\n",
        $item['name'], $item['size'], $item['color'], $item['quantity'], $item['price']
    );
}

$deliveryDetails = $requestBody['delivery'];
if ($requestBody['cdekPoint']) {
    $deliveryDetails .= "\nПункт выдачи СДЭК: {$requestBody['cdekPoint']['city']}, {$requestBody['cdekPoint']['address']}";
}

$message = "Здравствуйте, {$requestBody['name']}!\n\n";
$message .= "Ваш заказ №$orderNumber успешно оплачен.\n\n";
$message .= "Детали заказа:\n";
$message .= $itemsList;
$message .= "\nДоставка: {$deliveryDetails}\n";
$message .= "Стоимость доставки: {$requestBody['deliveryCost']} ₽\n";
$message .= "Итого: {$requestBody['total']} ₽\n\n";
$message .= "Адрес доставки: {$requestBody['address']}\n";
$message .= "Комментарий: " . ($requestBody['comment'] ?: 'Отсутствует') . "\n\n";
$message .= "Спасибо за покупку!\nSHYNEMOSCOW";

$mail = new PHPMailer(true);
try {
    // Настройки SMTP
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; // Укажите ваш SMTP-сервер
    $mail->SMTPAuth = true;
    $mail->Username = 'your-email@gmail.com'; // Ваш email
    $mail->Password = 'your-app-password'; // Пароль приложения (не обычный пароль Gmail)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Настройки письма
    $mail->setFrom('no-reply@shynemoscow.ru', 'SHYNEMOSCOW');
    $mail->addAddress($to);
    $mail->isHTML(false);
    $mail->Subject = $subject;
    $mail->Body = $message;

    $mail->send();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => "Ошибка отправки письма: {$mail->ErrorInfo}"]);
}
?>