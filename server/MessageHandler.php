<?php
require_once __DIR__ . '/mongo.php';

class Messages
{
    private $db_handler;

    public function __construct()
    {
        $this->db_handler = new DBHandler();
    }

    /**
     * Send message to specified room.
     * @param int $room_id
     * @param string $message
     * @param WebSocket $socket
     */
    public function sendMessageToRoom($room_id, $message, WebSocket $socket)
    {
        if ($user_ids_in_room = $this->db_handler->getOnlineUsersInRoom($room_id)) {
            foreach ($user_ids_in_room as $user_id) {
                $client = $socket->getClientByUserID($user_id);
                $socket->sendData($client, $message);
            }
        }
    }

    /**
     * Sends online users present in each room as associative array to the user.
     * @param Client $client
     * @param WebSocket $socket
     */

    public function sendOnlineUserNames(Client $client, WebSocket $socket)
    {
        if ($online_users = $this->db_handler->getOnlineUserNamesOfAccessRooms($client->getUserID())) //array with key 'room_id', 'user_names'
            $socket->sendData($client, json_encode(array('message' => $online_users, 'message_type' => 'online_users'))); //send online users in each room he has permission to user who just connected.
    }

    /**
     * Sends message to all users present in rooms that $client has access to, that $client has connected.
     * @param Client $client
     * @param WebSocket $socket
     */
    public function sendUserOnlineStatus(Client $client, WebSocket $socket)
    {
        if ($unique_users_in_rooms = $this->db_handler->getUniqueUsersInRoomsOfUser($client->getUserID()))
            foreach ($unique_users_in_rooms as $uid => $val) { //send current user is online to users in rooms that the current user has permissions to.
                if ($current_client = $socket->getClientByUserID($uid)) //maybe in database user shows online but if socket connection is closed then we get NULL for current_client and senddata throws exception.
                    $socket->sendData($current_client, json_encode(array(
                        'message' => $val['user_name'],
                        'room_ids' => $val['rooms'],
                        'message_type' => 'user_connected'
                    )));
            }
    }

    /**
     * Sends message to all users present in rooms that $client has access to, that $client has connected.
     * @param Client $client
     * @param WebSocket $socket
     */
    public function sendUserOfflineStatus(Client $client, WebSocket $socket)
    {
        if ($unique_users_in_rooms = $this->db_handler->getUniqueUsersInRoomsOfUser($client->getUserID()))
            foreach ($unique_users_in_rooms as $uid => $val) { //send current user is online to users in rooms that the current user has permissions to.
                $current_client = $socket->getClientByUserID($uid);
                $socket->sendData($current_client, json_encode(array(
                    'message' => $val['user_name'],
                    'room_ids' => $val['rooms'],
                    'message_type' => 'user_disconnected'
                )));
            }
    }

    public function sendMessageHistory(Client $client, WebSocket $socket)
    {
        if ($message_history = $this->db_handler->getMessageHistoryFromRooms($client->getUserID()))
            $socket->sendData($client, json_encode(array('message' => $message_history, 'message_type' => 'message_history')));
    }

}

