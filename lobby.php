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
    header('Location: login.php');
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

// Redirect to game.php if game is already active or finished
if ($game['status'] !== 'waiting') {
    header("Location: game.php?key=$gameKey");
    exit();
}

$user = $db->getUserById($_SESSION['user_id']);
$players = $db->getGamePlayers($gameId);
$humanPlayers = $db->getHumanPlayers($gameId);

$userPlayer = $UserModel->getUserPlayer($players);
// Check if user is in this game
$userInGame = $userPlayer ? true : false;

// Check if Mr. X is assigned
$mrXAssigned = false;
foreach ($players as $player) {
    if ($player['player_type'] == 'mr_x') {
        $mrXAssigned = true;
        break;
    }
}

// Handle game initialization (start game)
if ($userInGame && $game['status'] == 'waiting' && count($players) >= 2 && isset($_POST['start_game'])) {
    if (!$mrXAssigned) {
        $_SESSION['game_error'] = 'Please select Mr. X before starting the game.';
        header("Location: lobby.php?key=$gameKey");
        exit();
    }
    
    $gameEngine->initializeGame($gameId);
    header("Location: game.php?key=$gameKey");
    exit();
}

// Get error message from session
$errorMessage = $_SESSION['game_error'] ?? '';
unset($_SESSION['game_error']); // Clear the error after displaying

// Get success message from session
$successMessage = $_SESSION['game_success'] ?? '';
unset($_SESSION['game_success']); // Clear the success after displaying

// Set page variables for header
$pageTitle = 'Scotland Yard - Lobby - ' . htmlspecialchars($game['game_name']);
$includeGameCSS = false;

// Include header
require_once 'views/layouts/header.php';
?>

<div class="container-fluid mt-4">
    <div class="board-container">
        <div class="board-main">
            <h2><?= htmlspecialchars($game['game_name']) ?> - Lobby</h2>
            <p class="text-muted d-flex justify-content-between align-items-center">
                <span>
                    Status: <span class="badge bg-warning">Waiting for Players</span>
                    | Players: <span id="human-count"><?= count($humanPlayers) ?></span>/<?= $game['max_players'] ?>
                    <span id="user-controls">
                        <?php if ($userInGame): ?>
                            <button type="button" class="btn btn-danger mb-3" id="leave-game-btn">Leave Game</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-success mb-3" id="join-game-btn">Join Game</button>
                        <?php endif; ?>
                    </span>
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

            <!-- AJAX-based lobby management -->
            <div class="row">
                <div id="lobby-management-section" class="col-md-6">
                    <?=$gameEngine->renderHtmlTemplate('lobby_management', [
                        'game' => $game,
                        'players' => $players,
                        'humanPlayers' => $humanPlayers,
                        'userInGame' => $userInGame,
                        'mrXAssigned' => $mrXAssigned,
                        'userPlayer' => $userPlayer,
                        'db' => $db,
                        'gameId' => $gameId
                    ]);
                    ?>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body"  id="lobby-user-list-section">
                            <?=$gameEngine->renderHtmlTemplate('lobby_user_list', [
                                'game' => $game,
                                'players' => $players,
                                'humanPlayers' => $humanPlayers
                            ]);
                            ?>
                        </div>
                    </div> 
                    
                </div>
            </div>
        </div>
    </div>
</div>

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

<script src="https://code.jquery.com/jquery-3.7.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// AJAX Lobby Management
let lastUpdateTime = <?= $gameEngine->getMaxGameTimestamp($gameId) ?>;
let updateInterval = null;

// Initialize zoom controls
document.addEventListener('DOMContentLoaded', function() {
    startLobbyUpdates();
    setupEventListeners();
    buttonActionJoinLeave();
    
});

function startLobbyUpdates() {
    updateInterval = setInterval(checkLobbyUpdates, 5000); // Check every 5 seconds
}

function stopLobbyUpdates() {
    if (updateInterval) {
        clearInterval(updateInterval);
        updateInterval = null;
    }
}
function buttonActionJoinLeave() {
    // Attach event listeners to existing buttons
    const initialLeaveBtn = document.getElementById('leave-game-btn');
    const initialJoinBtn = document.getElementById('join-game-btn');
    
    if (initialLeaveBtn) {
        initialLeaveBtn.addEventListener('click', () => {
            showConfirmModal('Are you sure you want to leave the game?', () => {
                performLobbyAction('leave_game');
            });
        });
    }
    
    if (initialJoinBtn) {
        initialJoinBtn.addEventListener('click', () => {
            performLobbyAction('join_game');
        });
    }
}
function checkLobbyUpdates() {
    fetch('controller/ajax_lobby_updates.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'game_key=<?= $gameKey ?>&last_update=' + lastUpdateTime + '&operation=check_updates'
    })
    .then(response => response.json())
    .then(data => {
        if (data.response_status && data.has_updates) {
            // Update lobby data
            updateLobbyData(data.data);
            updateLobbyView(data.rendered_html);
            
            // Update the last update timestamp
            lastUpdateTime = data.timestamp;
            
            // Check if game status changed
            if (data.game_status !== 'waiting') {
                location.href = 'game.php?key=<?= $gameKey ?>';
                return;
            }
        } else if (data.response_status) {
            // No updates, but update timestamp to prevent unnecessary requests
            lastUpdateTime = data.timestamp;
        }
    })
    .catch(error => {
        console.error('Error checking lobby updates:', error);
    });
}

function updateLobbyData(data) {
    // Update player count
    document.getElementById('player-count').textContent = data.total_players;
    
    // Update user controls (join/leave button)
    updateUserControls(data.user_list);
    
    // Update join button visibility
    updateJoinButton(data.human_count, data.max_players);
    document.getElementById('human-count').textContent = data.human_count;

    // Update lobby sections visibility
    updateLobbySectionsVisibility(data.user_list);
}

function updateLobbyView(data) {
    if (data.lobby_management) {
        document.getElementById('lobby-management-section').innerHTML = data.lobby_management;
        // Reattach event listeners for new elements
        setupEventListeners();
    }
    if (data.lobby_user_list) {
        document.getElementById('lobby-user-list-section').innerHTML = data.lobby_user_list;
    }

}

function updateJoinButton(humanCount, maxPlayers) {
    const userControls = document.getElementById('user-controls');
    if (userControls) {
        const joinBtn = userControls.querySelector('#join-game-btn');
        if (joinBtn) {
            if (humanCount >= maxPlayers) {
                joinBtn.style.display = 'none';
            } else {
                joinBtn.style.display = 'inline-block';
            }
        }
    }
}

function updateUserControls(userList) {
    const userControls = document.getElementById('user-controls');
    if (!userControls) {
        return;
    }
    // Check if current user is in the game
    const currentUserInGame = userList.some(player => player.user_id == <?= $_SESSION['user_id'] ?>);
    
    if (currentUserInGame) {
        userControls.innerHTML = '<button type="button" class="btn btn-danger mb-3" id="leave-game-btn">Leave Game</button>';
    } else {
        userControls.innerHTML = '<button type="button" class="btn btn-success mb-3" id="join-game-btn">Join Game</button>';
    }
    buttonActionJoinLeave();
}

function setupEventListeners() {
    // Select Mr. X
    const selectMrXBtn = document.getElementById('select-mr-x-btn');
    if (selectMrXBtn) {
        selectMrXBtn.addEventListener('click', () => {
            const playerId = document.getElementById('mr-x-player').value;
            if (playerId) {
                performLobbyAction('select_mr_x', { player_id: playerId });
            } else {
                showAlert('Please select a player first.', 'warning');
            }
        });
    }
    
    // Create AI detectives
    const createAIBtn = document.getElementById('create-ai-btn');
    if (createAIBtn) {
        createAIBtn.addEventListener('click', () => {
            const numDetectives = document.getElementById('num-ai-detectives').value;
            if (numDetectives) {
                performLobbyAction('create_ai_detective', { num_detectives: numDetectives });
            } else {
                showAlert('Please select number of detectives to create.', 'warning');
            }
        });
    }
    
    // Update assignments
    const updateAssignmentsBtn = document.getElementById('update-assignments-btn');
    if (updateAssignmentsBtn) {
        updateAssignmentsBtn.addEventListener('click', () => {
            const assignments = {};
            document.querySelectorAll('.detective-assignment-select').forEach(select => {
                const aiId = select.dataset.aiId;
                const userId = select.value;
                if (userId !== 'none') {
                    assignments[aiId] = userId;
                }
            });
            
            // Send assignments one by one
            Object.entries(assignments).forEach(([aiId, userId]) => {
                performLobbyAction('assign_detective', { ai_id: aiId, user_id: userId });
            });
        });
    }
    
    // Attach remove AI listeners
    attachRemoveAIListeners();
}

function attachRemoveAIListeners() {
    document.querySelectorAll('.remove-ai-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const aiId = btn.dataset.aiId;
            showConfirmModal('Are you sure you want to remove this AI detective?', () => {
                performLobbyAction('remove_ai_detective', { ai_id: aiId });
            });
        });
    });
}

function performLobbyAction(operation, additionalData = {}) {
    stopLobbyUpdates();
    const formData = new FormData();
    formData.append('game_key', '<?= $gameKey ?>');
    formData.append('operation', operation);
    
    // Add additional data
    Object.keys(additionalData).forEach(key => {
        formData.append(key, additionalData[key]);
    });
    
    fetch('controller/ajax_lobby_updates.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.response_status) {
            showAlert(data.message, 'success');
            // Trigger an immediate update check
            checkLobbyUpdates();
        } else {
            showAlert(data.message, 'error');
        }
        startLobbyUpdates();
    })
    .catch(error => {
        startLobbyUpdates();
        console.error('AJAX Error:', error);
        showAlert('An error occurred while processing your request: ' + error.message, 'error');
    });
}

// Alert function for showing messages
function showAlert(message, type) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Insert at the top of the container
    const container = document.querySelector('.container-fluid') || document.querySelector('.container') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Confirmation modal function
function showConfirmModal(message, onConfirm) {
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    document.getElementById('confirmModalBody').textContent = message;
    
    // Remove existing event listeners
    const confirmBtn = document.getElementById('confirmModalYes');
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    // Add new event listener
    newConfirmBtn.addEventListener('click', () => {
        modal.hide();
        onConfirm();
    });
    
    modal.show();
}


function updateLobbySectionsVisibility(userList) {
    const currentUserInGame = userList.some(player => player.user_id == <?= $_SESSION['user_id'] ?>);

    // Sections to hide/remove
    const lobbySection = document.querySelector('#lobby-management-section');

    if (!currentUserInGame) {
        if (lobbySection) lobbySection.innerHTML="";
    }
}
</script>

<?php
// Include footer
include 'views/layouts/footer.php';
?> 