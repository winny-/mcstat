#!/usr/bin/env bash
Version='1.7.4'
Port='9876'
Hostname='127.0.0.1'
JarFile="minecraft_server.$Version.jar"
JarDownload="https://s3.amazonaws.com/Minecraft.Download/versions/$Version/$JarFile"
SERVERDIR='server'
ServerProperties='test-server.properties'

killserver() {
    kill -TERM $(cat PIDFILE) 2>/dev/null
}

run() {
    if ! "$@"; then
        printf 'Command "%s" failed. Exiting.\n' "$*" >&2
        exit 1
    fi
}

run mkdir -p "$SERVERDIR"
run cd "$SERVERDIR"
if [ ! -e "$JarFile" ]; then
    printf 'Downloading %s\n' "$JarFile"
    run curl -\# -O "$JarDownload"
fi

killserver

echo 'Starting server...'
run cp "../$ServerProperties" 'server.properties'
sed -e "s|VERSION|$Version|" -e "s|PORT|$Port|" -e "s|MOTD|$Motd|" -e "s|HOSTNAME|$Hostname|" < ../config-template.php > ../config.php
java -jar "$JarFile" -Xmx256M -Xms128M nogui &>/dev/null &
echo "$!" > PIDFILE

echo 'Waiting 10 seconds for sever to initialize.'
sleep 10

echo 'Running tests.'
echo
phpunit ../test.php
ret=$?

killserver
exit $ret
