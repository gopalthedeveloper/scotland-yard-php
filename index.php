<?php
require_once 'model/config.php';
require_once 'model/Database.php';
require_once 'model/GameEngine.php';

$db = new Database();
$gameEngine = new GameEngine();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user = $db->getUserById($_SESSION['user_id']);
$activeGames = $db->getActiveGames($_SESSION['user_id']);

// Set page variables for header
$pageTitle = 'Scotland Yard - Game Lobby';
$pageClass = 'page-index';
$includeGameCSS = false;

// Include header
include 'views/layouts/header.php';
?>

<div class="container mt-4">
    <div class="row">
            <div class="col-lg-12">
                <h2 class="page-title">Game Lobby</h2>
            </div>
            <div class="col-lg-9">
                
                <!-- Create New Game -->
                <div class="card mb-4 create-game-section">
                    <div class="card-header">
                        <h5 class="mb-0">Create New Game</h5>
                    </div>
                    <div class="card-body">
                        <form action="create_game.php" method="POST">
                            <div class="row">
                                <div class="col-md-6 col-sm-9">
                                    <label for="game_name" class="form-label">Game Name</label>
                                    <input type="text" class="form-control" id="game_name" name="game_name" required>
                                </div>
                                <div class="col-md-3 col-sm-3">
                                    <label for="max_players" class="form-label">Max Players</label>
                                    <select class="form-select" id="max_players" name="max_players">
                                        <option value="3">3 Players</option>
                                        <option value="4">4 Players</option>
                                        <option value="5">5 Players</option>
                                        <option value="6" selected>6 Players</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">Create Game</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">

                <!-- Join Lobby by Game Key -->
                <div class="card mb-4 create-game-section">
                    <div class="card-header">
                        <h5 class="mb-0">Join by Game Code</h5>
                    </div>
                    <div class="row justify-content-center">
                        <div class="card-body">
                            <form method="get" action="lobby.php">
                                    <label id="key" class="form-label">Game Code</label>

                                 <div class="col-md-6 col-sm-9 input-group">
                                    <input type="text" class="form-control" name="key" id="key" placeholder="Enter Game Code" required>
                                    <button type="submit" class="btn btn-success">Join Lobby</button>
                                </div>
                               
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-12">
                <!-- Active Games -->
                <h3 class="page-subtitle">Available Games</h3>
            </div>
         <div class="col-md-12">
            <?php if (empty($activeGames)): ?>
                <div class="alert alert-info">No active games available. Create a new game to get started!</div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($activeGames as $game): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <div class="card game-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($game['game_name']) ?></h5>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            Created by: <?= htmlspecialchars($game['creator_name']) ?><br>
                                            Players: <?= $game['player_count'] ?>/<?= $game['max_players'] ?><br>
                                            Status: <span class="status-<?= $game['status'] ?>"><?= ucfirst($game['status']) ?></span><br>Game code: <span  onclick="copyLobbyLink('<?= $game['game_key'] ?>',true)"> <?= htmlspecialchars($game['game_key']) ?></span><br>
                                            Created: <?= date('M j, Y g:i A', strtotime($game['created_at'])) ?>
                                        </small>
                                        <small class="text-muted ms-2"></small>  
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <a href="lobby.php?key=<?= $game['game_key'] ?>" class="btn btn-sm btn-primary">Go to Lobby</a>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="copyLobbyLink('<?= $game['game_key'] ?>')">Share Lobby</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<script>
function copyLobbyLink(gameKey, onlyKey = false) {
    const link = onlyKey? gameKey:`${window.location.origin}/lobby.php?key=${gameKey}`;
    navigator.clipboard.writeText(link).then(function() {
        console.log(onlyKey);
        alert(onlyKey?'Lobby key copied to clipboard!':'Lobby link copied to clipboard!');
    }, function(err) {
        alert('Failed to copy ' + err);
    });
}
</script>
<?php
// Include footer
include 'views/layouts/footer.php';
?> 