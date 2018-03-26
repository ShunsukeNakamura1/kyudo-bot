<?php
require_once __DIR__ . '/vendor/autoload.php';

error_log("start");

// POSTを受け取る
$postData = file_get_contents('php://input');
error_log($postData);

// jeson化
$json = json_decode($postData);
$event = $json->events[0];
error_log(var_export($event, true));

// ChannelAccessTokenとChannelSecret設定
$httpClient = sethttpClient();
$bot = setBot($httpClient);

// イベントタイプがmessage以外はスルー
if ($event->type != "message")
    return;

// メッセージタイプが文字列の場合文字列をコピー
if ($event->message->type == "text") {
  $copyMessage = $event->message->text;
}
//文字列以外は無視
else {
  $copyMessage = "分からん";
  return;
}

//応答メッセージ作成
$textMessages = array($copyMessage, "あ…");
//応答メッセージをLINE用に変換
$replyMessages = buildMessages($textMessages);

// メッセージ送信
$response = $bot->replyMessage($event->replyToken, $replyMessages);
error_log(var_export($response,true));
return;


//---------------------------------------------------------------------
function sethttpClient(){
  $client = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('LineMessageAPIChannelAccessToken'));
  return $client;
}
function setBot($httpClient){
  $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('LineMessageAPIChannelSecret')]);
  return $bot;
}

//文字列の配列を引数として送信用メッセージを返す
buildMessages($textMessages){
  $replyMessages = new \LINE\LINEBot\MessageBuilder\MultiMessageBuilder();
  foreach($textMessages as $message){
    $a = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message);
    $replyMessages->add($a);
  }
  return $replyMessages;
}