<?php

/*

	server status provider specially for isometric station https://github.com/Derven/Lili-station
	based on https://github.com/dualsaber/ss13serverdata

*/

error_reporting(0);

if (isset($_GET['server'])) {
	switch ($_GET['server']) {
		case 'iso':
			$servers[0] = Array();
			$servers[0]["address"] = "134.249.87.182"; // server IP
			$servers[0]["port"] = 5658; // server port
			$servers[0]["servername"] = "Isometry"; // server name, just for case
			break;
		default:
			die('Nothing for you.');
			break;
	}
} else {
	die('Nothing for you.');
}

function export($addr, $port, $str) {

	if (!@fsockopen($addr, $port, $errno, $errstr, $timeout = 2)) {
		die('{"ERROR": null}');
	}

	global $error;
	// All queries must begin with a question mark (ie "?players")
	if($str{0} != '?') $str = ('?' . $str);
	/* --- Prepare a packet to send to the server (based on a reverse-engineered packet structure) --- */
	$query = "\x00\x83" . pack('n', strlen($str) + 6) . "\x00\x00\x00\x00\x00" . $str . "\x00";
	/* --- Create a socket and connect it to the server --- */
	$server = socket_create(AF_INET,SOCK_STREAM,SOL_TCP) or exit("ERROR");
	socket_set_option($server, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 2, 'usec' => 0)); //sets connect and send timeout to 2 seconds

	if(!socket_connect($server,$addr,$port)) {
		$error = true;
		return "ERROR";
	}
	/* --- Send bytes to the server. Loop until all bytes have been sent --- */
	$bytestosend = strlen($query);
	$bytessent = 0;
	while ($bytessent < $bytestosend) {
		//echo $bytessent.'<br>';
		$result = socket_write($server,substr($query,$bytessent),$bytestosend-$bytessent);
		//echo 'Sent '.$result.' bytes<br>';
		if ($result===FALSE) die(socket_strerror(socket_last_error()));
		$bytessent += $result;
	}
	/* --- Idle for a while until recieved bytes from game server --- */
	$result = socket_read($server, 10000, PHP_BINARY_READ);
	socket_close($server); // we don't need this anymore
	if ($result != "") {
		if ($result{0} == "\x00" || $result{1} == "\x83") { // make sure it's the right packet format
			// Actually begin reading the output:
			$sizebytes = unpack('n', $result{2} . $result{3}); // array size of the type identifier and content
			$size = $sizebytes[1] - 1; // size of the string/floating-point (minus the size of the identifier byte)
			if ($result{4} == "\x2a") { // 4-byte big-endian floating-point
				$unpackint = unpack('f', $result{5} . $result{6} . $result{7} . $result{8}); // 4 possible bytes: add them up together, unpack them as a floating-point
				return $unpackint[1];
			}
			else if($result{4} == "\x06") { // ASCII string
				$unpackstr = ""; // result string
				$index = 5; // string index
				while($size > 0) { // loop through the entire ASCII string
					$size--;
					$unpackstr .= $result{$index}; // add the string position to return string
					$index++;
				}
				return $unpackstr;
			}
		}
	}
	$error = true;
	return "ERROR";
	
}

function getvar($array,$var) {
	if (array_key_exists($var, $array))
		return $array[$var];
	return null;
}

foreach ($servers as $server) {
	$port = $server["port"];
	$addr = $server["address"];
	$data = export($addr, $port, '?status');
	if(is_string($data)) {
		$data = str_replace("\x00", "", $data);
	}
	$variable_value_array = Array();
	$data_array = explode("&", $data);
	for ($i = 0; $i < count($data_array); $i++) {
		$row = explode("=", $data_array[$i]);
		if (isset($row[1])){
			$variable_value_array[$row[0]] = $row[1];
		} else {
			$variable_value_array[$row[0]] = null;
		}
	}
	if (array_key_exists('gamestate', $variable_value_array))
		if ($variable_value_array['gamestate'] == 4)
			$variable_value_array['restarting'] = 1;
	$serverinfo = $variable_value_array;

	if (isset($_GET['key'])) {
		if ($_GET['key'] == 'json') {
			echo json_encode($serverinfo, JSON_PRETTY_PRINT);
			header('Content-Type: text/plain');
		}
		if ($_GET['key'] == 'img') {
			p_gen($serverinfo);
		}
	}
}

function p_gen($si) {
	header("Content-type: image/png");
	$ss = imagecreatefrompng("dummy.png");
	$orange = imagecolorallocate($ss, 220, 210, 60);
	imagestring($ss, 9, 400, 70, $si["players"]."/60", $orange);
	imagepng($ss);
	imagedestroy($ss);
}
?>
