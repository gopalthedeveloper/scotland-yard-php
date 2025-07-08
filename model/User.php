<?php

require_once 'Database.php';
require_once 'GameEngine.php';

class User {
    private $config;
    private $db;
    private $gameEngine;
    const STATUS_ACTIVE = 1;
    const STATUS_REGISTERED = 0;
    const STATUS_BLOCK = 2;
    

    public function __construct() {
        $this->config = GAME_CONFIG;
        $this->db =  Database::getInstance();
        $this->gameEngine =  GameEngine::getInstance();

    }

    public function checkUserLoggedIn() {
        $result = ['response_status' => true];

        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            $result = ['response_status' => false, 'http_code' => 401, 'message' => 'Not authenticated'];
            header('Location: login.php');
        }
        return $result;
    }
    public function generatePasswordLink ($email){
        $result = ['response_status' => false, 'message' => 'Email is not registered'];
        $user = $this->db->getUserByColumn($email);
        if($user){
            if($user['user_status'] == User::STATUS_ACTIVE || User::STATUS_REGISTERED) {
                $update =['password_reset_token' => Helper::generateRandomString(9), 'password_token_time' => date('Y-m-d H:i:s')];
                $this->db->updateUserByColumn($user['id'], $update);
                $result = ['response_status' => true, 'message' => 'password reset link generated', 'data' => ['name' => $user['username'], 'email' => $user['email'],'reset_token' => $update['password_reset_token']]];
            } elseif($user['user_status'] == User::STATUS_BLOCK || 1) {
                $result['message'] = 'User is blocked contact Admin';
            }
        }
        return $result;
    }

    public function resetPasswordWithToken($token, $password) {
        $result = ['response_status' => false, 'message' => 'Invalid token or password'];
        if (empty($token) || empty($password)) {
            $result['message'] = 'Token and password are required';
            return $result;
        }

        // Validate token
        $user = $this->validatePasswordResetToken($token);
        if (!$user['response_status']) {
            return $user;
        }
        $user = $user['data'];

        // Update password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $updateData = [
            'password_hash' => $hashedPassword,
            'password_reset_token' => null,
            'password_token_time' => null,
            'user_status' => User::STATUS_ACTIVE // Set user status to active after password reset
        ];
        
        if ($this->db->updateUserByColumn($user['id'], $updateData)) {
            $result = ['response_status' => true, 'message' => 'Password has been reset successfully'];
        } else {
            $result['message'] = 'Failed to reset password';
        }
        
        return $result;
    }

    public function validatePasswordResetToken($token) {
        $result = ['response_status' => false, 'message' => 'Link is invalid or expired.', 'valid_token' => false, 'data' => null];
        if (!empty($token)) {
            $extra = 'AND password_token_time > "'. date('Y-m-d H:i:s', strtotime('-1 days')).'"';
            // Check if token exists and is valid
            $user = $this->db->getUserByColumn($token,'password_reset_token', $extra);
            if ($user) {
                $result = ['response_status' => true, 'message' => 'Valid token' , 'data' => $user];
            }
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