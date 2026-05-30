<?php

$BOT_TOKEN = '8817577004:AAHdoK2fVl6p2Y_V0Pkb0n7CwP9urSXv8K';
$CHAT_ID  = '-1003519701987';

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (isset($data['challenge'])) {
    header('Content-Type: text/plain');
    echo $data['challenge'];
    exit;
}

if (isset($data['subscription']['type']) && $data['subscription']['type'] === 'stream.online') {
    
    $event = $data['event'] ?? [];
    $title = $event['title'] ?? 'Без назви';
    $game  = $event['game_name'] ?? 'Не вказано';

    $message = "🔴 <b>ЙОКАЛИ МЕНЕ Я ПІДРУБИВСЯ!</b>\n\n";
    $message .= "🎮 Стрімлю: <b>{$game}</b>\n";
    $message .= "📝 <i>{$title}</i>\n\n";
    $message .= "🔥 <a href=\"https://www.twitch.tv/grizlibizli\">Залітай у чат →</a>";

    $ch = curl_init("https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $CHAT_ID,
        'parse_mode' => 'HTML',
        'text' => $message
    ]));
    curl_exec($ch);
    curl_close($ch);
}

http_response_code(200);
echo "OK";
?>
