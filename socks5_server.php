#!/usr/bin/env php
<?php

define('STATE_GREETING', 0);
define('STATE_REQUEST', 1);
define('STATE_TUNNELING', 2);

class Socks5Server {
    private $host;
    private $port;
    private $server;
    private $clients = [];
    private $targets = [];
    private $mappings = [];
    private $states = [];
    private $running = true;
    private $totalBytes = 0;
    private $connections = [];
    private $statsFile;
    private $logFile;

    private function writeStats() {
        @file_put_contents($this->statsFile, json_encode([
            'active_connections' => count($this->clients),
            'total_bytes' => $this->totalBytes,
            'connections' => array_values($this->connections),
            'last_updated' => time()
        ]));
    }

    public function __construct($host = '127.0.0.1', $port = 1080) {
        $this->host = $host;
        $this->port = $port;
        $this->statsFile = __DIR__ . '/stats.json';
        $this->logFile = __DIR__ . '/socks5.log';
    }

   
    public function start() {
        $address = "tcp://{$this->host}:{$this->port}";
        $this->server = @stream_socket_server($address, $errno, $errstr);

        if (!$this->server) {
            $this->log(0, "Failed to start server on {$address}: {$errstr} ({$errno})", "red");
            exit(1);
        }

        
        stream_set_blocking($this->server, false);

        $this->log(0, "SOCKS5 Proxy Server started on {$this->host}:{$this->port}", "green");
        $this->log(0, "Waiting for connections...", "yellow");


        if (function_exists('pcntl_signal')) {
            declare(ticks = 1);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
        }

        $this->run();
    }

    
    public function shutdown() {
        $this->log(0, "Shutting down SOCKS5 server...", "yellow");
        $this->running = false;
        
        foreach (array_keys($this->clients) as $id) {
            $this->closeConnection($id);
        }
        foreach (array_keys($this->targets) as $id) {
            $this->closeConnection($id);
        }
        
        if (is_resource($this->server)) {
            fclose($this->server);
        }
        @unlink(__DIR__ . '/stats.json');
        @unlink('socks5.pid');
        $this->log(0, "Server shutdown complete.", "green");
        exit(0);
    }

   
    private function run() {
        while ($this->running) {
            
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $read = [];
            $write = [];
            $except = null;

            
            if (is_resource($this->server)) {
                $read[] = $this->server;
            }

     
            foreach ($this->clients as $id => $socket) {
                if (is_resource($socket)) {
                    $read[] = $socket;
                } else {
                    $this->closeConnection($id);
                }
            }

    
            foreach ($this->targets as $id => $socket) {
                if (is_resource($socket)) {
                    $read[] = $socket;
                } else {
                    $this->closeConnection($id);
                }
            }

            if (empty($read)) {
                usleep(50000); 
                continue;
            }

          
            $numChanged = @stream_select($read, $write, $except, 1, 0);

            if ($numChanged === false) {
                continue;
            }

            foreach ($read as $socket) {
                if ($socket === $this->server) {
                    $this->handleNewConnection();
                } else {
                    $id = (int)$socket;
                    if (isset($this->clients[$id])) {
                        $this->handleClientData($id);
                    } elseif (isset($this->targets[$id])) {
                        $this->handleTargetData($id);
                    }
                }
            }
        }
    }

    
    private function handleNewConnection() {
        $clientSocket = @stream_socket_accept($this->server, 0);
        if (!$clientSocket) {
            return;
        }

        stream_set_blocking($clientSocket, false);
        $clientId = (int)$clientSocket;

        $this->clients[$clientId] = $clientSocket;
        $this->states[$clientId] = STATE_GREETING;

        
        $peerName = stream_socket_get_name($clientSocket, true);
        $clientIp = $peerName;
        $clientPort = null;
        if ($peerName !== false && strrpos($peerName, ':') !== false) {
            $pos = strrpos($peerName, ':');
            $clientIp = substr($peerName, 0, $pos);
            $clientPort = intval(substr($peerName, $pos + 1));
        }
        $this->connections[$clientId] = [
            'id' => $clientId,
            'client_ip' => $clientIp,
            'client_port' => $clientPort,
            'target' => '-',
            'started_at' => date('Y-m-d H:i:s'),
            'bytes_up' => 0,
            'bytes_down' => 0
        ];
        $this->log($clientId, "Incoming connection from {$peerName}", "green");
        $this->writeStats();
    }

 
    private function handleClientData($id) {
        $socket = $this->clients[$id];
        $state = $this->states[$id] ?? STATE_GREETING;

        if ($state === STATE_TUNNELING) {
            $data = @fread($socket, 8192);
            if ($data === false || $data === "") {
                $this->closeConnection($id);
                return;
            }
            $targetId = $this->mappings[$id] ?? null;
            if ($targetId && isset($this->targets[$targetId])) {
                $written = @fwrite($this->targets[$targetId], $data);
                if ($written === false) {
                    $this->closeConnection($id);
                    return;
                }
                $bytes = strlen($data);
                $this->totalBytes += $bytes;
                if (isset($this->connections[$id])) {
                    $this->connections[$id]['bytes_up'] += $bytes;
                }
                $this->writeStats();
                $this->log($id, ">>> Tunneling " . strlen($data) . " bytes to target #{$targetId}", "gray");
            }
            return;
        }


        if ($state === STATE_GREETING) {
            $data = @fread($socket, 1024);
            if ($data === false || strlen($data) < 2) {
                $this->closeConnection($id);
                return;
            }

            $version = ord($data[0]);
            $numMethods = ord($data[1]);

            if ($version !== 0x05) {
                $this->log($id, "Unsupported SOCKS version: {$version}", "red");
                $this->closeConnection($id);
                return;
            }

          
            $methods = [];
            for ($i = 0; $i < $numMethods; $i++) {
                if (isset($data[2 + $i])) {
                    $methods[] = ord($data[2 + $i]);
                }
            }

            if (in_array(0x00, $methods)) {
                @fwrite($socket, "\x05\x00"); 
                $this->states[$id] = STATE_REQUEST;
                $this->log($id, "Method negotiation completed (No Auth). Waiting for command...", "yellow");
            } else {
                $this->log($id, "No acceptable authentication methods provided.", "red");
                @fwrite($socket, "\x05\xff");
                $this->closeConnection($id);
            }
        } elseif ($state === STATE_REQUEST) {
            $data = @fread($socket, 1024);
            if ($data === false || strlen($data) < 6) {
                $this->closeConnection($id);
                return;
            }

            $version = ord($data[0]);
            $cmd = ord($data[1]);
            $rsv = ord($data[2]);
            $atyp = ord($data[3]);

            if ($version !== 0x05) {
                $this->closeConnection($id);
                return;
            }

            if ($cmd !== 0x01) { 
                $this->log($id, "Unsupported command code: {$cmd}", "red");
                @fwrite($socket, "\x05\x07\x00\x01\x00\x00\x00\x00\x00\x00"); // 0x07 = command not supported
                $this->closeConnection($id);
                return;
            }

            $offset = 4;
            $targetHost = "";

            if ($atyp === 0x01) { 
                if (strlen($data) < 10) {
                    $this->closeConnection($id);
                    return;
                }
                $ipBytes = substr($data, $offset, 4);
                $targetHost = long2ip(unpack('N', $ipBytes)[1]);
                $offset += 4;
            } elseif ($atyp === 0x03) { 
                $len = ord($data[$offset]);
                if (strlen($data) < $offset + 1 + $len + 2) {
                    $this->closeConnection($id);
                    return;
                }
                $targetHost = substr($data, $offset + 1, $len);
                $offset += 1 + $len;
            } elseif ($atyp === 0x04) {
                if (strlen($data) < 22) {
                    $this->closeConnection($id);
                    return;
                }
                $ipv6Bytes = substr($data, $offset, 16);
                $targetHost = inet_ntop($ipv6Bytes);
                $offset += 16;
            } else {
                $this->log($id, "Unsupported address type: {$atyp}", "red");
                @fwrite($socket, "\x05\x08\x00\x01\x00\x00\x00\x00\x00\x00"); // 0x08 = Address type not supported
                $this->closeConnection($id);
                return;
            }


            $portBytes = substr($data, $offset, 2);
            $targetPort = unpack('n', $portBytes)[1];

            $this->log($id, "Connecting to remote host: {$targetHost}:{$targetPort}", "cyan");

           
            $resolvedIp = $targetHost;
            if ($atyp === 0x03) {
                $resolvedIp = @gethostbyname($targetHost);
                if ($resolvedIp === $targetHost && !filter_var($targetHost, FILTER_VALIDATE_IP)) {
                    $this->log($id, "DNS resolution failed for {$targetHost}", "red");
                    @fwrite($socket, "\x05\x04\x00\x01\x00\x00\x00\x00\x00\x00"); 
                    $this->closeConnection($id);
                    return;
                }
            }


            $targetAddress = "tcp://{$resolvedIp}:{$targetPort}";
            $targetSocket = @stream_socket_client($targetAddress, $errno, $errstr, 2.5, STREAM_CLIENT_CONNECT);

            if (!$targetSocket) {
                $this->log($id, "Failed to connect to target {$targetHost}:{$targetPort} ({$targetAddress}): {$errstr}", "red");

                $repCode = ($errno === 61 || $errno === 111) ? "\x05" : "\x04";
                @fwrite($socket, "\x05" . $repCode . "\x00\x01\x00\x00\x00\x00\x00\x00");
                $this->closeConnection($id);
                return;
            }


            @fwrite($socket, "\x05\x00\x00\x01\x00\x00\x00\x00\x00\x00");

            stream_set_blocking($targetSocket, false);
            $targetId = (int)$targetSocket;

            $this->targets[$targetId] = $targetSocket;
            $this->mappings[$id] = $targetId;
            $this->mappings[$targetId] = $id;
            $this->states[$id] = STATE_TUNNELING;

            if (isset($this->connections[$id])) {
                $this->connections[$id]['target'] = $targetHost . ':' . $targetPort;
            }
            $this->writeStats();

            $this->log($id, "Tunnel established to {$targetHost}:{$targetPort} (Target ID #{$targetId})", "green");
        }
    }

    private function handleTargetData($id) {
        $socket = $this->targets[$id];
        $clientId = $this->mappings[$id] ?? null;

        if ($clientId && isset($this->clients[$clientId])) {
            $data = @fread($socket, 8192);
            if ($data === false || $data === "") {
                $this->closeConnection($id);
                return;
            }

            $written = @fwrite($this->clients[$clientId], $data);
            if ($written === false) {
                $this->closeConnection($id);
                return;
            }
            $bytes = strlen($data);
            $this->totalBytes += $bytes;
            if (isset($this->connections[$clientId])) {
                $this->connections[$clientId]['bytes_down'] += $bytes;
            }
            $this->writeStats();
            $this->log($clientId, "<<< Tunneling " . strlen($data) . " bytes from target #{$id}", "gray");
        } else {
            $this->closeConnection($id);
        }
    }


    private function closeConnection($id) {

        $pairedId = $this->mappings[$id] ?? null;

        if ($pairedId !== null) {
            unset($this->mappings[$id]);
            unset($this->mappings[$pairedId]);
            $this->closeConnection($pairedId);
        }

        if (isset($this->clients[$id])) {
            $socket = $this->clients[$id];
            if (is_resource($socket)) {
                @fclose($socket);
            }
            unset($this->clients[$id]);
            unset($this->states[$id]);
            unset($this->connections[$id]);
            $this->log($id, "Client connection closed.", "red");
        }

        if (isset($this->targets[$id])) {
            $socket = $this->targets[$id];
            if (is_resource($socket)) {
                @fclose($socket);
            }
            unset($this->targets[$id]);
            $this->log($id, "Target connection closed.", "red");
        }
        $this->writeStats();
    }

    private function log($id, $message, $color = 'reset') {
        $colors = [
            'red'    => "\033[31m",
            'green'  => "\033[32m",
            'yellow' => "\033[33m",
            'blue'   => "\033[34m",
            'cyan'   => "\033[36m",
            'gray'   => "\033[90m",
            'reset'  => "\033[0m"
        ];
        $c = $colors[$color] ?? $colors['reset'];
        $reset = $colors['reset'];
        $timestamp = date("Y-m-d H:i:s");

        $prefix = $id > 0 ? "[Client #{$id}] " : "[Server] ";
        $line = "[{$timestamp}] {$prefix}{$message}";

  
        @file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);


        echo "{$c}{$line}{$reset}\n";
    }
}


$options = getopt("h:p:", ["host:", "port:", "help"]);

if (isset($options['help'])) {
    echo "Usage: php socks5_server.php [options]\n";
    echo "Options:\n";
    echo "  -h, --host   Host bind address (default: 127.0.0.1)\n";
    echo "  -p, --port   Port to bind to (default: 1080)\n";
    echo "  --help       Show this help message\n";
    exit(0);
}

$host = $options['host'] ?? $options['h'] ?? '127.0.0.1';
$port = intval($options['port'] ?? $options['p'] ?? 1080);

$server = new Socks5Server($host, $port);
$server->start();
