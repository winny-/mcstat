#!/usr/bin/env bash
: ${Versions:='1.4.2 1.4.4 1.4.5 1.4.6 1.4.7 1.5 1.5.1 1.5.2 1.6.1 1.6.2 1.6.4 1.7.2 1.7.4 1.7.5'}
: ${Port:='9876'}
: ${Hostname:='127.0.0.1'}
MinecraftUsersSymlink="./minecraft_users_$Hostname:$Port"
MinecraftUsersDead='players.value 0
max_players.value 0'
MinecraftUsersEmpty='players.value 0
max_players.value 20'

ServerProperties='test-server.properties'

ok() {
    tput setaf 2
    printf 'OK\n'
    tput sgr0
}

fail() {
    tput setaf 1
    printf 'FAIL\n'
    tput sgr0
}

configureServer() {
    run cp "$ServerProperties" "$ServerDir/server.properties"
    run sed -e "s|VERSION|$Version|" -e "s|PORT|$Port|" -e "s|MOTD|$Motd|" \
        -e "s|HOSTNAME|$Hostname|" < config-template.php > config.php
    [ ! -p "$ServerDir/log.fifo" ] && run mkfifo "$ServerDir/log.fifo"
}

startServer() {
    printf '>> Starting server v%s ... ' "$Version"

    cd "$ServerDir"
    java -jar "$JarFile" -Xmx256M -Xms128M nogui &> 'log.fifo' &
    cd - >/dev/null
    echo "$!" > "$ServerDir/PIDFILE"
    run fgrep -m 1 'Query running on' < "$ServerDir/log.fifo" &>/dev/null
    ok
}

stopServer() {
    printf '>> Stopping server v%s ... ' "$Version"
    pid=$(<"$ServerDir/PIDFILE")
    while ps "$pid" &>/dev/null; do
        kill "$pid"
        sleep 2
    done
    ok
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
        printf '>> Downloading %s\n' "$JarFile"
        run curl -\# -o "$ServerDir/$JarFile" "$JarDownload"
    fi

    printf '> Testing against Minecraft Server v%s\n' "$Version"

    configureServer
    startServer

    echo '>>> Running PHPUnit ...'
    phpunit --colors test.php
    ret=$?

    printf '>>> Testing minecraft_users_ against empty server ... '
    reply="$("$MinecraftUsersSymlink")"
    if [ "$reply" != "$MinecraftUsersEmpty" ]; then
        fail
        printf '>>> Got "%s"\n' "$reply"
        ret=$(($ret + 1))
    else
        ok
    fi

    stopServer
    echo '===================='
}

run() {
    if ! "$@"; then
        printf '> Command "%s" failed. Exiting.\n' "$*" >&2
        exit 1
    fi
}

cd "$(dirname "$0")"
ln -sf ../minecraft_users_program.php "$MinecraftUsersSymlink"
errors=0
for v in $Versions; do
    runTest "$v"
    errors=$(($errors + $ret))
done

printf '>>> Testing minecraft_users_ against a dead server ... '
reply="$("$MinecraftUsersSymlink")"
if [ "$reply" != "$MinecraftUsersDead" ]; then
    fail
    errors=$(($errors + 1))
else
    ok
fi
printf '>>> Testing minecraft_users_ against unresponsive server ... '
nc -l "$Port" >/dev/null &
ncpid=$!
reply="$("$MinecraftUsersSymlink")"
if [ "$reply" != "$MinecraftUsersDead" ]; then
    fail
    errors=$(($errors + 1))
else
    ok
fi
kill $ncpid &>/dev/null

if [ $errors -eq 0 ]; then
    tput setaf 2
    printf 'All tests passed!\n'
    tput sgr0
else
    tput setaf 1
    printf '%s tests failed!\n' "$errors"
    tput sgr0
fi
exit $errors