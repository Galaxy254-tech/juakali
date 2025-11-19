<?php
/**
 * WebSocket Server for Real-time Gamification Updates
 * Provides live updates for points, badges, challenges, and leaderboards
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/gamification-system.php';
require_once __DIR__ . '/../includes/gamification-logger.php';

class GamificationWebSocketServer {
    private $master;
    private $sockets = [];
    private $users = []; // authenticated users
    private $logger;
    private $db;
    private $gamification;

    public function __construct($host = '0.0.0.0', $port = 8080) {
        $this->logger = new GamificationLogger(false);
        $this->db = Database::getInstance();
        $this->gamification = new GamificationSystem();

        // Create WebSocket server
        $context = stream_context_create([
            'ssl' => [
                'local_cert' => __DIR__ . '/../ssl/cert.pem',
                'local_pk' => __DIR__ . '/../ssl/key.pem',
                'allow_self_signed' => true,
                'verify_peer' => false
            ]
        ]);

        $this->master = stream_socket_server(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );

        if (!$this->master) {
            throw new Exception("Failed to create WebSocket server: {$errstr}");
        }

        stream_set_blocking($this->master, 0);

        $this->logger->log('INFO', 'WebSocket server started', [
            'host' => $host,
            'port' => $port
        ]);
    }

    /**
     * Run the WebSocket server
     */
    public function run() {
        $this->logger->log('INFO', 'WebSocket server running...');

        while (true) {
            $read = $this->sockets;
            $read[] = $this->master;
            $write = [];
            $except = [];

            if (stream_select($read, $write, $except, null)) {
                foreach ($read as $socket) {
                    if ($socket === $this->master) {
                        $this->handleNewConnection();
                    } else {
                        $this->handleClientMessage($socket);
                    }
                }
            }

            // Clean up disconnected clients
            $this->cleanup();
        }
    }

    /**
     * Handle new WebSocket connection
     */
    private function handleNewConnection() {
        $client = stream_socket_accept($this->master);

        if (!$client) {
            return;
        }

        stream_set_blocking($client, 0);
        $this->sockets[] = $client;

        $headers = $this->readHeaders($client);

        if ($this->performHandshake($client, $headers)) {
            $this->logger->log('INFO', 'New WebSocket connection established', [
                'client_ip' => stream_socket_get_name($client, true)
            ]);

            // Send welcome message
            $this->sendToClient($client, [
                'type' => 'welcome',
                'message' => 'Connected to JuaKali Lend Gamification WebSocket',
                'timestamp' => time()
            ]);
        } else {
            $this->disconnectClient($client);
        }
    }

    /**
     * Read HTTP headers from WebSocket handshake
     */
    private function readHeaders($client) {
        $headers = '';
        while (true) {
            $buffer = fread($client, 1024);
            if ($buffer === false || strlen($buffer) === 0) {
                return null;
            }
            $headers .= $buffer;
            if (strpos($headers, "\r\n\r\n") !== false) {
                break;
            }
        }

        $lines = explode("\r\n", $headers);
        $parsed = [];
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $parsed[trim(strtolower($key))] = trim($value);
            }
        }

        return $parsed;
    }

    /**
     * Perform WebSocket handshake
     */
    private function performHandshake($client, $headers) {
        if (!$headers || !isset($headers['sec-websocket-key'])) {
            return false;
        }

        $key = $headers['sec-websocket-key'];
        $acceptKey = base64_encode(
            pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11'))
        );

        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: {$acceptKey}\r\n";
        $response .= "\r\n";

        fwrite($client, $response);
        return true;
    }

    /**
     * Handle messages from clients
     */
    private function handleClientMessage($client) {
        $data = fread($client, 2048);

        if ($data === false || strlen($data) === 0) {
            $this->disconnectClient($client);
            return;
        }

        $frames = $this->parseWebSocketFrames($data);

        foreach ($frames as $frame) {
            $message = json_decode($frame['payload'], true);

            if ($message) {
                $this->processMessage($client, $message);
            }
        }
    }

    /**
     * Parse WebSocket frames
     */
    private function parseWebSocketFrames($data) {
        $frames = [];
        $offset = 0;
        $dataLength = strlen($data);

        while ($offset < $dataLength) {
            if ($offset + 2 > $dataLength) {
                break;
            }

            $firstByte = ord($data[$offset]);
            $secondByte = ord($data[$offset + 1]);

            $fin = ($firstByte & 0x80) !== 0;
            $opcode = $firstByte & 0x0F;
            $masked = ($secondByte & 0x80) !== 0;
            $payloadLength = $secondByte & 0x7F;

            $offset += 2;

            // Handle extended payload length
            if ($payloadLength === 126) {
                if ($offset + 2 > $dataLength) break;
                $payloadLength = (ord($data[$offset]) << 8) | ord($data[$offset + 1]);
                $offset += 2;
            } elseif ($payloadLength === 127) {
                if ($offset + 8 > $dataLength) break;
                $payloadLength = 0;
                for ($i = 0; $i < 8; $i++) {
                    $payloadLength = ($payloadLength << 8) | ord($data[$offset + $i]);
                }
                $offset += 8;
            }

            // Handle masking key
            $maskingKey = '';
            if ($masked) {
                if ($offset + 4 > $dataLength) break;
                $maskingKey = substr($data, $offset, 4);
                $offset += 4;
            }

            // Extract payload
            if ($offset + $payloadLength > $dataLength) break;
            $payload = substr($data, $offset, $payloadLength);
            $offset += $payloadLength;

            // Unmask payload if needed
            if ($masked) {
                $unmasked = '';
                for ($i = 0; $i < $payloadLength; $i++) {
                    $unmasked .= $payload[$i] ^ $maskingKey[$i % 4];
                }
                $payload = $unmasked;
            }

            $frames[] = [
                'fin' => $fin,
                'opcode' => $opcode,
                'payload' => $payload,
                'masked' => $masked
            ];

            // If this is not the final frame, continue reading
            if (!$fin) {
                continue;
            }

            break;
        }

        return $frames;
    }

    /**
     * Process client message
     */
    private function processMessage($client, $message) {
        try {
            switch ($message['type']) {
                case 'authenticate':
                    $this->handleAuthentication($client, $message);
                    break;

                case 'subscribe':
                    $this->handleSubscription($client, $message);
                    break;

                case 'heartbeat':
                    $this->sendToClient($client, [
                        'type' => 'heartbeat_response',
                        'timestamp' => time()
                    ]);
                    break;

                default:
                    $this->logger->log('WARNING', 'Unknown message type', [
                        'type' => $message['type'],
                        'client_ip' => stream_socket_get_name($client, true)
                    ]);
            }
        } catch (Exception $e) {
            $this->logger->logError('Error processing message', $e, [
                'message' => $message
            ]);
        }
    }

    /**
     * Handle client authentication
     */
    private function handleAuthentication($client, $message) {
        $token = $message['token'] ?? '';
        $userId = $this->validateToken($token);

        if ($userId) {
            $this->users[(int)$client] = [
                'user_id' => $userId,
                'subscriptions' => [],
                'authenticated_at' => time()
            ];

            $this->sendToClient($client, [
                'type' => 'authenticated',
                'user_id' => $userId,
                'timestamp' => time()
            ]);

            $this->logger->log('INFO', 'Client authenticated', [
                'user_id' => $userId,
                'client_ip' => stream_socket_get_name($client, true)
            ]);

            // Send initial user data
            $this->sendUserData($client, $userId);
        } else {
            $this->sendToClient($client, [
                'type' => 'authentication_failed',
                'message' => 'Invalid token',
                'timestamp' => time()
            ]);

            $this->disconnectClient($client);
        }
    }

    /**
     * Validate authentication token
     */
    private function validateToken($token) {
        if (empty($token)) {
            return false;
        }

        try {
            // Decode JWT token (simplified validation)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }

            $payload = json_decode(base64_decode($parts[1]), true);

            if (!$payload || !isset($payload['user_id']) || !isset($payload['exp'])) {
                return false;
            }

            // Check if token is expired
            if ($payload['exp'] < time()) {
                return false;
            }

            return $payload['user_id'];
        } catch (Exception $e) {
            $this->logger->log('WARNING', 'Token validation failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Handle subscription requests
     */
    private function handleSubscription($client, $message) {
        $userId = $this->users[(int)$client]['user_id'] ?? null;

        if (!$userId) {
            return;
        }

        $subscription = $message['subscription'] ?? '';
        $validSubscriptions = ['points', 'badges', 'challenges', 'leaderboard', 'rewards'];

        if (in_array($subscription, $validSubscriptions)) {
            $this->users[(int)$client]['subscriptions'][] = $subscription;

            $this->sendToClient($client, [
                'type' => 'subscribed',
                'subscription' => $subscription,
                'timestamp' => time()
            ]);

            $this->logger->log('DEBUG', 'Client subscribed', [
                'user_id' => $userId,
                'subscription' => $subscription
            ]);
        }
    }

    /**
     * Send user data after authentication
     */
    private function sendUserData($client, $userId) {
        try {
            $profile = $this->gamification->getUserGamificationProfile($userId);
            $challenges = $this->gamification->getUserChallenges($userId);
            $rewards = $this->gamification->getAvailableRewards($userId);

            $this->sendToClient($client, [
                'type' => 'user_data',
                'data' => [
                    'profile' => $profile,
                    'challenges' => $challenges,
                    'rewards' => $rewards
                ],
                'timestamp' => time()
            ]);
        } catch (Exception $e) {
            $this->logger->logError('Failed to send user data', $e, [
                'user_id' => $userId
            ]);
        }
    }

    /**
     * Send message to specific client
     */
    private function sendToClient($client, $data) {
        try {
            $payload = json_encode($data);
            $frame = $this->createWebSocketFrame($payload);
            fwrite($client, $frame);
        } catch (Exception $e) {
            $this->logger->log('WARNING', 'Failed to send message to client', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create WebSocket frame
     */
    private function createWebSocketFrame($payload, $opcode = 0x1) {
        $payloadLength = strlen($payload);
        $frame = '';

        // First byte: FIN + RSV + Opcode
        $frame .= chr(0x80 | $opcode);

        // Handle payload length
        if ($payloadLength < 126) {
            $frame .= chr($payloadLength);
        } elseif ($payloadLength < 65536) {
            $frame .= chr(126) . chr($payloadLength >> 8) . chr($payloadLength & 0xFF);
        } else {
            $frame .= chr(127);
            for ($i = 7; $i >= 0; $i--) {
                $frame .= chr(($payloadLength >> ($i * 8)) & 0xFF);
            }
        }

        // Payload
        $frame .= $payload;

        return $frame;
    }

    /**
     * Broadcast message to all authenticated users
     */
    public function broadcast($message, $targetUserId = null, $subscription = null) {
        $sent = 0;

        foreach ($this->users as $socket => $user) {
            // Filter by target user if specified
            if ($targetUserId && $user['user_id'] !== $targetUserId) {
                continue;
            }

            // Filter by subscription if specified
            if ($subscription && !in_array($subscription, $user['subscriptions'])) {
                continue;
            }

            $this->sendToClient($socket, $message);
            $sent++;
        }

        $this->logger->log('DEBUG', 'Message broadcasted', [
            'target_user' => $targetUserId,
            'subscription' => $subscription,
            'sent_to' => $sent
        ]);

        return $sent;
    }

    /**
     * Notify about points awarded
     */
    public function notifyPointsAwarded($userId, $points, $reason, $newTotal) {
        $message = [
            'type' => 'points_awarded',
            'data' => [
                'points' => $points,
                'reason' => $reason,
                'new_total' => $newTotal
            ],
            'timestamp' => time()
        ];

        $this->broadcast($message, $userId, 'points');
    }

    /**
     * Notify about badge earned
     */
    public function notifyBadgeEarned($userId, $badge) {
        $message = [
            'type' => 'badge_earned',
            'data' => [
                'badge' => $badge
            ],
            'timestamp' => time()
        ];

        $this->broadcast($message, $userId, 'badges');
    }

    /**
     * Notify about level up
     */
    public function notifyLevelUp($userId, $newLevel, $bonusPoints) {
        $message = [
            'type' => 'level_up',
            'data' => [
                'new_level' => $newLevel,
                'bonus_points' => $bonusPoints
            ],
            'timestamp' => time()
        ];

        $this->broadcast($message, $userId);
    }

    /**
     * Notify about challenge completion
     */
    public function notifyChallengeCompleted($userId, $challenge) {
        $message = [
            'type' => 'challenge_completed',
            'data' => [
                'challenge' => $challenge
            ],
            'timestamp' => time()
        ];

        $this->broadcast($message, $userId, 'challenges');
    }

    /**
     * Update leaderboard for all subscribers
     */
    public function updateLeaderboard($period = 'monthly', $limit = 10) {
        try {
            $leaderboard = $this->gamification->getLeaderboard('points', $period, $limit);

            $message = [
                'type' => 'leaderboard_updated',
                'data' => [
                    'period' => $period,
                    'leaderboard' => $leaderboard
                ],
                'timestamp' => time()
            ];

            $this->broadcast($message, null, 'leaderboard');
        } catch (Exception $e) {
            $this->logger->logError('Failed to update leaderboard', $e);
        }
    }

    /**
     * Disconnect client
     */
    private function disconnectClient($client) {
        $key = (int)$client;

        if (isset($this->users[$key])) {
            $userId = $this->users[$key]['user_id'];
            unset($this->users[$key]);

            $this->logger->log('INFO', 'Client disconnected', [
                'user_id' => $userId,
                'client_ip' => stream_socket_get_name($client, true)
            ]);
        }

        $index = array_search($client, $this->sockets);
        if ($index !== false) {
            unset($this->sockets[$index]);
        }

        fclose($client);
    }

    /**
     * Clean up disconnected clients
     */
    private function cleanup() {
        foreach ($this->sockets as $key => $socket) {
            // Check if socket is still alive
            if (!is_resource($socket) || feof($socket)) {
                $this->disconnectClient($socket);
            }
        }

        // Re-index array
        $this->sockets = array_values($this->sockets);

        // Clean up expired user sessions (older than 1 hour)
        $now = time();
        foreach ($this->users as $socket => $user) {
            if ($now - $user['authenticated_at'] > 3600) {
                $this->disconnectClient($socket);
            }
        }
    }

    /**
     * Get server statistics
     */
    public function getStats() {
        return [
            'connected_clients' => count($this->sockets),
            'authenticated_users' => count($this->users),
            'uptime' => time() - ($this->startTime ?? time()),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
}

// WebSocket server runner
if (php_sapi_name() === 'cli') {
    $host = $argv[1] ?? '0.0.0.0';
    $port = intval($argv[2] ?? 8080);

    try {
        $server = new GamificationWebSocketServer($host, $port);
        $server->run();
    } catch (Exception $e) {
        echo "WebSocket server error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>