<?php
require_once 'Database.php';

class GameEngine {
    private $db;
    private $config;
    private static  $instance;
    
    public function __construct() {
        $this->db =  Database::getInstance();
        $this->config = GAME_CONFIG;
    }

    /**
     * @return GameEngine
     */
    public static function getInstance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Game initialization
    public function initializeGame($gameId) {
        $game = $this->db->getGame($gameId);
        if (!$game || $game['status'] !== 'waiting') {
            return false;
        }
        
        $players = $this->db->getGamePlayers($gameId);
        if (count($players) < 2) {
            return false;
        }
        
        // Set initial positions
        $this->setInitialPositions($gameId, $players);
        
        // Find Mr. X (player_order = 0) and set as first player
        $mrX = null;
        foreach ($players as $player) {
            if ($player['player_order'] == 0 && $player['player_type'] == 'mr_x') {
                $mrX = $player;
                break;
            }
        }
        
        if (!$mrX) {
            return false; // Mr. X not found
        }
        
        // Set Mr. X as the first player
        $this->db->setCurrentPlayer($gameId, $mrX['id']);
        
        // Update game status
        $this->db->updateGameStatus($gameId, 'active');
        
        return true;
    }
    
    private function setInitialPositions($gameId, $players) {
        $nodes = $this->db->getBoardNodes();
        $nodeIds = array_column($nodes, 'node_id');
        
        // Shuffle positions
        shuffle($nodeIds);

        $detectiveOrder = 1;
        // Assign positions to players
        foreach ($players as $index => $player) {
            $position = $nodeIds[$index];
            $this->db->updatePlayerPosition($player['id'], $position);
            $this->db->setPlayerInitialTickets($player);
             if ($player['player_type'] != 'mr_x') {
                $this->db->updatePlayerOrder($player['id'], $detectiveOrder);
                $detectiveOrder++;
            }
        }
    }
    
    // Move validation and execution
    public function makeMove($gameId, $currentUserId, $playerId, $toPosition, $transportType, $isHidden = false, $isDoubleMove = false) {
        $game = $this->db->getGame($gameId);
        $currentPlayer = $this->db->getCurrentPlayer($gameId);
        
        // Validate it's the player's turn
        if ($currentPlayer['id'] != $playerId || $currentPlayer['user_id'] != $currentUserId) {
            return ['response_status' => false, 'message' => 'Not your turn'];
        }
        
        // Validate game is active
        if ($game['status'] !== 'active') {
            return ['response_status' => false, 'message' => 'Game is not active'];
        }
        
        // Check if a double move has already been used this round
        if ($isDoubleMove) {
            $doubleMoveUsed = $this->db->getGameSetting($gameId, 'double_move_used_round_' . $game['current_round']);
            if ($doubleMoveUsed == '1') {
                return ['response_status' => false, 'message' => 'Double move already used this round'];
            }
        }
        // Validate move
        $validation = $this->validateMove( $playerId, $currentPlayer['current_position'], $toPosition, $transportType, $isHidden, $isDoubleMove);
        if (!$validation['valid']) {
            return ['response_status' => false, 'message' => $validation['message']];
        }  
        // Execute move
        $this->executeMove($gameId, $playerId, $currentPlayer['current_position'], $toPosition, $transportType, $isHidden, $isDoubleMove);
        
        // Check win conditions
        $winCheck = $this->checkWinConditions($gameId);
        if ($winCheck['gameOver']) {
            $this->db->updateGameStatus($gameId, 'finished', $winCheck['winner']);
            return ['response_status' => true, 'gameOver' => true, 'winner' => $winCheck['winner']];
        }
        
        // For double moves, don't advance to next player yet
        if ($isDoubleMove) {
            // Mark that a double move has been used this round
            $this->db->setGameSetting($gameId, 'double_move_used_round_' . $game['current_round'], '1');
            // Set a flag to indicate this is the first move of a double move
            $this->db->setGameSetting($gameId, 'double_move_in_progress', '1');
            return ['response_status' => true, 'gameOver' => false, 'doubleMove' => true, 'message' => 'Make your second move'];
        } else {
            // Check if this is the second move of a double move
            $doubleMoveInProgress = $this->db->getGameSetting($gameId, 'double_move_in_progress');
            if ($doubleMoveInProgress == '1') {
                // Clear the double move flag
                $this->db->setGameSetting($gameId, 'double_move_in_progress', '0');
            }
            
            // Move to next player
            $this->nextTurn($gameId);
        }
        
        return ['response_status' => true, 'gameOver' => false];
    }
    
    private function validateMove($playerId, $fromPosition, $toPosition, $transportType, $isHidden, $isDoubleMove) {
        // Get the specific player data
        $player = $this->db->getPlayerById($playerId);
        
        if (!$player) {
            return ['valid' => false, 'message' => 'Player not found'];
        }
        
        // Validate double move
        if ($isDoubleMove) {
            // Only Mr. X can make double moves
            if ($player['player_type'] !== 'mr_x') {
                return ['valid' => false, 'message' => 'Only Mr. X can make double moves'];
            }
            
            // Check if player has double tickets
            if ($player['double_tickets'] <= 0) {
                return ['valid' => false, 'message' => 'No double tickets available'];
            }
        }
        
        // Check if destination is valid
        $possibleMoves = $this->db->getPossibleMoves($fromPosition);
        $validMove = false;
        $actualTransportType = $transportType;
        
        foreach ($possibleMoves as $move) {
            if ($move['to_node'] == $toPosition && $move['transport_type'] == $transportType) {
                $validMove = true;
                $actualTransportType = $move['transport_type'];
                break;
            }
        }
        
        if (!$validMove) {
            return ['valid' => false, 'message' => 'Invalid move'];
        }
        
        // For double moves, we don't need to check regular tickets since we'll use a double ticket
        if (!$isDoubleMove) {
            // Check if player has tickets
            if ($isHidden && $player['player_type'] == 'mr_x') {
                // Mr. X can use hidden tickets for any transport type
                if ($player['hidden_tickets'] <= 0) {
                    return ['valid' => false, 'message' => 'No hidden tickets available'];
                }
            } else {
                // Check regular tickets for the transport type
                $ticketColumn = $this->getTicketColumn($actualTransportType);
                if ($ticketColumn && $player[$ticketColumn . '_tickets'] <= 0) {
                    return ['valid' => false, 'message' => 'No tickets available for this transport type'];
                }
            }
        }
        
        // Check if destination is occupied by another detective (but allow moving to Mr. X's position)
        $gamePlayers = $this->db->getGamePlayers($player['game_id']);
        foreach ($gamePlayers as $p) {
            if ($p['current_position'] == $toPosition && $p['id'] != $playerId) {
                // Allow detectives to move to Mr. X's position
                if ($player['player_type'] !==  $p['player_type']) {
                    continue; // Allow this move
                }
                return ['valid' => false, 'message' => 'Destination is occupied'];
            }
        }
        
        return ['valid' => true];
    }
    
    private function executeMove($gameId, $playerId, $fromPosition, $toPosition, $transportType, $isHidden, $isDoubleMove) {
        $game = $this->db->getGame($gameId);
        
        // Record move
        $this->db->recordMove($gameId, $game['current_round'], $playerId, $fromPosition, $toPosition, $transportType, $isHidden, $isDoubleMove);
        
        // Update player position
        $this->db->updatePlayerPosition($playerId, $toPosition);
        
        // Update tickets
        $player = $this->db->getPlayerById($playerId);
        
        if ($player) {
            if ($isDoubleMove) {
                // Consume double ticket
                $this->db->updatePlayerTickets($playerId, '2', $player['double_tickets'] - 1);
            } else if ($transportType !== '.') {
                if ($isHidden && $player['player_type'] == 'mr_x') {
                    // Use hidden ticket instead of transport-specific ticket
                    $this->db->updatePlayerTickets($playerId, 'X', $player['hidden_tickets'] - 1);
                } else {
                    // Use regular transport ticket
                    $ticketColumn = $this->getTicketColumn($transportType);
                    if ($ticketColumn) {
                        $currentTickets = $player[$ticketColumn . '_tickets'];
                        $this->db->updatePlayerTickets($playerId, $transportType, $currentTickets - 1);
                    }
                }
            }
        }
    }
    
    private function getTicketColumn($transportType) {
        $mapping = [
            'T' => 'taxi',
            'B' => 'bus',
            'U' => 'underground',
            'X' => 'hidden',
            '2' => 'double'
        ];
        return $mapping[$transportType] ?? null;
    }
    
    public function canPlayerControlDetective($gameId, $playerId, $detectiveId) {
        // Get the detective's assignment
        $detectiveAssignments = $this->db->getDetectiveAssignments($gameId);
        foreach ($detectiveAssignments as $detective) {
            if ($detective['id'] == $detectiveId) {
                // Player can control if assigned OR if it's their own detective
                // For AI detectives (is_ai is true), only check if assigned
                if ($detective['is_ai']) {
                    return ($detective['controlled_by_user_id'] ?? null) == $this->getUserIdForPlayer($playerId);
                } else {
                    return ($detective['controlled_by_user_id'] ?? null) == $this->getUserIdForPlayer($playerId) || $detective['id'] == $playerId;
                }
            }
        }
        return false;
    }
    
    private function getUserIdForPlayer($playerId) {
        $player = $this->db->getPlayerById($playerId);
        return $player['user_id'] ?? null;
    }
    
    public function getControlledDetectives($gameId, $playerId) {
        $detectiveAssignments = $this->db->getDetectiveAssignments($gameId);
        $controlledDetectives = [];
        $userId = $this->getUserIdForPlayer($playerId);
        
        // Get the player's own detective if they are a detective (but not AI detectives)
        $player = $this->db->getPlayerById($playerId);
        if ($player && $player['player_type'] == 'detective' && !$player['is_ai']) {
            // Find the player's own detective in the assignments
            foreach ($detectiveAssignments as $detective) {
                if ($detective['id'] == $playerId) {
                    $controlledDetectives[] = $detective;
                    break;
                }
            }
        }
        
        // Get additional detectives assigned to this player (including AI detectives)
        foreach ($detectiveAssignments as $detective) {
            if (($detective['controlled_by_user_id'] ?? null) == $userId && $detective['id'] != $playerId) {
                $controlledDetectives[] = $detective;
            }
        }
        
        return $controlledDetectives;
    }
    
    private function nextTurn($gameId) {
        $game = $this->db->getGame($gameId);
        $players = $this->db->getGamePlayers($gameId);
        $currentPlayer = $this->db->getCurrentPlayer($gameId);
        
        // Find next player
        $currentIndex = array_search($currentPlayer['id'], array_column($players, 'id'));
        $nextIndex = ($currentIndex + 1) % count($players);
        
        // If we've gone through all players, increment round
        if ($nextIndex == 0) {
            $this->db->updateGameRound($gameId, $game['current_round'] + 1);
        }
        
        // Set next player
        $this->db->setCurrentPlayer($gameId, $players[$nextIndex]['id']);
    }
    
    private function checkWinConditions($gameId) {
        $game = $this->db->getGame($gameId);
        $players = $this->db->getGamePlayers($gameId);
        
        // Check if detectives caught Mr. X
        $mrX = null;
        $detectives = [];
        
        foreach ($players as $player) {
            if ($player['player_type'] == 'mr_x') {
                $mrX = $player;
            } else {
                $detectives[] = $player;
            }
        }
        
        // Check if any detective is on Mr. X's position
        foreach ($detectives as $detective) {
            if ($detective['current_position'] == $mrX['current_position']) {
                return ['gameOver' => true, 'winner' => 'detectives'];
            }
        }
        
        // Check if Mr. X has no valid moves
        $possibleMoves = $this->db->getPossibleMoves($mrX['current_position']);
        if (empty($possibleMoves)) {
            return ['gameOver' => true, 'winner' => 'detectives'];
        }
        
        // Check if we're in the final round and all detectives have moved
        if ($game['current_round'] == $this->config['max_rounds']) {
            $currentPlayer = $this->db->getCurrentPlayer($gameId);
            
            // If it's Mr. X's turn after all detectives have moved in the final round, end the game
            if ($currentPlayer['player_type'] == 'mr_x') {
                return ['gameOver' => true, 'winner' => 'mr_x'];
            }
        }
        
        // Check if max rounds exceeded (shouldn't happen with the above logic, but as a safety)
        if ($game['current_round'] > $this->config['max_rounds']) {
            return ['gameOver' => true, 'winner' => 'mr_x'];
        }
        
        return ['gameOver' => false];
    }
    
    // Get possible moves for a player
    public function getPossibleMovesForPlayer($gameId, $playerId) {
        $player = null;
        $players = $this->db->getGamePlayers($gameId);
        foreach ($players as $p) {
            if ($p['id'] == $playerId) {
                $player = $p;
                break;
            }
        }
        
        if (!$player) {
            return [];
        }
        
        // Get controlled detectives (including the player's own detective if they are a detective)
        $controlledDetectives = $this->getControlledDetectives($gameId, $playerId);
        
        $allMoves = [];
        
        // Get moves for the main player
        $playerMoves = $this->getMovesForPlayer($player, $players);
        
        return $playerMoves;
    }
    
    private function getMovesForPlayer($player, $allPlayers) {
        $possibleMoves = $this->db->getPossibleMoves($player['current_position']);
        $validMoves = [];
        
        foreach ($possibleMoves as $move) {
            // Check if destination is occupied by another player of the same type
            $occupied = false;
            foreach ($allPlayers as $p) {
                if ($p['current_position'] == $move['to_node'] && $p['id'] != $player['id']) {
                    // Allow detectives to move to Mr. X's position
                    if ($player['player_type'] !==  $p['player_type']) {
                        continue; // Allow this move
                    }
                    $occupied = true;
                    break;
                }
            }
            
            if (!$occupied) {
                // Check if player has tickets
                $ticketColumn = $this->getTicketColumn($move['transport_type']);
                $hasRegularTicket = $ticketColumn && $player[$ticketColumn . '_tickets'] > 0;
                $hasHiddenTicket = ($player['player_type'] == 'mr_x' && $player['hidden_tickets'] > 0);
                
                if ($hasRegularTicket || $hasHiddenTicket) {
                    $validMoves[] = [
                        'to_position' => $move['to_node'],
                        'transport_type' => $move['transport_type'],
                        'label' => $this->config['transport_types'][$move['transport_type']],
                        'can_use_hidden' => $hasHiddenTicket
                    ];
                }
            }
        }
        
        return $validMoves;
    }
    
    // Get game state for display
    public function getGameState($gameId) {
        $game = $this->db->getGame($gameId);
        $players = $this->db->getGamePlayers($gameId);
        $currentPlayer = $this->db->getCurrentPlayer($gameId);
        $moves = $this->db->getGameMoves($gameId, $game['current_round']);
        
        $state = [
            'game' => $game,
            'players' => $players,
            'current_player' => $currentPlayer,
            'moves' => $moves,
            'is_reveal_round' => in_array($game['current_round'], $this->config['reveal_rounds'])
        ];
        
        return $state;
    }
    
    /**
     * Get the maximum timestamp from all game-related tables
     * Used for efficient AJAX change detection
     */
    public function getMaxGameTimestamp($gameId) {
        $maxTimestamp = 0;
        
        // Get latest game update timestamp
        $game = $this->db->getGame($gameId);
        if ($game && $game['updated_at']) {
            $gameTimestamp = strtotime($game['updated_at']);
            $maxTimestamp = max($maxTimestamp, $gameTimestamp);
        }
        
        // Get latest player update timestamp
        $playerTimestamp = $this->db->getMaxPlayerUpdateTimestamp($gameId);
        $maxTimestamp = max($maxTimestamp, $playerTimestamp);
        
        // Get latest move timestamp
        $moveTimestamp = $this->db->getMaxMoveTimestamp($gameId);
        $maxTimestamp = max($maxTimestamp, $moveTimestamp);
        
        return $maxTimestamp;
    }
    
    /**
     * Check if there are any updates since the last check
     */
    public function hasGameUpdates($gameId, $lastUpdate) {
        $maxTimestamp = $this->getMaxGameTimestamp($gameId);
        return $maxTimestamp > $lastUpdate;
    }


    /**
     * Render HTML template with variables
     */
    public function renderHtmlTemplate($templateName, $variables = []) {
        // Extract variables to make them available in template scope
        extract($variables);
        
        // Start output buffering
        ob_start();
        // Include the template file
        $templatePath = __DIR__ . "/../views/templates/{$templateName}.php";
        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            $templatePath_real = realpath($templatePath);
            throw new Exception("Template file not found: {$templatePath} {$templatePath_real}");
        }
        
        // Get the rendered content and clean the buffer
        $html = ob_get_clean();
        
        return $html;
    }

    // Get game by key
    public function getGameByKey($gameKey) {
        return $this->db->getGameByKey($gameKey);
    }
}
?>