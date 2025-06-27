# GameRenders Template System

This directory contains template files for the Scotland Yard game rendering system. The `GameRenders` class provides a flexible template system that can render HTML using either external template files or inline template methods.

## How It Works

The `GameRenders` class provides a `renderHtmlTemplate()` method that can render templates in two ways:

1. **External Template Files**: If a template file exists in the `templates/` directory, it will be included and rendered
2. **Inline Templates**: If no external file exists, it falls back to inline template methods

## Available Templates

### 1. player_positions.php
Renders the player positions on the game map.

**Variables available:**
- `$players`: Array of all players in the game
- `$currentPlayer`: The player whose turn it currently is
- `$game`: The current game data
- `$userPlayer`: The current user's player

### 2. player_sidebar.php
Renders the player sidebar showing player information and positions.

**Variables available:**
- `$players`: Array of all players in the game
- `$currentPlayer`: The player whose turn it currently is
- `$userPlayer`: The current user's player

### 3. move_history.php
Renders the move history showing all moves made in the game.

**Variables available:**
- `$gameId`: The current game ID
- `$players`: Array of all players in the game
- `$game`: The current game data
- `$userPlayer`: The current user's player

## Usage Examples

### Using the Template System

```php
$gameRenders = new GameRenders();

// Render player positions
$playerPositionsHtml = $gameRenders->renderHtmlTemplate('player_positions', [
    'players' => $players,
    'currentPlayer' => $currentPlayer,
    'game' => $game,
    'userPlayer' => $userPlayer
]);

// Render player sidebar
$playerSidebarHtml = $gameRenders->renderHtmlTemplate('player_sidebar', [
    'players' => $players,
    'currentPlayer' => $currentPlayer,
    'userPlayer' => $userPlayer
]);

// Render move history
$moveHistoryHtml = $gameRenders->renderHtmlTemplate('move_history', [
    'gameId' => $gameId,
    'players' => $players,
    'game' => $game,
    'userPlayer' => $userPlayer
]);
```

### Creating Custom Templates

To create a new template:

1. Create a new PHP file in the `templates/` directory (e.g., `custom_template.php`)
2. Add your HTML/PHP code to the file
3. Use the `renderHtmlTemplate()` method with your template name

Example custom template:
```php
<?php
/**
 * Custom Template
 * 
 * Variables available:
 * - $customVariable: Your custom variable
 */

if (isset($customVariable)) {
    echo "<div class='custom-content'>";
    echo htmlspecialchars($customVariable);
    echo "</div>";
}
?>
```

Usage:
```php
$html = $gameRenders->renderHtmlTemplate('custom_template', [
    'customVariable' => 'Hello World!'
]);
```

## Template Variables

All templates have access to these global variables:
- `$db`: Database instance
- `$PLAYER_ICONS`: Array of player icon SVG definitions
- `$GAME_CONFIG`: Game configuration array

## Benefits

1. **Separation of Concerns**: HTML rendering is separated from business logic
2. **Reusability**: Templates can be reused across different parts of the application
3. **Maintainability**: Easy to modify HTML without touching PHP logic
4. **Flexibility**: Can use external files or inline methods
5. **Performance**: Templates are cached in memory during execution

## Fallback System

If a template file doesn't exist in the `templates/` directory, the system will fall back to inline template methods. This ensures backward compatibility and provides a safety net for missing templates. 