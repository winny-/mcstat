#!/usr/bin/env bash
Versions='1.5.2 1.7.4 1.7.5'
Port='9876'
Hostname='127.0.0.1'

ServerProperties='test-server.properties'

runtest() {
    Version=$1
    JarFile="minecraft_server.$Version.jar"
    JarDownload="https://s3.amazonaws.com/Minecraft.Download/versions/$Version/$JarFile"
    ServerDir="server-$Version"
    run mkdir -p "$ServerDir"
    killserver
    if [ ! -e "$ServerDir/$JarFile" ]; then
        printf 'Downloading %s\n' "$JarFile"
        run curl -\# -o "$ServerDir/$JarFile" "$JarDownload"
    fi
    printf 'Starting server %s ...\n' "$Version"
    run cp "$ServerProperties" "$ServerDir/server.properties"
    run sed -e "s|VERSION|$Version|" -e "s|PORT|$Port|" -e "s|MOTD|$Motd|" \
        -e "s|HOSTNAME|$Hostname|" < config-template.php > config.php
    cd "$ServerDir"
    java -jar "$JarFile" -Xmx256M -Xms128M nogui &> "mylog.txt" &
    cd - >/dev/null
    echo "$!" > "$ServerDir/PIDFILE"

    # Ideally this script should watch the log. Works for now.
    echo 'Waiting 20 seconds for sever to initialize.'
    sleep 20

    echo 'Running tests.'
    echo
    phpunit -v --color --debug test.php
    ret=$?

    killserver
}

killserver() {
    kill -TERM $(cat "$ServerDir/PIDFILE") 2>/dev/null
    # Same as above comment, should watch for the even, not simply wait a while.
    sleep 5
}

run() {
    if ! "$@"; then
        printf 'Command "%s" failed. Exiting.\n' "$*" >&2
        exit 1
    fi
}

errors=0
for v in $Versions; do
    runtest "$v"
    errors=$(($errors + $ret))
done
exit $errors