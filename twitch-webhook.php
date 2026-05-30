<?php
// Отримуємо ключі з налаштувань Render
$CLIENT_ID     = getenv('TWITCH_CLIENT_ID');
$CLIENT_SECRET = getenv('TWITCH_CLIENT_SECRET');

$BOT_TOKEN     = '8817577004:AAHdoK2fVl6p2Y_V0Pkb0n7CwP9urSXv8Ks'; 
$CHAT_ID       = '-1003519701987';
$CHANNEL_LOGIN = 'grizlibizli'; // Твій логін на Twitch

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Віддаємо відповідь Twitch відразу, щоб він не думав, що сервіс впав
if (isset($data['challenge'])) {
    header('Content-Type: text/plain');
    echo $data['challenge'];
    exit;
}

// Повідомляємо Twitch, що запит отримано, але продовжуємо роботу у фоні
if (isset($data['subscription']['type']) && $data['subscription']['type'] === 'stream.online') {
    
    // Ігноруємо тестові виклики від самого Twitch CLI, якщо вони порожні
    if (isset($data['event']['is_test_event']) && $data['event']['is_test_event'] === true) {
        http_response_code(200);
        echo "OK (Test Event Ignored)";
        exit;
    }

    // Хитрість: закриваємо з'єднання з Twitch, щоб він не чекав
    ignore_user_abort(true);
    set_time_limit(60); // Даємо скрипту до 1 хвилини на виконання
    
    header("Connection: close");
    header("Content-Length: 2");
    http_response_code(200);
    echo "OK";
    
    // Очищуємо буфери, щоб Render відпустив Twitch
    if (ob_get_level()) ob_end_flush();
    flush();

    // ВІДТЯЖКА: засинаємо на 12 секунд
    sleep(12);

    // 1. ОТРИМУЄМО ACCESS TOKEN ВІД TWITCH
    $ch = curl_init("https://id.twitch.tv/oauth2/token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id'     => $CLIENT_ID,
        'client_secret' => $CLIENT_SECRET,
        'grant_type'    => 'client_credentials'
    ]));
    $token_res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $access_token = $token_res['access_token'] ?? null;

    if ($access_token) {
        // 2. ЗАПИТУЄМО ДАНІ СТРІМУ
        $ch = curl_init("https://api.twitch.tv/helix/streams?user_login=" . $CHANNEL_LOGIN);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Client-ID: " . $CLIENT_ID,
            "Authorization: Bearer " . $access_token
        ]);
        $stream_res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $stream_data = $stream_res['data'][0] ?? null;

        if ($stream_data) {
            $title = $stream_data['title'] ?? 'Без назви';
            $game  = $stream_data['game_name'] ?? 'Не вказано';
            
            // Забираємо картинку стріму і виставляємо нормальний розмір
            $thumb_url = $stream_data['thumbnail_url'] ?? '';
            $thumb_url = str_replace(['{width}', '{height}'], ['1280', '720'], $thumb_url);
            if ($thumb_url) $thumb_url .= "?t=" . time(); 

            // Формуємо повідомлення
            $message = "🟢 <b>ЙОКАЛИ МЕНЕ Я ПІДРУБИВСЯ!</b>\n\n";
            $message .= "🎮 Стрімлю: <b>{$game}</b>\n";
            $message .= "📝 <i>{$title}</i>\n\n";
            $message .= "🔥 <a href=\"https://www.twitch.tv/grizlibizli\">Залітай у чат →</a>";

            // 3. ВІДПРАВЛЯЄМО В ТЕЛЕГРАМ
            $tg_url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage";
            $tg_data = [
                'chat_id'    => $CHAT_ID,
                'parse_mode' => 'HTML',
                'text'       => $message
            ];

            // Перевіряємо, чи картинка є і чи вона не є тимчасовою заглушкою "в обробці"
            if (!empty($thumb_url) && strpos($thumb_url, '404_processing') === false) {
                $tg_url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendPhoto";
                $tg_data = [
                    'chat_id' => $CHAT_ID,
                    'photo'   => $thumb_url,
                    'caption' => $message,
                    'parse_mode' => 'HTML'
                ];
            }

            $ch = curl_init($tg_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tg_data));
            $tg_res = curl_exec($ch);
            curl_close($ch);

            file_put_contents('twitch_debug.log', "[" . date('Y-m-d H:i:s') . "] SUCCESS: Повідомлення надіслано.\n", FILE_APPEND);
        } else {
            file_put_contents('twitch_debug.log', "[" . date('Y-m-d H:i:s') . "] ERROR: Стрім не знайдено в API.\n", FILE_APPEND);
        }
    } else {
        file_put_contents('twitch_debug.log', "[" . date('Y-m-d H:i:s') . "] ERROR: Не вдалося отримати токен Twitch.\n", FILE_APPEND);
    }
    exit;
}

// Перегляд логів
if (isset($_GET['show_logs'])) {
    header('Content-Type: text/plain');
    echo file_exists('twitch_debug.log') ? file_get_contents('twitch_debug.log') : 'Лог-файл порожній';
    exit;
}

http_response_code(200);
echo "OK";
?>
