<?php
require_once __DIR__ . '/mongo.php';

class Messages
{
    private $db_handler;

    public function __construct()
    {
        $this->db_handler = new DBHandler("localhost", 60000);
    }

    /**
     * Send message to all online users in a given room.
     * @param int $room_id
     * @param string $message
     * @param WebSocket $socket
     */
    public function sendMessageToRoom($room_id, $message, WebSocket $socket)
    {
        if ($online_users_in_room = $this->db_handler->getOnlineUsersInRoom($room_id))
            foreach ($online_users_in_room as $user)
                if ($clients = $socket->getClientByUserID($user['user_id']))
                    foreach ($clients as $client)
                        $socket->sendData($client, $message);


    }

    /**
     * Sends online users present in each room as associative array to the user.
     * @param Client $client
     * @param WebSocket $socket
     * @param array $rooms_access
     */

    public function sendOnlineUserNames(Client $client, WebSocket $socket, $rooms_access)
    {
        if ($online_users = $this->db_handler->getOnlineUserNamesOfAccessRooms($rooms_access)) //array with key 'room_id', 'user_names'
            $socket->sendData($client, json_encode(array('message' => $online_users, 'message_type' => 'online_users'))); //send online users in each room he has permission to user who just connected.
    }

    /**
     * Sends message to all users present in rooms that $client has access to, that $client has connected.
     * @param Client $client
     * @param WebSocket $socket
     * @param string $user_name
     */
    public function sendUserOnlineStatus(Client $client, WebSocket $socket, string $user_name)
    {
        if (count($socket->getClientByUserID($client->getUserID())) > 1) // check if client has user id set (or) another instance of same user id is already connected.
            return;
        if ($unique_users_in_rooms = $this->db_handler->getUniqueUsersInRoomsOfUser($client->getUserID()))
            foreach ($unique_users_in_rooms as $uid => $val) { //send current user is online to users in rooms that the current user has access to.
                if ($current_client = $socket->getClientByUserID($uid)) //maybe in database user shows online but if socket connection is closed then we get NULL for current_client and senddata throws exception.
                    foreach ($current_client as $client)
                        $socket->sendData($client, json_encode(array(
                            'message' => $user_name,
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
    public function sendUserOfflineStatus(Client $client, WebSocket $socket, string $user_name)
    {
        if (count($socket->getClientByUserID($client->getUserID())) > 1) // As long as at least one instance of client is connected, don't notify others when disconnect.
            return;
        if ($unique_users_in_rooms = $this->db_handler->getUniqueUsersInRoomsOfUser($client->getUserID()))
            foreach ($unique_users_in_rooms as $uid => $val) { //send current user is online to users in rooms that the current user has permissions to.
                if ($current_client = $socket->getClientByUserID($uid))
                    foreach ($current_client as $client)
                        $socket->sendData($client, json_encode(array(
                            'message' => $user_name,
                            'room_ids' => $val['rooms'],
                            'message_type' => 'user_disconnected'
                        )));
            }
    }

    public function sendMessageHistory(Client $client, WebSocket $socket, array $rooms_access)
    {
        if ($message_history = $this->db_handler->getMessageHistoryFromRooms($rooms_access))
            $socket->sendData($client, json_encode(array('message' => $message_history, 'message_type' => 'message_history')));
    }

}

