# mcstat

PHP class, web page, CLI tool, and [Munin][] plugin to get information from a
[Minecraft][] server.

[Munin]: http://munin-monitoring.org/
[Minecraft]: http://www.minecraft.net/

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

### mcstat as a Program
`mcstat.php` may be invoked as a program. Because it's also a php library,
it doesn't come with a shebang line. Install like this:

    $ echo '#!/usr/bin/env php'|cat - mcstat.php > ~/bin/mcstat
    $ chmod 755 ~/bin/mcstat

It's very simple and gets the job done:

    $ mcstat uberminecraft.com
    uberminecraft.com v1.7.4 2714/5000 131ms
    Uberminecraft Cloud | 22 Games
    1.7 Play Now!

*Please note:
[`TERM` must be set to a known terminal](https://github.com/nodesocket/commando/issues/9),
otherwise php spams stderr unconditionally.*

### minecraft_users.php — A Munin plugin

![Screenshot of the minecraft_users.php plugin](https://i.imgur.com/lAfCXLF.png)

Install minecraft_users.php like any other munin plugin:

    # cp minecraft_users.php /usr/share/munin/plugins/minecraft_users
    # chmod 755 /usr/share/munin/plugins/minecraft_users
    # ln -s /usr/share/munin/plugins/minecraft_users /etc/munin/plugins/minecraft_users

This is how you can configure the plugin:

    [minecraft_users]
    env.host aminecraftserver.org
    env.port 25565

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