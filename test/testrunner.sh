#!/usr/bin/env bash
JarDownload='https://s3.amazonaws.com/Minecraft.Download/versions/1.7.4/minecraft_server.1.7.4.jar'
JarFile='minecraft_server.1.7.4.jar'
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
    printf 'Downloading %s\n' "JarFile"
    run curl -\# -O "$JarDownload"
fi

killserver

echo 'Starting server...'
run cp "../$ServerProperties" 'server.properties'
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
