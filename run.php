<?php
// Make sure we're in the current directory
start:
chdir(dirname(realpath(__FILE__)));
require('Config/rooms.php');
require('Config/users.php');
require('Core/dAmnPHP.php');
$dAmn = new dAmnPHP;

$tokenlist = array();
foreach($users as $username => $password) {
	$socket[$username] = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
	@socket_connect($socket[$username], "chat.deviantart.com", 3900);
	$connected = TRUE;
	send("dAmnClient 0.3\nagent=IdleClient\n".chr(0), $username);
	$tokenarray = $dAmn->getCookie($username, $password);
	if(array_key_exists('cookie', $tokenarray)) {
		$token = $tokenarray['cookie'];
		$tokenlist[$username] = $token;
		$dAmn->Notice('Received cookie for '.$username);
	} else {
		$dAmn->Warning('Invalid password for '.$username);
	}
}

foreach($tokenlist as $un => $token) {
	send("login ".$un."\npk=".$token."\n".chr(0), $un);
	Startup();
}

while($connected) {
	global $socket;
	foreach($socket as $username => $sock) {
		$packet = "";
		@socket_recv($sock, $packet,8192,0);
		$packet = sort_dAmn_packet($packet);
		$packet = $packet['packet'];
		if($packet['cmd'] == 'disconnect') {
			break;
		}
		send("pong\n".chr(0),$username);
	}
	$packet = parse_dAmn_packet($packet['raw']);
}

goto start;

function send($data, $socketname, $boic = FALSE) {
	global $socket;
	@socket_send($socket[$socketname], $data, strlen($data), 0x0);
}

function Startup() {
	global $rooms, $un;
	foreach($rooms as $room) {
		send("join chat:".$room."\n".chr(0), $un);
		@socket_recv($sock, $response,8192,0);
		$response = parse_dAmn_packet($response);
		$e = sort_dAmn_packet($response['raw']);
	}
}

?>