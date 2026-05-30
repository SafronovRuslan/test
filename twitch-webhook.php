<?php
// Отримуємо ключі з налаштувань Render
$CLIENT_ID     = getenv('TWITCH_CLIENT_ID');
$CLIENT_SECRET = getenv('TWITCH_CLIENT_SECRET');

$BOT_TOKEN     = '8817577004:AAHdoK2fVl6p2Y_V0Pkb0n7CwP9urSXv8Ks'; 
$CHAT_ID       = '-1003519701987';
$CHANNEL_LOGIN = 'grizlibizli'; // Твій логін на Twitch

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Перевірка логів через браузер
if (isset($_GET['show_logs'])) {
    header('Content-Type: text/plain');
    echo file_exists('twitch_debug.log') ? file_get_contents('twitch_debug.log') : 'Лог-файл порожній';
    exit;
}

// 1. ВАЛІДАЦІЯ WEBHOOK (Миттєва відповідь)
if (isset($data['challenge'])) {
    header('Content-Type: text/plain');
    echo $data['challenge'];
    exit;
}

// 2. ОБРОБКА СТРІМУ
if (isset($data['subscription']['type']) && $data['subscription']['type'] === 'stream.online') {
    
    // Ігноруємо тестові події від Twitch CLI
    if (isset($data['event']['is_test_event']) && $data['event']['is_test_event'] === true) {
        http_response_code(200);
        echo "OK";
        exit;
    }

    // НІЯКИХ sleep()! Працюємо максимально швидко, щоб вкластися в 10 секунд.

    // Отримуємо токен доступу Twitch
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
        // Запитуємо дані поточного стріму
        $ch = curl_init("https://api.twitch.tv/helix/streams?user_login=" . $CHANNEL_LOGIN);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Client-ID: " . $CLIENT_ID,
            "Authorization: Bearer " . $access_token
        ]);
        $stream_res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $stream_data = $stream_res['data'][0] ?? null;

        // Навіть якщо Twitch ще не повернув дані у масиві streams (бо пройшло мало часу),
        // ми все одно згенеруємо красиве повідомлення, щоб не пропустити сповіщення.
        $title = $stream_data['title'] ?? 'Почався стрім!';
        $game  = $stream_data['game_name'] ?? 'Категорію оновлюється';
        
        // Генеруємо лінк на картинку. Навіть якщо її ще немає на серверах Twitch,
        // ми змусимо Telegram завантажити її за шаблоном. Вона підтягнеться автоматично за пару секунд!
        $thumb_url = "https://static-cdn.jtvnw.net/previews-ttv/live_user_" . $CHANNEL_LOGIN . "-1280x720.jpg?t=" . time();

        // Текст повідомлення
        $message = "🟢 <b>ЙОКАЛИ МЕНЕ Я ПІДРУБИВСЯ!</b>\n\n";
        $message .= "🎮 Стрімлю: <b>{$game}</b>\n";
        $message .= "📝 <i>{$title}</i>\n\n";
        $message .= "🔥 <a href=\"https://www.twitch.tv/grizlibizli\">Залітай у чат →</a>";

        // Відправляємо картку з фото. Telegram сам спробує її скачати.
        $tg_url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendPhoto";
        $tg_data = [
            'chat_id' => $CHAT_ID,
            'photo'   => $thumb_url,
            'caption' => $message,
            'parse_mode' => 'HTML'
        ];

        $ch = curl_init($tg_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tg_data));
        $tg_res = curl_exec($ch);
        curl_close($ch);

        file_put_contents('twitch_debug.log', "[" . date('Y-m-d H:i:s') . "] SUCCESS: Надіслано без затримки.\n", FILE_APPEND);
    } else {
        file_put_contents('twitch_debug.log', "[" . date('Y-m-d H:i:s') . "] ERROR: Немає токена Twitch.\n", FILE_APPEND);
    }

    // МИТТЄВО КАЖЕМО ТВІЧУ ЩО ВСЕ ДОБРЕ
    http_response_code(200);
    echo "OK";
    exit;
}

http_response_code(200);
echo "OK";
?>
