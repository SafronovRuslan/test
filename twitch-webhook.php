<?php
// ===== НАЛАШТУВАННЯ =====
$CLIENT_ID     = getenv('TWITCH_CLIENT_ID');
$CLIENT_SECRET = getenv('TWITCH_CLIENT_SECRET');
$BOT_TOKEN     = '8817577004:AAHdoK2fVl6p2Y_V0Pkb0n7CwP9urSXv8Ks';
$CHAT_ID       = '-1003519701987';
$CHANNEL_LOGIN = 'grizlibizli';

$payload = file_get_contents('php://input');
$data    = json_decode($payload, true);

// Перегляд логів через браузер: ...?show_logs=1
if (isset($_GET['show_logs'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo file_exists('twitch_debug.log') ? file_get_contents('twitch_debug.log') : 'Лог порожній';
    exit;
}

// ===== 1. ВАЛІДАЦІЯ WEBHOOK =====
if (isset($data['challenge'])) {
    header('Content-Type: text/plain');
    echo $data['challenge'];
    exit;
}

// ===== 2. ДЕДУПЛІКАЦІЯ (захист від повторів Twitch) =====
$msgId = $_SERVER['HTTP_TWITCH_EVENTSUB_MESSAGE_ID'] ?? null;
if ($msgId) {
    $seen = @file_get_contents('seen_ids.log') ?: '';
    if (strpos($seen, $msgId) !== false) {
        // вже обробляли цей самий event — мовчки виходимо
        http_response_code(200);
        echo "OK (duplicate)";
        exit;
    }
    // запам'ятовуємо id (тримаємо тільки останні 50, щоб файл не ріс)
    $ids = array_filter(explode("\n", $seen));
    $ids[] = $msgId;
    $ids = array_slice($ids, -50);
    @file_put_contents('seen_ids.log', implode("\n", $ids) . "\n");
}

// ===== 3. ОБРОБКА stream.online =====
if (isset($data['subscription']['type']) && $data['subscription']['type'] === 'stream.online') {

    // Ігноруємо тестові події Twitch CLI
    if (isset($data['event']['is_test_event']) && $data['event']['is_test_event'] === true) {
        http_response_code(200);
        echo "OK";
        exit;
    }

    // !!! ГОЛОВНЕ ВИПРАВЛЕННЯ !!!
    // Відразу кажемо Twitch'у "200 OK" і закриваємо з'єднання,
    // а важку роботу робимо вже ПІСЛЯ цього. Так Twitch не буде ретраїти.
    http_response_code(200);
    echo "OK";
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        // запасний варіант, якщо не php-fpm
        ignore_user_abort(true);
        header('Connection: close');
        header('Content-Length: ' . ob_get_length());
        ob_end_flush();
        @ob_flush();
        flush();
    }

    // --- далі вже не впливає на відповідь Twitch'у ---

    // Токен Twitch
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

    if (!$access_token) {
        file_put_contents('twitch_debug.log', "[" . date('Y-m-d H:i:s') . "] ERROR: Немає токена Twitch.\n", FILE_APPEND);
        exit;
    }

    // Дані стріму
    $ch = curl_init("https://api.twitch.tv/helix/streams?user_login=" . $CHANNEL_LOGIN);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Client-ID: " . $CLIENT_ID,
        "Authorization: Bearer " . $access_token
    ]);
    $stream_res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $stream_data = $stream_res['data'][0] ?? null;

    $title = $stream_data['title'] ?? 'Почався стрім!';
    $game  = $stream_data['game_name'] ?? 'Категорія оновлюється';
    $thumb_url = "https://static-cdn.jtvnw.net/previews-ttv/live_user_" . $CHANNEL_LOGIN . "-1280x720.jpg?t=" . time();

    $message  = "🟢 <b>ЙОКАЛИ МЕНЕ Я ПІДРУБИВСЯ!</b>\n\n";
    $message .= "🎮 Стрімлю: <b>{$game}</b>\n";
    $message .= "📝 <i>{$title}</i>\n\n";
    $message .= "🔥 <a href=\"https://www.twitch.tv/grizlibizli\">Залітай у чат →</a>";

    // Надсилаємо в Telegram
    $tg_url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendPhoto";
    $ch = curl_init($tg_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id'    => $CHAT_ID,
        'photo'      => $thumb_url,
        'caption'    => $message,
        'parse_mode' => 'HTML'
    ]));
    curl_exec($ch);
    curl_close($ch);

    file_put_contents('twitch_debug.log', "[" . date('Y-m-d H:i:s') . "] SUCCESS: msg_id={$msgId}\n", FILE_APPEND);
    exit;
}

// Будь-що інше
http_response_code(200);
echo "OK";
?>
