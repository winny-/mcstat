<?php

// This file is in the public domain.

class MinecraftStatus {

    public $hostname;
    public $port;

    public $stats;

    function __construct($hostname, $port=25565)
    {
        $this->hostname = $hostname;
        $this->port = $port;
    }

    public function ping()
    {
        $newStats = $this->serverListPing($this->hostname, $this->port);
        $this->stats[microtime()] = array(
                                          'stats' => $newStats,
                                          'method' => 'Server List Ping',
                                          'hostname' => $this->hostname,
                                          'port' => $this->port
                                          );

        return $newStats;
    }

    public function query($fullQuery=true)
    {
        if ($fullQuery) {
            $newStats = $this->fullQuery($this->hostname, $this->port);
            $this->stats[microtime()] = array(
                                              'stats' => $newStats,
                                              'method' => 'Full Query',
                                              'hostname' => $this->hostname,
                                              'port' => $this->port
                                              );
        } else {
            $newStats = $this->basicQuery($this->hostname, $this->port);
            $this->stats[microtime()] = array(
                                              'stats' => $newStats,
                                              'method' => 'Basic Query',
                                              'hostname' => $this->hostname,
                                              'port' => $this->port
                                              );
        }

        return $newStats;
    }

    /*
      ================
      Server List Ping
      ================

      An example of how to get a Minecraft server status's using a "Server List Ping" packet.
      See details here: http://www.wiki.vg/Server_List_Ping
    */

    private function packString($string)
    {
        return pack('n', strlen($string)) . mb_convert_encoding($string, 'UCS-2BE');
    }

    // This is needed since UCS-2 text rendered as UTF-8 contains unnecessary null bytes
    // and could cause other components, especially string functions to blow up. Boom!
    private function decodeUCS2BE($string)
    {
        return mb_convert_encoding($string, 'UTF-8', 'UCS-2BE');
    }

    private function serverListPing($hostname, $port=25565)
    {
        // 1. pack data to send
        $request = pack('nc', 0xfe01, 0xfa) .
            $this->packString('MC|PingHost') .
            pack('nc', 7+2*strlen($hostname), 73) .
            $this->packString($hostname) .
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
                     'player_count' => $this->decodeUCS2BE($response[4]),
                     'player_max' => $this->decodeUCS2BE($response[5]),
                     'motd' => $this->decodeUCS2BE($response[3]),
                     'server_version' => $this->decodeUCS2BE($response[2]),
                     'protocol_version' => $this->decodeUCS2BE($response[1]),
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

    private function getStrings($fp, $count)
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

    private function makeSessionId()
    {
        return rand(1, 0xFFFFFFFF) & 0x0F0F0F0F;
    }

    // Verify packet type and ensure it references our session ID.
    private function validateQueryResponse($response, $responseType, $sessionId)
    {
        if (strpos($response, $responseType) !== 0 && (int)substr($response, 1, 4) === $sessionId) {
            error_log('Received invalid response "' . bin2hex($response) . '". Returning.');
            return false;
        }
        return true;
    }

    private function handleQueryHandshake($fp, $sessionId)
    {
        $handshakeRequest = pack('cccN', 0xFE, 0xFD, 9, $sessionId);

        fwrite($fp, $handshakeRequest);
        $handshakeResponse = fread($fp, 2048);

        if (!$this->validateQueryResponse($handshakeResponse, 9, $sessionId)) {
            return false;
        }

        $challengeToken = substr($handshakeResponse, 5, -1);

        return $challengeToken;
    }

    private function basicQuery($hostname, $port=25565)
    {
        $sessionId = $this->makeSessionId();

        $fp = stream_socket_client('udp://' . $hostname . ':' . $port);
        if (!$fp) {
            return false;
        }

        $time = microtime(true);

        $challengeToken = $this->handleQueryHandshake($fp, $sessionId);
        if (!$challengeToken) {
            fclose($fp);
            return false;
        }

        $time = round((microtime(true)-$time)*1000);


        $statRequest = pack('cccNN', 0xFE, 0xFD, 0, $sessionId, $challengeToken);
        fwrite($fp, $statRequest);
        $statResponseHeader = fread($fp, 5);

        if (!$this->validateQueryResponse($statResponseHeader, 0, $sessionId)) {
            fclose($fp);
            return false;
        }

        $statData = array_merge($this->getStrings($fp, 5), unpack('v', fread($fp, 2)), $this->getStrings($fp, 1));

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

    private function fullQuery($hostname, $port=25565)
    {
        $sessionId = $this->makeSessionId();

        $fp = stream_socket_client('udp://' . $hostname . ':' . $port);
        if (!$fp) {
            return false;
        }

        $time = microtime(true);

        $challengeToken = $this->handleQueryHandshake($fp, $sessionId);
        if (!$challengeToken) {
            fclose($fp);
            return false;
        }

        $time = round((microtime(true)-$time)*1000);

        $statRequest = pack('cccNNN', 0xFE, 0xFD, 0, $sessionId, $challengeToken, 0);
        fwrite($fp, $statRequest);
        $statResponseHeader = fread($fp, 5);

        if (!$this->validateQueryResponse($statResponseHeader, 0, $sessionId)) {
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
}

?>
