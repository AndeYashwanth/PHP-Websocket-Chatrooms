<?php
require_once __DIR__ . "/../jwt.php";
require_once __DIR__ . '/ClientDBHandler.php';

if (!isset($_COOKIE['jwt'])) {
    header("Location: login.php");
    exit();
}

$db_handler = new ClientDBHandler("localhost", 60000);

$payload = json_decode((new JWT())->get_user_details_from_jwt($_COOKIE['jwt']), true);
$rooms = $db_handler->getRoomIDsNamesAccessToUser($payload['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
<!--    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" rel="stylesheet">-->
    <title>Echo server - Websocket Demo</title>
    <style type="text/css">
        * {
            margin: 0;
            padding: 0;
        }

        html, body {
            padding: 0;
            margin: 0;
            height: 100%;
        }

        /* width */
        ::-webkit-scrollbar {
            width: 8px;
            border-radius: 2px;
        }

        /* Track */
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        /* Handle */
        ::-webkit-scrollbar-thumb {
            background: #888;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
            width: 12px;
            background: #555;
        }

        body {
            /*background-color: black;*/
            /*color: white;*/
            margin: 0;
            text-align: left;
            font-family: monospace;
            font-size: 16px;
        }

        #frmInput {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }

        #leftarea {
            margin: 0;
            /*padding: 2px;*/
            width: 15%;
            height: 100%;
            text-align: center;
            float: left;
        }

        #roomlinks {
            margin: 0;
            width: 100%;
            height: 50%;
            /*border: 2px solid green;*/
            overflow-y: auto;
            overflow-x: hidden;
            overflow: -moz-scrollbars-vertical;
        }

        #roomlinks button {
            margin: 0;
            width: inherit;
            font-size: 18px;
            background-color: #eee;
            color: black;
            display: block;
            padding: 12px;
            text-decoration: none;
            overflow: visible;
            text-transform: none;
            cursor: pointer;
            font-family: inherit;
            -webkit-appearance: button;
            line-height: inherit;
            border-radius: 0;

        }

        #roomlinks button:hover {
            background-color: #ccc;
        }

        #roomlinks button.active {
            background-color: #4CAF50;
            color: white;
        }

        #onlineusersparent {
            box-sizing: border-box;
            -moz-box-sizing: border-box;
            -webkit-box-sizing: border-box;
            margin: 0;
            padding: 3px;
            height: 50%;
            border-top: 2px solid green;
        }

        #onlineusers {
            margin: 0;
            width: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            overflow: -moz-scrollbars-vertical;
        }

        #onlineusers span {
            width: 100%;
            font-size: 14px;
            background-color: #eee;
            color: black;
            display: block;
            padding: 5px;
            text-decoration: none;
            border-bottom: 1px solid grey;
        }

        #content {
            /*display: block;*/
            display: flex;
            flex-flow: column wrap;
            margin: 0;
            width: 85%;
            height: 100%;
            float: left;
            overflow-y: auto;
            overflow: -moz-scrollbars-vertical;
        }

        #messages {
            box-sizing: border-box; /*If you want the border to be inside margin. */
            -moz-box-sizing: border-box;
            -webkit-box-sizing: border-box;
            /*display: block;*/
            display: flex;
            flex: 1;
            flex-flow: column wrap;
            margin: 0;
            overflow-y: auto;
            overflow-x: hidden;
            width: 100%;
            border-left: 2px solid green;
            border-bottom: 1px solid black;
        }

        .message-row {
            list-style: none;
            border-top: 2px solid #42454a;
            width: 100%;
        }

        .avatarHolder {
            display: inline-block;
            width: 10%;
            text-align: center;
        }

        .message-holder {
            display: inline-block;
            width: 90%;
        }

        .message-holder-row1 {
            width: 100%;
            display: block;
        }

        .message-user {
            display: inline-block;
            font-weight: bold;
        }

        .message-date {
            font-size: 10px;
            display: inline-block;
            text-align: center;
        }

        .message-options {
            display: inline-block;
            float: right;
            font-size: 11px;
        }

        .message-holder-row2 {
            display: block;
            width: 100%;
        }

        .message-data {
            width: 100%;
            overflow-wrap: break-word;
        }

        #input {
            margin: 0;
            box-sizing: border-box;
            -moz-box-sizing: border-box;
            -webkit-box-sizing: border-box;
            height: 10%;
            bottom: 0;
            /*vertical-align: bottom;*/
            width: 100%;
            border: 2px solid black;
        }

        #messagebox {
            bottom: 5px;
            position: fixed; /*or absolute*/
            padding-left: 5px;
            /*display: inline-block;*/
            width: 70%;
            overflow: -moz-scrollbars-vertical;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 4em;
            line-height: 1.2em;
            max-height: 7em;
            white-space: pre-wrap;
        }

        #messagebox:focus {
            outline: none;
        }

        #messagebox[contenteditable]:empty::before {
            content: "Write messages here.";
            color: lightgrey;
        }

        #senddiv {
            float: right;
            padding: 5px;
            margin: 0 auto;
            width: 15%;
        }

        #senddiv button {
            width: auto;
            background-color: #4CAF50; /* Green */
            border: none;
            color: white;
            /*padding: 15px 32px;*/
            padding: 5px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
        }

        #senddiv button:hover {
            cursor: pointer;
        }
    </style>
    <script src="jquery-1.11.0.js"></script>
    <script>
        // Client here
        var socket = null;
        var uri = "ws://localhost:2207";
        var color = 'red';
        var online_users = null;
        var current_room_id = <?php echo array_key_first($rooms); ?>;
        var current_room_name = '<?php echo $rooms[array_key_first($rooms)]; ?>';
        const rooms_access = JSON.parse('<?php echo json_encode($rooms); ?>'); //key=room_id, value=room_name
        const username = '<?php echo $payload['user_name']; ?>';
        const default_avatar_url = 'https://img.icons8.com/doodle/48/000000/user.png';
        let scrollPositionsOfRooms = initScrollPositions();
        let messagebox_height = $("#messagebox").height();
        const epoch = 1577836800 * 1000; //in milli seconds till jan 1 2020.

        function initScrollPositions() {
            var scrollPositions = {};
            for (var room_id in rooms_access) {
                scrollPositions[room_id] = null;
            }
            return scrollPositions;
        }

        function timeConverter(UNIX_timestamp) { //in milli seconds
            var a = new Date(UNIX_timestamp);
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var year = a.getFullYear();
            var month = months[a.getMonth()];
            var date = a.getDate();
            var hour = a.getHours();
            var min = a.getMinutes();
            var sec = a.getSeconds();
            var time = date + ' ' + month + ' ' + year + ' ' + hour + ':' + min + ':' + sec;
            return time;
        }

        function renderMessage(message) {
            message.color = message.username === username ? color : 'blue';
            message.avatar_url = message.avatar_url ? message.avatar_url : default_avatar_url;
            message.message = message.message.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/(?:\r\n|\r|\n)/g, '<br>'); //html tags encoding

            let messageRow = document.createElement("li");
            messageRow.setAttribute('class', 'message-row');
            messageRow.innerHTML += `<div class='avatarHolder'><img title="${message.username}" alt="${message.username}" class="message-avatar" src='${message.avatar_url}'></div>`;

            let messageHolder = document.createElement('div');
            messageHolder.setAttribute('class', 'message-holder');
            messageHolder.innerHTML += `<div class="message-holder-row1"><div class="message-user">${message.username}</div><div class="message-date"> - ${timeConverter(message.updated_on ? message.updated_on : (message.message_id / 2**21) + epoch)}</div><div id="messageid-${message.message_id}" class="message-options">edit</div></div>`;
            messageHolder.innerHTML += `<div class="message-holder-row2"><span class="message-data">${message.message}</span> </div>`;
            messageRow.appendChild(messageHolder);

            return messageRow;
        }

        function connect() {
            socket = new WebSocket(uri);
            if (!socket) return false;
            socket.onopen = function () {
                writeToScreen('Connected to server ' + uri);
            }
            socket.onerror = function () {
                writeToScreen('Error!!!');
            }
            socket.onclose = function () {
                $('#send').prop('disabled', true);
                $('#close').prop('disabled', true);
                $('#connect').prop('disabled', false);
                $('#username').prop('disabled', false);
                $('#color').prop('disabled', false);
                writeToScreen('Socket closed!');
            }
            socket.onmessage = function (e) {
                console.log(e.data);
                writeToScreen(e.data);
            }
            // Init user data
            // Enable send and close button
            $('#send').prop('disabled', false);
        }

        function close() {
            socket.close();
        }

        function addConnectedUser(connected_user, room_ids) {
            room_ids.forEach(function (room_id) {
                online_users[room_id].push(connected_user);
            });
        }

        function removeDisconnectedUser(disconnected_user, room_ids) {
            room_ids.forEach(function (room_id) {
                const index = online_users[room_id].indexOf(disconnected_user);
                if (index !== -1) online_users[room_id].splice(index, 1);
            });
        }

        function writeToScreen(msg) { //string msg
            try {
                msg = JSON.parse(msg);
            } catch (e) {
                return false;
            }
            switch (msg.message_type) {
                case 'online_users':
                    online_users = msg.message;
                    displayOnlineUsers();
                    break;
                case 'message_history':
                    $.each(msg.message, function (room_id, arr) {
                        for (var i = 0; i < arr.length; i++) {
                            $('#room-' + room_id).append(renderMessage(arr[i]));
                        }
                    })
                    $("#messages").animate({scrollTop: $("#messages")[0].scrollHeight}, 500);
                    // $("#messages").scrollTop($("#messages")[0].scrollHeight);
                    break;
                case 'chat_message':
                    $('#room-' + msg.room_id).append(renderMessage(msg));
                    $("#messages").animate({scrollTop: $("#messages")[0].scrollHeight}, 500);
                    // $("#messages").scrollTop($("#messages")[0].scrollHeight);
                    break;
                case 'user_connected':
                    addConnectedUser(msg.message, msg.room_ids);
                    displayOnlineUsers();
                    // $('#onlineusers').html("online users:<br>" + JSON.stringify(online_users[current_room_id]));
                    break;
                case 'user_disconnected':
                    removeDisconnectedUser(msg.message, msg.room_ids);
                    displayOnlineUsers();
                    // $('#onlineusers').html("online users:<br>" + JSON.stringify(online_users[current_room_id]));
                    break;
                case 'error':
                    console.log(msg.message);
                    break;
            }
        }

        function sendMessage() {
            if (!socket) return false;
            var mess = $.trim($('#messagebox').text());
            mess = mess.replace(/</g, "&lt;").replace(/>/g, "&gt;"); //html character encoding.
            if (mess === '') return;
            socket.send(JSON.stringify({//user id will be obtained from cookie in server side.
                'message': mess,
                'room_id': current_room_id
            }));
            // Clear input
            $('#messagebox').text('');
            $('#messagebox').focus();
            setMessageBoxDivHeight();
        }

        $(document).ready(function () {
            connect();
            $('#messagebox').focus();
            $('#frmInput').submit(function () {
                sendMessage();
            });

            //message box textarea shift+enter, enter.
            $("#messagebox").keyup(function (e) {
                setMessageBoxDivHeight();
                if (e.which === 13 && !e.shiftKey) {
                    // document.getElementById("frmInput").submit();
                    // $(this).closest("form").submit();
                    sendMessage();
                    e.preventDefault();
                }
            });

            //display corresponding room when clicked the link
            document.querySelector('#roomlinks').addEventListener("click", displayRoom, false);
        });

        function setMessageBoxDivHeight() {
            const message_box = $("#messagebox");
            if (messagebox_height !== message_box.height()) {
                $("#input").animate({"height": message_box.height() + parseInt(message_box.css("bottom"), 10) + 5 + 'px'}, 50);
                // $("#input").height(message_box.height() + parseInt(message_box.css("bottom"), 10));
                messagebox_height = message_box.height();
                // $("#messages").animate({scrollTop: $("#messages")[0].scrollHeight}, 200);
            }
        }

        function displayRoom(e) {
            if (e.target !== e.currentTarget) {
                // if (scrollPositionsOfRooms[current_room_id] === 0)
                scrollPositionsOfRooms[current_room_id] = document.getElementById("messages").scrollTop; //store current scroll position before switching room, also before display:nonw.
                $("#room-" + current_room_id).css('display', 'none');
                $("#roomlink-" + current_room_id).attr("class", "");

                current_room_id = parseInt(e.target.id.replace(/roomlink-/, '')); //e.target.id is in form roomlink-1. extract digits from it.
                $("#room-" + current_room_id).css('display', ''); //display the target room.
                $("#roomlink-" + current_room_id).attr("class", "active"); //make current room link active
                $('#messagebox').focus(); //focus message box after switching room.

                if (scrollPositionsOfRooms[current_room_id] === null) { //if scrollheight for room is default then scroll to bottom, else scroll to location where user left off.
                    document.getElementById("messages").scrollTop = 0; //scroll from very top, not from the scroll height of previous room.
                    $("#messages").animate({scrollTop: $("#messages")[0].scrollHeight}, 500);
                } else
                    document.getElementById("messages").scrollTop = scrollPositionsOfRooms[current_room_id];

                displayOnlineUsers();
                // $('#onlineusers').html("online users:<br>" + JSON.stringify(online_users[current_room_id]));
            }
            e.stopPropagation();
        }

        function displayOnlineUsers() {
            $("#onlineusers").html("");
            online_users[current_room_id].forEach(function (user_name) {
                var spanElement = document.createElement("span");   // Create a <button> element
                spanElement.innerHTML = user_name;                   // Insert text
                document.getElementById("onlineusers").appendChild(spanElement);
            });
        }

        // function auto_grow(element) {
        //     element.style.height = "5px";
        //     element.style.height = (element.scrollHeight) + "px";
        // }


    </script>
</head>
<body>
<form id="frmInput" action="" onsubmit="return false;">
    <div id="leftarea">
        <div id="roomlinks">
            <?php
            $flag = 0;
            foreach ($rooms as $room_id => $room_name) {
                if ($flag === 0) {
                    echo '<button class="active" id="roomlink-' . $room_id . '">' . $room_name . '</button>';
                    $flag = 1;
                } else
                    echo '<button id="roomlink-' . $room_id . '">' . $room_name . '</button>';
            }
            ?>

        </div>
        <div id="onlineusersparent">
            <div id="onlineusershead">Online Users:</div>
            <div id="onlineusers">

            </div>
        </div>
    </div>
    <div id="content">
        <div id="messages">
            <?php
            $flag = 0;
            foreach (array_keys($rooms) as $room_id) {
                if ($flag === 0) {
                    echo '<div id="room-' . $room_id . '"></div>';
                    $flag = 1;
                } else
                    echo '<div style="display: none;" id="room-' . $room_id . '"></div>';
            }
            ?>
        </div>
        <div id="input">
            <!--            <div id="textarea">-->
            <span id="messagebox" role="textbox" contenteditable></span>
            <!--            </div>-->
            <div id="senddiv">
                <button disabled id="attachments" type="button">Attach</button>
                <button disabled id="send" type="submit">Send</button>
            </div>
        </div>
    </div>
</form>
</body>
</html>
