<?php
require_once 'config.php';
require_once 'Database.php';
require_once 'GameEngine.php';
require_once 'GameRenders.php';


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$gameId = $_POST['game_id'] ?? null;
$lastUpdate = $_POST['last_update'] ?? 0;

if (!$gameId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Game ID required']);
    exit();
}

try {
    $db = new Database();
    $gameEngine = new GameEngine();
    $gameRenders = new GameRenders();
    
    // Get current game state
    $game = $db->getGame($gameId);
    if (!$game) {
        echo json_encode(['success' => false, 'message' => 'Game not found']);
        exit();
    }
    
    // Get current players
    $players = $db->getGamePlayers($gameId);
    $currentPlayer = $db->getCurrentPlayer($gameId);
    
    // Get user player
    $userPlayer = $gameRenders->getUserPlayer($players);
    
    if (!$userPlayer) {
        echo json_encode(['success' => false, 'message' => 'User not in game']);
        exit();
    }
    
    // Check if there are any updates since last check
    $hasUpdates = false;
    
    // Check if game status changed
    if ($game['updated_at'] > $lastUpdate) {
        $hasUpdates = true;
    }
    
    // Check if any moves were made since last update
    $recentMoves = $db->getGameMoves($gameId);
    $newMoves = [];
    foreach ($recentMoves as $move) {
        if (strtotime($move['move_timestamp']) > $lastUpdate) {
            $newMoves[] = $move;
            $hasUpdates = true;
        }
    }
    
    // Check if it's now the user's turn
    $isUserTurn = false;
    if ($currentPlayer) {
        if ($currentPlayer['id'] == $userPlayer['id']) {
            $isUserTurn = true;
        } elseif ($currentPlayer['is_ai'] && $currentPlayer['user_id'] == $_SESSION['user_id']) {
            $isUserTurn = true;
        }
    }
    
    // Use GameRenders to generate HTML using the new template system
    $playerPositionsHtml = $gameRenders->renderHtmlTemplate('player_positions', [
        'players' => $players,
        'currentPlayer' => $currentPlayer,
        'game' => $game,
        'userPlayer' => $userPlayer,
        'boardNodes' =>  $db->getBoardNodes()
    ]);
    
    $playerSidebarHtml = $gameRenders->renderHtmlTemplate('player_sidebar', [
        'players' => $players,
        'currentPlayer' => $currentPlayer,
        'userPlayer' => $userPlayer
    ]);
    
    $moveHistoryHtml = $gameRenders->renderHtmlTemplate('move_history', [
        'gameId' => $gameId,
        'players' => $players,
        'game' => $game,
        'userPlayer' => $userPlayer,
        'moves' => $db->getGameMoves($gameId)
    ]);
    // Prepare response data
    $response = [
        'success' => true,
        'has_updates' => $hasUpdates,
        'timestamp' => time(),
        'game_status' => $game['status'],
        'current_round' => $game['current_round'],
        'is_user_turn' => $isUserTurn,
        'rendered_html' => [
            'player_positions' => $playerPositionsHtml,
            'player_sidebar' => $playerSidebarHtml,
            'move_history' => $moveHistoryHtml
        ],
        'current_player_id' => $currentPlayer ? $currentPlayer['id'] : null
    ];
    
    // Set JSON header
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?> 