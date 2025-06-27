<?php

require_once 'Database.php';

class GameRenders {
    private $db;
    private $config;

    public function __construct() {
        $this->db = new Database();
        $this->config = GAME_CONFIG;
    }

    /**
     * Get the current user's player from the players array
     */
    public function getUserPlayer($players) {
        $userPlayer = null;
        foreach ($players as $player) {
            if ($player['user_id'] == $_SESSION['user_id'] && $player['mapping_type'] == 'owner') {
                $userPlayer = $player;
                break;
            }
        }
        return $userPlayer;
    }

    public function isUserMrX($players) {
        $userPlayer = $this->getUserPlayer($players);
        return $userPlayer?$userPlayer['player_type'] == 'mr_x':false;
    }

    /**
     * Render HTML template with variables
     */
    public function renderHtmlTemplate($templateName, $variables = []) {
        // Extract variables to make them available in template scope
        extract($variables);
        
        // Start output buffering
        ob_start();
        
        // Include the template file
        $templatePath = "views/templates/{$templateName}.php";
        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            throw new Exception("Template file not found: {$templatePath}");
        }
        
        // Get the rendered content and clean the buffer
        $html = ob_get_clean();
        
        return $html;
    }
    
    
}