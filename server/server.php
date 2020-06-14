<?php

require_once __DIR__ . '/WebSocket.php';
require_once __DIR__ . '/Client.php';
require_once __DIR__ . '/MessageHandler.php';
require_once __DIR__ . '/MessageIdGenerator.php';
require_once __DIR__ . '/mongo.php';

set_time_limit(0);

$message_handler = new Messages();
$db_handler = new DBHandler("localhost", 60000);
$message_id_generator = new MessageIdGenerator();
$socket = new WebSocket("localhost", 2207);
$socket->setEnableLogging(true);

$socket->on("receive", function (Client $client, $data) use ($socket, $message_handler, $db_handler, $message_id_generator) {

    $data = json_decode($data, true); //data is message sent by the client.
    if ($user_name = $db_handler->checkUserPermissionToRoomAndGetUsername($client->getUserID(), $data['room_id'])) { //check if user has permission to send message to particular room.
        $unix_time_in_milli = round(microtime(true) * 1000);
//        $message_id = (int)($unix_time_in_milli . sprintf("%03d", $data['room_id'])); //message id is concat of roomid with padding and unix timestampin milliseconds
        $message_id = $message_id_generator->generateMessageId($unix_time_in_milli);
        $message = json_encode(array(
            'message_id' => $message_id,
            'username' => $user_name,
            'message' => $data['message'],
            'room_id' => $data['room_id'],
            'message_type' => 'chat_message'
        ));

        if ($db_handler->addMessageToRoom($message_id, $data['room_id'], $client->getUserID(), $user_name, $data['message'])) {
            $message_handler->sendMessageToRoom($data['room_id'], $message, $socket);
        } else{
            $message = json_encode(array(
                'message_type' => 'error',
                'message' => 'Unsuccessful message delivery.'
            ));
            $socket->sendData($client, $message);
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
    $user_from_db = $db_handler->getUserDetails($client->getUserID());

    $db_handler->insertOnlineUserInRooms($client->getUserID(), $user_from_db['user_name'], $user_from_db['rooms_access']);
    $message_handler->sendOnlineUserNames($client, $socket, $user_from_db['rooms_access']);
    $message_handler->sendUserOnlineStatus($client, $socket, $user_from_db['user_name']); // send user online status to others.
    $message_handler->sendMessageHistory($client, $socket, $user_from_db['rooms_access']);
});

$socket->on("send", function (Client $client, $data) {
});

$socket->on("ping", function (Client $client, $data) {
});

$socket->on("pong", function (Client $client, $data) {
});

$socket->on("disconnect", function (Client $client, $statusCode, $reason) use ($db_handler, $message_handler, $socket) {
    if ($client->getUserID()) { //When unauthorized user(not logged in) tries to connect and he is disconnected, he doesn't have any user_id.
        $user_from_db = $db_handler->getUserDetails($client->getUserID());

        $db_handler->deleteOfflineUserInRooms($client->getUserID(), $user_from_db['user_name'], $user_from_db['rooms_access']);
        $message_handler->sendUserOfflineStatus($client, $socket, $user_from_db['user_name']);
    }
});

$socket->on("error", function ($socket, $client, $phpError, $errorMessage, $errorCode) {
    var_dump("Error: => " . implode(" => ", $phpError));
});

$socket->startServer();
