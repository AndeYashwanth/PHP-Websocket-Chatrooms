<?php

use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;

require __DIR__ . "/../vendor/autoload.php";

class DBHandler
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
        $this->init();
    }

    private function init()
    {
        if (!$this->checkCollectionExists('rooms')) {
            $this->initRooms();
        }
        $this->clearOnlineUsersInRooms();
    }

    private function initRooms($nRooms = 5)
    {
        try {
            $count = 0;
            for ($i = 1; $i <= $nRooms; $i++) {
                $result = $this->rooms_collection->insertOne(['_id' => $i, 'room_name' => "Room $i", 'blocked_users' => [], 'message_ids' => [], 'rate_limit' => 1, 'online_users' => []]);
                $count += $result->getInsertedCount();
            }
            if ($count == $nRooms) {
                $this->log("Init rooms success", __FUNCTION__);
                return true;
            }
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    private function log($message, $method_name)
    {
        echo $message . " at " . $method_name . "\n";
    }

    private function addRoom($room_id, $room_name, $rate_limit = 1)
    {
        try {
            $result = $this->rooms_collection->insertOne(['_id' => $room_id, 'room_name' => $room_name, 'blocked_users' => [], 'message_ids' => [], 'rate_limit' => $rate_limit, 'online_users' => []]);
            return $result->getInsertedCount();
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    private function clearOnlineUsersInRooms($nRooms = 5)
    {
        try {
            $room_ids = range(1, 5);
            $result = $this->rooms_collection->updateMany(['_id' => ['$in' => $room_ids]], ['$set' => ['online_users' => []]]);
            if ($result->getModifiedCount() == $nRooms || $result->getMatchedCount() == $nRooms)
//                echo "clear online users success.\n";
                return true;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    /**
     * returns true if collection exists, otherwise false.
     * @param string $name Name of the collection.
     * @return bool
     */
    private function checkCollectionExists($name)
    {
        $result = $this->chat_db->listCollections(['filter' => ['name' => $name]]);
        foreach ($result as $item) {
            return true;
        }
        return false;
    }

    /**
     * @param string $user_id
     * @param string $user_name
     * @param array $rooms_access
     * @return bool true if success, false if failure.
     */

    public function createUser($user_id, $user_name, $rooms_access)
    {
        try {
            $result = $this->users_collection->insertOne(['_id' => $user_id, 'user_name' => $user_name, 'rooms_access' => $rooms_access]);
            return $result->getInsertedCount() === 1;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
            return false;
        }
    }

    public function getUserDetails($user_id)
    {
        try {
            return $this->users_collection->findOne(['_id' => $user_id]);
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
            return false;
        }
    }

    /**
     * Returns username if user has access to given room.(Should be separate methods but reduces one db call).
     * @param string $user_id
     * @param int $room_id
     * @return false|string
     */
    public function checkUserPermissionToRoomAndGetUsername($user_id, $room_id)
    {
        try {
            $room_id = $room_id + 0;
            return $this->users_collection->findOne(
                ['_id' => $user_id, 'rooms_access' => ['$in' => [$room_id]]],
                ['projection' => ['_id' => 0, 'user_name' => 1]]
            )['user_name'];
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
            return false;
        }
    }

    /**
     * Returns array of room id's. Returns false if there is an exception.
     * @param string $user_id
     * @return false|array
     */
    public function getRoomIDsOfUser($user_id)
    {
        try {
            $result = $this->users_collection->findOne(['_id' => $user_id], ['projection' => ['_id' => 0, 'rooms_access' => 1]]);
            return $result['rooms_access'];
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
            return false;
        }
    }

    /**
     *
     * @param string $user_id
     * @return false|array Returns associative array key room_id and value room_name.
     */
    public function getRoomIDsNamesAccessToUser($user_id)
    {
        try {
            $result = $this->users_collection->aggregate([
                ['$match' => ['_id' => $user_id]],
                ['$lookup' => ['from' => 'rooms', 'localField' => 'rooms_access', 'foreignField' => '_id', 'as' => 'rooms']],
                ['$unwind' => '$rooms'],
                ['$project' => ['_id' => 0, 'room_id' => '$rooms._id', 'room_name' => '$rooms.room_name']]
            ]);//array of associative arrays with keys 'room_id', 'room_name'
            $rooms = array();
            foreach ($result as $room) {
                $rooms[$room['room_id']] = $room['room_name'];
            }
            return $rooms;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
            return false;
        }
    }

    public function getOnlineUserNamesOfAccessRooms($user_id)
    {
        try {
            $result = $this->users_collection->aggregate([
                ['$match' => ['_id' => $user_id]],
                ['$lookup' => ['from' => 'rooms', 'localField' => 'rooms_access', 'foreignField' => '_id', 'as' => 'rooms']],
                ['$unwind' => '$rooms'],
                ['$lookup' => ['from' => 'users', 'localField' => 'rooms.online_users', 'foreignField' => '_id', 'as' => 'users']],
                ['$project' => ['_id' => 0, 'room_id' => '$rooms._id', 'users' => '$users.user_name']]
            ]); //output of form room_id => int, users => array of usernames.

            $online_users = array();
            foreach ($result as $room) {
                $online_users[$room['room_id']] = $room['users'];
            }

            return $online_users;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    public function getUniqueUsersInRoomsOfUser($user_id)
    {
        try {
            $result = $this->users_collection->aggregate([
                ['$match' => ['_id' => $user_id]],
                ['$lookup' => ['from' => 'rooms', 'localField' => 'rooms_access', 'foreignField' => '_id', 'as' => 'rooms']],
                ['$unwind' => '$rooms'],
                ['$lookup' => ['from' => 'users', 'localField' => 'rooms.online_users', 'foreignField' => '_id', 'as' => 'users']],
                ['$unwind' => '$users'],
                ['$match' => ['$expr' => ['$ne' => ['$_id', '$users._id']]]],
                ['$project' => [
                    '_id' => 0,
                    'room_id' => '$rooms._id',
                    'user_id' => '$users._id',
                    'user_name' => '$users.user_name',
                    'rooms_intersect' => ['$setIntersection' => ['$rooms_access', '$users.rooms_access']]]
                ],
                ['$group' => ['_id' => '$user_id', 'user_name' => ['$first' => '$user_name'], 'rooms' => ['$first' => '$rooms_intersect']]]
            ]);
            $unique_users = array();
            foreach ($result as $user) {
                $unique_users[$user['_id']] = array('user_name' => $user['user_name'], 'rooms' => $user['rooms']);
            }
            return $unique_users;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    public function getMessageHistoryFromRooms($user_id, $n = 50)
    {
        try {
            $result = $this->users_collection->aggregate([
                ['$match' => ['_id' => $user_id]],
                ['$project' => ['_id' => 0, 'rooms_access' => 1]],
                ['$lookup' => ['from' => 'rooms', 'localField' => 'rooms_access', 'foreignField' => '_id', 'as' => 'rooms']],
                ['$unwind' => '$rooms'],
                ['$project' => ['room_id' => '$rooms._id', 'message_ids' => ['$slice' => ['$rooms.message_ids', -$n]]]],
                ['$lookup' => ['from' => 'messages', 'localField' => 'message_ids', 'foreignField' => '_id', 'as' => 'messages']],
                ['$unset' => ['message_ids', 'messages._id', 'messages.stats']],
                ['$unwind' => '$messages'],
                ['$lookup' => ['from' => 'users', 'localField' => 'messages.from', 'foreignField' => '_id', 'as' => 'user']],
                ['$unwind' => '$user'],
                ['$set' => ['messages.username' => '$user.user_name']],
                ['$unset' => 'messages.from'],
                ['$group' => ['_id' => '$room_id', 'room_id' => ['$first' => '$room_id'], 'messages' => ['$push' => '$messages']]],
                ['$unset' => '_id']
            ]);

            $message_history = array();
            foreach ($result as $room) {
                $message_history[$room['room_id']] = $room['messages'];
            }
            return $message_history;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    /**
     * Returns array of online userids.
     * @param string $room_id
     * @return bool|mixed
     */
    public function getOnlineUsersInRoom($room_id)
    {
        try {
            $room_id = $room_id + 0;
            $result = $this->rooms_collection->findOne(['_id' => $room_id], ['projection' => ['_id' => 0, 'online_users' => 1]]);
            return $result['online_users'];
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    /**
     * Insert userid in online_users array of each room to which the user has access to.
     * @param string $user_id
     * @return bool
     */
    public function insertOnlineUserInRooms($user_id)
    {
        try {
            $rooms_access = $this->getRoomIDsOfUser($user_id);
            $result = $this->rooms_collection->updateMany(
                ['_id' => ['$in' => $rooms_access]],
                ['$addToSet' => ['online_users' => $user_id]]
            );
            return $result->getModifiedCount() === 1;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    /**
     * @param string $user_id
     * @return bool
     */
    public function deleteOfflineUserInRooms($user_id)
    {
        try {
            $rooms_access = $this->getRoomIDsOfUser($user_id);
            $result = $this->rooms_collection->updateMany(
                ['_id' => ['$in' => $rooms_access]],
                ['$pull' => ['online_users' => $user_id]]
            );
            return true;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    /**
     * creates a message document in messages collection and pushes the id in message_ids of a room in rooms collection.
     * @param int $room_id
     * @param string $user_id
     * @param array $message
     * @param int $message_id
     * @return bool
     */
    public function addMessageToRoom($room_id, $user_id, $message, $message_id)
    {
        $session = $this->client->startSession();
        $session->startTransaction($this->transactionOptions);
        try {
            $room_id = $room_id + 0; //room_id is integer
            $result = $this->messages_collection->insertOne([
                '_id' => $message_id,
                'from' => $user_id,
                'message' => $message['message'],
                'created_on' => $message['created_on']
            ]);
            if ($result->getInsertedCount() === 1) {
                $result = $this->rooms_collection->updateOne(['_id' => $room_id], ['$push' => ['message_ids' => $message_id]]);
                $session->commitTransaction();
                return true;
            }
            $session->abortTransaction();
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
            $session->abortTransaction();
        }
        return false;
    }
}