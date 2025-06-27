<?php
require_once 'config.php';
require_once 'Database.php';
require_once 'GameEngine.php';
require_once 'GameRenders.php';

$db = new Database();
$gameEngine = new GameEngine();
$gameRenders = new GameRenders();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$gameId = $_GET['id'] ?? null;
if (!$gameId) {
    header('Location: index.php');
    exit();
}

$game = $db->getGame($gameId);
if (!$game) {
    header('Location: index.php');
    exit();
}

$user = $db->getUserById($_SESSION['user_id']);
$players = $db->getGamePlayers($gameId);
$humanPlayers = $db->getHumanPlayers($gameId);
$currentPlayer = $db->getCurrentPlayer($gameId);


$userPlayer = $gameRenders->getUserPlayer($players);
// Check if user is in this game
$userInGame = $userPlayer?true:false;


// Check if Mr. X is assigned
$mrXAssigned = false;
foreach ($players as $player) {
    if ($player['player_type'] == 'mr_x') {
        $mrXAssigned = true;
        break;
    }
}

// Handle join game
if (!$userInGame && $game['status'] == 'waiting' && count($humanPlayers) < $game['max_players']) {
    if (isset($_POST['join_game'])) {
        // Try to find an unassigned AI detective
        $aiDetectives = $db->getDetectiveAssignments($gameId);
        $assigned = false;
        $firstAiDetective = null;
        $pureAiDetective = null;
        foreach ($aiDetectives as $ai) {
            // Check if this AI detective is not controlled by any user (no owner mapping)
            $hasOwner = false;
            foreach ($players as $p) {
                if ($p['id'] == $ai['id'] && $p['mapping_type'] != 'owner') {
                    if(!$firstAiDetective){
                        $firstAiDetective = $ai;
                    }
                    if($p['controlled_by_user_id'] == null){
                        $pureAiDetective = $ai;
                        break;
                    }
                }
            }
            if($pureAiDetective)break;
            
        }
       
        $detective = $pureAiDetective??$firstAiDetective;
        if ($detective) {
            if($detective['controlled_by_user_id'] != null){
                $db->deleteUserPlayerMapping($detective['id'], 'controller','player');
            }
            // Assign this AI detective to the user
            $db->assignAIDetectiveToUser($detective['id'], $_SESSION['user_id']);
        } else {
            // No available AI detective, create a new detective for the user
            $playerType = 'detective';
            $playerOrder = count($players);
            $db->addPlayerToGame($gameId, $_SESSION['user_id'], $playerType, $playerOrder);
        }
        header("Location: game.php?id=$gameId");
        exit();
    }
}

// Handle Mr. X selection
if ($userInGame && $game['status'] == 'waiting' && isset($_POST['select_mr_x'])) {
    $selectedPlayerId = (int)($_POST['mr_x_player'] ?? 0);
    
    // Verify the selected player is in this game
    $validPlayer = false;

    foreach ($players as $player) {
        if ($player['id'] == $selectedPlayerId && $player['mapping_type'] == 'owner') {
            $validPlayer = $player;
            break;
        }
    }
    
    if ($validPlayer) {
        // Change all players to detectives first
        $db->updatePlayerType($gameId, 'detective','game');
        // Set the selected player as Mr. X
        $db->updatePlayerType($selectedPlayerId, 'mr_x');
        // Remove user as controller
        $db->deleteUserPlayerMapping($validPlayer['user_id'], 'controller');
        
        // Reorder players: Mr. X gets order 0, detectives get 1, 2, 3, etc.
        $db->updatePlayerOrder($selectedPlayerId, 0);
        $detectiveOrder = 1;
        foreach ($players as $player) {
            if ($player['id'] != $selectedPlayerId) {
                $db->updatePlayerOrder($player['id'], $detectiveOrder);
                $detectiveOrder++;
            }
        }
        
        // Store success message
        $_SESSION['game_success'] = 'Mr. X selected successfully! Player order has been updated.';
        
        header("Location: game.php?id=$gameId");
        exit();
    }
}

// Handle game initialization
if ($game['status'] == 'waiting' && count($players) >= 2 && isset($_POST['start_game'])) {
    
    if (!$mrXAssigned) {
        $_SESSION['game_error'] = 'Please select Mr. X before starting the game.';
        header("Location: game.php?id=$gameId");
        exit();
    }
    
    $gameEngine->initializeGame($gameId);
    header("Location: game.php?id=$gameId");
    exit();
}

// Handle moves
if ($game['status'] == 'active' && $userInGame && isset($_POST['make_move'])) {
    $toPosition = $_POST['to_position'] ?? null;
    $transportType = $_POST['transport_type'] ?? null;
    $isHidden = isset($_POST['is_hidden'])?$_POST['is_hidden']:false;
    $isDoubleMove = isset($_POST['is_double_move'])?$_POST['is_double_move']:false;
    
    // Determine which player is making the move
    $playerMakingMove = $userPlayer;

    if ($currentPlayer['id'] != $userPlayer['id'] || $currentPlayer['is_ai'] && $currentPlayer['user_id'] == $_SESSION['user_id']) {
        $playerMakingMove = $currentPlayer;
    }

    if ($toPosition && $transportType) {
        $result = $gameEngine->makeMove($gameId,$_SESSION['user_id'], $playerMakingMove['id'], $toPosition, $transportType, $isHidden, $isDoubleMove);
        if ($result['success']) {
            header("Location: game.php?id=$gameId");
            exit();
        } else {
            // Store error message in session   
            $_SESSION['game_error'] = $result['message'];
            header("Location: game.php?id=$gameId");
            exit();
        }
    } else {
        $_SESSION['game_error'] = 'Please select both destination and transport type.';
        header("Location: game.php?id=$gameId");
        exit();
    }
}

// Handle detective assignments
if ($userInGame && $game['status'] == 'waiting' && isset($_POST['assign_detectives'])) {
    $assignments = $_POST['detective_assignments'] ?? [];
    
    $aiDetectives = [];
    if(count($assignments) > 0){
        $aiDetectives = $db->getAIDetectives($gameId);
    }
    
    if(count($aiDetectives) > 0){
      
        $aiDetectivesById = array_column($aiDetectives,null,'id');
       
        // Get joined users who are detectives (excluding AI and Mr. X)
        $joinedUsers = [];
        foreach ($players as $player) {
            if ($player['player_type'] == 'detective' && $player['user_id'] !== null) {
                $joinedUsers[$player['user_id']] = $player['username'];
            }
        }
        
           
        // Apply new assignments
        foreach ($assignments as $detectiveId => $controllingUserId) {
            if ($controllingUserId && isset($aiDetectivesById[$detectiveId]) && $aiDetectivesById[$detectiveId]['controlled_by_user_id'] != $controllingUserId) {
                $db->assignDetectiveToPlayer($detectiveId, $controllingUserId);
            } else if($controllingUserId == 'none'){
                $db->deleteUserPlayerMapping($detectiveId, 'controller','player');
            }
        }
    }
        
    $_SESSION['game_success'] = 'Detective assignments updated successfully!';
    header("Location: game.php?id=$gameId");
    exit();
}
// Handle AI detective creation
if ($userInGame && $game['status'] == 'waiting' && isset($_POST['create_ai_detectives'])) {
    $numDetectives = (int)($_POST['num_ai_detectives'] ?? 0);
    
    if ($numDetectives > 0 && $numDetectives <= $game['max_players']) { // Limit to 10 AI detectives
        // Get current player count to determine starting order
        $currentPlayerCount = count($players);
        
        // Create AI detectives
        for ($i = 0; $i < $numDetectives; $i++) {
            $db->createAIDetective($gameId, $currentPlayerCount + $i);
        }
        
        $_SESSION['game_success'] = "Created $numDetectives AI detective(s) successfully!";
        header("Location: game.php?id=$gameId");
        exit();
    } else {
        $_SESSION['game_error'] = 'Please enter a valid number of AI detectives (1-10).';
        header("Location: game.php?id=$gameId");
        exit();
    }
}

// Handle leave game
if ($userInGame && $game['status'] == 'waiting' && isset($_POST['leave_game'])) {
    // Remove the user's detective (owned player)
    foreach ($players as $player) {
        if ($player['user_id'] == $_SESSION['user_id'] && $player['mapping_type'] == 'owner') {
            $db->convertHumanToAIDetective($player['id']);
            $db->removeUserFromGame($_SESSION['user_id']);
            break;
        }
    }
    header("Location: game.php?id=$gameId");
    exit();
}

// Handle remove AI detective
if ($userInGame && $game['status'] == 'waiting' && isset($_POST['remove_ai_detective'])) {
    $aiId = (int)$_POST['remove_ai_detective'];
    $db->removeAIDetective($aiId);
    header("Location: game.php?id=$gameId");
    exit();
}

// Refresh data
$game = $db->getGame($gameId);
$players = $db->getGamePlayers($gameId);
$currentPlayer = $db->getCurrentPlayer($gameId);
$gameState = $gameEngine->getGameState($gameId);
// Get possible moves for current user
$possibleMoves = [];
if ($userInGame && $game['status'] == 'active') {
    // Check if current player is the user's player
    if ($currentPlayer['id'] == $userPlayer['id']) {
        $possibleMoves = $gameEngine->getPossibleMovesForPlayer($gameId, $userPlayer['id']);
    }
    // Check if current player is an AI detective controlled by the user
    elseif ($currentPlayer['is_ai'] && $currentPlayer['user_id'] == $_SESSION['user_id']) {

        $possibleMoves = $gameEngine->getPossibleMovesForPlayer($gameId, $currentPlayer['id']);
    }
}


// Generate QR data for Mr. X
$isUserMrX = false;

if ($userInGame && ($game['status'] !== 'active' || $userPlayer['player_type'] == 'mr_x')) {
    $isUserMrX = true;
}

// Get board nodes for positioning
$boardNodes = $db->getBoardNodes();
$nodePositions = [];
foreach ($boardNodes as $node) {
    $nodePositions[$node['node_id']] = [$node['x_coord'], $node['y_coord']];
}

// Player icons (SVG definitions)
$playerIcons = [
    '0 0 512 512|<circle style="fill:white;stroke:black;stroke-width:5%" cx="256" cy="256" r="256"/><path style="fill:black" d="M256.9 235.6a97.3 97.3 0 0 1-92.7-67.9l-2.7 3c-12 12-29.5 15.7-40.7 16.7a9.5 9.5 0 0 1-10.5-10.5c1-11.1 4.8-28.7 16.8-40.7 4.4-4.4 9.5-7.7 14.9-10.2a51 51 0 0 1-15-10.2c-12-12-15.6-29.4-16.7-40.6a9.5 9.5 0 0 1 10.5-10.5c11.1 1 28.7 4.8 40.7 16.8 3.6 3.6 6.6 7.9 8.9 12.2a97.3 97.3 0 1 1 86.5 141.9Zm-170.3 172c0-63 42.9-115.8 101-131 4.5-1.3 9.2.6 12 4.4l47.6 63.3a12.2 12.2 0 0 0 19.4 0l47.5-63.3c2.8-3.8 7.5-5.7 12-4.4 58.2 15.2 101 68 101 131a22.6 22.6 0 0 1-22.6 22.6H109.2a22.6 22.6 0 0 1-22.6-22.6ZM208.2 114a12.2 12.2 0 0 0 0 24.3h97.3c6.7 0 12.2-5.5 12.2-12.1 0-6.7-5.5-12.2-12.2-12.2h-97.3Z"/>',
    '0 0 512 512|<circle style="fill:black;stroke:white;stroke-width:5%" cx="256" cy="256" r="256"/><path style="fill:red" d="M256.9 225a97.3 97.3 0 1 0 0-194.6 97.3 97.3 0 0 0 0 194.6ZM222 261.4A135.5 135.5 0 0 0 86.6 397a22.6 22.6 0 0 0 22.6 22.6h295.3a22.6 22.6 0 0 0 22.6-22.6c0-74.8-60.6-135.5-135.5-135.5h-69.5Z"/>',
    '0 0 512 512|<circle style="fill:black;stroke:white;stroke-width:5%" cx="256" cy="256" r="256"/><path style="fill:lightgreen" d="M256.9 225a97.3 97.3 0 1 0 0-194.6 97.3 97.3 0 0 0 0 194.6ZM222 261.4A135.5 135.5 0 0 0 86.6 397a22.6 22.6 0 0 0 22.6 22.6h295.3a22.6 22.6 0 0 0 22.6-22.6c0-74.8-60.6-135.5-135.5-135.5h-69.5Z"/>',
    '0 0 512 512|<circle style="fill:black;stroke:white;stroke-width:5%" cx="256" cy="256" r="256"/><path style="fill:cyan" d="M256.9 225a97.3 97.3 0 1 0 0-194.6 97.3 97.3 0 0 0 0 194.6ZM222 261.4A135.5 135.5 0 0 0 86.6 397a22.6 22.6 0 0 0 22.6 22.6h295.3a22.6 22.6 0 0 0 22.6-22.6c0-74.8-60.6-135.5-135.5-135.5h-69.5Z"/>',
    '0 0 512 512|<circle style="fill:black;stroke:white;stroke-width:5%" cx="256" cy="256" r="256"/><path style="fill:orange" d="M256.9 225a97.3 97.3 0 1 0 0-194.6 97.3 97.3 0 0 0 0 194.6ZM222 261.4A135.5 135.5 0 0 0 86.6 397a22.6 22.6 0 0 0 22.6 22.6h295.3a22.6 22.6 0 0 0 22.6-22.6c0-74.8-60.6-135.5-135.5-135.5h-69.5Z"/>',
    '0 0 512 512|<circle style="fill:black;stroke:white;stroke-width:5%" cx="256" cy="256" r="256"/><path style="fill:yellow" d="M256.9 225a97.3 97.3 0 1 0 0-194.6 97.3 97.3 0 0 0 0 194.6ZM222 261.4A135.5 135.5 0 0 0 86.6 397a22.6 22.6 0 0 0 22.6 22.6h295.3a22.6 22.6 0 0 0 22.6-22.6c0-74.8-60.6-135.5-135.5-135.5h-69.5Z"/>'
];

// Get error message from session
$errorMessage = $_SESSION['game_error'] ?? '';
unset($_SESSION['game_error']); // Clear the error after displaying

// Get success message from session
$successMessage = $_SESSION['game_success'] ?? '';
unset($_SESSION['game_success']); // Clear the success after displaying

// Debug: Show current player order
$debugInfo = '';
if ($game['status'] == 'waiting') {
    $debugInfo = "Current player order: ";
    foreach ($players as $player) {
        $debugInfo .= $player['username'] . " (order: " . $player['player_order'] . ", type: " . $player['player_type'] . ") ";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scotland Yard - <?= htmlspecialchars($game['game_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="qrcode.min.js"></script>
    <style>
        @keyframes blink {
            50% { opacity: 0; }
        }

        @keyframes scale {
            0% { transform: scale(3); }
            100% { transform: scale(1); }
        }

        #svgs {
            display: none;
        }

        #map {
            background-size: cover;
            position: relative;
            width: 2570px;
            height: 1926px;
            background-image: url('map-tk.webp');
            background-color: #f0f0f0; /* Fallback color */
            transform: scale(0.6);
            transform-origin: top left;
            margin-bottom: 20px;
            border: 2px solid #ccc;
            transition: transform 0.3s ease;
            display: inline-block;
        }

        .map-controls {
            top: 10px;
            left: 10px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 260px;
            transition: position 0.3s ease;
        }

        .map-controls.fixed {
            position: fixed;
        }

        .map-controls button {
            font-size: 16px;
            margin: 0 5px;
            padding: 5px 10px;
            border: 1px solid #ccc;
            background: white;
            border-radius: 3px;
            cursor: pointer;
            min-width: auto;
        }

        .map-controls button:hover {
            background: #f0f0f0;
        }

        .map-controls .zoom-level {
            display: inline-block;
            margin: 0 10px;
            font-weight: bold;
            min-width: 40px;
            text-align: center;
        }

        #play {
            position: fixed;
            right: 0px;
            top: 0px;
            max-height: 87vh;
            overflow-y: auto;
            border: 3px solid black;
            background: lightgray;
            width: 400px;
        }

        #play > div {
            margin: 1rem;
        }

        #playerpos p {
            font-size: 120%;
            cursor: pointer;
        }

        #playerpos p.cur {
            background: lightskyblue;
        }

        #playerpos svg, #movelist svg {
            width: 24px;
        }

        #playerpos b {
            display: block;
            float: right;
        }

        #movelist {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        #movelist.minimized {
            max-height: 50px;
            overflow: hidden;
        }

        #movelist h4 {
            cursor: pointer;
            margin-bottom: 10px;
            position: sticky;
            top: 0;
            background: #f8f9fa;
            padding: 5px 0;
            margin: 0 0 10px 0;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .moves-minimize-btn {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 10px;
        }

        .moves-minimize-btn:hover {
            background: #5a6268;
        }

        .btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .btn.disabled:hover {
            background-color: inherit;
        }

        #movelist ul {
            list-style-type: none;
            padding: 0;
            margin: 0 0 5px 0;
        }
    
        #movelist li.rounds{
            display: inline-block;
            font-weight: bold;
            width: 3ch;
            font-family: monospace;
        }
        #movelist li.moves{
            display: inline-block;
            font-weight: normal;
            width: 5ch;
            font-family: monospace;
            border-radius: 3px;
            text-align: center;
        }

        .m_T {
            background-color: #f4e886;
        }

        .m_B {
            background-color: #72bfb5;
        }

        .m_U {
            background-color: #e88072;
        }

        .m_X {
            background-color: #aaa;
        }

        .m_\. {
            background-color: #e9ecef;
            color: #6c757d;
        }

        .no-move {
            color: #ccc;
        }

        #movetbl.small {
            max-height: 4em;
            overflow: hidden;
        }

        #setupwrap, #movewrap {
            border: 3px solid lightskyblue;
        }

        #moveinfo {
            line-height: 2;
        }

        #qrmove {
            background: white;
        }

        h1, h2 {
            font-size: 1.2em;
            text-align: center;
            cursor: pointer;
        }

        h4 {
            font-size: 100%;
            margin: 0;
            margin-block: 0;
            padding: 0 0 0.3rem 0;
        }

        button, select {
            min-width: 4rem;
        }

        button.sel {
            background: lightblue;
        }

        .player {
            display: block;
            position: absolute;
            width: 40px;
            height: 40px;
        }

        .player.cur {
            animation: scale 1s linear, blink 2s ease-in-out 3 1s;
        }

        .game-board {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            overflow: auto;
            max-width: 100%;
        }

        .player-info {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #007bff;
        }

        .player-info.current {
            border-left-color: #28a745;
            background: #f8fff9;
        }

        .player-info.mr-x {
            border-left-color: #dc3545;
        }

        .move-history {
            max-height: 300px;
            overflow-y: auto;
        }

        .qr-container {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
        }

        .board-container {
            display: flex;
            gap: 20px;
        }

        .board-main {
            width: 98vw;
            margin: 0 auto;
            padding: 20px;
        }

        .board-sidebar {
            width: 300px;
            background: #f8f9fa;
            border-left: 1px solid #dee2e6;
            overflow-y: auto;
            position: relative;
        }

        #play {
            margin-top: 55px;
            transition: all 0.3s ease;
        }

        #play.minimized {
            height: 60px;
            overflow: hidden;
            width: 100px;
        }

        #play.minimized #playerpos,
        #play.minimized #movelist,
        #play.minimized #movewrap {
            display: none;
        }

        .minimize-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 12px;
            z-index: 100;
        }

        .minimize-btn:hover {
            background: #5a6268;
        }

        #play h1 {
            margin-bottom: 15px;
            font-size: 1.5rem;
            position: relative;
            text-align: left;
        }

        .player.highlighted {
            animation: pulse 1s infinite;
            filter: drop-shadow(0 0 10px rgba(255, 255, 0, 0.8));
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        #playerpos p {
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        #playerpos p:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }

        #playerpos p.highlighted {
            background-color: rgba(255, 193, 7, 0.3);
            border-radius: 5px;
        }

        .detective-assignment {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin: 5px 0;
        }

        .detective-assignment ul {
            margin-bottom: 0;
            padding-left: 20px;
        }

        .detective-assignment li {
            margin-bottom: 2px;
        }

        .highlighted {
            background-color: #fff3cd !important;
            border: 2px solid #ffc107 !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Scotland Yard</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3"><?= htmlspecialchars($user['username']) ?></span>
                <a class="nav-link" href="index.php">Lobby</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="board-container">
            <div class="board-main">
                <h2><?= htmlspecialchars($game['game_name']) ?></h2>
                <p class="text-muted d-flex justify-content-between align-items-center">
                    <span>
                        Status: <span class="badge bg-<?= $game['status'] == 'waiting' ? 'warning' : ($game['status'] == 'active' ? 'success' : 'danger') ?>">
                            <?= ucfirst($game['status']) ?>
                        </span>
                        | Round: <?= $game['current_round'] ?> | Players: <?= $game['status'] == 'waiting'?count($humanPlayers).'/'. $game['max_players']:count($players).' total' ?>
                        <!-- Join Game -->
                        <?php if (!$userInGame && count($humanPlayers) < $game['max_players']):?> 
                        <button type="submit" name="join_game" class="btn btn-primary ms-3" onclick="document.getElementById('join-form').submit();">Join Game</button>
                        <form id="join-form" method="POST" style="display: none;">
                            <input type="hidden" name="join_game" value="1">
                        </form>
                        <?php endif; ?>
                    </span>
                    <span>
                        <?php if ($game['status'] == 'active' || $game['status'] == 'finished'): ?>
                        <!-- Map Zoom Controls -->
                        <div class="map-controls d-inline-block">
                            <button id="zoom-out" title="Zoom Out (Ctrl + -)">−</button>
                            <span class="zoom-level" id="zoom-level">60%</span>
                            <button id="zoom-in" title="Zoom In (Ctrl + +)">+</button>
                            <button id="zoom-reset" title="Reset Zoom (Ctrl + 0)">Reset</button>
                        </div>
                        <?php endif; ?>
                    </span>
                </p>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($errorMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($debugInfo): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <strong>Debug Info:</strong> <?= htmlspecialchars($debugInfo) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($game['status'] == 'finished'): ?>
                    <div class="alert alert-info">
                        <h4>Game Over!</h4>
                        <p><strong><?= ucfirst($game['winner']) ?></strong> won the game!</p>
                        <a href="index.php" class="btn btn-primary">Back to Lobby</a>
                    </div>
                <?php endif; ?>

                <?php if ($userInGame && $game['status'] == 'waiting'): ?>
                    <form id="leave-game-form" method="POST" style="display:inline-block; margin-bottom:10px;">
                        <button type="button" class="btn btn-danger" id="leave-game-btn">Leave Game</button>
                        <input type="hidden" name="leave_game" value="1">
                    </form>
                <?php endif; ?>

                <div class="row">
                    <div class="col-sm-6 col-xs-12">
                        <!-- Mr. X Selection -->
                        <?php if ($userInGame && $game['status'] == 'waiting' && count($players) >= 2): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5>Choose Mr. X</h5>
                                    <p>Select which player will be Mr. X:</p>
                                    <form method="POST">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <select class="form-select" name="mr_x_player" required>
                                                    <option value="">Select a player...</option>
                                                    <?php foreach ($players as $player): 
                                                        if($player['mapping_type'] !== 'owner')continue;
                                                        ?>
                                                        <option value="<?= $player['id'] ?>" <?= ($player['player_type'] == 'mr_x') ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($player['username']) ?> 
                                                            (<?= $player['player_type'] == 'mr_x' ? 'Currently Mr. X' : 'Detective' ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <button type="submit" name="select_mr_x" class="btn btn-warning">Set Mr. X</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- AI Detective Creation -->
                        <?php if ($userInGame && $mrXAssigned && $game['status'] == 'waiting' && count($players) >= 2): ?>
                            <?php 
                            // Get current AI detectives
                            $aiDetectives = $db->getAIDetectives($gameId);
                            $totalDetectives = count($players) - 1; // Exclude Mr. X
                            $maxPossibleDetectives = $game['max_players'] - 1; // Max players minus 1 for Mr. X
                            $canCreateMore = $totalDetectives < $maxPossibleDetectives;
                            $assignment = $db->getDetectiveAssignments($gameId);
                            $assignmentByPlayer = array_column($assignment,null,'id');
                            ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5>Create AI Detectives</h5>
                                    <p>Add AI-controlled detectives to the game (<?= $totalDetectives ?> current detectives, max <?= $maxPossibleDetectives ?>):</p>
                                    
                                    <?php if (!$canCreateMore): ?>
                                        <div class="alert alert-warning">
                                            <strong>Maximum detectives reached!</strong> You cannot create more detectives.
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Number of AI detectives to create:</label>
                                                <select class="form-select" name="num_ai_detectives" required>
                                                    <option value="">Select number...</option>
                                                    <?php for ($i = 1; $i <= min(10, $maxPossibleDetectives - $totalDetectives); $i++): ?>
                                                        <option value="<?= $i ?>"><?= $i ?> detective<?= $i > 1 ? 's' : '' ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 d-flex align-items-end">
                                                <button type="submit" name="create_ai_detectives" class="btn btn-success">Create AI Detectives</button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- Show current AI detectives -->
                                    <?php if (!empty($aiDetectives)): ?>
                                        <div class="mt-3">
                                            <h6>Current AI Detectives:</h6>
                                            <div class="detective-assignment">
                                                <?php 
                                                
                                                foreach ($aiDetectives as $aiDetective): ?>
                                                    <div class="mb-1">
                                                        <strong>AI Detective <?= $aiDetective['id'] ?></strong>
                                                        <?php 
                                                        $isAssigned = false;
                                                        $assignedTo = '';
                                                        if(isset($assignmentByPlayer[$aiDetective['id']]) && $assignmentByPlayer[$aiDetective['id']]['controlled_by_user_id']){
                                                            $isAssigned = true;
                                                            $assignedTo = $assignmentByPlayer[$aiDetective['id']]['username'];
                                                        }
                                                        ?>
                                                        <?php if ($isAssigned): ?>
                                                            <span class="badge bg-info ms-2">Controlled by <?= htmlspecialchars($assignedTo) ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary ms-2">AI Controlled</span>
                                                        <?php endif; ?>
                                                        <?php if ($game['status'] == 'waiting'): ?>
                                                            <form class="remove-ai-form" method="POST" style="display:inline-block; margin-left:10px;">
                                                                <input type="hidden" name="remove_ai_detective" value="<?= $aiDetective['id'] ?>">
                                                                <button type="button" class="btn btn-sm btn-outline-danger remove-ai-btn">Remove</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php
                            // Get joined users who are detectives (excluding AI and Mr. X)
                            $joinedUsers = [];
                            foreach ($players as $player) {
                                if ($player['player_type'] == 'detective' && $player['user_id'] !== null) {
                                    $joinedUsers[$player['user_id']] = $player['username'];
                                }
                            }
                            ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5>Assign Detectives</h5>
                                    <p>Assign detectives to players (<?= count($joinedUsers) ?> joined detectives can control <?= count($aiDetectives) - count($joinedUsers) ?> additional detectives):</p>
                                    
                                    <?php if (count($aiDetectives) == 0): ?>
                                        <div class="alert alert-info">
                                            <strong>No detectives to assign!</strong> All detectives are already controlled by joined players.
                                        </div>
                                    <?php else: ?>
                                        <form method="POST">
                                            <div class="row">
                                                <?php foreach ($aiDetectives as $detective): ?>
                                                    <div class="col-md-6 mb-2">
                                                        <label class="form-label">
                                                            <strong>AI Detective <?= $detective['id'] ?></strong>
                                                        </label>
                                                        <select class="form-select" name="detective_assignments[<?= $detective['id'] ?>]">
                                                            <option value="none">No assignment (AI controlled)</option>
                                                            <?php foreach ($joinedUsers as $userId => $username): ?>
                                                                <option value="<?= $userId ?>" <?= (isset($assignmentByPlayer[$detective['id']]) && $assignmentByPlayer[$detective['id']]['controlled_by_user_id'] == $userId) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($username) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="mt-3">
                                                <button type="submit" name="assign_detectives" class="btn btn-info">Update Assignments</button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- Show current assignments -->
                                    <div class="mt-3">
                                        <h6>Current Assignments:</h6>
                                        <div class="detective-assignment">
                                            <?php foreach ($joinedUsers as $userId => $username): ?>
                                                <div class="mb-2">
                                                    <strong><?= htmlspecialchars($username) ?></strong> controls:
                                                    <ul class="mt-1">
                                                        <li>• Their own detective (Detective <?= $userId ?>)</li>
                                                        <?php 
                                                        $controlledCount = 0;
                                                        foreach ($aiDetectives as $detective): 
                                                            if ($detective['controlled_by_user_id'] == $userId && $detective['id'] != $userId):
                                                                $controlledCount++;
                                                                $isAI = $detective['user_id'] === null;
                                                        ?>
                                                            <li>• <?= $isAI ? 'AI Detective' : 'Detective' ?> <?= $detective['id'] ?></li>
                                                        <?php 
                                                            endif;
                                                        endforeach; 
                                                        if ($controlledCount == 0):
                                                        ?>
                                                            <li class="text-muted">• No additional detectives</li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Start Game -->
                        <?php if ($userInGame && $game['status'] == 'waiting' && count($players) >= 2): ?>
                            
                            <?php if ($mrXAssigned): ?>
                                <div class="card">
                                    <div class="card-body">
                                        <h5>Ready to start?</h5>
                                        <p>Mr. X has been selected. Click to start the game.</p>
                                        <form method="POST">
                                            <button type="submit" name="start_game" class="btn btn-success">Start Game</button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="card">
                                    <div class="card-body">
                                        <h5>Game Setup Required</h5>
                                        <p class="text-warning">Please select Mr. X before starting the game.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6 col-xs-12">
                        <!-- Player List for Joined Players -->
                        <?php if ( $game['status'] == 'waiting'): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5>Players in this game</h5>
                                    <?php if (empty($players)): ?>
                                        <p class="text-muted">No players have joined yet.</p>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($players as $player): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span>
                                                        <strong><?= htmlspecialchars($player['username']) ?></strong>
                                                        <?php if ($player['user_id'] === null || $player['is_ai'] == 1): ?>
                                                            <span class="badge bg-secondary ms-2">AI</span>
                                                        <?php endif; ?>
                                                        <?php if ($player['player_type'] == 'mr_x'): ?>
                                                            <span class="badge bg-danger ms-2">Mr. X</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-primary ms-2">Detective</span>
                                                        <?php endif; ?>
                                                        <?php if ($player['user_id'] == $_SESSION['user_id']): ?>
                                                            <span class="badge bg-success ms-2">You</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <?php if ($player['user_id'] !== null): ?>
                                                            Joined <?= date('M j, g:i A', strtotime($player['joined_at'])) ?>
                                                        <?php else: ?>
                                                            AI Detective
                                                        <?php endif; ?>
                                                    </small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <p class="mt-2"><small class="text-muted"><?= count($players) ?>/<?= $game['max_players'] ?> players</small></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Game Board -->
                <?php if ($game['status'] == 'active' || $game['status'] == 'finished'): ?>
                    <div class="game-board">
                        <h4>Game Board</h4>
                        
                        
                        
                        <!-- SVG Definitions -->
                        <svg id="svgs">
                            <defs>
                                <?php foreach ($players as $index => $player): ?>
                                    <symbol id="i-p<?= $index ?>" viewBox="<?= explode('|', $playerIcons[$index])[0] ?>">
                                        <?= explode('|', $playerIcons[$index])[1] ?>
                                    </symbol>
                                <?php endforeach; ?>
                            </defs>
                        </svg>

                        <!-- Game Map -->
                        <div id="map">
                            <?php foreach ($players as $index => $player): ?>
                                <?php if ($player['current_position'] && isset($nodePositions[$player['current_position']])): ?>
                                    <?php 
                                    $pos = $nodePositions[$player['current_position']];
                                    $boardScalex = 1; // Same as original game default
                                    $boardScaley = 1; // Same as original game default
                                    $centerX = 0; // Same as original game
                                    $centerY = 0; // Same as original game
                                    $x = ($pos[0] - $centerX) * $boardScalex;
                                    $y = ($pos[1] - $centerY) * $boardScaley;
                                    
                                    // Show Mr. X only to the Mr. X player, or on reveal rounds, or when game is finished
                                    $showPlayer = true;
                                    if ($player['player_type'] == 'mr_x' && $game['status'] == 'active') {
                                        $showPlayer = ($userPlayer && $userPlayer['player_type'] == 'mr_x') || 
                                                    in_array($game['current_round'], GAME_CONFIG['reveal_rounds']) || 
                                                    $game['status'] == 'finished';
                                    }
                                    ?>
                                    <?php if ($showPlayer): ?>
                                        <svg id="p<?= $index ?>" 
                                             title="<?= htmlspecialchars($player['username']) ?>" 
                                             class="player <?= ($currentPlayer['id'] == $player['id']) ? 'cur' : '' ?>" 
                                             viewBox="<?= explode('|', $playerIcons[$index])[0] ?>"
                                             style="left: <?= $x ?>px; top: <?= $y ?>px;">
                                             <?= explode('|', $playerIcons[$index])[1] ?>

                                            <use href="#i-p<?= $index ?>"/>
                                        </svg>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php 
            $canMakeMove = false;
            if ($game['status'] == 'active' || $game['status'] == 'finished'): ?>
            <div class="board-sidebar">
                <div id="play">
                    <h1><?= htmlspecialchars($game['game_name']) ?>
                        <button class="minimize-btn" id="minimize-btn" title="Minimize/Maximize">−</button>
                    </h1>
                    
                    <!-- Player Positions -->
                    <div id="playerpos">
                        <?= $gameRenders->renderHtmlTemplate('player_sidebar', [
                            'players' => $players,
                            'currentPlayer' => $currentPlayer,
                            'game' => $game,
                            'userPlayer' => $userPlayer,
                            'boardNodes' =>  $boardNodes
                        ]);?>
                    </div>

                    <!-- Move List -->
                    <?php if ($game['status'] == 'active' || $game['status'] == 'finished'): ?>
                        <div id="movelist">
                            <h4>Moves
                                <button class="moves-minimize-btn" id="moves-minimize-btn" title="Minimize/Maximize">−</button>
                            </h4>
                            <div id="movetbl">
                                <?=$gameRenders->renderHtmlTemplate('move_history', [
                                        'gameId' => $gameId,
                                        'players' => $players,
                                        'game' => $game,
                                        'userPlayer' => $userPlayer,
                                        'moves' => $db->getGameMoves($gameId)
                                    ]);
                                ?>
                            </div>
                        </div>

                        <!-- Move Interface -->
                        <?php 
                        // Check if it's the current player's turn and they can make a move
                        $currentPlayerForMove = null;
                        
                        if ($userInGame && $game['status'] == 'active') {
                            // Check if current player is the user's player
                            if ($currentPlayer['id'] == $userPlayer['id']) {
                                $canMakeMove = true;
                                $currentPlayerForMove = $userPlayer;
                            }
                            // Check if current player is an AI detective controlled by the user
                            elseif ($currentPlayer['is_ai'] && $currentPlayer['user_id'] == $_SESSION['user_id']) {
                                $canMakeMove = true;
                                $currentPlayerForMove = $currentPlayer;
                            }
                        }
                        
                        if ($canMakeMove): 
                        ?>
                            <div id="movewrap">
                                <div id="moveinfo">
                                    <h4>Round: <?= $game['current_round'] ?></h4>
                                    <?php if ($currentPlayerForMove['player_type'] == 'mr_x'): ?>
                                        <p>T: <?= $currentPlayerForMove['taxi_tickets'] ?> B: <?= $currentPlayerForMove['bus_tickets'] ?> U: <?= $currentPlayerForMove['underground_tickets'] ?> X: <?= $currentPlayerForMove['hidden_tickets'] ?> 2: <?= $currentPlayerForMove['double_tickets'] ?></p>
                                    <?php else: ?>
                                        <p>T: <?= $currentPlayerForMove['taxi_tickets'] ?> B: <?= $currentPlayerForMove['bus_tickets'] ?> U: <?= $currentPlayerForMove['underground_tickets'] ?></p>
                                    <?php endif; ?>
                                </div>

                                <form method="POST">
                                    <input type="hidden" name="is_hidden" id="is_hidden" value="">
                                    <input type="hidden" name="is_double_move" id="is_double_move" value="">
                                    <!-- Player Selection (for controlled detectives) -->
                                    
                                    <select id="move" name="to_position" required>
                                        <option value="">Select destination...</option>
                                        <?php if (is_array($possibleMoves) && count($possibleMoves) > 0): ?>
                                            <?php foreach ($possibleMoves as $move): ?>
                                                <option value="<?= $move['to_position'] ?>" data-transport="<?= $move['transport_type'] ?>">
                                                    <?= $move['to_position'] ?> (<?= $move['label'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    
                                    <select name="transport_type" required>
                                        <option value="">Select transport...</option>
                                        <?php if (is_array($possibleMoves) && count($possibleMoves) > 0): ?>
                                            <?php $uniqueTransports = [];
                                             foreach ($possibleMoves as $move):
                                                if(in_array($move['transport_type'], $uniqueTransports))continue;
                                                $uniqueTransports[] = $move['transport_type'];
                                              ?>
                                                <option value="<?= $move['transport_type'] ?>" data-position="<?= $move['to_position'] ?>">
                                                    <?= $move['label'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>

                                    <?php if ($currentPlayerForMove['player_type'] == 'mr_x'): ?>
                                        <div class="mt-3">
                                            <button type="button" id="move-x" class="btn btn-secondary">X</button>
                                            <?php 
                                            // Check if double move has already been used this round
                                            $doubleMoveUsed = $db->getGameSetting($gameId, 'double_move_used_round_' . $game['current_round']);
                                            $doubleMoveDisabled = ($doubleMoveUsed == '1' || $currentPlayerForMove['double_tickets'] <= 0);
                                            ?>
                                            <button type="button" id="move-2" class="btn btn-secondary <?= $doubleMoveDisabled ? 'disabled' : '' ?>" <?= $doubleMoveDisabled ? 'disabled' : '' ?> title="<?= $doubleMoveDisabled ? 'Double move not available' : 'Double move' ?>">2</button>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-3">
                                        <button type="submit" name="make_move" class="btn btn-primary">Go</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Waiting Message -->
                        <?php if ($userInGame && $game['status'] == 'active' && !$canMakeMove): ?>
                            <div class="alert alert-warning">
                                <h5>Waiting for <?= htmlspecialchars($currentPlayer['username'] ?? 'Unknown Player') ?> to make their move...</h5>
                                <p>It's not your turn yet. Please wait for the current player to complete their move.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="confirmModalBody">
            Are you sure you want to proceed?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmModalYes">Yes</button>
          </div>
        </div>
      </div>
    </div>
    
    <script>
        // Map zoom functionality
        let currentZoom = 0.6; // Starting zoom level (60%)
        const minZoom = 0.2;   // Minimum zoom (20%)
        const maxZoom = 2.0;   // Maximum zoom (200%)
        const zoomStep = 0.1;  // Zoom increment

        function updateZoom() {
            const map = document.getElementById('map');
            const zoomLevel = document.getElementById('zoom-level');
            
            map.style.transform = `scale(${currentZoom})`;
            zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
        }

        function zoomIn() {
            if (currentZoom < maxZoom) {
                currentZoom += zoomStep;
                updateZoom();
            }
        }

        function zoomOut() {
            if (currentZoom > minZoom) {
                currentZoom -= zoomStep;
                updateZoom();
            }
        }

        function resetZoom() {
            currentZoom = 0.6;
            updateZoom();
        }

        // Function to reattach player highlighting event listeners
        function reattachPlayerHighlighting() {
            const playerPositions = document.querySelectorAll('#playerpos p');
            const mapPlayers = document.querySelectorAll('#map .player');
            
            playerPositions.forEach(function(playerPos, index) {
                // Remove any existing event listeners to prevent duplicates
                playerPos.removeEventListener('click', playerPos.highlightHandler);
                
                // Create and store the event handler
                playerPos.highlightHandler = function() {
                    // Remove previous highlights
                    playerPositions.forEach(p => p.classList.remove('highlighted'));
                    mapPlayers.forEach(p => p.classList.remove('highlighted'));
                    
                    // Add highlight to clicked player
                    playerPos.classList.add('highlighted');
                    
                    // Add highlight to corresponding map player
                    const mapPlayer = document.getElementById('p' + index);
                    if (mapPlayer) {
                        mapPlayer.classList.add('highlighted');
                        
                        // Scroll map to player position if needed
                        const mapContainer = document.getElementById('map');
                        const playerRect = mapPlayer.getBoundingClientRect();
                        const mapRect = mapContainer.getBoundingClientRect();
                        
                        // Check if player is outside visible area
                        if (playerRect.left < mapRect.left || playerRect.right > mapRect.right ||
                            playerRect.top < mapRect.top || playerRect.bottom > mapRect.bottom) {
                            mapPlayer.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center',
                                inline: 'center'
                            });
                        }
                    }
                };
                
                // Add the event listener
                playerPos.addEventListener('click', playerPos.highlightHandler);
            });
            
            // Clear highlights when clicking elsewhere (only attach once)
            if (!window.highlightClearHandler) {
                window.highlightClearHandler = function(e) {
                    if (!e.target.closest('#playerpos p') && !e.target.closest('#map .player')) {
                        playerPositions.forEach(p => p.classList.remove('highlighted'));
                        mapPlayers.forEach(p => p.classList.remove('highlighted'));
                    }
                };
                document.addEventListener('click', window.highlightClearHandler);
            }
        }

        // Initialize zoom controls
        document.addEventListener('DOMContentLoaded', function() {
            const zoomInBtn = document.getElementById('zoom-in');
            const zoomOutBtn = document.getElementById('zoom-out');
            const zoomResetBtn = document.getElementById('zoom-reset');

            if (zoomInBtn) zoomInBtn.addEventListener('click', zoomIn);
            if (zoomOutBtn) zoomOutBtn.addEventListener('click', zoomOut);
            if (zoomResetBtn) zoomResetBtn.addEventListener('click', resetZoom);

            // Minimize/Maximize functionality
            const minimizeBtn = document.getElementById('minimize-btn');
            const playElement = document.getElementById('play');
            
            if (minimizeBtn && playElement) {
                minimizeBtn.addEventListener('click', function() {
                    playElement.classList.toggle('minimized');
                    minimizeBtn.textContent = playElement.classList.contains('minimized') ? '+' : '−';
                    minimizeBtn.title = playElement.classList.contains('minimized') ? 'Maximize' : 'Minimize';
                });
            }

            // Moves minimize functionality
            const movesMinimizeBtn = document.getElementById('moves-minimize-btn');
            const movesList = document.getElementById('movelist');
            
            if (movesMinimizeBtn && movesList) {
                movesMinimizeBtn.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent triggering the header click
                    movesList.classList.toggle('minimized');
                    movesMinimizeBtn.textContent = movesList.classList.contains('minimized') ? '+' : '−';
                    movesMinimizeBtn.title = movesList.classList.contains('minimized') ? 'Maximize' : 'Minimize';
                });
            }

            // Player position highlighting - use the same function as AJAX updates
            reattachPlayerHighlighting();

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Only handle shortcuts when not typing in input fields
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') {
                    return;
                }

                if (e.ctrlKey || e.metaKey) {
                    switch(e.key) {
                        case '=':
                        case '+':
                            e.preventDefault();
                            zoomIn();
                            break;
                        case '-':
                            e.preventDefault();
                            zoomOut();
                            break;
                        case '0':
                            e.preventDefault();
                            resetZoom();
                            break;
                    }
                }
            });

            // Mouse wheel zoom (optional)
            const map = document.getElementById('map');
            if (map) {
                map.addEventListener('wheel', function(e) {
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        if (e.deltaY < 0) {
                            zoomIn();
                        } else {
                            zoomOut();
                        }
                    }
                });
            }

            // Map controls scroll behavior
            const mapControls = document.querySelector('.map-controls');
            const gameBoard = document.querySelector('.game-board');
            
            if (mapControls && gameBoard) {
                const gameBoardTop = gameBoard.offsetTop;
                
                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > gameBoardTop) {
                        mapControls.classList.add('fixed');
                    } else {
                        mapControls.classList.remove('fixed');
                    }
                });
            }

            // Move list toggle functionality
            const moveListHeader = document.querySelector('#movelist h4');
            const moveTable = document.querySelector('#movetbl');
            
            if (moveListHeader && moveTable) {
                moveListHeader.addEventListener('click', function() {
                    moveTable.classList.toggle('small');
                });
            }
        });


        // Auto-refresh every 5 seconds
        // setTimeout(function() {
        //     location.reload();
        // }, 5000);

        // Auto-refresh when it's not the user's turn
        <?php if ($userInGame && $game['status'] == 'active' && !$canMakeMove): ?>
        // AJAX updates handle this now - no need for page refresh
        <?php endif; ?>

        <?php if($canMakeMove): ?>
        // Handle move selection
        document.getElementById('move').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const transportSelect = document.querySelector('select[name="transport_type"]');
            
            const transportOption = transportSelect.querySelector(`option[data-position="${this.value}"]`);
            
            if (transportOption) {
                transportSelect.value = transportOption.value;
            } else {
                transportSelect.value = selectedOption.getAttribute('data-transport');
            }
        });

        // Handle transport selection
        document.querySelector('select[name="transport_type"]').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const moveSelect = document.getElementById('move');
            const moveOption = moveSelect.querySelector(`option[data-transport="${this.value}"]`);
            
            if (moveOption) {
                moveSelect.value = moveOption.value;
            }
        });
        <?php endif; ?>

        // Handle X button (hidden move)
        const moveXBtn = document.getElementById('move-x');
        if (moveXBtn) {
            moveXBtn.addEventListener('click', function() {
                const isHiddenField = document.getElementById('is_hidden');
                const isHidden = isHiddenField.value === '1';
                isHiddenField.value = isHidden ? '' : '1';
                
                // Update button appearance
                if (isHiddenField.value === '1') {
                    this.classList.add('btn-primary');
                    this.classList.remove('btn-secondary');
                } else {
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-secondary');
                }
            });
        }

        // Handle 2 button (double move)
        const move2Btn = document.getElementById('move-2');
        if (move2Btn) {
            move2Btn.addEventListener('click', function() {
                // Don't allow clicking if button is disabled
                if (this.classList.contains('disabled') || this.disabled) {
                    return;
                }
                
                const isDoubleField = document.getElementById('is_double_move');
                const isDouble = isDoubleField.value === '1';
                
                if (isDouble) {
                    // Clear double move
                    isDoubleField.value = '';
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-secondary');
                } else {
                    // Set double move
                    isDoubleField.value = '1';
                    this.classList.add('btn-primary');
                    this.classList.remove('btn-secondary');
                }
            });
        }

        // Detective control functionality
        const playerSelect = document.getElementById('player-select');
        const moveSelect = document.getElementById('move');
        const transportSelect = document.querySelector('select[name="transport_type"]');
        
        if (playerSelect && moveSelect && transportSelect) {
            // Store the original possible moves data
            const possibleMovesData = <?= json_encode($possibleMoves) ?>;
            
            function updateMoveOptions() {
                const selectedPlayerId = playerSelect.value;
                let selectedPlayerMoves = null;
                
                // Find the selected player's moves
                if (selectedPlayerId === '') {
                    // Main player
                    selectedPlayerMoves = possibleMovesData.find(p => p.is_main_player);
                } else {
                    // Controlled detective
                    selectedPlayerMoves = possibleMovesData.find(p => p.player_id == selectedPlayerId);
                }
                
                if (selectedPlayerMoves) {
                    // Update destination options
                    moveSelect.innerHTML = '<option value="">Select destination...</option>';
                    selectedPlayerMoves.moves.forEach(move => {
                        const option = document.createElement('option');
                        option.value = move.to_position;
                        option.textContent = `${move.to_position} (${move.label})`;
                        option.dataset.transport = move.transport_type;
                        moveSelect.appendChild(option);
                    });
                    
                    // Update transport options
                    transportSelect.innerHTML = '<option value="">Select transport...</option>';
                    const uniqueTransports = [];
                    selectedPlayerMoves.moves.forEach(move => {
                        if (!uniqueTransports.includes(move.transport_type)) {
                            uniqueTransports.push(move.transport_type);
                            const option = document.createElement('option');
                            option.value = move.transport_type;
                            option.textContent = move.label;
                            option.dataset.position = move.to_position;
                            transportSelect.appendChild(option);
                        }
                    });
                }
            }
            
            // Initialize with first player's moves
            if (possibleMovesData.length > 0) {
                updateMoveOptions();
            }
            
            // Update when player selection changes
            playerSelect.addEventListener('change', updateMoveOptions);
        }

        // Bootstrap modal confirmation for Leave Game and Remove AI Detective
        let confirmAction = null;
        let confirmForm = null;
        let confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));

        // Leave Game
        const leaveGameBtn = document.getElementById('leave-game-btn');
        if (leaveGameBtn) {
            leaveGameBtn.addEventListener('click', function(e) {
                confirmAction = 'leave';
                confirmForm = document.getElementById('leave-game-form');
                document.getElementById('confirmModalBody').textContent = 'Are you sure you want to leave the game?';
                confirmModal.show();
            });
        }

        // Remove AI Detective
        const removeAiBtns = document.querySelectorAll('.remove-ai-btn');
        removeAiBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                confirmAction = 'remove_ai';
                confirmForm = btn.closest('form');
                document.getElementById('confirmModalBody').textContent = 'Are you sure you want to remove this AI detective?';
                confirmModal.show();
            });
        });

        // Modal Yes button
        const confirmModalYes = document.getElementById('confirmModalYes');
        confirmModalYes.addEventListener('click', function() {
            if (confirmForm) {
                confirmForm.submit();
                confirmModal.hide();
            }
        });

        // AJAX game state updates
        let lastUpdateTime = <?= $gameEngine->getMaxGameTimestamp($gameId) ?>;
        let updateInterval = null;

        function startGameUpdates() {
            updateInterval = setInterval(checkGameUpdates, 2000); // Check every 2 seconds
        }

        function stopGameUpdates() {
            if (updateInterval) {
                clearInterval(updateInterval);
                updateInterval = null;
            }
        }

        function checkGameUpdates() {
            fetch('ajax_game_updates.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'game_id=<?= $gameId ?>&last_update=' + lastUpdateTime
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.has_updates) {
                    console.log('AJAX Update - Changes detected at timestamp:', data.timestamp);
                    
                    // Update player positions on the map using pre-rendered HTML
                    updatePlayerPositions(data.rendered_html.player_positions);
                    
                    // Update player sidebar using pre-rendered HTML
                    updatePlayerSidebar(data.rendered_html.player_sidebar);
                    
                    // Update move history using pre-rendered HTML
                    if (data.rendered_html.move_history) {
                        updateMoveHistory(data.rendered_html.move_history);
                    }
                    
                    // Update game status if changed
                    if (data.game_status !== '<?= $game['status'] ?>') {
                        location.reload(); // Full reload if game status changed
                        return;
                    }
                    
                    // Check if it's now the user's turn
                    if (data.is_user_turn && !<?= $canMakeMove ? 'true' : 'false' ?>) {
                        location.reload(); // Reload to show move interface
                        return;
                    }
                    
                    // Update the last update timestamp
                    lastUpdateTime = data.timestamp;
                } else if (data.success) {
                    // No updates, but update timestamp to prevent unnecessary requests
                    lastUpdateTime = data.timestamp;
                }
            })
            .catch(error => {
                console.error('Error checking game updates:', error);
            });
        }

        function updatePlayerPositions(positionsHtml) {
            const map = document.getElementById('map');
            if (map) {
                // Remove existing player elements
                map.querySelectorAll('.player').forEach(el => el.remove());
                // Add new player elements
                map.insertAdjacentHTML('beforeend', positionsHtml);
                // Reattach highlighting to include new map players
                reattachPlayerHighlighting();
            }
        }

        function updatePlayerSidebar(sidebarHtml) {
            const playerPos = document.getElementById('playerpos');
            if (playerPos) {
                playerPos.innerHTML = sidebarHtml;
                // Reattach event listeners after DOM update
                reattachPlayerHighlighting();
            }
        }

        function updateMoveHistory(moveHistoryHtml) {
            const moveTable = document.getElementById('movetbl');
            if (moveTable) {
                moveTable.innerHTML= moveHistoryHtml;
            }
        }

        // Start AJAX updates when game is active
        <?php if ($game['status'] == 'active'): ?>
        startGameUpdates();
        <?php endif; ?>

        // Stop updates when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopGameUpdates();
            } else {
                <?php if ($game['status'] == 'active'): ?>
                startGameUpdates();
                <?php endif; ?>
            }
        });
    </script>
</body>
</html> 