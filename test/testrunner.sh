#!/usr/bin/env bash
: ${Versions:='1.4.2 1.4.4 1.4.5 1.4.6 1.4.7 1.5 1.5.1 1.5.2 1.6.1 1.6.2 1.6.4 1.7.2 1.7.4 1.7.5'}
: ${Port:='9876'}
: ${Hostname:='127.0.0.1'}
MinecraftUsersDead='players.value 0
max_players.value 0'
MinecraftUsersEmpty='players.value 0
max_players.value 20'

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
    rm "$ServerDir/PIDFILE"
}

runTest() {
    Version=$1
    JarFile="minecraft_server.$Version.jar"
    JarDownload="https://s3.amazonaws.com/Minecraft.Download/versions/$Version/$JarFile"
    ServerDir="server-$Version"
    run mkdir -p "$ServerDir"
    [ -e "$ServerDir/PIDFILE" ] && kill $(<"$ServerDir/PIDFILE") &>/dev/null
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

    echo 'Testing minecraft_users.php against empty server.'
    reply="$(host="$Hostname" port="$Port" ../minecraft_users.php)"
    if [ "$reply" != "$MinecraftUsersEmpty" ]; then
        echo 'minecraft_users.php failed "Empty server test".'
        printf 'Got "%s"\n' "$reply"
        ret=$(($ret + 1))
    else
        echo .
    fi

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

echo 'Testing minecraft_users.php against a dead server.'
reply="$(host="$Hostname" port="$Port" ../minecraft_users.php)"
if [ "$reply" != "$MinecraftUsersDead" ]; then
    echo 'FAILED'
    errors=$(($errors + 1))
else
    echo .
fi
echo 'Testing minecraft_users.php against unresponsive server.'
nc -l "$Port" >/dev/null &
ncpid=$!
reply="$(host="$Hostname" port="$Port" ../minecraft_users.php)"
if [ "$reply" != "$MinecraftUsersDead" ]; then
    echo 'FAILED'
    errors=$(($errors + 1))
else
    echo .
fi
kill $ncpid &>/dev/null

exit $errors