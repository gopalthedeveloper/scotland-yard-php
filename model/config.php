<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;   
require_once __DIR__ . '/../vendor/autoload.php';


function startsWithAny(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (str_starts_with($haystack, $needle)) {
            return true;
        }
    }
    return false;
}
$hostName = startsWithAny($_SERVER['HTTP_HOST'], ['localhost', '192.168.','127.0.0.']) ? 'scotlandyard.rf.gd' : $_SERVER['HTTP_HOST'];
$server_host = (empty($_SERVER['HTTPS']) ? 'http' : 'https')."://$hostName";
$actual_host = (empty($_SERVER['HTTPS']) ? 'http' : 'https')."://$_SERVER[HTTP_HOST]";
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'scotland_yard');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SERVER_HOST_URL',$server_host);
define('ACTUAL_HOST_URL',$actual_host);
define('EMAIL_CONFIG', [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_user' => 'mapulais@gmail.com',
    'smtp_pass' => 'fwsu ognv qmxx kdhu',
    'smtp_port' => 587,
    'smtp_encryption' => PHPMailer::ENCRYPTION_STARTTLS,
    'from_email' => 'sribannariammanengineers@gmail.com',
    'from_name' => 'Scotland Yard',
    'debug_mode' => SMTP::DEBUG_OFF // Set to SMTP::DEBUG_SERVER for debugging
]);
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

define('PLAYER_ICONS', [
    '0 0 512 512|<circle style="fill:white;stroke:black;stroke-width:5%" cx="256" cy="256" r="256"/><path style="fill:black" d="M256.9 235.6a97.3 97.3 0 0 1-92.7-67.9l-2.7 3c-12 12-29.5 15.7-40.7 16.7a9.5 9.5 0 0 1-10.5-10.5c1-11.1 4.8-28.7 16.8-40.7 4.4-4.4 9.5-7.7 14.9-10.2a51 51 0 0 1-15-10.2c-12-12-15.6-29.4-16.7-40.6a9.5 9.5 0 0 1 10.5-10.5c11.1 1 28.7 4.8 40.7 16.8 3.6 3.6 6.6 7.9 8.9 12.2a97.3 97.3 0 1 1 86.5 141.9Zm-170.3 172c0-63 42.9-115.8 101-131 4.5-1.3 9.2.6 12 4.4l47.6 63.3a12.2 12.2 0 0 0 19.4 0l47.5-63.3c2.8-3.8 7.5-5.7 12-4.4 58.2 15.2 101 68 101 131a22.6 22.6 0 0 1-22.6 22.6H109.2a22.6 22.6 0 0 1-22.6-22.6ZM208.2 114a12.2 12.2 0 0 0 0 24.3h97.3c6.7 0 12.2-5.5 12.2-12.1 0-6.7-5.5-12.2-12.2-12.2h-97.3Z"/>',
    '0 0 512 512|<circle style="fill:black;stroke:white;stroke-width:5%" cx="256" cy="256" r="256"/><path style="fill:red" d="M256.9 225a97.3 97.3 0 1 0 0-194.6 97.3 97.3 0 0 0 0 194.6ZM222 261.4A135.5 135.5 0 0 0 86.6 397a22.6 22.6 0 0 0 22.6 22.6h295.3a22.6 22.6 0 0 0 22.6-22.6c0-74.8-60.6-135.5-135.5-135.5h-69.5Z"/>',
    '0 0 512 512|<circle style="fill:black;stroke:white;stroke-width:5%" cx="256" cy="256" r="256"/><path style="fill:lightgreen" d="M256.9 225a97.3 97.3 0 1 0 0-194.6 97.3 97.3 0 0 0 0 194.6ZM222 261.4A135.5 135.5 0 0 0 86.6 397a22.6 22.6 0 0 0 22.6 22.6h295.3a22.6 22.6 0 0 0 22.6-22.6c0-74.8-60.6-135.5-135.5-135.5h-69.5Z"/>',
    '0 0 512 512|<circle style="fill:black;stroke:white;stroke-width:5%" cx="256" cy="256" r="256"/><path style="fill:cyan" d="M256.9 225a97.3 97.3 0 1 0 0-194.6 97.3 97.3 0 0 0 0 194.6ZM222 261.4A135.5 135.5 0 0 0 86.6 397a22.6 22.6 0 0 0 22.6 22.6h295.3a22.6 22.6 0 0 0 22.6-22.6c0-74.8-60.6-135.5-135.5-135.5h-69.5Z"/>',
    '0 0 512 512|<circle style="fill:black;stroke:white;stroke-width:5%" cx="256" cy="256" r="256"/><path style="fill:orange" d="M256.9 225a97.3 97.3 0 1 0 0-194.6 97.3 97.3 0 0 0 0 194.6ZM222 261.4A135.5 135.5 0 0 0 86.6 397a22.6 22.6 0 0 0 22.6 22.6h295.3a22.6 22.6 0 0 0 22.6-22.6c0-74.8-60.6-135.5-135.5-135.5h-69.5Z"/>',
    '0 0 512 512|<circle style="fill:black;stroke:white;stroke-width:5%" cx="256" cy="256" r="256"/><path style="fill:yellow" d="M256.9 225a97.3 97.3 0 1 0 0-194.6 97.3 97.3 0 0 0 0 194.6ZM222 261.4A135.5 135.5 0 0 0 86.6 397a22.6 22.6 0 0 0 22.6 22.6h295.3a22.6 22.6 0 0 0 22.6-22.6c0-74.8-60.6-135.5-135.5-135.5h-69.5Z"/>'
]);
// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Lax'); // Allow cross-site requests for AJAX
ini_set('session.cookie_path', '/'); // Ensure cookie is available for all paths

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('Helpers.php');
?> 