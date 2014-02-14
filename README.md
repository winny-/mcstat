# mcstat

PHP class to get information from a Minecraft server. 

## Protocol Support

mcstat supports 1.5.2 style [Server List Ping][] which still works on 1.7.4.
It also supports the UDP [Query][] protocol.

[Server List Ping]: http://wiki.vg/Server_List_Ping
[Query]: http://wiki.vg/Query

## Usage

### stat.php
`stat.php` is a simple web page that lets users query a given server.
**Note:** `stat.php` shouldn't be used on a public server as it's not
well tested!

![Screenshot of stat.php](https://i.imgur.com/Nc4yVOi.png)

### Usage as a PHP Class
    php > require_once './mcstat.php';
    php > $m = new MinecraftStatus('Uberminecraft.com');
    php > var_dump($m->ping());
    array(6) {
      ["player_count"]=>
      string(4) "2026"
      ["player_max"]=>
      string(4) "5000"
      ["motd"]=>
      string(62) "§aUberminecraft §aCloud §6| §c22 Games§b
    §l1.7 Play Now!"
      ["server_version"]=>
      string(5) "1.7.4"
      ["protocol_version"]=>
      string(1) "8"
      ["latency"]=>
      float(150)
    }