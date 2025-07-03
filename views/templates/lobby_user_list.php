<?php
// Variables expected: $players, $game
?>

<h5>Players in this game</h5>
<div>
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
                            Joined <?= date('M j, g:i A', strtotime($player['created_at'])) ?>
                        <?php else: ?>
                            AI Detective
                        <?php endif; ?>
                    </small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<p class="mt-2"><small class="text-muted"><span id="player-count"><?= count($players) ?></span>/<?= $game['max_players'] ?> players</small></p>