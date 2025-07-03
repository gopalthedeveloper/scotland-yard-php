<?php

require_once 'Database.php';

class User {
    private $config;
    private $user;

    public function __construct() {
        $this->config = GAME_CONFIG;
        $this->user = null;
    }

    public function checkUserLoggedIn() {
        $result = ['success' => true];

        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            $result = ['success' => false, 'http_code' => 401, 'message' => 'Not authenticated'];
            header('Location: login.php');
        }
        return $result;
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

    public function getAllUserPlayers($players) {
        $userPlayers = [];
        foreach ($players as $player) {
            if (!empty($player['user_id']) && $player['mapping_type'] == 'owner') {
                $userPlayers[] = $player;
            }
        }
        return $userPlayers;
    }
    

    public function isUserMrX($players) {
        $userPlayer = $this->getUserPlayer($players);
        return $userPlayer?$userPlayer['player_type'] == 'mr_x':false;
    }

}