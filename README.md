# Scotland Yard PHP Game

A multi-user web-based implementation of the classic Scotland Yard board game using PHP, MySQL, and modern web technologies.

## Features

- **Multi-user Support**: Multiple players can join games simultaneously
- **Session Management**: Secure user authentication and session handling
- **Database Storage**: All game data is stored in MySQL database
- **Real-time Updates**: Auto-refresh functionality for live game updates
- **QR Code System**: Mr. X uses QR codes to keep moves secret
- **Responsive Design**: Works on desktop and mobile devices
- **Game History**: Complete move history and replay functionality

## Game Rules

### Objective
- **Detectives**: Work together to catch Mr. X
- **Mr. X**: Evade capture for 24 rounds

### Transportation Types
- **T (Taxi)**: 11 tickets - Short distance moves
- **B (Bus)**: 8 tickets - Medium distance moves  
- **U (Underground)**: 4 tickets - Long distance moves
- **X (Hidden)**: 5 tickets - Mr. X only, moves without revealing location
- **2 (Double)**: 2 tickets - Mr. X only, move twice in one turn

### Special Rules
- Mr. X's position is revealed on rounds: 3, 8, 13, 18, 23, 28, 33, 38
- Mr. X uses QR codes to keep moves secret from detectives
- Detectives can see each other's positions
- Players cannot occupy the same position

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (optional, for dependencies)

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd scotland-yard-php
   ```

2. **Configure database**
   - Create a MySQL database
   - Update database credentials in `config.php`
   - Import the database schema:
   ```bash
   mysql -u username -p database_name < database.sql
   ```

3. **Configure web server**
   - Point your web server to the project directory
   - Ensure PHP has write permissions for sessions

4. **Set up virtual host (optional)**
   ```apache
   <VirtualHost *:80>
       ServerName scotland-yard.local
       DocumentRoot /path/to/scotland-yard-php
       <Directory /path/to/scotland-yard-php>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

5. **Access the application**
   - Navigate to `http://localhost` or your configured domain
   - Register a new account
   - Start playing!

## File Structure

```
scotland-yard-php/
├── config.php              # Configuration settings
├── Database.php            # Database connection and operations
├── GameEngine.php          # Game logic and rules
├── index.php               # Main lobby page
├── login.php               # User authentication
├── register.php            # User registration
├── game.php                # Game interface
├── create_game.php         # Game creation handler
├── logout.php              # Logout handler
├── database.sql            # Database schema
└── README.md               # This file
```

## Database Schema

### Tables
- **users**: User accounts and authentication
- **games**: Game instances and metadata
- **game_players**: Player assignments and game state
- **game_moves**: Move history and tracking
- **game_settings**: Game-specific configuration
- **board_nodes**: Board positions and coordinates
- **board_connections**: Valid moves between positions

## Usage

### For Players

1. **Registration/Login**
   - Create an account or log in with existing credentials
   - All game data is tied to your user account

2. **Joining Games**
   - Browse available games in the lobby
   - Join games that are waiting for players
   - First player to join becomes Mr. X

3. **Playing the Game**
   - **Detectives**: Select destination and transportation type
   - **Mr. X**: Scan QR code to see possible moves, then select letter
   - Game automatically tracks turns and validates moves

4. **Game Features**
   - Real-time updates every 5 seconds
   - Complete move history
   - Player ticket tracking
   - Win condition checking

### For Administrators

1. **Database Management**
   - Monitor game activity through database queries
   - Backup game data regularly
   - Clean up old games if needed

2. **Configuration**
   - Modify game rules in `config.php`
   - Adjust board layout in database
   - Customize ticket limits and reveal rounds

## Security Features

- **Password Hashing**: All passwords are hashed using PHP's password_hash()
- **SQL Injection Protection**: All database queries use prepared statements
- **Session Security**: Secure session configuration
- **Input Validation**: All user input is validated and sanitized
- **CSRF Protection**: Form-based protection against cross-site request forgery

## Customization

### Game Rules
Edit `config.php` to modify:
- Maximum players per game
- Number of rounds
- Ticket limits
- Reveal round schedule
- Transportation types

### Board Layout
Modify the database to:
- Add/remove board positions
- Change transportation connections
- Update starting positions
- Customize board coordinates

### UI/UX
- Modify CSS styles in individual PHP files
- Add JavaScript for enhanced interactivity
- Implement real-time updates using WebSockets
- Add sound effects and animations

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify database credentials in `config.php`
   - Ensure MySQL service is running
   - Check database permissions

2. **Session Issues**
   - Verify PHP session configuration
   - Check file permissions for session storage
   - Ensure cookies are enabled

3. **QR Code Not Displaying**
   - Check internet connection for CDN resources
   - Verify JavaScript is enabled
   - Check browser console for errors

4. **Game Not Starting**
   - Ensure minimum 2 players have joined
   - Check database for game state
   - Verify player assignments

### Debug Mode
Enable debug mode by setting in `config.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- Original Scotland Yard board game by Ravensburger
- QR Code library by QRCode.js
- Bootstrap for responsive design
- PHP community for best practices

## Support

For support and questions:
- Create an issue on GitHub
- Check the troubleshooting section
- Review the game rules and documentation

---

**Enjoy playing Scotland Yard!** 🕵️‍♂️🚔
