<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'scotland_yard');
define('DB_USER', 'root');
define('DB_PASS', '');

// Game configuration
define('GAME_CONFIG', [
    'max_players' => 6,
    'max_rounds' => 24,
    'reveal_rounds' => [3, 8, 13, 18, 23, 28, 33, 38],
    'tickets' => [
        'detective' => [
            'taxi' => 10,
            'bus' => 8,
            'underground' => 4,
            'hidden' => 0,
            'double' => 0
        ],
        'mr_x' => [
            'hidden' => 5,
            'double' => 2,
            'taxi' => 99,
            'bus' => 99,
            'underground' => 99
        ]
    ],
    'transport_types' => [
        'T' => 'Taxi',
        'B' => 'Bus', 
        'U' => 'Underground',
        'F' => 'Ferry',
        'X' => 'Hidden',
        '2' => 'Double',
        '.' => 'Stay'
    ]
]);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?> 