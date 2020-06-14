# Simple chatrooms using PHP websockets and mongodb
Websocket server(server/Websocket.php and server/Client.php) is forked from https://github.com/heminei/php-websocket and modified according to the requirements.

## Features
- Authentication using jwt cookie instead of php sessions.
- Supports multiple rooms.
- Display online users for each room on the client.
- Scroll positions between rooms will be preserved when switching between rooms(client side).
- Application works with sharding collection in mongodb.
- Deploy sharded clusters using docker containers.

## Requirements
- [Mongodb](https://www.mongodb.com/try/download/community)
- [Composer](https://getcomposer.org/doc/00-intro.md#installation-windows) (to download required php libraries)
- Mongodb [extension](https://docs.mongodb.com/drivers/php#installation) for php
- Docker (optional for sharding)

## Installation
1. Clone the repo.
    ```
    git clone https://github.com/AndeYashwanth/PHP-Websocket-Chatrooms
    cd PHP-Websocket-Chatrooms/
    ```
1. Install dependencies from composer.json
    ```
    composer install
    ```

### Sharding(Optional)

#### Initialize shards

Folder [Sharding/](https://github.com/AndeYashwanth/Mongodb-Sharding-Docker) is a submodule for this repo. Go through it's readme to create and configure docker instances of shards.(Its very simple)

#### Shard database

In order to shard a collection, first you need to shard a database.

1. Connect to query router(mongos)

   ```
   mongo --port 60000
   ```

2. Select chat database

   ```
   use chat
   ```

3. enable sharding for chat db

   ```
   sh.enableSharding("chat")
   ```

#### Create Indexes

```
db.messages.createIndex({"room_id":1, "from.user_id":1})
```

```
db.messages.createIndex({"room_id":1, "_id":1})
```

#### Shard messages collection

```
sh.shardCollection("chat.messages", {"room_id":1, "from.user_id":1})
```



## Running
1. start server.php in server/server.php
    ```
    php server/server.php
    ```
1. open login.php in client/login.php and login/register. User will be created if he/she doesn't exist.
1. You will be redirected to client/client.php after successful login/sign-up.


## Known Issues
- Pressing shift+enter to increase text-box size on the client will lag.
- Upon testing a single client can send upto 80 messages/sec. Not sure if it is true for single client at a time or multiple clients.
- In mongodb you a lookup cannot be done to another collection which is sharded from a collection which is unsharded/sharded. So seperate queries need to be made instead of using $lookup.\
   Affected Collections:-
   - messages :
        - Problem: 'from' field stores json with 'user_name' and 'user_id' fields. So if a user changes his 'user_name' it should be updated in every message he sent till date. 
        - Solution: Shard key for messages collection is used as (room_id, from.user_id).
   - rooms :
        - Problem: 'online_users' stores json similar to messages.from
        - Solution: When user updates username, update the online_users array for every room he has access to.
- Due to limitation of unsigned 64bit integer, message_ids are only 63 bits. Somewhat similar to [twitter snowflake](https://github.com/twitter-archive/snowflake/tree/snowflake-2010).
    - 42 bit timestamp in milli seconds
    - 12 bit server id
    - 9 bit increment (i.e., for each ms server can generate upto 2<sup>9</sup> message ids)
- $server_id in server/MessageIdGenerator.php should be manually set between 0 and 2<sup>12</sup>-1 when used in distributed environment.
- Some features like displaying online users on client might break when not used with single app server.

## Upcoming features.
- Add password authentication to mongo instances in docker containers.
- Edit message.(to be implemented on client side)
- Upload files(upload maybe handled and stored by another service, and just the url is stored in DB).
- Delivery report.
- No.of users seen report.
- ~~Making application work with sharding in mongodb.~~ 
- Support application to deploy on multiple instances of web servers.
- Rate limit number of messages/sec for each room.
- ~~Deploy sharded clusters using docker containers.~~


## Database Schema
Database: chat

- users collection
    ```
    {
        "_id": String,
        "user_name": String,
        "rooms_access":["$rooms._id"] //to which chat room _ids user has access to.
    }
    ```

- rooms collection
    ```
    {
        "_id": int, //room_id
        "room_name": String,
        "banned_users":["$users._id"], //_id of users banned
        "rate_limit": int, // messages/sec similar to discord.
        "online_users":[{'user_id': "$users._id", 'user_name': "$users.user_name"}] //Users who are currently online.
    }
    ```
- messages collection
    ```
    {
        '_id': int, //63 bit integer
        'from':{'user_id': '$users._id', 'user_name': '$users.user_name'},
        'message': String, 
        'last_edited': int,
        'attachments':[String], //list of urls of files
        'stats':[ //user is added only after message is delivered to some user.
            {
                'user_id': "$users_id",
                'user_name': "$users.user_name",
                'delivered_on': int, //time of delivery
                'read_on': int //time of read.
            },
        ]
    }
    ```