<?php
require_once 'config.php';

class Database {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    // User management
    public function createUser($username, $email, $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        return $stmt->execute([$username, $email, $hash]);
    }
    
    public function authenticateUser($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $this->updateLastLogin($user['id']);
            return $user;
        }
        return false;
    }
    
    public function updateLastLogin($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    // Game management
    public function createGame($gameName, $createdBy, $maxPlayers = 6) {
        $stmt = $this->pdo->prepare("INSERT INTO games (game_name, max_players, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$gameName, $maxPlayers, $createdBy]);
        return $this->pdo->lastInsertId();
    }
    
    public function getGame($gameId) {
        $stmt = $this->pdo->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
        return $stmt->fetch();
    }
    
    public function getActiveGames() {
        $stmt = $this->pdo->prepare("SELECT g.*, u.username as creator_name, COUNT(gp.id) as player_count 
                                    FROM games g 
                                    LEFT JOIN users u ON g.created_by = u.id 
                                    LEFT JOIN game_players gp ON g.id = gp.game_id 
                                    WHERE g.status IN ('waiting', 'active') 
                                    GROUP BY g.id 
                                    ORDER BY g.created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function updateGameStatus($gameId, $status, $winner = null) {
        $stmt = $this->pdo->prepare("UPDATE games SET status = ?, winner = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$status, $winner, $gameId]);
    }
    
    public function updateGameRound($gameId, $round) {
        $stmt = $this->pdo->prepare("UPDATE games SET current_round = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$round, $gameId]);
    }
    
    // Player management
    public function addPlayerToGame($gameId, $userId, $playerType, $playerOrder) {
        $stmt = $this->pdo->prepare("INSERT INTO game_players (game_id, user_id, player_type, player_order) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$gameId, $userId, $playerType, $playerOrder]);
    }
    
    public function getGamePlayers($gameId) {
        if ($gameId === null) {
            // Get all players from all games
            $stmt = $this->pdo->prepare("SELECT gp.*, u.username FROM game_players gp 
                                        JOIN users u ON gp.user_id = u.id 
                                        ORDER BY gp.game_id, gp.player_order");
            $stmt->execute();
            return $stmt->fetchAll();
        } else {
            $stmt = $this->pdo->prepare("SELECT gp.*, u.username FROM game_players gp 
                                        JOIN users u ON gp.user_id = u.id 
                                        WHERE gp.game_id = ? 
                                        ORDER BY gp.player_order");
            $stmt->execute([$gameId]);
            return $stmt->fetchAll();
        }
    }
    
    public function getPlayerById($playerId) {
        $stmt = $this->pdo->prepare("SELECT gp.*, u.username FROM game_players gp 
                                    JOIN users u ON gp.user_id = u.id 
                                    WHERE gp.id = ?");
        $stmt->execute([$playerId]);
        return $stmt->fetch();
    }
    
    public function getCurrentPlayer($gameId) {
        $stmt = $this->pdo->prepare("SELECT gp.*, u.username FROM game_players gp 
                                    JOIN users u ON gp.user_id = u.id 
                                    WHERE gp.game_id = ? AND gp.is_current_turn = 1");
        $stmt->execute([$gameId]);
        return $stmt->fetch();
    }
    
    public function setCurrentPlayer($gameId, $playerId) {
        $stmt = $this->pdo->prepare("UPDATE game_players SET is_current_turn = 0 WHERE game_id = ?");
        $stmt->execute([$gameId]);
        
        $stmt = $this->pdo->prepare("UPDATE game_players SET is_current_turn = 1 WHERE id = ?");
        $stmt->execute([$playerId]);
    }
    
    public function updatePlayerPosition($playerId, $position) {
        $stmt = $this->pdo->prepare("UPDATE game_players SET current_position = ? WHERE id = ?");
        $stmt->execute([$position, $playerId]);
    }
    
    public function updatePlayerTickets($playerId, $ticketType, $count) {
        // Map transport types to database column names
        $columnMapping = [
            'T' => 'taxi_tickets',
            'B' => 'bus_tickets',
            'U' => 'underground_tickets',
            'X' => 'hidden_tickets',
            '2' => 'double_tickets'
        ];
        
        $column = $columnMapping[$ticketType] ?? null;
        if (!$column) {
            throw new Exception("Invalid ticket type: $ticketType");
        }
        
        $stmt = $this->pdo->prepare("UPDATE game_players SET $column = ? WHERE id = ?");
        $stmt->execute([$count, $playerId]);
    }
    
    // Move management
    public function recordMove($gameId, $roundNumber, $playerId, $fromPosition, $toPosition, $transportType, $isHidden = false, $isDoubleMove = false) {
        $stmt = $this->pdo->prepare("INSERT INTO game_moves (game_id, round_number, player_id, from_position, to_position, transport_type, is_hidden, is_double_move) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        // Convert boolean values to integers for MySQL
        $isHiddenInt = $isHidden ? 1 : 0;
        $isDoubleMoveInt = $isDoubleMove ? 1 : 0;
        return $stmt->execute([$gameId, $roundNumber, $playerId, $fromPosition, $toPosition, $transportType, $isHiddenInt, $isDoubleMoveInt]);
    }
    
    public function getGameMoves($gameId, $roundNumber = null) {
        if ($roundNumber) {
            $stmt = $this->pdo->prepare("SELECT gm.*, gp.player_type, u.username 
                                        FROM game_moves gm 
                                        JOIN game_players gp ON gm.player_id = gp.id 
                                        JOIN users u ON gp.user_id = u.id 
                                        WHERE gm.game_id = ? AND gm.round_number = ? 
                                        ORDER BY gm.move_timestamp");
            $stmt->execute([$gameId, $roundNumber]);
        } else {
            $stmt = $this->pdo->prepare("SELECT gm.*, gp.player_type, u.username 
                                        FROM game_moves gm 
                                        JOIN game_players gp ON gm.player_id = gp.id 
                                        JOIN users u ON gp.user_id = u.id 
                                        WHERE gm.game_id = ? 
                                        ORDER BY gm.round_number DESC, gm.move_timestamp");
            $stmt->execute([$gameId]);
        }
        return $stmt->fetchAll();
    }
    
    // Board management
    public function getBoardNodes() {
        $stmt = $this->pdo->prepare("SELECT * FROM board_nodes ORDER BY node_id");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getBoardConnections() {
        $stmt = $this->pdo->prepare("SELECT * FROM board_connections");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getPossibleMoves($position) {
        $stmt = $this->pdo->prepare("SELECT if(from_node = ?, to_node, from_node) as to_node, transport_type FROM board_connections WHERE from_node = ? OR to_node = ?");
        $stmt->execute([$position,$position,$position]);
        $moves = $stmt->fetchAll();
        return $moves;
    }
    
    // Game settings
    public function setGameSetting($gameId, $key, $value) {
        $stmt = $this->pdo->prepare("INSERT INTO game_settings (game_id, setting_key, setting_value) 
                                    VALUES (?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$gameId, $key, $value, $value]);
    }
    
    public function getGameSetting($gameId, $key) {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM game_settings WHERE game_id = ? AND setting_key = ?");
        $stmt->execute([$gameId, $key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : null;
    }
    
    public function getGameSettings($gameId) {
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM game_settings WHERE game_id = ?");
        $stmt->execute([$gameId]);
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }
}
?> 