# Simple chatrooms using PHP websockets and mongodb
Websocket server is forked from <a href="https://github.com/heminei/php-websocket">https://github.com/heminei/php-websocket</a> and modified according to the requirements.

## Features
- Authentication using jwt cookie instead of php sessions.
- Supports multiple rooms.
- Online users for each room.
- Scroll positions between rooms are preserved(client side).

## Requirements
- Mongodb
- Composer

## Installation
- Clone the repo.
- Run
```
composer install
```

## Running
- start server.php in server/server.php
- open login.php in client/login.php . User is created if doesn't exists.
- User will be redirected to client/client.php after successful login/signup.


## Known Issues
- Pressing shift+enter to increase textbox size on client lags.
- Upon testing a client can send upto 80 messages/sec. Not sure if it is true for single client at a time or multiple clients.


## Upcomming features.
- Making application work with sharding in mongodb.
- Support application to deploy on multiple instances of web servers.
- Rate limit number of messages/sec for each room.
- Deploying using docker containers.

