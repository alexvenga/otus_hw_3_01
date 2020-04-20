#!/usr/bin/env php
<?php

error_reporting(E_ALL);

/* Позволяет скрипту ожидать соединения бесконечно. */
set_time_limit(0);

function writeFileLog($message = '', $type = 'INFO')
{
    $fileHandler = fopen(dirname(__DIR__) . '/logs/messages.log', 'a');
    fwrite($fileHandler, '[' . date('Y-m-d H:i:s') . '] ' . $type . ': ' . $message . PHP_EOL);
    fclose($fileHandler);
    echo '[' . date('Y-m-d H:i:s') . '] ' . $type . ': ' . $message . PHP_EOL;
}

/* Включает скрытое очищение вывода так, что мы видим данные
 * как только они появляются. */
ob_implicit_flush();

$shortopts = "";
$shortopts .= "p:";  // Обязательное значение порта

$longopts = [
    "port:",
];
$options = getopt($shortopts, $longopts);

$port = false;
if (isset($options['p'])) {
    $options['p'] = (int)$options['p'];
    $port = $options['p'];
}
if ((!$port) && isset($options['port'])) {
    $options['port'] = (int)$options['port'];
    $port = $options['port'];
}
if (!$port) {
    $port = 7777;
}

$address = '192.168.0.37';

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    writeFileLog("socket_create() failed: reason: " . socket_strerror(socket_last_error()), 'ERROR');
    exit;
}

if (socket_bind($sock, $address, $port) === false) {
    writeFileLog("socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)), 'ERROR');
    exit;
}

if (socket_listen($sock, 5) === false) {
    writeFileLog("socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock)), 'ERROR');
    exit;
}

writeFileLog("Listen " . $address . " " . $port . "...", 'START');

$stop_server = false;

while (!$stop_server) {

    if (($msgsock = socket_accept($sock)) === false) {
        writeFileLog("socket_accept() failed: reason: " . socket_strerror(socket_last_error($sock)), 'ERROR');
        continue;
    }

    socket_getpeername($msgsock, $address);
    writeFileLog('New socket connected from "'.$address.'"');

    $stop_socket = false;

    while (!$stop_socket) {

        if (false === ($buf = socket_read($msgsock, 2048, PHP_BINARY_READ))) {
            writeFileLog("socket_read() failed: reason: " . socket_strerror(socket_last_error($msgsock)), 'ERROR');
            continue;
        }

        if (!$buf = trim($buf)) {
            continue;
        }

        writeFileLog("New message ".$buf);

    }

}

