<?php
require_once '../model/config.php';
require_once '../model/Database.php';
require_once '../model/GameEngine.php';
require_once '../model/User.php';


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

$db = new Database();
$gameEngine = new GameEngine();
$UserModel = new User();

// Get current game state
$game = $db->getGame($gameId);

if (!$game) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Game not found']);
    exit();
}
// Get the maximum timestamp from all relevant tables using GameEngine
$maxTimestamp = $gameEngine->getMaxGameTimestamp($gameId);

// Check if there are any updates since last check
$hasUpdates = $gameEngine->hasGameUpdates($gameId, $lastUpdate);

if (!$hasUpdates) {
    // No updates, return minimal response
    $response = [
        'success' => true,
        'timestamp' => $maxTimestamp,
        'has_updates' => false
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}


$players = $db->getGamePlayers($gameId);
$currentPlayer = $db->getCurrentPlayer($gameId);
$userPlayer = $UserModel->getUserPlayer($players);

// There are updates, process the data
$isUserTurn = false;
if ($userPlayer && $currentPlayer) {
    // Check if current player is the user's player
    if ($currentPlayer['id'] == $userPlayer['id']) {
        $isUserTurn = true;
    }
    // Check if current player is an AI detective controlled by the user
    elseif ($currentPlayer['is_ai'] && $currentPlayer['user_id'] == $_SESSION['user_id']) {
        $isUserTurn = true;
    }
}

// Use GameEngine to generate HTML using the new template system
$playerPositionsHtml = $gameEngine->renderHtmlTemplate('player_positions', [
    'players' => $players,
    'currentPlayer' => $currentPlayer,
    'game' => $game,
    'userPlayer' => $userPlayer,
    'boardNodes' =>  $db->getBoardNodes()
]);

$playerSidebarHtml = $gameEngine->renderHtmlTemplate('player_sidebar', [
    'players' => $players,
    'currentPlayer' => $currentPlayer,
    'userPlayer' => $userPlayer
]);

$moveHistoryHtml = $gameEngine->renderHtmlTemplate('move_history', [
    'gameId' => $gameId,
    'players' => $players,
    'game' => $game,
    'userPlayer' => $userPlayer,
    'moves' => $db->getGameMoves($gameId)
]);

// Prepare response data
$response = [
    'success' => true,
    'timestamp' => $maxTimestamp,
    'has_updates' => true,
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

header('Content-Type: application/json');
echo json_encode($response);
?> 