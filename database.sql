-- Database schema for Scotland Yard PHP game

CREATE DATABASE IF NOT EXISTS scotland_yard;
USE scotland_yard;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- Games table
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_name VARCHAR(100) NOT NULL,
    max_players INT DEFAULT 6,
    current_round INT DEFAULT 1,
    max_rounds INT DEFAULT 24,
    status ENUM('waiting', 'active', 'finished') DEFAULT 'waiting',
    winner ENUM('detectives', 'mr_x', 'none') DEFAULT 'none',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Game players table
CREATE TABLE game_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    user_id INT NOT NULL,
    player_type ENUM('mr_x', 'detective') NOT NULL,
    player_order INT NOT NULL,
    current_position INT DEFAULT 0,
    taxi_tickets INT DEFAULT 11,
    bus_tickets INT DEFAULT 8,
    underground_tickets INT DEFAULT 4,
    hidden_tickets INT DEFAULT 5,
    double_tickets INT DEFAULT 2,
    is_current_turn BOOLEAN DEFAULT FALSE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_game_player (game_id, user_id)
);

-- Game moves table
CREATE TABLE game_moves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    round_number INT NOT NULL,
    player_id INT NOT NULL,
    from_position INT NOT NULL,
    to_position INT NOT NULL,
    transport_type ENUM('T', 'B', 'U', 'F', 'X', '2', '.') NOT NULL,
    is_hidden BOOLEAN DEFAULT FALSE,
    is_double_move BOOLEAN DEFAULT FALSE,
    move_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES game_players(id) ON DELETE CASCADE
);

-- Game settings table
CREATE TABLE game_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT NOT NULL,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    UNIQUE KEY unique_game_setting (game_id, setting_key)
);

-- Game board nodes table
CREATE TABLE board_nodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    node_id INT NOT NULL,
    x_coord INT NOT NULL,
    y_coord INT NOT NULL,
    UNIQUE KEY unique_node (node_id)
);

-- Game board connections table
CREATE TABLE board_connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_node INT NOT NULL,
    to_node INT NOT NULL,
    transport_type ENUM('T', 'B', 'U', 'F') NOT NULL,
    FOREIGN KEY (from_node) REFERENCES board_nodes(node_id),
    FOREIGN KEY (to_node) REFERENCES board_nodes(node_id)
);

-- Insert default board data
INSERT INTO board_nodes (node_id, x_coord, y_coord) VALUES
(1, 100, 100), (2, 150, 120), (3, 200, 100), (4, 250, 120), (5, 300, 100),
(6, 350, 120), (7, 400, 100), (8, 450, 120), (9, 500, 100), (10, 550, 120),
(11, 600, 100), (12, 650, 120), (13, 700, 100), (14, 750, 120), (15, 800, 100);

-- Insert some sample connections
INSERT INTO board_connections (from_node, to_node, transport_type) VALUES
(1, 2, 'T'), (2, 3, 'T'), (3, 4, 'T'), (4, 5, 'T'), (5, 6, 'T'),
(1, 3, 'B'), (2, 4, 'B'), (3, 5, 'B'), (4, 6, 'B'), (5, 7, 'B'),
(1, 5, 'U'), (2, 6, 'U'), (3, 7, 'U'), (4, 8, 'U'), (5, 9, 'U');

-- Create indexes for better performance
CREATE INDEX idx_games_status ON games(status);
CREATE INDEX idx_game_players_game_id ON game_players(game_id);
CREATE INDEX idx_game_moves_game_round ON game_moves(game_id, round_number);
CREATE INDEX idx_board_connections_from ON board_connections(from_node);
CREATE INDEX idx_board_connections_to ON board_connections(to_node); 