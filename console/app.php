#!/usr/bin/env php
<?php

require_once '../vendor/autoload.php';

use AlexVenga\BracketsMathematicalExpressions;
use AlexVenga\CheckBrackets;

error_reporting(E_ALL);

/* Позволяет скрипту ожидать соединения бесконечно. */
set_time_limit(0);

function writeFileLog($message = '', $type = 'INFO')
{
    $fileHandler = fopen(dirname(__DIR__) . '/logs/messages.log', 'a');
    fwrite($fileHandler, '[' . date('Y-m-d H:i:s') . '] ' . $type . ': ' . $message . PHP_EOL);
    fclose($fileHandler);
}

function isDaemonActive($pid_file) {
    if( is_file($pid_file) ) {
        $pid = file_get_contents($pid_file);
        //проверяем на наличие процесса
        if(posix_kill($pid,0)) {
            //демон уже запущен
            return true;
        } else {
            //pid-файл есть, но процесса нет
            if(!unlink($pid_file)) {
                //не могу уничтожить pid-файл. ошибка
                exit(-1);
            }
        }
    }
    return false;
}

if (isDaemonActive('/tmp/alexvenga_otushw3001.pid')) {
    echo 'Daemon already active';
    exit;
}

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

// создаем дочерний процесс
$child_pid = pcntl_fork();

if( $child_pid ) {
    // выходим из родительского, привязанного к консоли, процесса
    exit;
}

// делаем основным процессом дочерний.
// После этого он тоже может плодить детей.
// Суровая жизнь у этих процессов...
posix_setsid();

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

writeFileLog("Listen port: " . $port . "...");

$child_processes = [];

$stop_server = false;

declare(ticks=1);

//Обработчик
function sigHandler($signo) {
    global $stop_server;
    switch($signo) {
        case SIGTERM: {
            $stop_server = true;
            break;
        }
        default: {
            //все остальные сигналы
        }
    }
}
//регистрируем обработчик
pcntl_signal(SIGTERM, "sigHandler");

$child_processes = array();

while (!$stop_server) {

    if (($msgsock = socket_accept($sock)) === false) {
        writeFileLog("socket_accept() failed: reason: " . socket_strerror(socket_last_error($sock)), 'ERROR');
        $stop_server = true;
    }

    socket_getpeername($msgsock, $socket_address);

    $pid = pcntl_fork();
    if ($pid == -1) {
        writeFileLog('ошибка - не смогли создать процесс', 'ERROR');
    } elseif ($pid) {
        writeFileLog('создан процесс для обработки соединения ' . $socket_address . '');
        //процесс создан

        while ($signaled_pid = pcntl_waitpid(-1, $status, WNOHANG)) {
            if ($signaled_pid == -1) {
                $child_processes = array();
                break;
            } else {
                unset($child_processes[$signaled_pid]);
            }
        }

        $child_processes[$pid] = true;
    } else {
        $child_pid = getmypid();
        writeFileLog('IP: ' . $socket_address . ' Начат процесс для обработки соединения ' . $socket_address . '');

        while (true) {
            if (false === ($buf = socket_read($msgsock, 2048, PHP_BINARY_READ))) {
                writeFileLog("socket_read() failed: reason: " . socket_strerror(socket_last_error($msgsock)), 'ERROR');
                $stop_server = true;
                break;
            }

            if (!$buf = trim($buf)) {
                continue;
            }

            if ($buf == 'quit') {
                break;
            }

            writeFileLog('IP: ' . $socket_address . ' Начат процесс для обработки запроса "' . $buf . '"');

            try {
                $data = new BracketsMathematicalExpressions($buf);
                $calculator = new CheckBrackets();
                if ($calculator->checkBracketsInString($data)) {
                    socket_write($msgsock, "1\n");
                    writeFileLog('IP: ' . $socket_address . ' Результат обработки запроса 1"');
                } else {
                    socket_write($msgsock, "0\n");
                    writeFileLog('IP: ' . $socket_address . ' Результат обработки запроса 0"');
                }
            } catch (InvalidArgumentException $ex) {
                socket_write($msgsock, "ERROR: InvalidArgumentException: " . $ex->getMessage() . "\n");
            } catch (Exception $ex) {
                socket_write($msgsock, "Exception: " . $ex->getMessage() . "\n");
            }
        }
        exit;
    }

    socket_close($msgsock);
}

socket_close($sock);
