<?php
require_once 'model/config.php';
require_once 'model/Database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    
    $gameName = trim($_POST['game_name'] ?? '');
    $maxPlayers = (int)($_POST['max_players'] ?? 6);
    
    if (empty($gameName)) {
        header('Location: index.php?error=Game name is required');
        exit();
    }
    
    if ($maxPlayers < 2 || $maxPlayers > 6) {
        header('Location: index.php?error=Invalid number of players');
        exit();
    }
    
    try {
        $gameId = $db->createGame($gameName, $_SESSION['user_id'], $maxPlayers);
        
        // Add creator as first player (detective by default, can be changed to Mr. X later)
        $db->addPlayerToGame($gameId, $_SESSION['user_id'], 'detective');
        
        header("Location: game.php?id=$gameId");
        exit();
    } catch (Exception $e) {
        header('Location: index.php?error=Failed to create game');
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?> 