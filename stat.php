<?php

require_once './mcstat.php';
require_once './mcformat.php';

$hostname = htmlspecialchars($_GET['server']);

if ($hostname) {
    $status = MC_getServerStatus($hostname);
}

echo '
<html>
<head>
<style>
.motd {
text-shadow: 1px 1px 1px #000000;
        filter: dropshadow(color=#000000, offx=1, offy=1);
}
</style>';

echo '<title> Minecraft Server Status' . ($hostname ? ' :: ' . $hostname : '') . '</title>';

echo '</head>
<body>
<p>Query server status:</p>
<form name="MC" method="get" action="">
<input type="text" name="server" onClick="this.select();" value="'.($hostname ? $hostname : '').'">
<input type="submit">
</form>';

if ($hostname) {
    echo '<h1>Status for ' . $hostname . '</h1>';
    if ($status) {
        echo '<table>
<tr><th>MOTD</th><th>Server version</th><th>Players</th><th>Ping</th></tr>';
        echo '<tr><td class="motd">' . MC_parseMotdColors($status['motd']). '</td><td>' .
            $status['server_version'] . '</td><td>' . $status['player_count'] .
            '/' . $status['player_max'] . '</td><td>' . $status['latency'] . '</td></tr>';
        echo '</table>';
    } else {
        echo '<p>Could not query server.</p>';
    }
}

echo '</body>
</html>';
        

?>
