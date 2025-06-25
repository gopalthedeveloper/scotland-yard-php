<?php
require_once 'Database.php';

class GameEngine {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = new Database();
        $this->config = GAME_CONFIG;
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
        
        // Set first player (Mr. X)
        $this->db->setCurrentPlayer($gameId, $players[0]['id']);
        
        // Update game status
        $this->db->updateGameStatus($gameId, 'active');
        
        return true;
    }
    
    private function setInitialPositions($gameId, $players) {
        $nodes = $this->db->getBoardNodes();
        $nodeIds = array_column($nodes, 'node_id');
        
        // Shuffle positions
        shuffle($nodeIds);
        
        // Assign positions to players
        foreach ($players as $index => $player) {
            $position = $nodeIds[$index];
            $this->db->updatePlayerPosition($player['id'], $position);
        }
    }
    
    // Move validation and execution
    public function makeMove($gameId, $playerId, $toPosition, $transportType, $isHidden = false, $isDoubleMove = false) {
        $game = $this->db->getGame($gameId);
        $currentPlayer = $this->db->getCurrentPlayer($gameId);
        
        // Validate it's the player's turn
        if ($currentPlayer['id'] != $playerId) {
            return ['success' => false, 'message' => 'Not your turn'];
        }
        
        // Validate game is active
        if ($game['status'] !== 'active') {
            return ['success' => false, 'message' => 'Game is not active'];
        }
        
        // Check if a double move has already been used this round
        if ($isDoubleMove) {
            $doubleMoveUsed = $this->db->getGameSetting($gameId, 'double_move_used_round_' . $game['current_round']);
            if ($doubleMoveUsed == '1') {
                return ['success' => false, 'message' => 'Double move already used this round'];
            }
        }
        
        // Validate move
        $validation = $this->validateMove($playerId, $currentPlayer['current_position'], $toPosition, $transportType, $isHidden, $isDoubleMove);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        // Execute move
        $this->executeMove($gameId, $playerId, $currentPlayer['current_position'], $toPosition, $transportType, $isHidden, $isDoubleMove);
        
        // Check win conditions
        $winCheck = $this->checkWinConditions($gameId);
        if ($winCheck['gameOver']) {
            $this->db->updateGameStatus($gameId, 'finished', $winCheck['winner']);
            return ['success' => true, 'gameOver' => true, 'winner' => $winCheck['winner']];
        }
        
        // For double moves, don't advance to next player yet
        if ($isDoubleMove) {
            // Mark that a double move has been used this round
            $this->db->setGameSetting($gameId, 'double_move_used_round_' . $game['current_round'], '1');
            // Set a flag to indicate this is the first move of a double move
            $this->db->setGameSetting($gameId, 'double_move_in_progress', '1');
            return ['success' => true, 'gameOver' => false, 'doubleMove' => true, 'message' => 'Make your second move'];
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
        
        return ['success' => true, 'gameOver' => false];
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
        
        $possibleMoves = $this->db->getPossibleMoves($player['current_position']);
        $validMoves = [];
        
        foreach ($possibleMoves as $move) {
            // Check if destination is occupied by another player of the same type
            $occupied = false;
            foreach ($players as $p) {
                if ($p['current_position'] == $move['to_node'] && $p['id'] != $playerId) {
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
    
    // Generate QR code data for Mr. X moves
    public function generateQRData($gameId, $playerId) {
        $player = null;
        $players = $this->db->getGamePlayers($gameId);
        foreach ($players as $p) {
            if ($p['id'] == $playerId) {
                $player = $p;
                break;
            }
        }
        
        if (!$player || $player['player_type'] !== 'mr_x') {
            return null;
        }
        
        $possibleMoves = $this->getPossibleMovesForPlayer($gameId, $playerId);
        
        // Generate QR data
        $qrData = "Position: " . $player['current_position'] . "\n";
        $qrData .= "Tickets: T:" . $player['taxi_tickets'] . " B:" . $player['bus_tickets'] . " U:" . $player['underground_tickets'] . " X:" . $player['hidden_tickets'] . " 2:" . $player['double_tickets'] . "\n";
        
        // Add possible moves with letters
        $letters = range('A', 'Z');
        foreach ($possibleMoves as $index => $move) {
            if ($index < count($letters)) {
                $qrData .= $letters[$index] . "=" . $move['transport_type'] . $move['to_position'] . "\n";
            }
        }
        
        return $qrData;
    }
}
?> 