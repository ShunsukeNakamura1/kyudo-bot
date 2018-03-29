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
$httpClient = setHttpClient();
$bot = setBot($httpClient);

// イベントタイプがmessage以外はスルー
if ($event->type != "message") {
    return;
}

//ここから応答
$textMessages = array(); //送信する文字列たちを格納する配列
// メッセージタイプが文字列の場合
if ($event->message->type == "text") {
  $userMessage = $event->message->text;
  $mode = replyMode($userMessage);
  //それぞれの送られてくる文字列に対して応答
  switch ($mode) {
  case "hello":
    $textMessages[] = "はい";
    break;
  case "insert_request":
    $num = explode("/", $userMessage);
    $textMessages[] = "射数:".$num[1]."的中数:".$num[0]."で登録をします";
    break;
  default:
    $textMessages[] = $event->message->text;
    $textMessages[] = "aiueo";
  }
}
//文字列以外は無視
else {
  $textMessages[] = "分からん";
  return;
}

//応答メッセージをLINE用に変換
$replyMessage = buildMessages($textMessages);

// メッセージ送信
$response = $bot->replyMessage($event->replyToken, $replyMessage);
error_log(var_export($response,true));
return;

//---------------------------------------------------------------------
function setHttpClient()
{
  $client = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('LineMessageAPIChannelAccessToken'));
  return $client;
}

function setBot($httpClient)
{
  $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('LineMessageAPIChannelSecret')]);
  return $bot;
}

//ユーザ入力が分数の形かつ分母が大きいかを調べる
function isFraction($userMessage)
{
  if ( preg_match("#^\d+/\d+$#", $userMessage, $matches) ) {
    $numbers = explode("/", $userMessage);
    if ($numbers[0] <= $numbers[1]) {
      return true;
    }
  }
  return false;
}

//ユーザメッセージに応じて対応のモードを返す
function replyMode($userMessage)
{
  if (isFraction($userMessage)) {
    return "insert_request";
  }else if ($userMessage == "こんにちは") {
    return "hello";
  }else {
    return "copy";
  }
}

//文字列の配列を引数として送信用メッセージ(LINE用)を返す
function buildMessages($textMessages)
{
  $replyMessage = new \LINE\LINEBot\MessageBuilder\MultiMessageBuilder();
  foreach($textMessages as $message){
    $a = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($message);
    $replyMessage->add($a);
  }
  return $replyMessage;
}