#!/usr/bin/env bash
Versions='1.4.2 1.4.4 1.4.5 1.4.6 1.4.7 1.5 1.5.1 1.5.2 1.6.1 1.6.2 1.6.4 1.7.2 1.7.4 1.7.5'
Port='9876'
Hostname='127.0.0.1'

ServerProperties='test-server.properties'

waitForServerStart() {
    printf 'Waiting for server to start up... '
    run fgrep -m 1 'Query running on' < "$ServerDir/log.fifo" &>/dev/null
    printf 'ready.\n'
}

waitForServerStop() {
    printf 'Waiting for server to stop... '
    pid=$(<"$ServerDir/PIDFILE")
    while ps "$pid" &>/dev/null; do
        kill "$pid"
        sleep 2
    done
    printf 'stopped.\n'
}

runTest() {
    Version=$1
    JarFile="minecraft_server.$Version.jar"
    JarDownload="https://s3.amazonaws.com/Minecraft.Download/versions/$Version/$JarFile"
    ServerDir="server-$Version"
    run mkdir -p "$ServerDir"
    kill $(<"$ServerDir/PIDFILE") &>/dev/null
    if [ ! -e "$ServerDir/$JarFile" ]; then
        printf 'Downloading %s\n' "$JarFile"
        run curl -\# -o "$ServerDir/$JarFile" "$JarDownload"
    fi
    printf 'Starting server %s ...\n' "$Version"
    run cp "$ServerProperties" "$ServerDir/server.properties"
    run sed -e "s|VERSION|$Version|" -e "s|PORT|$Port|" -e "s|MOTD|$Motd|" \
        -e "s|HOSTNAME|$Hostname|" < config-template.php > config.php
    [ ! -p "$ServerDir/log.fifo" ] && run mkfifo "$ServerDir/log.fifo"
    cd "$ServerDir"
    java -jar "$JarFile" -Xmx256M -Xms128M nogui &> 'log.fifo' &
    cd - >/dev/null
    echo "$!" > "$ServerDir/PIDFILE"

    waitForServerStart

    echo 'Running tests.'
    echo
    phpunit -v --color --debug test.php
    ret=$?

    waitForServerStop
}

run() {
    if ! "$@"; then
        printf 'Command "%s" failed. Exiting.\n' "$*" >&2
        exit 1
    fi
}

errors=0
for v in $Versions; do
    runTest "$v"
    errors=$(($errors + $ret))
done
exit $errors