#!/usr/bin/env php
<?php

/*
  ===============
  minecraft_users
  ===============

  This is munin plugin to monitor the player count on a Minecraft server.
  Install it like any other munin plugin:
  # cp minecraft_users.php /usr/share/munin/plugins/minecraft_users
  # chmod 755 /usr/share/munin/plugins/minecraft_users
  # ln -s /usr/share/munin/plugins/minecraft_users /etc/munin/plugins/minecraft_users

  Config:
  [minecraft_users]
  env.host aminecraftserver.org
  env.port 25565
 */

error_reporting(E_ERROR | E_PARSE);

$host = getenv('host');
$host = $host ? $host : 'localhost';

$port = getenv('port');
$port = $port ? $port : '25565';

if ((count($argv) > 1) && ($argv[1] == 'config')) {
    print("graph_title Players connected to {$host}:{$port}\n");
    print("graph_vlabel players\n");
    print("players.label Number of players\n");
    print("max_players.label Max players\n");
    print("graph_info Number of players connected to Minecraft. " .
          "If Max players is 0, the server is unreachable.\n");
    print("graph_scale no\n");
    print("graph_category minecraft\n");
    exit();
}

/*
  ================
  Server List Ping
  ================

  An example of how to get a Minecraft server status's using a "Server List Ping" packet.
  See details here: http://www.wiki.vg/Server_List_Ping
*/

function MC_packString($string)
{
    $letterCount = strlen($string);
    return pack('n', $letterCount) . mb_convert_encoding($string, 'UTF-16BE');}
}

// This is needed since UCS-2 text rendered as UTF-8 contains unnecessary null bytes
// and could cause other components, especially string functions to blow up. Boom!
function MC_decodeUTF16BE($string)
{
    return mb_convert_encoding($string, 'UTF-8', 'UTF-16BE');
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
                 'player_count' => MC_decodeUTF16BE($response[4]),
                 'player_max' => MC_decodeUTF16BE($response[5]),
                 'motd' => MC_decodeUTF16BE($response[3]),
                 'server_version' => MC_decodeUTF16BE($response[2]),
                 'protocol_version' => MC_decodeUTF16BE($response[1]),
                 'latency' => $time
                 );
}

// ============================================================

$reply = MC_serverListPing($host, $port);

print('players.value ' . $reply['player_count'] . "\n");
print('max_players.value ' . $reply['player_max'] . "\n");
?>