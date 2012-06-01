<?php
start:
function send($data, $socketname, $boic = FALSE) {
	global $socket;
	@socket_send($socket[$socketname], $data, strlen($data), 0x0);
}

include 'rooms.php';
function Startup() {
	global $rooms, $un;
	foreach($rooms as $room) {
		send("join chat:".$room."\n".chr(0), $un);
	}
}

include 'users.php';
$tokenlist = array();
foreach($users as $username => $password) {
	$socket[$username] = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
	@socket_connect($socket[$username], "chat.deviantart.com", 3900);
	$connected = TRUE;
	send("dAmnClient 0.3\nagent=IdleClient\n".chr(0), $username);
	$tokenarray = getCookie($username, $password);
	$token = $tokenarray['cookie'];
	$tokenlist[$username] = $token;
}

foreach($tokenlist as $un => $token) {
	send("login ".$un."\npk=".$token."\n".chr(0), $un);
	Startup();
}
while($connected) { 
	global $socket;
	foreach($socket as $username => $sock) {
		$response = "";
		@socket_recv($sock, $response,8192,0);
		send("pong\n".chr(0),$username);
	}
	$response = parse_dAmn_packet($response);
	if(!empty($response) && $response != '' && $response != ' ' && strstr($response, '#$chat') === false) {
		Message($response);
	}
}


// All of the stuff below should NOT be touched!
function Times($ts=false) { return date('H:i:s', ($ts===false?time():$ts)); }
function Clock($ts=false) {     return '['.Times($ts).']'; }
function Message($str = '', $ts = false) { echo Clock($ts),' '.$str,chr(10); }
function Notice($str = '', $ts = false)  { Message('** '.$str,$ts); }
function Warning($str = '', $ts = false) { Message('>> '.$str,$ts); }

function deform_chat($chat, $discard=false) {
	if(substr($chat, 0, 5)=='chat:') return '#'.str_replace('chat:','',$chat);
	if(substr($chat, 0, 6)=='pchat:') {
		if($discard===false) return $chat;
		$chat = str_replace('pchat:','',$chat);
		$chat1=substr($chat,0,strpos($chat,':'));
		$chat2=substr($chat,strpos($chat,':')+1);
		$mod = true;
		if(strtolower($chat1)==strtolower($discard)) return '@'.$chat1;
		else return '@'.$chat2;
	}
	return (substr($chat,0,1)=='#') ? $chat : (substr($chat, 0, 1)=='@' ? $chat : '#$chat');
}

function parse_dAmn_packet($data, $sep = '=') {
	$data = parse_tablumps($data, array(                       // Regex stuff for removing tablumps.
		'a1' => array(
			"&b\t",  "&/b\t",    "&i\t",    "&/i\t", "&u\t",   "&/u\t", "&s\t",   "&/s\t",    "&sup\t",    "&/sup\t", "&sub\t", "&/sub\t", "&code\t", "&/code\t",
			"&br\t", "&ul\t",    "&/ul\t",  "&ol\t", "&/ol\t", "&li\t", "&/li\t", "&bcode\t", "&/bcode\t",
			"&/a\t", "&/acro\t", "&/abbr\t", "&p\t", "&/p\t"
		),
		'a2' => array(
			"<b>",  "</b>",       "<i>",     "</i>", "<u>",   "</u>", "<s>",   "</s>",    "<sup>",    "</sup>", "<sub>", "</sub>", "<code>", "</code>",
			"\n",   "<ul>",       "</ul>",   "<ol>", "</ol>", "<li>", "</li>", "<bcode>", "</bcode>",
			"</a>", "</acronym>", "</abbr>", "<p>",  "</p>\n"
		),
		'b1' => array(
			"/&emote\t([^\t]+)\t([^\t]+)\t([^\t]+)\t([^\t]+)\t([^\t]+)\t/",
			"/&a\t([^\t]+)\t([^\t]*)\t/",
			"/&link\t([^\t]+)\t&\t/",
			"/&link\t([^\t]+)\t([^\t]+)\t&\t/",
			"/&dev\t[^\t]\t([^\t]+)\t/",
			"/&avatar\t(.*?)\t(.*?)\t/",
			"/&thumb\t([0-9]+)\t([^\t]+)\t([^\t]+)\t([^\t]+)\t([^\t]+)\t([^\t]+)\t([^\t]+)\t/",
			"/&img\t([^\t]+)\t([^\t]*)\t([^\t]*)\t/",
			"/&iframe\t([^\t]+)\t([0-9%]*)\t([0-9%]*)\t&\/iframe\t/",
			"/&acro\t([^\t]+)\t/",
			"/&abbr\t([^\t]+)\t/"
		),
		'b2' => array(
			"\\1",
			"<a href=\"\\1\" title=\"\\2\">",
			"\\1",
			"\\1 (\\2)",
			":dev\\1:",
			":icon\\1:",
			":thumb\\1:",
			"<img src=\"\\1\" alt=\"\\2\" title=\"\\3\" />",
			"<iframe src=\"\\1\" width=\"\\2\" height=\"\\3\" />",
			"<acronym title=\"\\1\">",
			"<abbr title=\"\\1\">"
		),
	));

	$packet = array(
		'cmd' => Null,
		'param' => Null,
		'args' => array(),
		'body' => Null,
		'raw' => $data
	);
	if(stristr($data, "\n\n")) {
		$packet['body'] = trim(stristr($data, "\n\n"));
		$data = substr($data, 0, strpos($data, "\n\n"));
	}
	$data = explode("\n", $data);
	foreach($data as $id => $str) {
		if(strpos($str, $sep) != 0)
			$packet['args'][substr($str, 0, strpos($str, $sep))] = substr($str, strpos($str, $sep)+1);
		elseif(isset($str[1])) {
			if(!stristr($str, ' ')) { $packet['cmd'] = $str; } else {
				$packet['cmd'] = substr($str, 0, strpos($str, ' '));
				$packet['param'] = trim(stristr($str, ' '));
			}
		}
	}
	$echo = '['.deform_chat($packet['param']).']';
	$room = deform_chat($packet['param']);
	$args = explode("\n", $packet['body']);
	$switch = explode(" ", $args[0]);
	switch($switch[0]) {
		case 'property':
			preg_match_all('/property chat:.*\np=(.*)/', $packet['body'], $matches);
			foreach($matches[1] as $property) {
				switch($property) {
					case 'title':
						Notice('Got title for '.$room.' **');
						break;
					case 'privclasses':
						Notice('Got privclasses for '.$room.' **');
						break;
					case 'members':
						Notice('Got members for '.$room.' **');
						break;
				}
			}
			$echo = '';
			break;
		case 'msg':
			$from = str_replace('from=', '', $args[1]);
			$message = $args[3];
			$echo = '['.$room.'] <'.$from.'> '.$message;
			break;
		case 'action':
			$from = str_replace('from=', '', $args[1]);
			$message = $args[3];
			$echo = '['.$room.'] * '.$from.' '.$message;
			break;
		case 'join':
			$from = $switch[1];
			$echo = '['.$room.'] ** '.$from.' has joined';
			break;
		case 'part':
			$from = $switch[1];
			$echo = '['.$room.'] ** '.$from.' has left';
			break;
		
	}
	return $echo;
}

function parse_tablumps($data, $tablumps) {
	$data = str_replace($tablumps['a1'], $tablumps['a2'], $data);
	$data = preg_replace($tablumps['b1'], $tablumps['b2'], $data);
	$data = preg_replace('/<abbr title="colors:[A-F0-9]{6}:[A-F0-9]{6}"><\/abbr>/','',$data);
	return preg_replace('/<([^>]+) (width|height|title|alt)=""([^>]*?)>/', "<\\1\\3>", $data);
}

// dAmnPHP stuff




function getCookie($username, $pass) {
	$server = array(
	'chat' => array(
		'host' => 'chat.deviantart.com',
		'version' => '0.3',
		'port' => 3900,
	),
	'login' => array(
		'transport' => 'ssl://',
		'host' => 'www.deviantart.com',
		'file' => '/users/login',
		'port' => 443,
	),
);
	// Method to get the cookie! Yeah! :D
	// Our first job is to open an SSL connection with our host.
	$socket = fsockopen(
		$server['login']['transport'].$server['login']['host'],
		$server['login']['port']
	);
	// If we didn't manage that, we need to exit!
	if($socket === false) {
	return array(
		'status' => 2,
		'error' => 'Could not open an internet connection');
	}
	// Fill up the form payload
	$POST = '&username='.urlencode($username);
	$POST.= '&password='.urlencode($pass);
	$POST.= '&remember_me=1';
	// And now we send our header and post data and retrieve the response.
	$response = send_headers(
		$socket,
		$server['login']['host'],
		$server['login']['file'],
		'http://www.deviantart.com/users/rockedout',
		$POST
	);
		// Now that we have our data, we can close the socket.
	fclose ($socket);
	// And now we do the normal stuff, like checking if the response was empty or not.
	if(empty($response))
	return array(
		'status' => 3,
		'error' => 'No response returned from the server'
	);
	if(stripos($response, 'set-cookie') === false)
	return array(
		'status' => 4,
		'error' => 'No cookie returned'
	);
	// Grab the cookies from the header
	$response=explode("\r\n", $response);
	$cookie_jar = array();
	foreach ($response as $line) {
		if(strpos($line, 'Location: ') !== false)
			if($line == 'Location: http://www.deviantart.com/users/wrong-password')
			return array(
				'status' => 6,
				'error' => 'Wrong password returned'
			);
			if (strpos($line, 'Set-Cookie:')!== false)
				$cookie_jar[] = substr($line, 12, strpos($line, '; ')-12);
	}
	// Using these cookies, we're gonna go to chat.deviantart.com and get
	// our authtoken from the dAmn client.
	if (($socket = @fsockopen('ssl://www.deviantart.com', 443)) == false)
		return array(
		'status' => 2,
		'error' => 'Could not open an internet connection');
		$response = send_headers(
		$socket,
		'chat.deviantart.com',
		'/chat/Botdom',
		'http://chat.deviantart.com',
		null,
		$cookie_jar
	);
		// Now search for the authtoken in the response
	$cookie = null;
	if(($pos = strpos($response, 'dAmn_Login( ')) !== false) {
		$response = substr($response, $pos+12);
		$cookie = substr($response, strpos($response, '", ')+4, 32);
	}
	elseif(($pos = strpos($response, 'Location: http://verify.deviantart.com')) !== false)
	return array(
		'status' => 6,
		'error' => 'Account not verfied, check your email and verify your account first'
	);
	else return array(
		'status' => 4,
		'error' => 'No authtoken found in dAmn client'
	);
		// Because errors still happen, we need to make sure we now have an array!
	if(!$cookie)
	return array(
		'status' => 5,
		'error' => 'Malformed cookie returned'
	);
	// We got a valid cookie!
	return array(
		'status' => 1,
		'cookie' => $cookie,
	);
}		

function send_headers($socket, $host, $url, $referer, $post=null, $cookies=array()) {
	 try {
		$headers = '';
		if (isset($post))
			$headers .= "POST $url HTTP/1.1\r\n";
		else $headers .= "GET $url HTTP/1.1\r\n";
		$headers .= "Host: $host\r\n";
		$headers .= 'User-Agent: IdleClient\r\n';
		$headers .= "Referer: $referer\r\n";
		if ($cookies != array())
			$headers .= 'Cookie: '.implode("; ", $cookies)."\r\n";
		$headers .= "Connection: close\r\n";
		$headers .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*\/*;q=0.8\r\n";
		$headers .= "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7\r\n";
		$headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
		if (isset($post))
			$headers .= 'Content-Length: '.strlen($post)."\r\n\r\n$post";
		else $headers .= "\r\n";
		$response = '';
		fputs($socket, $headers);
		while (!@feof ($socket)) $response .= @fgets ($socket, 8192);
		return $response;
	} catch (Exception $e) {
	echo 'Exception occured: '.$e->getMessage()."\n";
	return '';
	}
}
?>