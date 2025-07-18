<?php
require_once 'model/config.php';
require_once 'model/Database.php';
require_once 'model/GameEngine.php';
require_once 'model/User.php';

$db = new Database();
$gameEngine = new GameEngine();
$UserModel = new User();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=game.php?key=' . ($_GET['key'] ?? ''));
    exit();
}


$gameKey = $_GET['key'] ?? null;
if (!$gameKey) {
    header('Location: index.php');
    exit();
}

$game = $db->getGameByKey($gameKey);
if (!$game) {
    header('Location: index.php');
    exit();
}
$gameId = $game['id'];

// Redirect to lobby.php if game is in waiting status
if ($game['status'] === 'waiting') {
    header("Location: lobby.php?key=$gameKey");
    exit();
}

$user = $db->getUserById($_SESSION['user_id']);
$players = $db->getGamePlayers($gameId);
$humanPlayers = $db->getHumanPlayers($gameId);
$currentPlayer = $db->getCurrentPlayer($gameId);

$userPlayer = $UserModel->getUserPlayer($players);
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
        if ($result['response_status']) {
            header("Location: game.php?key=$gameKey");
            exit();
        } else {
            // Store error message in session   
            $_SESSION['game_error'] = $result['message'];
            header("Location: game.php?key=$gameKey");
            exit();
        }
    } else {
        $_SESSION['game_error'] = 'Please select both destination and transport type.';
        header("Location: game.php?key=$gameKey");
        exit();
    }
}

// Refresh data
$game = $db->getGame($gameId);
$players = $db->getGamePlayers($gameId);
// echo '<pre>';print_r($players );die;
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
$playerIcons =PLAYER_ICONS;

// Set page variables for header
$pageTitle = 'Scotland Yard - ' . htmlspecialchars($game['game_name']);
$includeGameCSS = true;

// Include header
require_once 'views/layouts/header.php';
?>

    <div class="board-main">
        <h2><?= htmlspecialchars($game['game_name']) ?></h2>
        <p class="text-muted d-flex justify-content-between align-items-center">
            <span>
                Status: <span class="badge bg-<?= $game['status'] == 'waiting' ? 'warning' : ($game['status'] == 'active' ? 'success' : 'danger') ?>">
                    <?= ucfirst($game['status']) ?>
                </span>
                | Round: <?= $game['current_round'] ?> | Players: <?= $game['status'] == 'waiting'?count($humanPlayers).'/'. $game['max_players']:count($players).' total' ?>
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


        <?php if ($game['status'] == 'finished'): ?>
            <div class="alert alert-info">
                <h4>Game Over!</h4>
                <p><strong><?= ucfirst($game['winner']) ?></strong> won the game!</p>
                <a href="index.php" class="btn btn-primary">Back to Lobby</a>
            </div>
        <?php endif; ?>

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
                <?= $gameEngine->renderHtmlTemplate('player_sidebar', [
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
                        <?= $gameEngine->renderHtmlTemplate('move_history', [
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
   

<?php
// Start output buffering to capture JavaScript
ob_start();
?>
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
                    // Auto-refresh every 5 seconds
                    setTimeout(function() {
                        mapPlayer.classList.remove('highlighted');
                    }, 5000);
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
        fetch('controller/ajax_game_updates.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'game_key=<?= $gameKey ?>&last_update=' + lastUpdateTime
        })
        .then(response => response.json())
        .then(data => {
            if (data.response_status && data.has_updates) {
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
            } else if (data.response_status) {
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

<?php
// Set custom JavaScript for footer
$includeCustomJS = ob_get_contents();
ob_end_clean();

// Include footer
include 'views/layouts/footer.php';
?> 