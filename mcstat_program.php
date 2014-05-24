#!/usr/bin/env php
<?php
require_once dirname(__FILE__).'/mcstat.php';

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