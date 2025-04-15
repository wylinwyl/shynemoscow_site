<?php
// Настройки письма
$to = 'vladislavlis1337@gmail.com, zemtcov.ivan0605@gmail.com'; // Получатели
$subject = 'Тестовое письмо от SHYNEMOSCOW';
$message = "Это тестовое письмо.\n\nЕсли вы его получили, значит отправка писем работает корректно!\n\nSHYNEMOSCOW";
$headers = "From: SHYNEMOSCOW <shynemoscow@shynemoscow.ru>\r\n";
$headers .= "Reply-To: shynemoscow@shynemoscow.ru\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// Отправка письма
if (mail($to, $subject, $message, $headers)) {
    echo "Письмо успешно отправлено!";
} else {
    echo "Ошибка отправки письма.";
}
?>