<?php
/*
 * @todo Separate the methods performing different operations like inserts, edits etc by putting in different classes.
 */

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
//    const BUCKET_SIZE = 1000 * 60 * 60 * 24 * 10;

    /**
     * DBHandler constructor.
     * @param string $host
     * @param int $port
     */
    function __construct($host = "localhost", $port = 27017)
    {
        $this->client = new MongoDB\Client("mongodb://{$host}:{$port}", [], ["typeMap" => ['root' => 'array', 'document' => 'array']]);
        $this->chat_db = $this->client->selectDatabase("chat");
        $this->messages_collection = $this->chat_db->selectCollection("messages");
        $this->users_collection = $this->chat_db->selectCollection("users");
        $this->rooms_collection = $this->chat_db->selectCollection("rooms");
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
                $count += $this->addRoom($i, "Room $i", 10);
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
        echo $message . " at " . $method_name . "()\n";
    }

    /**
     * Returns true if insert is success, else returns false.
     * @param int $room_id
     * @param string $room_name
     * @param int $rate_limit
     * @return bool
     * @todo Only authorized person should be able add room. require $user_id in input.
     */
    private function addRoom($room_id, $room_name, $rate_limit = 10)
    {
        try {
            $result = $this->rooms_collection->insertOne([
                '_id' => $room_id,
                'room_name' => $room_name,
                'created_on' => round(microtime(true) * 1000),
                'banned_users' => [],
                'rate_limit' => $rate_limit,
                'online_users' => []
            ]);
            return $result->getInsertedCount() === 1;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    /**
     * Require user id to check permission to be able to delete room.
     * @param string $user_id
     * @param int $room_id
     * @todo Complete the function.
     */
    public function deleteRoom($user_id, $room_id)
    {

    }

    /**
     * @param int $nRooms
     * @return bool
     */
    private function clearOnlineUsersInRooms($nRooms = 5)
    {
        try {
            $room_ids = range(1, 5);
            $result = $this->rooms_collection->updateMany(['_id' => ['$in' => $room_ids]], ['$set' => ['online_users' => []]]);
            if ($result->getModifiedCount() == $nRooms || $result->getMatchedCount() == $nRooms)
                return true;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    /**
     * Returns true if collection exists, otherwise false.
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
     * @return bool returns true if success, false if failure.
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
     * Returns associative array with key room_id and value array of usernames if user exists and room he has access to exists.
     * @param string $user_id
     * @param array $rooms_access
     * @return array|false
     */
    public function getOnlineUserNamesOfAccessRooms($rooms_access)
    {
        try {
            $result = $this->rooms_collection->aggregate([
                ['$match' => ['_id' => ['$in' => $rooms_access]]],
                ['$project' => ['online_usernames' => '$online_users.user_name']]
            ]); //output of form _id => int, online_users => array of usernames.

            $online_users = array();
            foreach ($result as $room) {
                $online_users[$room['_id']] = $room['online_usernames'];
            }

            return $online_users;

        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    /**
     * unique users(common) in all rooms for which the user has access to.
     * @param string $user_id
     * @return array|bool
     */
    public function getUniqueUsersInRoomsOfUser($user_id)
    {
        try {
            if ($rooms_access_arr = $this->users_collection->findOne(['_id' => $user_id], ['projection' => ['_id' => 0, 'rooms_access' => 1]])['rooms_access']) {
                $result = $this->rooms_collection->aggregate([
                    ['$match' => ['_id' => ['$in' => $rooms_access_arr]]],
                    ['$project' => ['online_users' => 1]],
                    ['$unwind' => '$online_users'],
                    ['$match' => ['$expr' => ['$ne' => ['$online_users.user_id', $user_id]]]],
                    ['$group' => ['_id' => '$online_users.user_id', 'rooms' => ['$push' => '$_id'], 'user_name' => ['$first' => '$online_users.user_name']]]
                ]);

//            $result = $this->users_collection->aggregate([
//                ['$match' => ['_id' => $user_id]],
//                ['$lookup' => ['from' => 'rooms', 'localField' => 'rooms_access', 'foreignField' => '_id', 'as' => 'rooms']],
//                ['$unwind' => '$rooms'],
//                ['$lookup' => ['from' => 'users', 'localField' => 'rooms.online_users', 'foreignField' => '_id', 'as' => 'users']],
//                    ['$unwind' => '$online_users'],
//                    ['$match' => ['$expr' => ['$ne' => ['$_id', '$users._id']]]],
//                    ['$project' => [
//                        '_id' => 0,
//                        'room_id' => '$rooms._id',
//                        'user_id' => '$users._id',
//                        'user_name' => '$users.user_name',
//                        'rooms_intersect' => ['$setIntersection' => ['$rooms_access', '$users.rooms_access']]]
//                    ],
//                    ['$group' => ['_id' => '$user_id', 'user_name' => ['$first' => '$user_name'], 'rooms' => ['$first' => '$rooms_intersect']]]
//                ]);
                $unique_users = array();
                foreach ($result as $user) {
                    $unique_users[$user['_id']] = array('user_name' => $user['user_name'], 'rooms' => $user['rooms']);
                }
                return $unique_users;
            }
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    public function getMessageHistoryFromRooms($rooms_access, $skip = 0, $limit = 50)
    { // index (room_id, _id) on messages collection is used.
//        https://stackoverflow.com/questions/33458107/limit-and-sort-each-group-by-in-mongodb-using-aggregation
//        https://docs.mongodb.com/manual/core/aggregation-pipeline-optimization/#agg-sort-skip-limit-sequence
        try {
            $message_history = array();
            foreach ($rooms_access as $room_id) {
                $result = $this->messages_collection->aggregate([
                    ['$match' => ['room_id' => $room_id]],
                    ['$sort' => ['_id' => -1]],
                    ['$skip' => $skip],
                    ['$limit' => $limit],
                    ['$set' => ['message_id' => '$_id']],
                    ['$unset' => ['stats', '_id']],
                    ['$sort' => ['_id' => 1]],
                    ['$group' => ['_id' => '$room_id', 'messages' => ['$push' => '$$ROOT']]],
                    ['$unset' => 'messages.room_id']
                ]);
                foreach ($result as $room_messages) {
                    $message_history[$room_messages['_id']] = $room_messages['messages'];
                }
            }

            return $message_history;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    /**
     * Returns array of json objects with keys user_id, user_name for given room_id.
     * @param int $room_id
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
     * Insert user id, user name in online_users array of each room to which the user has access to.
     * @param string $user_id
     * @param string $user_name
     * @param array $rooms_access
     * @return bool
     */
    public function insertOnlineUserInRooms($user_id, $user_name, $rooms_access): bool
    {
        try {
            $result = $this->rooms_collection->updateMany(
                ['_id' => ['$in' => $rooms_access]],
                ['$addToSet' => ['online_users' => ['user_id' => $user_id, 'user_name' => $user_name]]]
            );
            return $result->getModifiedCount() === 1;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    /**
     * Removes user id from online_users array for given room in rooms collection.
     * @param string $user_id
     * @param string $user_name
     * @param array $rooms_access
     * @return bool
     */
    public function deleteOfflineUserInRooms(string $user_id, string $user_name, array $rooms_access): bool
    {
        try {
            $result = $this->rooms_collection->updateMany(
                ['_id' => ['$in' => $rooms_access]],
                ['$pull' => ['online_users' => ['user_id' => $user_id, 'user_name' => $user_name]]]
            );
            return true;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    /**
     * creates a message document in messages collection and pushes the id in message_ids of a room in rooms collection.
     * @param int $message_id
     * @param int $room_id
     * @param string $user_id
     * @param string $user_name
     * @param string $message
     * @return bool
     */
    public function addMessageToRoom($message_id, $room_id, $user_id, $user_name, $message): bool
    {
        try {
            $room_id = $room_id + 0; //room_id is integer
            $result = $this->messages_collection->insertOne([
                '_id' => $message_id,
                'room_id' => $room_id,
//                'bucket' => (int)(($message_id >> 21) / self::BUCKET_SIZE), //10 days of messages will be stored in at most 1 bucket.
                'from' => ['user_id' => $user_id, 'user_name' => $user_name],
                'message' => $message
            ]);
            return $result->getInsertedCount() === 1;
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }

    /**
     * @param int $room_id
     * @param string $user_id user id which belongs to the message
     * @param string $by_user_id user id who is editing the message
     * @param int $message_id
     * @param string $message
     * @return bool
     * @todo Check if $by_user_id has permission to edit the message in the room.
     */
    public function editMessageInMessageCollection($room_id, $user_id, $by_user_id, $message_id, $message): bool
    {
        try {
            if ($user_id === $by_user_id) { //User can edit his own message.
                $room_id = $room_id + 0; //room_id is integer. implicit conversion.
                $result = $this->messages_collection->updateOne(
                    ['room_id' => $room_id, 'from.user_id' => $user_id, '_id' => $message_id],
                    ['messages' => $message]
                );
                return $result->getModifiedCount() === 1;
            }
        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage(), __FUNCTION__);
        }
        return false;
    }
}