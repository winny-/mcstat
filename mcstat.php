<?php


define('MCSTAT_NETWORK_TIMEOUT', 5);


class MinecraftStatus
{

    public $hostname;
    public $port;

    public $lastError;
    public $stats;

    function __construct($hostname, $port=25565)
    {
        $this->hostname = $hostname;
        $this->port = $port;
        $this->lastError = null;
        $this->stats = array();
    }

    public function ping()
    {
        try {
            $newStats = MinecraftServerListPing::ping($this->hostname, $this->port);
        } catch (Exception $e) {
            $newStats = false;
            $this->lastError = $e->getMessage();
        }
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
            try {
                $newStats = MinecraftQuery::fullQuery($this->hostname, $this->port);
            } catch (Exception $e) {
                $newStats = false;
                $this->lastError = $e->getMessage();
            }
            $this->stats[microtime()] = array(
                                              'stats' => $newStats,
                                              'method' => 'Full Query',
                                              'hostname' => $this->hostname,
                                              'port' => $this->port
                                              );
        } else {
            try {
                $newStats = MinecraftQuery::basicQuery($this->hostname, $this->port);
            } catch (Exception $e) {
                $newStats = false;
                $this->lastError = $e->getMessage();
            }
            $this->stats[microtime()] = array(
                                              'stats' => $newStats,
                                              'method' => 'Basic Query',
                                              'hostname' => $this->hostname,
                                              'port' => $this->port
                                              );
        }

        return $newStats;
    }
}

/*
  ================
  Server List Ping
  ================

  An example of how to get a Minecraft server status's using a "Server List Ping" packet.
  See details here: http://www.wiki.vg/Server_List_Ping
*/

class MinecraftServerListPing
{

    private static function packString($string)
    {
        $letterCount = strlen($string);
        return pack('n', $letterCount) . mb_convert_encoding($string, 'UTF-16BE');
    }

    // This is needed since UTF-16BE text rendered as UTF-8 contains unnecessary null bytes
    // and could cause other components, especially string functions to blow up. Boom!
    private static function decodeUTF16BE($string)
    {
        return mb_convert_encoding($string, 'UTF-8', 'UTF-16BE');
    }

    public static function ping($hostname, $port=25565)
    {
        // 1. pack data to send
        $request = pack('nc', 0xfe01, 0xfa) .
            self::packString('MC|PingHost') .
            pack('nc', 7+2*strlen($hostname), 73) .
            self::packString($hostname) .
            pack('N', 25565);

        // 2. open communication socket and make transaction
        $time = microtime(true);
        $fp = stream_socket_client('tcp://' . $hostname . ':' . $port, $errno, $errmsg, MCSTAT_NETWORK_TIMEOUT);
        stream_set_timeout($fp, MCSTAT_NETWORK_TIMEOUT);
        if (!$fp) {
            throw new Exception($errmsg);
        }
        fwrite($fp, $request);
        $response = fread($fp, 2048);
        $socketInfo = stream_get_meta_data($fp);
        fclose($fp);
        if ($socketInfo['timed_out']) {
            throw new Exception('Connection timed out');
        }
        $time = round((microtime(true)-$time)*1000);

        // 3. unpack data and return
        if (strpos($response, 0xFF) !== 0) {
            throw new Exception('Bad reply from server');
        }
        $response = substr($response, 3);
        $response = explode(pack('n', 0), $response);

        return array(
                     'player_count' => self::decodeUTF16BE($response[4]),
                     'player_max' => self::decodeUTF16BE($response[5]),
                     'motd' => self::decodeUTF16BE($response[3]),
                     'server_version' => self::decodeUTF16BE($response[2]),
                     'protocol_version' => self::decodeUTF16BE($response[1]),
                     'latency' => $time
                     );
    }
}

/*
  =====
  Query
  =====

  This section utilizes the UT3 Query protocol to query a Minecraft server.
  Read about it here: http://wiki.vg/Query
*/

class MinecraftQuery
{

    private static function getStrings($fp, $count)
    {
        for ($nulsProcessed = 0; $nulsProcessed < $count; $nulsProcessed++) {
            for ($lastChar = fread($fp, 1), $currentString = ''; $lastChar !== chr(0); $lastChar = fread($fp, 1)) {
                $currentString .= $lastChar;
            }
            $strings[] = $currentString;
        }

        return $strings;
    }

    private static function makeSessionId()
    {
        return rand(1, 0xFFFFFFFF) & 0x0F0F0F0F;
    }

    // Verify packet type and ensure it references our session ID.
    private static function validateResponse($response, $type, $sessionId)
    {
        $invalidType = ($response['type'] !== $type);
        $invalidSessionId = ($response['sessionId'] !== $sessionId);
        if ($invalidType || $invalidSessionId) {
            $errorMessage = 'Invalid Response:';
            $errorMessage .= ($invalidType) ? " {$response['type']} !== {$type}" : '';
            $errorMessage .= ($invalidSessionId) ? " {$response['sessionId']} !== {$sessionId}" : '';
            error_log($errorMessage);
            return false;
        }
        return true;
    }

    private static function handleHandshake($fp, $sessionId)
    {
        $handshakeRequest = pack('cccN', 0xFE, 0xFD, 9, $sessionId);

        fwrite($fp, $handshakeRequest);
        $handshakeResponse = self::readResponseHeader($fp, true);
        if (!self::validateResponse($handshakeResponse, 9, $sessionId)) {
            return false;
        }

        return $handshakeResponse['challengeToken'];
    }

    private static function readResponseHeader($fp, $withChallengeToken=false)
    {
        $header = fread($fp, 5);
        $unpacked = unpack('ctype/NsessionId', $header);
        if ($withChallengeToken) {
            $ary = self::getStrings($fp, 1);
            $unpacked['challengeToken'] = (int)$ary[0];
        }
        return $unpacked;
    }

    private static function startQuery($hostname, $port, $fullQuery)
    {
        $sessionId = self::makeSessionId();

        $fp = stream_socket_client('udp://' . $hostname . ':' . $port, $errno, $errmsg, MCSTAT_NETWORK_TIMEOUT);
        stream_set_timeout($fp, MCSTAT_NETWORK_TIMEOUT);
        if (!$fp) {
            throw new Exception($errmsg);
        }

        $time = microtime(true);

        $challengeToken = self::handleHandshake($fp, $sessionId);
        if (!$challengeToken) {
            fclose($fp);
            throw new Exception('Bad handshake response');
        }

        $time = round((microtime(true)-$time)*1000);

        $statRequest = pack('cccNN', 0xFE, 0xFD, 0, $sessionId, $challengeToken);
        if ($fullQuery) {
            $statRequest .= pack('N', 0);
        }
        fwrite($fp, $statRequest);
        $statResponseHeader = self::readResponseHeader($fp);

        if (!self::validateResponse($statResponseHeader, 0, $sessionId)) {
            fclose($fp);
            throw new Exception('Bad query response');
        }

        return array(
            'sessionId'      => $sessionId,
            'challengeToken' => $challengeToken,
            'fp'             => $fp,
            'time'           => $time,
        );
    }

    public static function basicQuery($hostname, $port=25565)
    {
        $vars = self::startQuery($hostname, $port, false);
        $fp = $vars['fp'];
        $time = $vars['time'];



        $statData = array_merge(self::getStrings($fp, 5), unpack('v', fread($fp, 2)), self::getStrings($fp, 1));

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

    public static function fullQuery($hostname, $port=25565)
    {
        $vars = self::startQuery($hostname, $port, true);
        $fp = $vars['fp'];
        $time = $vars['time'];

        fread($fp, 11);

        $doubleNulsEncountered = 0;
        $lastWasNul = false;
        $statResponse = '';
        // Should encounter double null only thrice.
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


/*
  =========================
  Program portion of mcstat
  =========================

  Make sure to add a shebang to the first line to use as a cli program. Note
  the shebang will be visible in webpages, so don't use a shebanged copy in
  a website. An example shebang as follows:

  #!/usr/bin/env php


  Invocation like so:

  $ mcstat uberminecraft.com
  uberminecraft.com v1.7.4 2714/5000 131ms
  Uberminecraft Cloud | 22 Games
  1.7 Play Now!
 */

// This is PHP's idiom to check if script is being invoked directly.
// http://stackoverflow.com/questions/2413991/php-equivalent-of-pythons-name-main
if (!count(debug_backtrace())) {
    error_reporting(E_ERROR | E_PARSE);
    $STDERR = fopen('php://stderr', 'w+');
    $errorCount = 0;

    $args = array_slice($argv, 1);

    foreach ($args as $arg) {
        $hostWithPort = explode(':', $arg);
        $len = count($hostWithPort);
        $host = $hostWithPort[0];
        $port = 25565;
        if ($len == 2) {
            $port = $hostWithPort[1];
        } elseif ($len != 1) {
            print('Invalid host '.$arg);
            exit(++$errorCount);
        }

        $m = new MinecraftStatus($host, $port);
        $reply = $m->ping();
        if (!$reply) {
            fwrite($STDERR, 'Error pinging '.$host.':'.$port.' ('.$m->lastError.")\n");
            $errorCount++;
            continue;
        }
        $motd = preg_replace("/\\x{00A7}./u", '', $reply['motd']);

        $message = $host;
        $message .= ($port == 25565) ? '' : ':'.$port;
        $message .= ' '.$reply['server_version'];
        $message .= ' '.$reply['player_count'].'/'.$reply['player_max'];
        $message .= ' '.$reply['latency'].'ms'."\n";
        $message .= $motd."\n";
        print($message);
    }
    exit($errorCount);
}

?>