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
$activeGames = $db->getActiveGames();

// Set page variables for header
$pageTitle = 'Scotland Yard - Game Lobby';
$pageClass = 'page-index';
$includeGameCSS = false;

// Include header
include 'views/layouts/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <h2 class="page-title">Game Lobby</h2>
            
            <!-- Create New Game -->
            <div class="card mb-4 create-game-section">
                <div class="card-header">
                    <h5 class="mb-0">Create New Game</h5>
                </div>
                <div class="card-body">
                    <form action="create_game.php" method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="game_name" class="form-label">Game Name</label>
                                <input type="text" class="form-control" id="game_name" name="game_name" required>
                            </div>
                            <div class="col-md-3">
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

            <!-- Active Games -->
            <h3 class="page-subtitle">Available Games</h3>
            <?php if (empty($activeGames)): ?>
                <div class="alert alert-info">No active games available. Create a new game to get started!</div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($activeGames as $game): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card game-card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($game['game_name']) ?></h5>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            Created by: <?= htmlspecialchars($game['creator_name']) ?><br>
                                            Players: <?= $game['player_count'] ?>/<?= $game['max_players'] ?><br>
                                            Status: <span class="status-<?= $game['status'] ?>"><?= ucfirst($game['status']) ?></span>
                                        </small>
                                    </p>
                                    <div class="d-flex justify-content-between">
                                        <a href="game.php?id=<?= $game['id'] ?>" class="btn btn-sm btn-primary">Join Game</a>
                                        <small class="text-muted"><?= date('M j, Y g:i A', strtotime($game['created_at'])) ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <!-- Game Rules -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">How to Play</h5>
                </div>
                <div class="card-body">
                    <h6>Objective</h6>
                    <p><strong>Detectives:</strong> Work together to catch Mr. X<br>
                    <strong>Mr. X:</strong> Evade capture for <?= GAME_CONFIG['max_rounds'] ?> rounds</p>
                    
                    <h6>Transportation</h6>
                    <ul class="list-unstyled">
                        <li><strong>T:</strong> Taxi (<?= GAME_CONFIG['tickets']['detective']['taxi'] ?> tickets, detectives), (<?= GAME_CONFIG['tickets']['mr_x']['taxi'] ?> tickets, Mr. X)</li>
                        <li><strong>B:</strong> Bus (<?= GAME_CONFIG['tickets']['detective']['bus'] ?> tickets, detectives), (<?= GAME_CONFIG['tickets']['mr_x']['bus'] ?> tickets, Mr. X)</li>
                        <li><strong>U:</strong> Underground (<?= GAME_CONFIG['tickets']['detective']['underground'] ?> tickets, detectives), (<?= GAME_CONFIG['tickets']['mr_x']['underground'] ?> tickets, Mr. X)</li>
                        <li><strong>X:</strong> Hidden moves (<?= GAME_CONFIG['tickets']['mr_x']['hidden'] ?> tickets, Mr. X only)</li>
                        <li><strong>2:</strong> Double moves (<?= GAME_CONFIG['tickets']['mr_x']['double'] ?> tickets, Mr. X only)</li>
                    </ul>
                    
                    <h6>Special Rules</h6>
                    <ul>
                        <li>Mr. X's position is revealed on rounds <?= implode(', ', GAME_CONFIG['reveal_rounds']) ?></li>
                        <li>Detectives can see each other's positions</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'views/layouts/footer.php';
?> 