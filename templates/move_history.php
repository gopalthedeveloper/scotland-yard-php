<?php
/**
 * Move History Template
 * 
 * This template renders the move history showing all moves made in the game.
 * Variables available:
 * - $gameId: The current game ID
 * - $players: Array of all players in the game
 * - $game: The current game data
 * - $userPlayer: The current user's player
 */

if (!$gameId) return;

$playerIcons = PLAYER_ICONS;
$isUserMrX = ($userPlayer && $userPlayer['player_type'] == 'mr_x');

// Header row
?>
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
                $initialPosition = $initialMove ? $initialMove['from_position'] : $player['current_position'];
            } else {    
                $initialPosition = '--';
            }
        ?>
            <li class="moves m_.">
                .<?= $initialPosition ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif;?>

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
    foreach (array_reverse($movesByRound, true) as $round => $roundMoves): ?>
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
                        $showMove = in_array($round, GAME_CONFIG['reveal_rounds']) || $game['status'] == 'finished';
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
endif; ?>
<?php
?> 