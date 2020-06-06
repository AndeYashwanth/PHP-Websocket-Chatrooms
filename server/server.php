<?php

require_once __DIR__ . '/WebSocket.php';
require_once __DIR__ . '/Client.php';
require_once __DIR__ . '/MessageHandler.php';
require_once __DIR__ . '/MessageIdGenerator.php';
require_once __DIR__ . '/mongo.php';

set_time_limit(0);
$message_handler = new Messages();
$db_handler = new DBHandler();
$message_id_generator = new MessageIdGenerator();
$socket = new WebSocket("localhost", 2207);
$socket->setEnableLogging(true);

$socket->on("receive", function (Client $client, $data) use ($socket, $message_handler, $db_handler, $message_id_generator) {
    $data = json_decode($data, true);
    if ($user_name = $db_handler->checkUserPermissionToRoomAndGetUsername($client->getUserID(), $data['room_id'])) { //check if user has permission to send message to particular room.
        $unix_time_in_milli = round(microtime(true) * 1000);
        $message = json_encode(array(
            'username' => $user_name,
            'message' => $data['message'],
            'room_id' => $data['room_id'],
            'created_on' => round($unix_time_in_milli / 1000), //unix time in seconds.
            'message_type' => 'chat_message'
        ));
//        $message_id = (int)($unix_time_in_milli . sprintf("%03d", $data['room_id'])); //message id is concat of roomid with padding and unix timestampin milliseconds
        $message_id = $message_id_generator->generateMessageId($unix_time_in_milli);
        if ($db_handler->addMessageToRoom($data['room_id'], $client->getUserID(), json_decode($message, true), $message_id)) {
            $message_handler->sendMessageToRoom($data['room_id'], $message, $socket);
        }
    } else {
        $message = json_encode(array(
            'message_type' => 'error',
            'message' => 'Insufficient Permissions to Room.'
        ));
        $socket->sendData($client, $message);
    }
});
$socket->on("connect", function (Client $client) use ($message_handler, $socket, $db_handler) {
    $db_handler->insertOnlineUserInRooms($client->getUserID());
    $message_handler->sendOnlineUserNames($client, $socket);
    $message_handler->sendUserOnlineStatus($client, $socket);
    $message_handler->sendMessageHistory($client, $socket);
});

//$socket->on("receive", function (Client $client, $data) {
//});

$socket->on("send", function (Client $client, $data) {
});

$socket->on("ping", function (Client $client, $data) {
});

$socket->on("pong", function (Client $client, $data) {
});

$socket->on("disconnect", function (Client $client, $statusCode, $reason) use ($db_handler, $message_handler, $socket) {
    $db_handler->deleteOfflineUserInRooms($client->getUserID());
    $message_handler->sendUserOfflineStatus($client, $socket);
});

$socket->on("error", function ($socket, $client, $phpError, $errorMessage, $errorCode) {
    var_dump("Error: => " . implode(" => ", $phpError));
});

$socket->startServer();
