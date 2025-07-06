<?php
// session_start() is already called in config.php
require_once '../model/config.php';
require_once '../model/Database.php';
require_once '../model/GameEngine.php';
require_once '../model/User.php';

$db = new Database();
$gameEngine = new GameEngine();
$UserModel = new User();

function handleResult($response) {
    if(isset($response['http_code']))http_response_code($response['http_code']);
    echo json_encode($response);
    exit();
}

$result = $UserModel->checkUserLoggedIn();
if(!$result['response_status'])handleResult($result);


$gameId = $_POST['game_id'] ?? null;
if (!$gameId) {
    http_response_code(400);
    echo json_encode(['response_status' => false, 'message' => 'Game ID required']);
    exit();
}

$game = $db->getGame($gameId);
if (!$game) {
    http_response_code(404);
    echo json_encode(['response_status' => false, 'message' => 'Game not found']);
    exit();
}

// Handle different operations
$operation = $_POST['operation'] ?? 'check_updates';

// Debug logging

switch ($operation) {
    case 'join_game':
        handleJoinGame($db, $gameId, $_SESSION['user_id']);
        break;
    case 'leave_game':
        handleLeaveGame($db, $gameId, $_SESSION['user_id']);
        break;
    case 'create_ai_detective':
        handleCreateAIDetective($db, $gameId, $_POST['num_detectives'] ?? 1);
        break;
    case 'remove_ai_detective':
        handleRemoveAIDetective($db, $gameId, $_POST['ai_id'] ?? null);
        break;
    case 'assign_detective':
        handleAssignDetective($db, $gameId, $_POST['ai_id'] ?? null, $_POST['user_id'] ?? null);
        break;
    case 'select_mr_x':
        handleSelectMrX($db, $gameId, $_POST['player_id'] ?? null);
        break;
    case 'check_updates':
    default:
        handleCheckUpdates($db, $gameId, $_POST['last_update'] ?? 0);
        break;
}

function handleJoinGame($db, $gameId, $userId) {
    
    $game = $db->getGame($gameId);
    $players = $db->getGamePlayers($gameId);
    $humanPlayers = $db->getHumanPlayers($gameId);
    
    // Check if user is already in game
    $userInGame = false;
    foreach ($players as $player) {
        if ($player['user_id'] == $userId && $player['mapping_type'] == 'owner') {
            $userInGame = true;
            break;
        }
    }
    
    if ($userInGame) {
        echo json_encode(['response_status' => false, 'message' => 'Already in game']);
        return;
    }
    
    if ($game['status'] != 'waiting') {
        echo json_encode(['response_status' => false, 'message' => 'Game is not in waiting status']);
        return;
    }
    
    if (count($humanPlayers) >= $game['max_players']) {
        echo json_encode(['response_status' => false, 'message' => 'Game is full']);
        return;
    }
    
    // Try to find an unassigned AI detective
    $aiDetectives = $db->getDetectiveAssignments($gameId);
    
    $assigned = false;
    $firstAiDetective = null;
    $pureAiDetective = null;
    
    foreach ($aiDetectives as $ai) {
        $hasOwner = false;
        foreach ($players as $p) {
            if ($p['id'] == $ai['id'] && $p['mapping_type'] != 'owner') {
                if (!$firstAiDetective) {
                    $firstAiDetective = $ai;
                }
                if (($p['controlled_by_user_id'] ?? null) == null) {
                    $pureAiDetective = $ai;
                    break;
                }
            }
        }
        if ($pureAiDetective) break;
    }
    
    $detective = $pureAiDetective ?? $firstAiDetective;
    if ($detective) {
        if (($detective['controlled_by_user_id'] ?? null) != null) {
            $db->deleteUserPlayerMapping($detective['id'], 'controller', 'player');
        }
        $db->assignAIDetectiveToUser($detective['id'], $userId);
    } else {
        $playerType = 'detective';
        $playerOrder = count($players);
        $db->addPlayerToGame($gameId, $userId, $playerType,1);
    }
    
    // Update user timestamp
    $db->updateGameTimestamp($gameId, 'user');
    
    echo json_encode(['response_status' => true, 'message' => 'Joined game successfully']);
}

function handleLeaveGame($db, $gameId, $userId) {
    $players = $db->getGamePlayers($gameId);
    $userFound = false;
    foreach ($players as $player) {
        if ($player['user_id'] == $userId && $player['mapping_type'] == 'owner') {
            $userFound = true;
            $db->convertHumanToAIDetective($player['id']);
            $db->removeUserFromGame($userId);
            break;
        }
    }
    
    if (!$userFound) {
        echo json_encode(['response_status' => false, 'message' => 'User not found in game or not owner']);
        return;
    }
    
    // Update user timestamp
    $db->updateGameTimestamp($gameId, 'user');
    
    echo json_encode(['response_status' => true, 'message' => 'Left game successfully']);
}

function handleCreateAIDetective($db, $gameId, $numDetectives) {
    $game = $db->getGame($gameId);
    $players = $db->getGamePlayers($gameId);
    
    if ($numDetectives > 0 && $numDetectives <= $game['max_players']) {
        
        for ($i = 0; $i < $numDetectives; $i++) {
            $db->createAIDetective($gameId);
        }
        
        // Update user timestamp
        $db->updateGameTimestamp($gameId, 'user');
        
        echo json_encode(['response_status' => true, 'message' => "Created $numDetectives AI detective(s) successfully"]);
    } else {
        echo json_encode(['response_status' => false, 'message' => 'Invalid number of detectives']);
    }
}

function handleRemoveAIDetective($db, $gameId, $aiId) {
    if ($aiId) {
        $db->removeAIDetective($aiId);
        
        // Update user timestamp
        $db->updateGameTimestamp($gameId, 'user');
        
        echo json_encode(['response_status' => true, 'message' => 'AI detective removed successfully']);
    } else {
        echo json_encode(['response_status' => false, 'message' => 'AI ID required']);
    }
}

function handleAssignDetective($db, $gameId, $aiId, $userId) {
    if ($aiId && $userId) {
        if ($userId == 'none') {
            $db->deleteUserPlayerMapping($aiId, 'controller', 'player');
        } else {
            $db->assignDetectiveToPlayer($aiId, $userId);
        }
        
        // Update user timestamp
        $db->updateGameTimestamp($gameId, 'user');
        
        echo json_encode(['response_status' => true, 'message' => 'Detective assignment updated']);
    } else {
        echo json_encode(['response_status' => false, 'message' => 'AI ID and User ID required']);
    }
}

function handleSelectMrX($db, $gameId, $playerId) {
    $players = $db->getGamePlayers($gameId);
    
    // Verify the selected player is in this game
    $validPlayer = false;
    foreach ($players as $player) {
        if ($player['id'] == $playerId && $player['mapping_type'] == 'owner') {
            $validPlayer = $player;
            break;
        }
    }
    
    if ($validPlayer) {
        // Change all players to detectives first
        $db->updatePlayerType($gameId, 'detective', 'game');
        // Set the selected player as Mr. X
        $db->updatePlayerType($playerId, 'mr_x');
        // Remove user as controller
        $db->deleteUserPlayerMapping($validPlayer['user_id'], 'controller');
        
        // Reorder players: Mr. X gets order 0, detectives get 1, and ai 2
        $db->resetLobbyPlayerOrder($gameId);
        
        // Update user timestamp
        $db->updateGameTimestamp($gameId, 'user');
        
        echo json_encode(['response_status' => true, 'message' => 'Mr. X selected successfully']);
    } else {
        echo json_encode(['response_status' => false, 'message' => 'Invalid player selected']);
    }
}

function handleCheckUpdates($db, $gameId, $lastUpdate) {
    $UserModel = new User();

    $game = $db->getGame($gameId);
    $players = $db->getGamePlayers($gameId);
    $humanPlayers = $UserModel->getAllUserPlayers($players);
    $userPlayer = $UserModel->getUserPlayer($players);
    // Get current timestamp
    $currentTimestamp = $db->getMaxGameTimestamp($gameId);
    
    // Check if there are updates since last check
    $hasUpdates = $currentTimestamp > $lastUpdate;
    
    if ($hasUpdates) {
        // Get updated data
        $userList = [];
        $mrXAssigned = false;
        
        foreach ($players as $player) {
            if ($player['player_type'] == 'mr_x') {
                $mrXAssigned = true;
            }
            
            if ($player['user_id'] !== null || $player['is_ai'] != 1) {
                $userList[] = [
                    'id' => $player['id'],
                    'user_id' => $player['user_id'],
                    'username' => $player['username'],
                    'player_type' => $player['player_type'],
                    'joined_at' => $player['joined_at']
                ];
            }
        }
        
        // Get AI detective assignments
        
        $gameEngine = new GameEngine();
        $lobbyManagementHtml = $gameEngine->renderHtmlTemplate('lobby_management', [
            'game' => $game,
            'players' => $players,
            'userInGame' => $userPlayer,
            'mrXAssigned' => $mrXAssigned,
            'db' => $db,
            'gameId' => $gameId
        ]);
        $lobbyUserListHtml = $gameEngine->renderHtmlTemplate('lobby_user_list', [
            'game' => $game,
            'players' => $players
        ]);

        $response = [
            'response_status' => true,
            'has_updates' => true,
            'timestamp' => $currentTimestamp,
            'game_status' => $game['status'],
            'data' => [
                'user_list' => $userList,
                'human_count' => count($humanPlayers),
                'max_players' => $game['max_players'],
                'total_players' => count($players)
            ],
            'rendered_html' => [
                'lobby_management' => $lobbyManagementHtml,
                'lobby_user_list' => $lobbyUserListHtml
            ]
        ];
        echo json_encode($response);
    } else {
        echo json_encode([
            'response_status' => true,
            'has_updates' => false,
            'timestamp' => $currentTimestamp
        ]);
    }
}
?> 