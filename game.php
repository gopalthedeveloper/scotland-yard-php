<?php
require_once 'config.php';
require_once 'Database.php';
require_once 'GameEngine.php';

$db = new Database();
$gameEngine = new GameEngine();

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
$currentPlayer = $db->getCurrentPlayer($gameId);

// Check if user is in this game
$userInGame = false;
$userPlayer = null;
foreach ($players as $player) {
    if ($player['user_id'] == $_SESSION['user_id']) {
        $userInGame = true;
        $userPlayer = $player;
        break;
    }
}

// Handle join game
if (!$userInGame && $game['status'] == 'waiting' && count($players) < $game['max_players']) {
    if (isset($_POST['join_game'])) {
        $playerType = 'detective'; // Default to detective, can be changed to Mr. X later
        $playerOrder = count($players);
        $db->addPlayerToGame($gameId, $_SESSION['user_id'], $playerType, $playerOrder);
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
        if ($player['id'] == $selectedPlayerId) {
            $validPlayer = true;
            break;
        }
    }
    
    if ($validPlayer) {
        // Change all players to detectives first
        foreach ($players as $player) {
            $db->updatePlayerType($player['id'], 'detective');
        }
        // Set the selected player as Mr. X
        $db->updatePlayerType($selectedPlayerId, 'mr_x');
        
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
    // Check if Mr. X is assigned
    $mrXAssigned = false;
    foreach ($players as $player) {
        if ($player['player_type'] == 'mr_x') {
            $mrXAssigned = true;
            break;
        }
    }
    
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
    
    if ($toPosition && $transportType) {
        $result = $gameEngine->makeMove($gameId, $userPlayer['id'], $toPosition, $transportType, $isHidden, $isDoubleMove);
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

// Refresh data
$game = $db->getGame($gameId);
$players = $db->getGamePlayers($gameId);
$currentPlayer = $db->getCurrentPlayer($gameId);
$gameState = $gameEngine->getGameState($gameId);

// Get possible moves for current user
$possibleMoves = [];
if ($userInGame && $game['status'] == 'active' && $currentPlayer['id'] == $userPlayer['id']) {
    $possibleMoves = $gameEngine->getPossibleMovesForPlayer($gameId, $userPlayer['id']);
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
                        | Round: <?= $game['current_round'] ?> | Players: <?= count($players) ?>/<?= $game['max_players'] ?>
                        <!-- Join Game -->
                        <?php if (!$userInGame && count($players) < $game['max_players']):?> 
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
                                                    <?php foreach ($players as $player): ?>
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

                        <!-- Start Game -->
                        <?php if ($userInGame && $game['status'] == 'waiting' && count($players) >= 2): ?>
                            <?php 
                            // Check if Mr. X is assigned
                            $mrXAssigned = false;
                            foreach ($players as $player) {
                                if ($player['player_type'] == 'mr_x') {
                                    $mrXAssigned = true;
                                    break;
                                }
                            }
                            ?>
                            
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
                                                        <?php if ($player['player_type'] == 'mr_x'): ?>
                                                            <span class="badge bg-danger ms-2">Mr. X</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-primary ms-2">Detective</span>
                                                        <?php endif; ?>
                                                        <?php if ($player['user_id'] == $_SESSION['user_id']): ?>
                                                            <span class="badge bg-success ms-2">You</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <small class="text-muted">Joined <?= date('M j, g:i A', strtotime($player['joined_at'])) ?></small>
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
                                                    in_array($game['current_round'], [3, 8, 13, 18, 23, 28, 33, 38]) || 
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

            <?php if ($game['status'] == 'active' || $game['status'] == 'finished'): ?>
            <div class="board-sidebar">
                <div id="play">
                    <h1>SSY - <?= htmlspecialchars($game['game_name']) ?>
                        <button class="minimize-btn" id="minimize-btn" title="Minimize/Maximize">−</button>
                    </h1>
                    
                    <!-- Player Positions -->
                    <div id="playerpos">
                        <?php foreach ($players as $index => $player): ?>
                            <p id="pos<?= $index ?>" class="<?= ($currentPlayer['id'] == $player['id']) ? 'cur' : '' ?>">
                                <svg viewBox="<?= explode('|', $playerIcons[$index])[0] ?>">
                                    <?= explode('|', $playerIcons[$index])[1] ?>
                                    <use href="#i-p<?= $index ?>"/>
                                </svg>
                                <?= htmlspecialchars($player['username']) ?>
                                <b>
                                    <?php if($player['player_type'] != 'mr_x' || $isUserMrX): ?>
                                        <?= $player['current_position'] ?: '0' ?>
                                    <?php else: ?>
                                        --
                                    <?php endif; ?>
                                </b>
                            </p>
                        <?php endforeach; ?>
                    </div>

                    <!-- Move List -->
                    <?php if ($game['status'] == 'active' || $game['status'] == 'finished'): ?>
                        <div id="movelist">
                            <h4>Moves
                                <button class="moves-minimize-btn" id="moves-minimize-btn" title="Minimize/Maximize">−</button>
                            </h4>
                            <div id="movetbl">
                                <ul>
                                    <li class="rounds"></li>
                                    <?php foreach ($players as $index => $player): ?>
                                        <li class="moves">
                                            <svg viewBox="<?= explode('|', $playerIcons[$index])[0] ?>">
                                                <?= explode('|', $playerIcons[$index])[1] ?>
                                                <use href="#i-p<?= $index ?>"/>
                                            </svg>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php
                                $moves = $db->getGameMoves($gameId);
                                
                                // Get initial positions from database (round 1 moves)
                                $initialMoves = [];
                                if (!empty($moves)) {
                                    foreach ($moves as $move) {
                                        if ($move['round_number'] == 1) {
                                            $playerIndex = array_search($move['player_id'], array_column($players, 'id'));
                                            if ($playerIndex !== false) {
                                                $initialMoves[$playerIndex] = $move;
                                            }
                                        }
                                    }
                                }
                                
                                // Always show initial positions (round 1)
                                if ($game['status'] == 'active' || $game['status'] == 'finished'):
                                ?>
                                    <ul>
                                        <li class="rounds">R.i</li>
                                        <?php foreach ($players as $index => $player): 
                                            $initialMove = $initialMoves[$index] ?? null;
                                            if($player['player_type'] != 'mr_x' || $isUserMrX){
                                                $iniatialPosition = $initialMove ? $initialMove['from_position'] : $player['current_position'];
                                            } else {    
                                                $iniatialPosition = '--';
                                            }
                                        ?>
                                            <li class="moves m_.">
                                                .<?= $iniatialPosition ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                
                                <?php
                                if (!empty($moves)):
                                    // Group moves by round
                                    $movesByRound = [];
                                    foreach ($moves as $move) {
                                        $round = $move['round_number'];
                                        if (!isset($movesByRound[$round])) {
                                            $movesByRound[$round] = [];
                                        }
                                        $movesByRound[$round][] = $move;
                                    }
                                    
                                    // Display moves by round (newest first)
                                    foreach (array_reverse($movesByRound, true) as $round => $roundMoves):
                                ?>
                                    <ul>
                                        <li class="rounds">R<?= $round ?></li>
                                        <?php 
                                        // Find moves for each player in this round
                                        $playerMoves = [];
                                        foreach ($roundMoves as $move) {
                                            $playerIndex = array_search($move['player_id'], array_column($players, 'id'));
                                            if ($playerIndex !== false) {
                                                if (!isset($playerMoves[$playerIndex])) {
                                                    $playerMoves[$playerIndex] = [];
                                                }
                                                $playerMoves[$playerIndex][] = $move;
                                            }
                                        }
                                        
                                        foreach ($players as $index => $player): 
                                            $pMoves = $playerMoves[$index] ?? [];
                                            $move = array_shift($pMoves);

                                            if(false){
                                                doubleMove:
                                                $move = array_shift($pMoves);
                                                ?>
                                                </ul>
                                                <ul>
                                                    <li class="rounds">R<?= $round ?></li>
                                            <?php
                                            } 
                                            if ($move): ?>
                                                <?php
                                                $transportType = $move['transport_type'];
                                                $showMove = true;
                                                
                                                // Hide Mr. X moves unless it's a reveal round or game is finished
                                                if ($player['player_type'] == 'mr_x' && $game['status'] == 'active' && !$isUserMrX) {
                                                    $showMove = in_array($round, [3, 8, 13, 18, 23, 28, 33, 38]) || $game['status'] == 'finished';
                                                }
                                                $transportType = $move['is_hidden']? 'X':$move['transport_type'];

                                                $moveText = $showMove ? $transportType . '.' . $move['to_position'] :$transportType . '.--';
                                                
                                                ?>
                                                <li class="moves m_<?= $transportType ?>">
                                                    <?= $moveText ?>
                                                </li>
                                            <?php endif; 
                                            if(count($pMoves) > 0){
                                                goto doubleMove;
                                            }
                                            ?>

                                        <?php endforeach; ?>
                                    </ul>
                                <?php 
                                    endforeach;
                                endif;
                                ?>
                            </div>
                        </div>

                        <!-- Move Interface -->
                        <?php if ($userInGame && $game['status'] == 'active' && $currentPlayer['id'] == $userPlayer['id']): ?>
                            <div id="movewrap">
                                <div id="moveinfo">
                                    <h4>Round: <?= $game['current_round'] ?></h4>
                                    <?php if ($userPlayer['player_type'] == 'mr_x'): ?>
                                        <p>T: <?= $userPlayer['taxi_tickets'] ?> B: <?= $userPlayer['bus_tickets'] ?> U: <?= $userPlayer['underground_tickets'] ?> X: <?= $userPlayer['hidden_tickets'] ?> 2: <?= $userPlayer['double_tickets'] ?></p>
                                    <?php else: ?>
                                        <p>T: <?= $userPlayer['taxi_tickets'] ?> B: <?= $userPlayer['bus_tickets'] ?> U: <?= $userPlayer['underground_tickets'] ?></p>
                                    <?php endif; ?>
                                </div>

                                <form method="POST">
                                    <input type="hidden" name="is_hidden" id="is_hidden" value="">
                                    <input type="hidden" name="is_double_move" id="is_double_move" value="">
                                    
                                    <select id="move" name="to_position" required>
                                        <option value="">Select destination...</option>
                                        <?php foreach ($possibleMoves as $move): ?>
                                            <option value="<?= $move['to_position'] ?>" data-transport="<?= $move['transport_type'] ?>">
                                                <?= $move['to_position'] ?> (<?= $move['label'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <select name="transport_type" required>
                                        <option value="">Select transport...</option>
                                        <?php $uniqueTransports = [];
                                         foreach ($possibleMoves as $move):
                                            if(in_array($move['transport_type'], $uniqueTransports))continue;
                                            $uniqueTransports[] = $move['transport_type'];
                                          ?>
                                            <option value="<?= $move['transport_type'] ?>" data-position="<?= $move['to_position'] ?>">
                                                <?= $move['label'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <?php if ($userPlayer['player_type'] == 'mr_x'): ?>
                                        <div class="mt-3">
                                            <button type="button" id="move-x" class="btn btn-secondary">X</button>
                                            <?php 
                                            // Check if double move has already been used this round
                                            $doubleMoveUsed = $db->getGameSetting($gameId, 'double_move_used_round_' . $game['current_round']);
                                            $doubleMoveDisabled = ($doubleMoveUsed == '1' || $userPlayer['double_tickets'] <= 0);
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
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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

            // Player position highlighting
            const playerPositions = document.querySelectorAll('#playerpos p');
            const mapPlayers = document.querySelectorAll('#map .player');
            
            playerPositions.forEach(function(playerPos, index) {
                playerPos.addEventListener('click', function() {
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
                });
            });

            // Clear highlights when clicking elsewhere
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#playerpos p') && !e.target.closest('#map .player')) {
                    playerPositions.forEach(p => p.classList.remove('highlighted'));
                    mapPlayers.forEach(p => p.classList.remove('highlighted'));
                }
            });

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
    </script>
</body>
</html> 