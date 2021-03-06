<?php

use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;

require_once __DIR__ . "/../vendor/autoload.php";

class ClientDBHandler
{
    private $client;
    private $chat_db;
    private $messages_collection;
    private $users_collection;
    private $rooms_collection;
    private $transactionOptions;

    /**
     * DBHandler constructor.
     * @param string $host
     * @param int $port
     */
    function __construct($host = "localhost", $port = 27017)
    {
        $this->client = new MongoDB\Client("mongodb://{$host}:{$port}", [], ["typeMap" => ['root' => 'array', 'document' => 'array']]);
        $this->chat_db = $this->client->chat;
        $this->messages_collection = $this->chat_db->messages;
        $this->users_collection = $this->chat_db->users;
        $this->rooms_collection = $this->chat_db->rooms;
        $this->transactionOptions = [
            'readConcern' => new ReadConcern(ReadConcern::LOCAL),
            'writeConcern' => new WriteConcern(WriteConcern::MAJORITY, 1000),
            'readPreference' => new ReadPreference(ReadPreference::RP_PRIMARY),
        ];
    }

    /**
     *
     * @param string $user_id
     * @return false|array Returns associative array key room_id and value room_name.
     */
    public function getRoomIDsNamesAccessToUser($user_id)
    {
        try {
            $rooms_access = $this->users_collection->findOne(['_id' => $user_id], ['projection' => ['_id' => 0, 'rooms_access' => 1]])['rooms_access'];
            $result = $this->rooms_collection->find(['_id' => ['$in' => $rooms_access]], ['projection' => ['room_name' => 1]]);
            $rooms = array();
            foreach ($result as $room) {
                $rooms[$room['_id']] = $room['room_name'];
            }
            return $rooms;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param string $user_id
     * @param string $user_name
     * @param array $rooms_access
     * @return bool false if exception, 0 if not inserted, 1 if insert successful.
     */
    public function createUser($user_id, $user_name, $rooms_access)
    {
        try {
            $result = $this->users_collection->insertOne(['_id' => $user_id, 'user_name' => $user_name, 'rooms_access' => $rooms_access]);
            return $result->getInsertedCount() === 1;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getUserDetails($user_id)
    {
        try {
            return $this->users_collection->findOne(['_id' => $user_id]);
        } catch (Exception $e) {
            return false;
        }
    }


}