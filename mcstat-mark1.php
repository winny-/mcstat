<html>
  <head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
<style>
   .colored_motd { text-shadow: 1px 1px 2px #000000;
 filter: dropshadow(color=#000000, offx=1, offy=1);
 }
</style>
  </head>
  <body>
<form name="input" action="./mcstat.php" method="get">
<input type="text" name="hostname">
<input type="submit">
</form>
<pre>
<?php

function pack_string($string) {
  return pack('n', strlen($string)) . mb_convert_encoding($string, 'UCS-2BE');
}

// http://www.wiki.vg/Server_List_Ping
function get_minecraft_server_status($hostname, $port=25565, $use_old_method=false) {
  // 1. pack bytes to send
  if (!$use_old_method) {
    $request = pack('nc', 0xfe01, 0xfa) .
      pack_string('MC|PingHost') .
      pack('nc', 7+2*strlen($hostname), 73) .
      pack_string($hostname) .
      pack('N', 25565);
  } else {
    $request = pack('n', 0xfe01);
  }

  // 2. open communication socket and make transaction
  $t = microtime(true);
  $fp = stream_socket_client('tcp://'.$hostname.':'.$port, $errno, $errstr, 10);
  if (!$fp) {
    // handle errors
    return false;
  } else {
    fwrite($fp, $request);
    $response = fread($fp, 2048);
    fclose($fp);
  }
  $t = round((microtime(true) - $t)*1000);

  // 3. unpack data & populate $result
  if (strpos($response,0xFF) !== 0) {
    return false;
  } else {
    $response = substr($response, 3);
    $response = explode(chr(0).chr(0), $response);

    $result = array();
    $result['player_count'] = str_replace(chr(0), '', $response[4]);
    $result['player_max'] = str_replace(chr(0), '', $response[5]);
    $result['motd'] = mb_convert_encoding($response[3], 'UTF-8', 'UCS-2BE');
    $result['latency'] = $t;
    $result['server_version'] = str_replace(chr(0), '', $response[2]);
    $result['protocol_version'] = str_replace(chr(0), '', $response[1]);
  }


  return $result;
}

function mb_str_split( $string ) { 
  // Split at all position not after the start: ^ 
  // and not before the end: $ 
    return preg_split('/(?<!^)(?!$)/u', $string ); 
}

// http://www.wiki.vg/Chat
function parse_motd_colors($motd) {
  $in_color_sequence = false;
  $open_span = false;
  $colored_motd = '';

  foreach (mb_str_split($motd) as $c) {
    if ($in_color_sequence) {

      // find color and insert span
      switch ($c) {
      case '0':
	$color = '#000000';
	break;
      case '1':
	$color = '#0000aa';
	break;
      case '2':
	$color = '#00aa00';
	break;
      case '3':
	$color = '#00aaaa';
	break;
      case '4':
	$color = '#aa0000';
	break;
      case '5':
	$color = '#aa00aa';
	break;
      case '6':
	$color = '#ffaa00';
	break;
      case '7':
	$color = '#aaaaaa';
	break;
      case '8':
	$color = '#555555';
	break;
      case '9':
	$color = '#5555ff';
	break;
      case 'a':
	$color = '#55ff55';
	break;
      case 'b':
	$color = '#55ffff';
	break;
      case 'c':
	$color = '#ff5555';
	break;
      case 'd':
	$color = '#ff55ff';
	break;
      case 'e':
	$color = '#ffff55';
	break;
      case 'f':
      case 'r':
	$color = '#ffffff';
	break;
      default:
	$color = false;
	break;
      }

      if ($color) {
	if ($open_span) {
	  $colored_motd .= '</span>';
	}

	$colored_motd .= '<span style="color:' . $color . ';">';
	$open_span = true;
      }

      $in_color_sequence = false;
    } elseif ($c == '§') {
      $in_color_sequence = true;
    } else {
      $colored_motd .= $c;
    }
  }
  if ($open_span) {
    $colored_motd .= '</span>';
  }

  return $colored_motd;
}


echo($_GET['hostname'].': ');
if (!isset($_GET['port'])) {
    $_GET['port'] = 25565;
}
$r = get_minecraft_server_status($_GET['hostname'], $_GET['port']);
if (!$r) {
  echo('failed... '.$r[1].', '.$r[2]);
} else {
  print_r($r);
}
?>
</pre>
<div class="colored_motd">
<?php   echo(parse_motd_colors($r['motd'])); ?>
</div>
</body>
</html>