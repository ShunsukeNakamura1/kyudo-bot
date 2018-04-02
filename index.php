<?php
require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('Asia/Tokyo');

error_log("start");

// POSTを受け取る
$postData = file_get_contents('php://input');
error_log($postData);

// jeson化
$json = json_decode($postData);

// ChannelAccessTokenとChannelSecret設定
$httpClient = setHttpClient();
$bot = setBot($httpClient);

foreach ($json->events as $event) {
    //ポストバックイベントだった場合
    if (isPostback($event)) {
        error_log(var_export($event, true));
        if (isGroup($event)) { //グループからの送信なら何もしない
            return;
        }
        $data = explode("/", $event->postback->data);
        if ($data[0] == "no" ) { //noなら時間の更新だけ行う
            $dateTime = $data[1];
            try { //データベースに接続
                $pdo = connectDataBase();
                $userID = $event->source->userId;
                $buf = explode(" ", $dateTime);
                $date = $buf[0];
                $time = $buf[1];
                $stmt = $pdo->prepare("update record set time=:time where userid = :userID and date=:date");
                $stmt->bindParam(':time', $time, PDO::PARAM_STR);
                $stmt->bindParam(':userID', $userID, PDO::PARAM_STR);
                $stmt->bindParam(':date', $date, PDO::PARAM_STR);
                $stmt->execute();
            } catch (PDOException $e) {
                echo "PDO Error:".$e->getMessage()."\n";
                die();
            }
            $pdo = null;
            $stmt = null;
            //メッセージ送信
            $message = array("キャンセルしました\n".$dateTime);
            $bot->replyMessage($event->replyToken, buildMessages($message));
            return; 
        } else { //yesならレコードの登録を行う
            $dateTime = $data[2];
            try { //データベースに接続
                $pdo = connectDataBase();
                $userID = $event->source->userId;
                $hit = $data[0];
                $atmpt = $data[1];
                $buf = explode(" ", $dateTime);
                $date = $buf[0];
                $time = $buf[1];
                //リクエストがあったレコードの日にすでにレコードがあるか調べる
                $stmt = $pdo->prepare("select * from record where userid = :userID and date=:date");
                $stmt->bindParam(':userID', $userID, PDO::PARAM_STR);
                $stmt->bindParam(':date', $date, PDO::PARAM_STR);
                $stmt->execute();
                if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    //登録済みだった場合レコードを更新
                    $hit += $result['hit'];
                    $atmpt += $result['atmpt'];
                    $stmt = $pdo->prepare("update record set hit=:hit, atmpt=:atmpt, time=:time where userid = :userID and date=:date");
                } else {
                    //登録されていなかった場合レコードを挿入
                    $stmt = $pdo->prepare("insert into record values(:userID, :hit, :atmpt, :date, :time)");
                }
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
            $pdo = null;
            $stmt = null;
            //メッセージ送信
            $message = array("登録しました\n今日の記録は\n射数:".$atmpt."\n的中数:".$hit."\nです\n".$dateTime);
            $bot->replyMessage($event->replyToken, buildMessages($message));
            return;
        }
    }
    // イベントタイプがmessage以外はスルー
    else if (!isMessage($event)) {
        return;
    }
    
    //ここから応答
    $textMessages = array(); //送信する文字列たちを格納する配列
    // メッセージタイプが文字列の場合
    if (isMessage_Text($event)) {
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
            $no_post = new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder("いいえ", "no/".$now);
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
function setHttpClient(): \LINE\LINEBot\HTTPClient\CurlHTTPClient
{
    $client = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('LineMessageAPIChannelAccessToken'));
    return $client;
}

function setBot(\LINE\LINEBot\HTTPClient\CurlHTTPClient $httpClient): \LINE\LINEBot
{
    $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('LineMessageAPIChannelSecret')]);
    return $bot;
}
//データベース接続
function connectDataBase(): PDO
{
    $url = parse_url(getenv('DATABASE_URL'));
    $dsn = sprintf('pgsql:host=%s;dbname=%s', $url['host'], substr($url['path'], 1));
    $pdo = new PDO($dsn, $url['user'], $url['pass']);
    return $pdo;
}

function isPostback($event): bool
{
    if ($event->type == "postback") {
        return true;
    } else {
        return false;
    }
}
function isMessage($event): bool
{
    if ($event->type == "message") {
        return true;
    } else {
        return false;
    }
}
function isMessage_Text($event): bool
{
    if($event->message->type == "text") {
        return true;
    } else {
        return false;
    }
}
function isGroup($event): bool
{
    if ($event->source->type == "group") {
        return true;
    } else {
        return false;
    }
}

//登録しようとしているデータが新しいもの(登録済みでない or Noが押されてない)か調べる
//NEW    :その日初めてのデータ
//OLD    :最新のものではないデータ
//UPDATE :最新のデータ(更新する)
function isNewData($pdo, $userID, $newDateTime)
{
    //時間を見て調べる
    $stmt = $pdo->prepare("select * from record where userid = :userID and date=:date");
}

//ユーザ入力が分数の形かつ分母が大きいかを調べる
function isFraction($userMessage): bool
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