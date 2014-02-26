<?php

require_once dirname(__FILE__).'/../mcstat.php';

class MinecraftStatusTest extends PHPUnit_Framework_TestCase
{
    public function testServerReply()
    {
        $m = new MinecraftStatus('localhost', 9876);
        $ping = $m->ping();

        $this->assertNull($m->lastError);
        $this->assertEquals('1.7.4', $ping['server_version']);
        $this->assertEquals('A Minecraft Server', $ping['motd']);
        $this->assertEquals(0, $ping['player_count']);
        $this->assertEquals(20, $ping['player_max']);
        $this->assertEquals(true, is_float($ping['latency']));
    }

    public function testBasicQuery()
    {
        $m = new MinecraftStatus('localhost', 9876);
        $query = $m->query(false);

        $this->assertNull($m->lastError);
        $this->assertEquals('A Minecraft Server', $query['motd']);
        $this->assertEquals(0, $query['player_count']);
        $this->assertEquals(20, $query['player_max']);
        $this->assertEquals(true, is_float($query['latency']));
    }
}

?>