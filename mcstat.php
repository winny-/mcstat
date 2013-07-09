<?php

// This file is in the public domain.

/*
  ================
  Server List Ping
  ================

  An example of how to get a Minecraft server status's using a "Server List Ping" packet.
  See details here: http://www.wiki.vg/Server_List_Ping
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

function MC_serverListPing($hostname, $port=25565)
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

/*
  =====
  Query
  =====

  This section utilizes the UT3 Query protocol to query a Minecraft server.
  Read about it here: http://wiki.vg/Query
*/

function MC_getStrings($fp, $count)
{
    $nulsProcessed = 0;

    while ($nulsProcessed < $count) {
        while ($c != chr(0)) {
            $s .= $c;
            $c = fread($fp, 1);
        }

        $strings[] = $s;
        $nulsProcessed++;

        unset($c);
        unset($s);
    }

    return $strings;
}

function MC_query($hostname, $port=25565)
{
    $sessionId = rand(1, 0xFFFFFFFF) & 0x0F0F0F0F;
    $handshakeRequest = pack('cccN', 0xFE, 0xFD, 9, $sessionId);

    $fp = stream_socket_client('udp://' . $hostname . ':' . $port);
    if (!$fp) {
        return false;
    }

    fwrite($fp, $handshakeRequest);
    $handshakeResponse = fread($fp, 2048);

    // Ensure packet type is "handshake" and references our session ID.
    if (strpos($handshakeResponse, 9) !== 0 && substr(1, 4) != $sessionId) {
        fclose($fp);
        error_log('Received invalid handshake response "' . bin2hex($handshakeResponse) . '". Returning.');
        return false;
    }

    $challengeToken = substr($handshakeResponse, 5, -1);

    $statRequest = pack('cccNN', 0xFE, 0xFD, 0, $sessionId, $challengeToken);
    fwrite($fp, $statRequest);
    $statResponseHeader = fread($fp, 5);

    if (strpos($statResponseHeader, 0) !== 0 && substr($statResponseHeader, 1, 4) != $sessionId) {
        fclose($fp);
        error_log('Received invalid stat response header "' . bin2hex($statResponseHeader) . '". Returning.');
        return false;
    }

    $statData = array_merge(MC_getStrings($fp, 5), unpack('v', fread($fp, 2)), MC_getStrings($fp, 1));

    fclose($fp);
    return array(
                 'motd' => $statData[0],
                 'gametype' => $statData[1],
                 'map' => $statData[2],
                 'players' => $statData[3],
                 'players_max' => $statData[4],
                 'port' => $statData[5],
                 'ip' => $statData[6]
                 );
}

?>
