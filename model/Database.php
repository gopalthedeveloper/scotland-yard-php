<?php
require_once 'config.php';

class Database {
    private $pdo;
    private $config;
    private static $instance;
    
    
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
        $this->config = GAME_CONFIG;
    }

    /**
     * @return Database
     */
    public static function getInstance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // User management
    public function createUser($username, $email, $hash, $activate_Token) {
        $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password_hash, password_reset_token, user_status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$username, $email, $hash, $activate_Token, User::STATUS_REGISTERED, date('Y-m-d H:i:s')]);
    }

    public function generateUniqueToken($table, $column, $length = 10) {
        $isTokenExists = true;
        while($isTokenExists) {
            $token = Helper::generateRandomString($length);
            $stmt = $this->pdo->prepare("SELECT $column from $table WHERE $column = ?");
            $stmt->execute([$token]);
            $isTokenExists = $stmt->fetch() !== false;
        }
        return $token;
    }

    public function getUserByColumn($value, $column = 'email',$extra = '') {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE $column = ? $extra");
        $stmt->execute([$value]);
        return $stmt->fetch();
    }

    public function updateUserByColumn($userId, $updateData) {
        if(count(array_keys($updateData)) == 0){
            return;
        } 
        $updateColumns = '';
        $subsctituteValue = [];
        foreach($updateData as $key => $value) {
            if($updateColumns == '') {
                $updateColumns = $key.'= ?';
            } else {
                $updateColumns .= ",{$key} = ?";
            }
            $subsctituteValue[]=$value;
        }
        $subsctituteValue[] = $userId;
        $stmt = $this->pdo->prepare("UPDATE users SET $updateColumns WHERE id = ?");
        return $stmt->execute($subsctituteValue);
    }
    
    public function authenticateUser($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? AND user_status = ?");
        $stmt->execute([$username,User::STATUS_ACTIVE]);
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
        $this->updateGameTimestamp($gameId, 'game');
    }
    
    public function updateGameRound($gameId, $round) {
        $stmt = $this->pdo->prepare("UPDATE games SET current_round = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$round, $gameId]);
    }
    // Player management
    public function addPlayerToGame($gameId, $userId, $playerType, $playerOrder) {
        // First create the player
        $stmt = $this->pdo->prepare("INSERT INTO game_players (game_id, player_type, player_order, player_name, is_ai) 
        VALUES (?, ?, 1, ?, ?)");
        $playerName = "Player " . time(); // Generate unique name
        $isAI = 0; // Convert boolean to integer for MySQL
        $stmt->execute([$gameId, $playerType, $playerName, $isAI]);
        
        $playerId = $this->pdo->lastInsertId();
        
        // Then create the user mapping
        $stmt = $this->pdo->prepare("INSERT INTO user_game_mappings (game_id, user_id, player_id, mapping_type) VALUES (?, ?, ?, 'owner')");
        $stmt->execute([$gameId, $userId, $playerId]);
        
        return $playerId;
    }
    
    public function createAIDetective($gameId) {
        // Create an AI detective
        $stmt = $this->pdo->prepare("INSERT INTO game_players (game_id, player_type, player_order, player_name, is_ai) 
        VALUES (?, 'detective', 2, ?, ?)");
        $playerName = "AI Detective " . time(); // Generate unique name
        $isAI = 1; // Convert boolean to integer for MySQL
        $stmt->execute([$gameId, $playerName, $isAI]);
        
        return $this->pdo->lastInsertId();
    }
    
    public function getAIDetectives($gameId) {
        $stmt = $this->pdo->prepare("SELECT * FROM game_players WHERE game_id = ? AND player_type = 'detective' AND is_ai = TRUE");
        $stmt->execute([$gameId]);
        return $stmt->fetchAll();
    }
    
    public function getHumanPlayers($gameId) {
        $stmt = $this->pdo->prepare("
            SELECT gp.*, 
                   u.username,
                   ugm.mapping_type,
                   ugm.user_id
            FROM game_players gp 
            JOIN user_game_mappings ugm ON gp.id = ugm.player_id AND ugm.mapping_type = 'owner'
            LEFT JOIN users u ON ugm.user_id = u.id 
            WHERE gp.game_id = ? AND gp.is_ai = 0
            ORDER BY gp.player_order
        ");
        $stmt->execute([$gameId]);
        return $stmt->fetchAll();
    }
    
    public function getGamePlayers($gameId) {
        if ($gameId === null) {
            // Get all players from all games with user mappings
            $stmt = $this->pdo->prepare("
                SELECT gp.*, 
                       u.username,
                       ugm.mapping_type,
                       ugm.user_id,
                       ugm.created_at
                FROM game_players gp 
                LEFT JOIN user_game_mappings ugm ON gp.id = ugm.player_id
                LEFT JOIN users u ON ugm.user_id = u.id 
                ORDER BY gp.game_id, gp.player_order
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } else {
            $stmt = $this->pdo->prepare("
                SELECT gp.*, 
                       u.username,
                       ugm.mapping_type,
                       ugm.user_id,
                       ugm.created_at
                FROM game_players gp 
                LEFT JOIN user_game_mappings ugm ON gp.id = ugm.player_id
                LEFT JOIN users u ON ugm.user_id = u.id 
                WHERE gp.game_id = ? 
                ORDER BY gp.player_order,ugm.created_at
            ");
            $stmt->execute([$gameId]);
            return $stmt->fetchAll();
        }
    }
    
    public function getPlayerById($playerId) {
        $stmt = $this->pdo->prepare("
            SELECT gp.*, 
                   u.username,
                   ugm.mapping_type,
                   ugm.user_id
            FROM game_players gp 
            LEFT JOIN user_game_mappings ugm ON gp.id = ugm.player_id
            LEFT JOIN users u ON ugm.user_id = u.id 
            WHERE gp.id = ?
        ");
        $stmt->execute([$playerId]);
        return $stmt->fetch();
    }
    
    public function getCurrentPlayer($gameId) {
        $stmt = $this->pdo->prepare("
            SELECT gp.*, 
                   u.username,
                   ugm.mapping_type,
                   ugm.user_id
            FROM game_players gp 
            LEFT JOIN user_game_mappings ugm ON gp.id = ugm.player_id
            LEFT JOIN users u ON ugm.user_id = u.id 
            WHERE gp.game_id = ? AND gp.is_current_turn = 1
        ");
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
    
    public function updatePlayerType($id, $playerType, $byType = 'player') {
        $whereCol = $byType == 'game'?'game_id':'id';
        $stmt = $this->pdo->prepare("UPDATE game_players SET player_type = ? WHERE $whereCol = ?");
        $stmt->execute([$playerType, $id]);
    }
    public function resetLobbyPlayerOrder($gameId) {
        $stmt = $this->pdo->prepare("UPDATE game_players SET 
        player_order =  (CASE
                        WHEN player_type = 'mr_x' THEN 0
                        WHEN is_ai = 0 THEN 1
                        ELSE 2 END )
         WHERE game_id = ?");
        $stmt->execute([$gameId]);
    }
    public function updatePlayerOrder($playerId, $playerOrder) {
        $stmt = $this->pdo->prepare("UPDATE game_players SET player_order = ? WHERE id = ?");
        $stmt->execute([$playerOrder, $playerId]);
    }
    
    public function deleteUserPlayerMapping($id, $mappingType, $byType = 'user' ) {
        $whereCol = $byType != 'player'?'user_id':'player_id';
        $stmt = $this->pdo->prepare("DELETE FROM user_game_mappings WHERE $whereCol = ? AND mapping_type = ?");
        $stmt->execute([$id,$mappingType]);
    }

    public function assignDetectiveToPlayer($detectiveId, $controllingPlayerId) {
        // First, remove any existing controller mappings for this detective
        $stmt = $this->pdo->prepare("DELETE FROM user_game_mappings WHERE player_id = ? AND mapping_type = 'controller'");
        $stmt->execute([$detectiveId]);
        
        // Get the user_id of the controlling player
        $stmt = $this->pdo->prepare("SELECT user_id FROM user_game_mappings WHERE user_id = ? AND mapping_type = 'owner'");
        $stmt->execute([$controllingPlayerId]);
        $result = $stmt->fetch();

        $stmt = $this->pdo->prepare("SELECT player_id FROM user_game_mappings WHERE player_id = ? AND mapping_type = 'owner'");
        $stmt->execute([$detectiveId]);
        $resultPlayer = $stmt->fetch();
        
        if ($result && !$resultPlayer) {
            // Create new controller mapping
            $stmt = $this->pdo->prepare("INSERT INTO user_game_mappings (game_id, user_id, player_id, mapping_type) VALUES ((SELECT game_id FROM game_players WHERE id = ?), ?, ?, 'controller')");
            $stmt->execute([$detectiveId, $result['user_id'], $detectiveId]);
        }
    }
    
    public function getDetectiveAssignments($gameId) {
        $stmt = $this->pdo->prepare("
            SELECT gp.*, 
                   u.username,
                   ugm.mapping_type,
                   ugm.user_id,
                   controller_ugm.user_id as controlled_by_user_id,
                   controller_u.username as controlled_by_username
            FROM game_players gp 
            LEFT JOIN user_game_mappings ugm ON gp.id = ugm.player_id 
            LEFT JOIN users u ON ugm.user_id = u.id 
            LEFT JOIN user_game_mappings controller_ugm ON gp.id = controller_ugm.player_id AND controller_ugm.mapping_type = 'controller'
            LEFT JOIN users controller_u ON controller_ugm.user_id = controller_u.id
            WHERE gp.game_id = ? AND gp.player_type = 'detective'
        ");
        $stmt->execute([$gameId]);
        return $stmt->fetchAll();
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
            $stmt = $this->pdo->prepare("SELECT gm.*, gp.player_type, COALESCE(u.username, CONCAT('AI Detective ', gp.id)) as username 
                                        FROM game_moves gm 
                                        JOIN game_players gp ON gm.player_id = gp.id 
                                        LEFT JOIN user_game_mappings ugm ON gp.id = ugm.player_id AND ugm.mapping_type = 'owner'
                                        LEFT JOIN users u ON ugm.user_id = u.id 
                                        WHERE gm.game_id = ? AND gm.round_number = ? 
                                        ORDER BY gm.move_timestamp");
            $stmt->execute([$gameId, $roundNumber]);
        } else {
            $stmt = $this->pdo->prepare("SELECT gm.*, gp.player_type, COALESCE(u.username, CONCAT('AI Detective ', gp.id)) as username 
                                        FROM game_moves gm 
                                        JOIN game_players gp ON gm.player_id = gp.id 
                                        LEFT JOIN user_game_mappings ugm ON gp.id = ugm.player_id AND ugm.mapping_type = 'owner'
                                        LEFT JOIN users u ON ugm.user_id = u.id 
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

    public function setPlayerInitialTickets($player) {
        $stmt = $this->pdo->prepare("UPDATE game_players SET taxi_tickets = ?, bus_tickets = ?, underground_tickets = ?, hidden_tickets = ?, double_tickets = ? WHERE id = ?");
        $stmt->execute([
            $this->config['tickets'][$player['player_type']]['taxi'], 
            $this->config['tickets'][$player['player_type']]['bus'], 
            $this->config['tickets'][$player['player_type']]['underground'], 
            $this->config['tickets'][$player['player_type']]['hidden'], 
            $this->config['tickets'][$player['player_type']]['double'], 
            $player['id']
        ]);
    }

    public function assignAIDetectiveToUser($aiPlayerId, $userId) {
        // Set is_ai to 0 (now human-controlled)
        $stmt = $this->pdo->prepare("UPDATE game_players SET is_ai = 0,player_order=1 WHERE id = ?");
        $stmt->execute([$aiPlayerId]);
        // Add owner mapping
        $player = $this->getPlayerById($aiPlayerId);
        $stmt = $this->pdo->prepare("INSERT INTO user_game_mappings (game_id, user_id, player_id, mapping_type) VALUES (?, ?, ?, 'owner')");
        $stmt->execute([$player['game_id'], $userId, $aiPlayerId]);
    }

    public function convertHumanToAIDetective($playerId) {
        $stmt = $this->pdo->prepare("UPDATE game_players SET is_ai = 1,player_order=2, player_type = 'detective' WHERE id = ?");
        $stmt->execute([$playerId]);
    }

    public function removeUserFromGame($userId) {
        // Remove all user_game_mappings for this player
        $stmt = $this->pdo->prepare("DELETE FROM user_game_mappings WHERE user_id = ?");
        $stmt->execute([$userId]);
        
    }

    public function removeAIDetective($playerId) {
        // Only remove if is_ai = 1
        $stmt = $this->pdo->prepare("DELETE FROM game_players WHERE id = ? AND is_ai = 1");
        $stmt->execute([$playerId]);
        // Remove any mappings just in case
        $stmt = $this->pdo->prepare("DELETE FROM user_game_mappings WHERE player_id = ?");
        $stmt->execute([$playerId]);
    }
    
    /**
     * Get the maximum updated_at timestamp from game_players table for a specific game
     */
    public function getMaxPlayerUpdateTimestamp($gameId) {
        $stmt = $this->pdo->prepare("SELECT MAX(updated_at) as max_player_update FROM game_players WHERE game_id = ?");
        $stmt->execute([$gameId]);
        $result = $stmt->fetch();
        return $result && $result['max_player_update'] ? strtotime($result['max_player_update']) : 0;
    }
    
    /**
     * Get the maximum move_timestamp from game_moves table for a specific game
     */
    public function getMaxMoveTimestamp($gameId) {
        $stmt = $this->pdo->prepare("SELECT MAX(move_timestamp) as max_move_timestamp FROM game_moves WHERE game_id = ?");
        $stmt->execute([$gameId]);
        $result = $stmt->fetch();
        return $result && $result['max_move_timestamp'] ? strtotime($result['max_move_timestamp']) : 0;
    }
    
    /**
     * Get the maximum timestamp from game_updates table for a specific game
     */
    public function getMaxGameTimestamp($gameId) {
        $stmt = $this->pdo->prepare("SELECT MAX(timestamp) as max_timestamp FROM game_updates WHERE game_id = ?");
        $stmt->execute([$gameId]);
        $result = $stmt->fetch();
        return $result && $result['max_timestamp'] ? strtotime($result['max_timestamp']) : 0;
    }
    
    /**
     * Update the timestamp for a specific update type in game_updates table
     */
    public function updateGameTimestamp($gameId, $updateType) {
        $stmt = $this->pdo->prepare("INSERT INTO game_updates (game_id, update_type, timestamp) 
                                    VALUES (?, ?, CURRENT_TIMESTAMP) 
                                    ON DUPLICATE KEY UPDATE timestamp = CURRENT_TIMESTAMP");
        $stmt->execute([$gameId, $updateType]);
    }
    
    // Get game by key
    public function getGameByKey($gameKey) {
        $stmt = $this->pdo->prepare("SELECT * FROM games WHERE game_key = ?");
        $stmt->execute([$gameKey]);
        return $stmt->fetch();
    }
}
?>