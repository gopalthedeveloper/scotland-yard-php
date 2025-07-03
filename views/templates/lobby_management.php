<?php
// Variables expected: $game, $players, $userInGame, $mrXAssigned,$db, $gameId
?>
<!-- Mr. X Selection -->
<?php if ($userInGame && $game['status'] == 'waiting' && count($players) >= 2): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h5>Choose Mr. X</h5>
            <p>Select which player will be Mr. X:</p>
            <form id="mr-x-form">
                <div class="row">
                    <div class="col-md-8">
                        <select class="form-select" id="mr-x-player" required>
                            <option value="">Select a player...</option>
                            <?php foreach ($players as $player): 
                                if($player['mapping_type'] !== 'owner')continue;
                                ?>
                                <option value="<?= $player['id'] ?>" <?= ($player['player_type'] == 'mr_x') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($player['username']) ?> 
                                    (<?= $player['player_type'] == 'mr_x' ? 'Currently Mr. X' : 'Detective' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="button" id="select-mr-x-btn" class="btn btn-warning">Set Mr. X</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- AI Detective Creation -->
<?php if ($userInGame && $mrXAssigned && $game['status'] == 'waiting' && count($players) >= 2): ?>
    <?php 
    $aiDetectives = $db->getAIDetectives($gameId);
    $totalDetectives = count($players) - 1; // Exclude Mr. X
    $maxPossibleDetectives = $game['max_players'] - 1; // Max players minus 1 for Mr. X
    $canCreateMore = $totalDetectives < $maxPossibleDetectives;
    $assignment = $db->getDetectiveAssignments($gameId);
    $assignmentByPlayer = array_column($assignment,null,'id');
    ?>
    <div class="card mb-3">
        <div class="card-body">
            <h5>Create AI Detectives</h5>
            <p>Add AI-controlled detectives to the game (<?= $totalDetectives ?> current detectives, max <?= $maxPossibleDetectives ?>):</p>
            <?php if (!$canCreateMore): ?>
                <div class="alert alert-warning">
                    <strong>Maximum detectives reached!</strong> You cannot create more detectives.
                </div>
            <?php else: ?>
                <form id="create-ai-form" class="row">
                    <div class="col-md-6">
                        <label class="form-label">Number of AI detectives to create:</label>
                        <select class="form-select" id="num-ai-detectives" required>
                            <option value="">Select number...</option>
                            <?php for ($i = 1; $i <= min(10, $maxPossibleDetectives - $totalDetectives); $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> detective<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="button" id="create-ai-btn" class="btn btn-success">Create AI Detectives</button>
                    </div>
                </form>
            <?php endif; ?>
            <div id="ai-detective-list" class="mt-3">
                <?php if (!empty($aiDetectives)): ?>
                    <h6>Current AI Detectives:</h6>
                    <div class="detective-assignment">
                        <?php foreach ($aiDetectives as $aiDetective): ?>
                            <div class="mb-1">
                                <strong>AI Detective <?= $aiDetective['id'] ?></strong>
                                <?php 
                                $isAssigned = false;
                                $assignedTo = '';
                                if(isset($assignmentByPlayer[$aiDetective['id']]) && ($assignmentByPlayer[$aiDetective['id']]['controlled_by_user_id'] ?? null)){
                                    $isAssigned = true;
                                    $assignedTo = $assignmentByPlayer[$aiDetective['id']]['username'];
                                }
                                ?>
                                <?php if ($isAssigned): ?>
                                    <span class="badge bg-info ms-2">Controlled by <?= htmlspecialchars($assignedTo) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary ms-2">AI Controlled</span>
                                <?php endif; ?>
                                <?php if ($game['status'] == 'waiting'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-ai-btn ms-2" data-ai-id="<?= $aiDetective['id'] ?>">Remove</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php 
    $joinedUsers = [];
    foreach ($players as $player) {
        if ($player['player_type'] == 'detective' && $player['user_id'] !== null) {
            $joinedUsers[$player['user_id']] = $player['username'];
        }
    }
    ?>
    <div class="card mb-3">
        <div class="card-body">
            <h5>Assign Detectives</h5>
            <p>Assign detectives to players (<?= count($joinedUsers) ?> joined detectives can control <?= count($aiDetectives) - count($joinedUsers) ?> additional detectives):</p>
            <?php if (count($aiDetectives) == 0): ?>
                <div class="alert alert-info">
                    <strong>No detectives to assign!</strong> All detectives are already controlled by joined players.
                </div>
            <?php else: ?>
                <form id="assign-detective-form">
                    <div class="row" id="detective-assignments">
                        <?php foreach ($aiDetectives as $detective): ?>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">
                                    <strong>AI Detective <?= $detective['id'] ?></strong>
                                </label>
                                <select class="form-select detective-assignment-select" data-ai-id="<?= $detective['id'] ?>">
                                    <option value="none">No assignment (AI controlled)</option>
                                    <?php foreach ($joinedUsers as $userId => $username): ?>
                                        <option value="<?= $userId ?>" <?= (isset($assignmentByPlayer[$detective['id']]) && ($assignmentByPlayer[$detective['id']]['controlled_by_user_id'] ?? null) == $userId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($username) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3">
                        <button type="button" id="update-assignments-btn" class="btn btn-info">Update Assignments</button>
                    </div>
                </form>
            <?php endif; ?>
            <div class="mt-3">
                <h6>Current Assignments:</h6>
                <div id="current-assignments" class="detective-assignment">
                    <?php foreach ($joinedUsers as $userId => $username): ?>
                        <div class="mb-2">
                            <strong><?= htmlspecialchars($username) ?></strong> controls:
                            <ul class="mt-1">
                                <li>• Their own detective (Detective <?= $userId ?>)</li>
                                <?php 
                                $controlledCount = 0;
                                foreach ($aiDetectives as $detective): 
                                    if (($detective['controlled_by_user_id'] ?? null) == $userId && $detective['id'] != $userId):
                                        $controlledCount++;
                                        $isAI = $detective['user_id'] === null;
                                ?>
                                    <li>• <?= $isAI ? 'AI Detective' : 'Detective' ?> <?= $detective['id'] ?></li>
                                <?php 
                                    endif;
                                endforeach; 
                                if ($controlledCount == 0):
                                ?>
                                    <li class="text-muted">• No additional detectives</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Start Game -->
<?php if ($userInGame && $game['status'] == 'waiting' && count($players) >= 2): ?>
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