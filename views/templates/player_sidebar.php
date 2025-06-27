<?php
/**
 * Player Sidebar Template
 * 
 * This template renders the player sidebar showing player information and positions.
 * Variables available:
 * - $players: Array of all players in the game
 * - $currentPlayer: The player whose turn it currently is
 * - $userPlayer: The current user's player
 */

$isUserMrX = ($userPlayer && $userPlayer['player_type'] == 'mr_x');
$playerIcons = PLAYER_ICONS;
foreach ($players as $index => $player) {
    $sidebarPosition = '0';
    if ($player['current_position']) {
        if ($player['player_type'] != 'mr_x' || $isUserMrX) {
            $sidebarPosition = $player['current_position'];
        } else {    
            $sidebarPosition = '--';
        }
    }
    
    $currentClass = ($currentPlayer['id'] == $player['id']) ? 'cur' : '';
    $iconData = explode('|', $playerIcons[$index]);
    ?>
    <p id="pos<?= $index ?>" class="<?= $currentClass ?>">
        <svg viewBox="<?= $iconData[0] ?>">
            <?= $iconData[1] ?>
            <use href="#i-p<?= $index ?>"/>
        </svg>
        <?= htmlspecialchars($player['username']) ?>
        <b><?= $sidebarPosition ?></b>
    </p>
    <?php
}
?> 