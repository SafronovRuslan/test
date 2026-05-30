<?php
$BOT_TOKEN = '8817577004:AAHdoK2fVl6p2Y_V0Pkb0n7CwP9urSXv8Ks'; 
$CHAT_ID  = '-1003519701987';

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// ЛОГУВАННЯ: Записуємо вхідний запит
file_put_contents('twitch_debug.log', "[" . date('Y-m-d H:i:s') . "] PAYLOAD: " . $payload . "\n", FILE_APPEND);

if (isset($data['challenge'])) {
    header('Content-Type: text/plain');
    echo $data['challenge'];
    exit;
}

if (isset($data['subscription']['type']) && $data['subscription']['type'] === 'stream.online') {
    $event = $data['event'] ?? [];
    $title = $event['title'] ?? 'Без назви';
    $game  = $event['game_name'] ?? 'Не вказано';

    // ЗАМІНИВ КУЛЬКУ НА ЗЕЛЕНУ 🟢
    $message = "🟢 <b>ЙОКАЛИ МЕНЕ Я ПІДРУБИВСЯ!</b>\n\n";
    $message .= "🎮 Стрімлю: <b>{$game}</b>\n";
    $message .= "📝 <i>{$title}</i>\n\n";
    $message .= "🔥 <a href=\"https://www.twitch.tv/grizlibizli\">Залітай у чат →</a>";

    $ch = curl_init("https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Забороняємо виводити відповідь прямо в екран
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $CHAT_ID,
        'parse_mode' => 'HTML',
        'text' => $message
    ]));
    
    $tg_res = curl_exec($ch);
    // ЛОГУВАННЯ: Записуємо відповідь TG у лог-файл
    file_put_contents('twitch_debug.log', "[" . date('Y-m-d H:i:s') . "] TG RESPONSE: " . $tg_res . "\n", FILE_APPEND);
    
    curl_close($ch);
}

// Хук для перевірки логів через браузер
if (isset($_GET['show_logs'])) {
    header('Content-Type: text/plain');
    echo file_exists('twitch_debug.log') ? file_get_contents('twitch_debug.log') : 'Лог-файл порожній';
    exit;
}

// Тепер headers already sent не вилізе, бо ми нічого не виводили на екран завдякиRETURNTRANSFER
http_response_code(200);
echo "OK";
?>
