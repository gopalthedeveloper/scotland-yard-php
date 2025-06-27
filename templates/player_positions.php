<?php
/**
 * Player Positions Template
 * 
 * This template renders the player positions on the game map.
 * Variables available:
 * - $players: Array of all players in the game
 * - $currentPlayer: The player whose turn it currently is
 * - $game: The current game data
 * - $userPlayer: The current user's player
 */

$nodePositions = [];
foreach ($boardNodes as $node) {
    $nodePositions[$node['node_id']] = [$node['x_coord'], $node['y_coord']];
}

$isUserMrX = ($userPlayer && $userPlayer['player_type'] == 'mr_x');

foreach ($players as $index => $player) {
    if ($player['current_position'] && isset($nodePositions[$player['current_position']])) {
        $pos = $nodePositions[$player['current_position']];
        $boardScalex = 1;
        $boardScaley = 1;
        $centerX = 0;
        $centerY = 0;
        $x = ($pos[0] - $centerX) * $boardScalex;
        $y = ($pos[1] - $centerY) * $boardScaley;
        
        // Show Mr. X only to the Mr. X player, or on reveal rounds, or when game is finished
        $showPlayer = true;
        if ($player['player_type'] == 'mr_x' && $game['status'] == 'active') {
            $showPlayer = ($userPlayer && $userPlayer['player_type'] == 'mr_x') || 
                        in_array($game['current_round'], [3, 8, 13, 18, 23, 28, 33, 38]) || 
                        $game['status'] == 'finished';
        }
        
        if ($showPlayer) {
            $currentClass = ($currentPlayer['id'] == $player['id']) ? 'cur' : '';
            $playerIcons = PLAYER_ICONS;
            $iconData = explode('|', $playerIcons[$index]);
            ?>
            <svg id="p<?= $index ?>" 
                 title="<?= htmlspecialchars($player['username']) ?>" 
                 class="player <?= $currentClass ?>" 
                 viewBox="<?= $iconData[0] ?>"
                 style="left: <?= $x ?>px; top: <?= $y ?>px;">
                <?= $iconData[1] ?>
                <use href="#i-p<?= $index ?>"/>
            </svg>
            <?php
        }
    }
}
?> 