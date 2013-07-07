<?php

/*
  An example of how to get a Minecraft server status's using a "Server List Ping" packet.
  See details here: http://www.wiki.vg/Server_List_Ping

  This file is in the public domain.
*/

function MC_packString($string)
{
    return pack('n', strlen($string)) . mb_convert_encoding($string, 'UCS-2BE');
}

// This is needed since UCS-2 text rendered as UTF-8 contains unnecessary null bytes
// and could cause other components, especially string functions to blow up. Boom!
function MC_decodeUCS2BE($string)
{
    return mb_convert_encoding($string, 'UTF-8', 'UCS-2BE');
}

function MC_getServerStatus($hostname, $port=25565)
{
    // 1. pack data to send
    $request = pack('nc', 0xfe01, 0xfa) .
        MC_packString('MC|PingHost') .
        pack('nc', 7+2*strlen($hostname), 73) .
        MC_packString($hostname) .
        pack('N', 25565);

    // 2. open communication socket and make transaction
    $time = microtime(true);
    $fp = stream_socket_client('tcp://' . $hostname . ':' . $port);
    if (!$fp) {
        return false;
    }
    fwrite($fp, $request);
    $response = fread($fp, 2048);
    fclose($fp);
    $time = round((microtime(true)-$time)*1000);

    // 3. unpack data and return
    if (strpos($response, 0xFF) !== 0) {
        return false;
    }
    $response = substr($response, 3);
    $response = explode(pack('n', 0), $response);

    return array(
                 'player_count' => MC_decodeUCS2BE($response[4]),
                 'player_max' => MC_decodeUCS2BE($response[5]),
                 'motd' => MC_decodeUCS2BE($response[3]),
                 'server_version' => MC_decodeUCS2BE($response[2]),
                 'protocol_version' => MC_decodeUCS2BE($response[1]),
                 'latency' => $time
                 );
}

?>
