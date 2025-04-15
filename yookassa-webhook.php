<?php
require __DIR__ . '/vendor/autoload.php';

// Настройка логгирования
file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " IP: " . $_SERVER['REMOTE_ADDR'] . "\n" . file_get_contents('php://input') . "\n\n", FILE_APPEND);

$data = json_decode(file_get_contents('php://input'), true);

// Общие данные для всех событий
$payment = $data['object'] ?? [];
$metadata = $payment['metadata'] ?? [];
$idempotenceKey = isset($metadata['idempotenceKey']) ? $metadata['idempotenceKey'] : 'N/A';

// Получаем данные заказа из metadata
$orderData = $metadata['orderData'] ?? null;

// Функция для формирования списка товаров и деталей доставки
function prepareOrderDetails($orderData, $payment) {
    // Формируем список товаров
    $itemsList = '';
    foreach ($orderData['items'] as $item) {
        $itemsList .= sprintf(
            "%s (%s, %s) - %d x %d ₽\n",
            $item['name'], $item['size'], $item['color'], $item['quantity'], $item['price']
        );
    }

    // Формируем детали доставки
    $deliveryDetails = $orderData['delivery'];
    if ($orderData['cdekPoint']) {
        $deliveryDetails .= "\nПункт выдачи СДЭК: {$orderData['cdekPoint']['city']}, {$orderData['cdekPoint']['address']}";
    }

    return [
        'itemsList' => $itemsList,
        'deliveryDetails' => $deliveryDetails
    ];
}

// Проверяем наличие события
if (isset($data['event'])) {
    // Общие заголовки для писем
    $headers = "From: SHYNEMOSCOW <shynemoscow@shynemoscow.ru>\r\n";
    $headers .= "Reply-To: shynemoscow@shynemoscow.ru\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // Событие: платеж успешно оплачен
    if ($data['event'] === 'payment.succeeded') {
        // Логирование успешного платежа
        file_put_contents('yookassa_payments.log',
            date('[Y-m-d H:i:s]') . " Успешный платеж\n" .
            "ID: " . $payment['id'] . "\n" .
            "Сумма: " . $payment['amount']['value'] . " " . $payment['amount']['currency'] . "\n" .
            "Idempotence Key: " . $idempotenceKey . "\n" .
            "Заказ: " . (isset($metadata['order_id']) ? $metadata['order_id'] : 'N/A') . "\n\n",
            FILE_APPEND);

        if (!$orderData) {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Ошибка: orderData не найдено в metadata\n", FILE_APPEND);
            http_response_code(200);
            exit;
        }

        // Декодируем orderData
        $orderData = json_decode($orderData, true);

        // Формируем детали заказа
        $details = prepareOrderDetails($orderData, $payment);
        $itemsList = $details['itemsList'];
        $deliveryDetails = $details['deliveryDetails'];

        // Формируем текст письма для покупателя
        $message = "Здравствуйте, {$orderData['name']}!\n\n";
        $message .= "Ваш заказ №{$payment['id']} успешно оплачен.\n\n";
        $message .= "Детали заказа:\n";
        $message .= $itemsList;
        $message .= "\nДоставка: {$deliveryDetails}\n";
        $message .= "Стоимость доставки: {$orderData['deliveryCost']} ₽\n";
        $message .= "Итого: {$orderData['total']} ₽\n\n";
        $message .= "Адрес доставки: {$orderData['address']}\n";
        $message .= "Комментарий: " . ($orderData['comment'] ?: 'Отсутствует') . "\n\n";
        $message .= "Спасибо за покупку!\nSHYNEMOSCOW";

        // Формируем текст письма для продавца
        $sellerMessage = "Новый заказ №{$payment['id']}\n\n";
        $sellerMessage .= "Клиент: {$orderData['name']}\n";
        $sellerMessage .= "Телефон: {$orderData['phone']}\n";
        $sellerMessage .= "Email: {$orderData['email']}\n\n";
        $sellerMessage .= "Детали заказа:\n";
        $sellerMessage .= $itemsList;
        $sellerMessage .= "\nДоставка: {$deliveryDetails}\n";
        $sellerMessage .= "Стоимость доставки: {$orderData['deliveryCost']} ₽\n";
        $sellerMessage .= "Итого: {$orderData['total']} ₽\n\n";
        $sellerMessage .= "Адрес доставки: {$orderData['address']}\n";
        $sellerMessage .= "Комментарий: " . ($orderData['comment'] ?: 'Отсутствует') . "\n\n";
        $sellerMessage .= "SHYNEMOSCOW";

        // Отправка письма покупателю
        $toCustomer = $orderData['email'];
        $subjectCustomer = "SHYNEMOSCOW: Ваш заказ №{$payment['id']} оплачен";
        if (mail($toCustomer, $subjectCustomer, $message, $headers)) {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Письмо покупателю успешно отправлено\n", FILE_APPEND);
        } else {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Ошибка отправки письма покупателю\n", FILE_APPEND);
        }

        // Отправка письма продавцам
        $toSeller = 'vladislavlis1337@gmail.com, zemtcov.ivan0605@gmail.com';
        $subjectSeller = "SHYNEMOSCOW: Новый заказ №{$payment['id']}";
        if (mail($toSeller, $subjectSeller, $sellerMessage, $headers)) {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Письмо продавцам успешно отправлено\n", FILE_APPEND);
        } else {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Ошибка отправки письма продавцам\n", FILE_APPEND);
        }
    }

    // Событие: платеж ожидает подтверждения (пользователь начал оплату, но не завершил)
    elseif ($data['event'] === 'payment.waiting_for_capture') {
        if (!$orderData) {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Ошибка: orderData не найдено в metadata\n", FILE_APPEND);
            http_response_code(200);
            exit;
        }

        // Декодируем orderData
        $orderData = json_decode($orderData, true);

        // Формируем детали заказа
        $details = prepareOrderDetails($orderData, $payment);
        $itemsList = $details['itemsList'];
        $deliveryDetails = $details['deliveryDetails'];

        // Формируем текст письма для покупателя (напоминание)
        $message = "Здравствуйте, {$orderData['name']}!\n\n";
        $message .= "Вы начали оплату заказа №{$payment['id']}, но она еще не завершена.\n\n";
        $message .= "Детали заказа:\n";
        $message .= $itemsList;
        $message .= "\nДоставка: {$deliveryDetails}\n";
        $message .= "Стоимость доставки: {$orderData['deliveryCost']} ₽\n";
        $message .= "Итого: {$orderData['total']} ₽\n\n";
        $message .= "Пожалуйста, завершите оплату, чтобы мы могли приступить к обработке вашего заказа.\n";
        $message .= "Если вы уже оплатили заказ, просто проигнорируйте это письмо.\n\n";
        $message .= "SHYNEMOSCOW";

        // Отправка письма покупателю
        $toCustomer = $orderData['email'];
        $subjectCustomer = "SHYNEMOSCOW: Завершите оплату заказа №{$payment['id']}";
        if (mail($toCustomer, $subjectCustomer, $message, $headers)) {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Напоминание покупателю об оплате успешно отправлено\n", FILE_APPEND);
        } else {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Ошибка отправки напоминания покупателю\n", FILE_APPEND);
        }
    }

    // Событие: платеж отменен (например, время на оплату истекло)
    elseif ($data['event'] === 'payment.canceled') {
        if (!$orderData) {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Ошибка: orderData не найдено в metadata\n", FILE_APPEND);
            http_response_code(200);
            exit;
        }

        // Декодируем orderData
        $orderData = json_decode($orderData, true);

        // Формируем детали заказа
        $details = prepareOrderDetails($orderData, $payment);
        $itemsList = $details['itemsList'];
        $deliveryDetails = $details['deliveryDetails'];

        // Формируем текст письма для покупателя (уведомление об отмене)
        $message = "Здравствуйте, {$orderData['name']}!\n\n";
        $message .= "К сожалению, оплата заказа №{$payment['id']} не была завершена, и заказ был отменен.\n\n";
        $message .= "Детали заказа:\n";
        $message .= $itemsList;
        $message .= "\nДоставка: {$deliveryDetails}\n";
        $message .= "Стоимость доставки: {$orderData['deliveryCost']} ₽\n";
        $message .= "Итого: {$orderData['total']} ₽\n\n";
        $message .= "Вы можете оформить заказ заново на сайте SHYNEMOSCOW.\n";
        $message .= "Если у вас есть вопросы, свяжитесь с нами: shynemoscow@shynemoscow.ru\n\n";
        $message .= "SHYNEMOSCOW";

        // Формируем текст письма для продавцов (уведомление об отмене)
        $sellerMessage = "Заказ №{$payment['id']} отменен\n\n";
        $sellerMessage .= "Клиент: {$orderData['name']}\n";
        $sellerMessage .= "Телефон: {$orderData['phone']}\n";
        $sellerMessage .= "Email: {$orderData['email']}\n\n";
        $sellerMessage .= "Детали заказа:\n";
        $sellerMessage .= $itemsList;
        $sellerMessage .= "\nДоставка: {$deliveryDetails}\n";
        $sellerMessage .= "Стоимость доставки: {$orderData['deliveryCost']} ₽\n";
        $sellerMessage .= "Итого: {$orderData['total']} ₽\n\n";
        $sellerMessage .= "SHYNEMOSCOW";

        // Отправка письма покупателю
        $toCustomer = $orderData['email'];
        $subjectCustomer = "SHYNEMOSCOW: Заказ №{$payment['id']} отменен";
        if (mail($toCustomer, $subjectCustomer, $message, $headers)) {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Уведомление об отмене заказа покупателю отправлено\n", FILE_APPEND);
        } else {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Ошибка отправки уведомления об отмене покупателю\n", FILE_APPEND);
        }

        // Отправка письма продавцам
        $toSeller = 'vladislavlis1337@gmail.com, zemtcov.ivan0605@gmail.com';
        $subjectSeller = "SHYNEMOSCOW: Заказ №{$payment['id']} отменен";
        if (mail($toSeller, $subjectSeller, $sellerMessage, $headers)) {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Уведомление об отмене заказа продавцам отправлено\n", FILE_APPEND);
        } else {
            file_put_contents('yookassa-webhook.log', date('[Y-m-d H:i:s]') . " Ошибка отправки уведомления об отмене продавцам\n", FILE_APPEND);
        }
    }
}

http_response_code(200);
?>