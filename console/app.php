#!/usr/bin/env php

<?php

require_once '../vendor/autoload.php';

use AlexVenga\BracketsMathematicalExpressions;
use AlexVenga\CheckBrackets;

error_reporting(E_ALL);

/* Позволяет скрипту ожидать соединения бесконечно. */
set_time_limit(0);

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
    echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
    exit;
}

if (socket_bind($sock, $address, $port) === false) {
    echo "socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
    exit;
}

if (socket_listen($sock, 5) === false) {
    echo "socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
    exit;
}

echo "Listen port: " . $port . "...\n";

do {
    if (($msgsock = socket_accept($sock)) === false) {
        echo "socket_accept() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
        break;
    }

    do {
        if (false === ($buf = socket_read($msgsock, 2048, PHP_BINARY_READ))) {
            echo "socket_read() failed: reason: " . socket_strerror(socket_last_error($msgsock)) . "\n";
            break 2;
        }
        if (!$buf = trim($buf)) {
            continue;
        }

        if ($buf == 'quit') {
            break;
        }
        if ($buf == 'shutdown') {
            socket_close($msgsock);
            break 2;
        }

        // обработка самого запроса

        try {
            $data = new BracketsMathematicalExpressions($buf);
            $calculator = new CheckBrackets();
            if ($calculator->checkBracketsInString($data)) {
                socket_write($msgsock, "1\n");
                echo $buf . ': Mathematical string OK' . PHP_EOL;
            } else {
                socket_write($msgsock, "0\n");
                echo $buf . ': Mathematical string ERROR' . PHP_EOL;
            }
        } catch (InvalidArgumentException $ex) {
            socket_write($msgsock, "ERROR: InvalidArgumentException: " . $ex->getMessage() . "\n");
        } catch (Exception $ex) {
            socket_write($msgsock, "Exception: " . $ex->getMessage() . "\n");
        }

    } while (true);
    socket_close($msgsock);
} while (true);

socket_close($sock);