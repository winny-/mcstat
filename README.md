# mcstat

[![Build Status](https://travis-ci.org/winny-/mcstat.png?branch=master)](https://travis-ci.org/winny-/mcstat)

PHP class, web page, CLI tool, and [Munin][] plugin to get information from a
[Minecraft][] server.

[Munin]: http://munin-monitoring.org/
[Minecraft]: http://www.minecraft.net/

## Protocol Support

mcstat supports 1.5.2 style [Server List Ping][] which works with minecraft server `1.4.2` and later, including `1.7.5`.
mcstat also supports the UDP [Query][] protocol.

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

### minecraft_users_ — A Munin plugin

![Screenshot of the minecraft_users_ plugin](https://i.imgur.com/VutO3X9.png)

Install minecraft_users_ like any other munin plugin:

    # cp minecraft_users.php /usr/share/munin/plugins/minecraft_users
    # chmod 755 /usr/share/munin/plugins/minecraft_users
    # ln -s /usr/share/munin/plugins/minecraft_users_ /etc/munin/plugins/minecraft_users_<hostname>:<port>

No configuration is necessary because minecraft_users_ is a wildcard plugin.

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

## Testing

The testing script requires `bash`, [`phpunit`][phpunit], and `java`. The tests
are ran against against a live server running on localhost.

Run the script as follows:

    cd test && ./testrunner.sh

By default `testrunner.sh` tests against all server versions `1.4.2` and later.
Override this like so:

    cd test && env Versions='1.7.4 1.7.5' ./testrunner.sh

As of commit `979fed97d06a35a96af9195e7750ea1648602154`, `basicQuery`,
`fullQuery`, and `serverListPing` all pass.

[phpunit]: http://phpunit.de/