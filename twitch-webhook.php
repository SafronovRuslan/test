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

// ===== 2. ОБРОБКА stream.online =====
if (isset($data['subscription']['type']) && $data['subscription']['type'] === 'stream.online') {

    // Ігноруємо тестові події Twitch CLI
    if (isset($data['event']['is_test_event']) && $data['event']['is_test_event'] === true) {
        http_response_code(200); 
        echo "OK (Test Event)"; 
        exit;
    }

    // --- ДЕДУП ПО ВІКНУ ЧАСУ (cooldown) ---
    $COOLDOWN = 600; // 10 хвилин
    $fp = fopen('last_notify.txt', 'c+');
    if ($fp) {
        flock($fp, LOCK_EX);
        $last = (int) trim(stream_get_contents($fp));
        $now  = time();
        
        if ($now - $last < $COOLDOWN) {
            flock($fp, LOCK_UN);
            fclose($fp);
            file_put_contents('twitch_debug.log', "[" . date('Y-m-d H:i:s') . "] IGNORED: Кулдаун.\n", FILE_APPEND);
            http_response_code(200); 
            echo "OK (cooldown)"; 
            exit;
        }
        
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, (string) $now);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    // --- МІКРО-ВІДТЯЖКА ДЛЯ TWITCH API ---
    // Чекаємо 6 секунд, щоб сервери Twitch встигли оновити назву гри та стріму,
    // але не перевищуємо жорсткий ліміт Twitch у 10 секунд.
    sleep(6);

    // --- ОТРИМАННЯ ДАНИХ З TWITCH API ---
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
        http_response_code(200); echo "OK"; exit;
    }

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

    // Якщо навіть за 6 секунд база не оновилась, беремо дефолт
    $title = $stream_data['title'] ?? 'Почався стрім!';
    $game  = $stream_data['game_name'] ?? 'Категорія оновлюється';
    $thumb_url = "https://static-cdn.jtvnw.net/previews-ttv/live_user_" . $CHANNEL_LOGIN . "-1280x720.jpg?t=" . time();

    $message  = "🟢 <b>ЙОКАЛИ МЕНЕ Я ПІДРУБИВСЯ!</b>\n\n";
    $message .= "🎮 Стрімлю: <b>{$game}</b>\n";
    $message .= "📝 <i>{$title}</i>\n\n";
    $message .= "🔥 <a href=\"https://www.twitch.tv/grizlibizli\">Залітай у чат →</a>";

    // --- НАДСИЛАННЯ В TELEGRAM ---
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
    $tg_res = curl_exec($ch);
    curl_close($ch);

    file_put_contents('twitch_debug.log', "[" . date('Y-m-d H:i:s') . "] SUCCESS: Надіслано з 6с затримкою.\n", FILE_APPEND);

    http_response_code(200);
    echo "OK";
    exit;
}

http_response_code(200);
echo "OK";
?>
