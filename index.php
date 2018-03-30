<?php
require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('Asia/Tokyo');

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

foreach ($json->events as $event) {
    //ポストバックイベントだった場合
    if ($event->type == "postback") {
        if ($event->postback->data == "no" || $event->source->type == "group") {
            return; //dataがnoもしくはグループからの送信なら何もしない
        } else { //yesの処理
            $data = explode("/", $event->postback->data);
            $dateTime = new DateTime($data[2]);
            //データベースに接続
            try {
                $url = parse_url(getenv('DATABASE_URL'));
                $dsn = sprintf('pgsql:host=%s;dbname=%s', $url['host'], substr($url['path'], 1));
                $pdo = new PDO($dsn, $url['user'], $url['pass']);
                $userID = $event->source->userId;
                $hit = $data[0];
                $atmpt = $data[1];
                $buf = explode(" ", $dateTime->format('Y-m-d H:i:s'));
                $date = $buf[0];
                $time = $buf[1];
                $stmt = $pdo->prepare("insert into recor values(:userID, :hit, :atmpt, :date, :time)");
                $stmt->bindParam(':userID', $userID, PDO::PARAM_STR);
                $stmt->bindParam(':hit', $hit, PDO::PARAM_INT);
                $stmt->bindParam(':atmpt', $atmpt, PDO::PARAM_INT);
                $stmt->bindParam(':date', $date, PDO::PARAM_STR);
                $stmt->bindParam(':time', $time, PDO::PARAM_STR);
                $stmt->execute();
            } catch (PDOException $e) {
                echo "PDO Error:".$e->getMessage()."\n";
                die();
            }
            $dns = null;
            //メッセージ送信
            $message = array("射数:".$atmpt."\n的中数:".$hit."\nで登録しました\n".$dateTime->format('Y-m-d H:i:s'));
            $bot->replyMessage($event->replyToken, buildMessages($message));
            return;
        }
    }
    // イベントタイプがmessage以外はスルー
    else if ($event->type != "message") {
            return;
    }
    
    
    //ここから応答
    $textMessages = array(); //送信する文字列たちを格納する配列
    // メッセージタイプが文字列の場合
    if ($event->message->type == "text") {
        $userMessage = $event->message->text;
        $mode = replyMode($userMessage);
        //それぞれのモードに対して応答
        switch ($mode) {
        case "hello":
            $textMessages[] = "はい";
            break;
        case "insert_request":
            $num = explode("/", $userMessage);
            $now = date('Y-m-d H:i:s');
            $confirmMessage = "射数:".$num[1]."\n的中数:".$num[0]."\nで登録をします\n".$now;
            //はい ボタン
            $yes_post = new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder("はい", $userMessage."/".$now);
            //いいえボタン
            $no_post = new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder("いいえ", "no");
            //Confirmテンプレート
            $confirm = new LINE\LINEBot\MessageBuilder\TemplateBuilder\ConfirmTemplateBuilder($confirmMessage, [$yes_post, $no_post]);
            // Confirmメッセージを作る
            $replyMessage = new LINE\LINEBot\MessageBuilder\TemplateMessageBuilder("メッセージ", $confirm);
            $response = $bot->replyMessage($event->replyToken, $replyMessage);
            error_log(var_export($response,true));
            return;
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
    
    //メッセージ送信
    $response = $bot->replyMessage($event->replyToken, $replyMessage);
    error_log(var_export($response,true));
}
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