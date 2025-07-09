<?php
require_once 'Database.php';

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
    public function createUser($username, $email, $password) {
        $result = ['response_status' => false, 'message' => 'Failed to create user'];
        if (empty($username) || empty($email) || empty($password)) {
            $result['message'] = 'All fields are required';
            return $result;
        }

        // Check if email already exists
        if ($this->db->getUserByColumn($email)) {
            $result['message'] = 'Email is already registered';
            return $result;
        }

        // Check if username already exists
        if ($this->db->getUserByColumn($username, 'username')) {
            $result['message'] = 'Username is already registered';
            return $result;
        }

        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $activate_Token = $this->db->generateUniqueToken('users', 'password_reset_token', 10);


        // Insert user into database
        if ($this->db->createUser($username, $email, $hashedPassword, $activate_Token)) {

            // Prepare HTML email
            $subject = "Welcome to Scotland Yard - Let the Hunt Begin!";
            $message = $this->gameEngine->renderHtmlTemplate('email/activation_link', ['token' => $activate_Token,'name' => $username], true);
            Helper::sendEmail($email, $subject, $message);
            
            $result = ['response_status' => true, 'message' => 'Account created successfully! Check your email to activate your account.'];
        }
        
        return $result;
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
                $activate_Token =$this->db->generateUniqueToken('users', 'password_reset_token', 10);
                $update =[
                    'password_reset_token' => $activate_Token, 
                    'password_token_time' => date('Y-m-d H:i:s')
                ];
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
    public function userActivateToken($token) {
        $result = ['response_status' => false, 'message' => 'Invalid activation link or link has expired'];

        $extra = ' AND password_token_time IS NULL AND user_status = '.User::STATUS_REGISTERED.' AND created_at > "'. date('Y-m-d H:i:s', strtotime('-1 days')).'"';
        // Check if token exists and is valid
        $user = $this->db->getUserByColumn($token,'password_reset_token', $extra);
        if ($user) {
            // Update user status to active
            $updateData = [
                'user_status' => User::STATUS_ACTIVE,
                'password_reset_token' => null
            ];
            if ($this->db->updateUserByColumn($user['id'], $updateData)) {
                $result = ['response_status' => true, 'message' => 'Account activated successfully'];
            } else {
                $result['message'] = 'Failed to activate account';
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