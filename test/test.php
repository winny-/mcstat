<?php

require_once dirname(__FILE__).'/../mcstat.php';
require_once 'config.php';

class MinecraftStatusTest extends PHPUnit_Framework_TestCase
{
    public function testServerListPing()
    {
        global $hostname, $port, $mc_version, $motd;

        $m = new MinecraftStatus($hostname, $port);
        $ping = $m->ping();

        $this->assertNull($m->lastError);
        $this->assertEquals($mc_version, $ping['server_version']);
        $this->assertEquals($motd, $ping['motd']);
        $this->assertEquals(0, $ping['player_count']);
        $this->assertEquals(20, $ping['player_max']);
        $this->assertEquals(true, is_float($ping['latency']));
    }

    public function testBasicQuery()
    {
        global $hostname, $port, $mc_version, $motd;

        $m = new MinecraftStatus($hostname, $port);
        $query = $m->query(false);

        $this->assertNull($m->lastError);
        $this->assertEquals($motd, $query['motd']);
        $this->assertEquals(0, $query['player_count']);
        $this->assertEquals(20, $query['player_max']);
        $this->assertEquals(true, is_float($query['latency']));
        $this->assertEquals($port, $query['port']);
    }

    public function testFullQuery()
    {
        global $hostname, $port, $mc_version, $motd;

        $m = new MinecraftStatus($hostname, $port);
        $query = $m->query(true);

        $this->assertNull($m->lastError);
        $this->assertEquals($motd, $query['motd']);
        $this->assertEquals(0, $query['player_count']);
        $this->assertEquals(20, $query['player_max']);
        $this->assertEquals(true, is_float($query['latency']));
        $this->assertEquals($port, $query['port']);
    }
}

?>