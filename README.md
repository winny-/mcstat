# mcstat

[![Build Status](https://travis-ci.org/winny-/mcstat.png?branch=master)](https://travis-ci.org/winny-/mcstat)

PHP class, web page, CLI tool, and [Munin][] plugin to get information from a
[Minecraft][] server.

[Munin]: http://munin-monitoring.org/
[Minecraft]: http://www.minecraft.net/

## Protocol Support

mcstat supports [Server List Ping][] as seen in `1.7` and later, and `1.5.2`. Server List Ping `1.5.2` works for older Minecraft servers (all the way back to `1.4.2`), while the `1.7` Server List Ping should be used for newer setups. mcstat also supports the UDP full and basic [Query][] protocols.

[Server List Ping]: http://wiki.vg/Server_List_Ping
[Query]: http://wiki.vg/Query

## Usage

### stat.php

`stat.php` is a simple web page that lets users query a given server.
**Note:** `stat.php` shouldn't be used on a public server as it's not
well tested!

![Screenshot of stat.php](https://i.imgur.com/Nc4yVOi.png)

### mcstat as a Program

`mcstat_program.php` is a script for querying Minecraft servers. You can install a stand-alone version like so:

    $ make
    $ cp mcstat ~/bin/mcstat

It's very simple and gets the job done:

    $ mcstat uberminecraft.com
    uberminecraft.com 1.7.4 2714/5000 131ms
    Uberminecraft Cloud | 22 Games
    1.7 Play Now!

*Please note:
[`TERM` must be set to a known terminal](https://github.com/nodesocket/commando/issues/9),
otherwise php spams stderr unconditionally.*

### minecraft_users_ — A Munin plugin

![Screenshot of the minecraft_users_ plugin](https://i.imgur.com/VutO3X9.png)

Install minecraft_users_ like any other munin plugin:

    $ make # This create a stand-alone minecraft_users_ script
    # cp minecraft_users_ /usr/share/munin/plugins/minecraft_users_
    # chmod 755 /usr/share/munin/plugins/minecraft_users
    # ln -s /usr/share/munin/plugins/minecraft_users_ \
        /etc/munin/plugins/minecraft_users_<hostname>:<port>
    # service munin-node reload

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

    make test

By default `testrunner.sh` tests against all server versions `1.4.2` and later.
Override this like so:

    env Versions='1.7.4 1.7.5' make test

As of commit `979fed97d06a35a96af9195e7750ea1648602154`, `basicQuery`,
`fullQuery`, and `serverListPing` all pass.

[phpunit]: http://phpunit.de/
