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

function MC_makeSessionId()
{
    return rand(1, 0xFFFFFFFF) & 0x0F0F0F0F;
}

// Verify packet type and ensure it references our session ID.
function MC_validateQueryResponse($response, $responseType, $sessionId)
{
    if (strpos($response, $responseType) !== 0 && (int)substr($response, 1, 4) === $sessionId) {
        error_log('Received invalid response "' . bin2hex($response) . '". Returning.');
        return false;
    }
    return true;
}

function MC_handleQueryHandshake($fp, $sessionId)
{
    $handshakeRequest = pack('cccN', 0xFE, 0xFD, 9, $sessionId);

    fwrite($fp, $handshakeRequest);
    $handshakeResponse = fread($fp, 2048);

    if (!MC_validateQueryResponse($handshakeResponse, 9, $sessionId)) {
        return false;
    }

    $challengeToken = substr($handshakeResponse, 5, -1);

    return $challengeToken;
}

function MC_basicQuery($hostname, $port=25565)
{
    $sessionId = MC_makeSessionId();

    $fp = stream_socket_client('udp://' . $hostname . ':' . $port);
    if (!$fp) {
        return false;
    }

    $time = microtime(true);

    $challengeToken = MC_handleQueryHandshake($fp, $sessionId);
    if (!$challengeToken) {
        fclose($fp);
        return false;
    }

    $time = round((microtime(true)-$time)*1000);


    $statRequest = pack('cccNN', 0xFE, 0xFD, 0, $sessionId, $challengeToken);
    fwrite($fp, $statRequest);
    $statResponseHeader = fread($fp, 5);

    if (!MC_validateQueryResponse($statResponseHeader, 0, $sessionId)) {
        fclose($fp);
        return false;
    }

    $statData = array_merge(MC_getStrings($fp, 5), unpack('v', fread($fp, 2)), MC_getStrings($fp, 1));

    fclose($fp);
    return array(
                 'motd' => $statData[0],
                 'gametype' => $statData[1],
                 'map' => $statData[2],
                 'player_count' => $statData[3],
                 'player_max' => $statData[4],
                 'port' => (string)$statData[5],
                 'ip' => $statData[6],
                 'latency' => $time
                 );
}

function MC_fullQuery($hostname, $port=25565)
{
    $sessionId = MC_makeSessionId();

    $fp = stream_socket_client('udp://' . $hostname . ':' . $port);
    if (!$fp) {
        return false;
    }

    $time = microtime(true);

    $challengeToken = MC_handleQueryHandshake($fp, $sessionId);
    if (!$challengeToken) {
        fclose($fp);
        return false;
    }

    $time = round((microtime(true)-$time)*1000);

    $statRequest = pack('cccNNN', 0xFE, 0xFD, 0, $sessionId, $challengeToken, 0);
    fwrite($fp, $statRequest);
    $statResponseHeader = fread($fp, 5);

    if (!MC_validateQueryResponse($statResponseHeader, 0, $sessionId)) {
        fclose($fp);
        return false;
    }

    fread($fp, 11);
 
    // Should only encounter double null thrice.
    while ($doubleNulsEncountered < 3) {
        $c = fread($fp, 1);
        $statResponse .= $c;

        if ($lastWasNul && $c === chr(0)) {
            $doubleNulsEncountered++;
        }

        $lastWasNul = ($c === chr(0));
    }

    fclose($fp);

    $statResponseData = explode(pack('cccccccccccc', 0x00, 0x00, 0x01, 0x70, 0x6C, 0x61,
                                     0x79, 0x65, 0x72, 0x5F, 0x00, 0x00), $statResponse);
    foreach (explode(chr(0), $statResponseData[0]) as $index => $item) {
        if (!($index % 2)) {
            switch ($item) {
            case 'numplayers':
                $key = 'player_count';
                break;
            case 'maxplayers':
                $key = 'player_max';
                break;
            case 'hostname':
                $key = 'motd';
                break;
            case 'hostip':
                $key = 'ip';
                break;
            case 'hostport':
                $key = 'port';
                break;
            default:
                $key = $item;
                break;
            }
        } else {
            if ($key == 'port') {
                $item = (string)$item;
            }
            $stats[$key] = $item;
        }
    }

    $stats['latency'] = $time;

    $players = explode(chr(0), $statResponseData[1]);
    array_pop($players);

    $stats['players'] = $players;
    return $stats;
}

?>
