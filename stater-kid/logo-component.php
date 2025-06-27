<?php
/**
 * Logo Component for Scotland Yard Game
 * Include this file to display the logo
 */
?>
<div class="logo-container text-center mb-4">
    <div class="d-inline-block">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 60" width="200" height="60" class="game-logo">
            <defs>
                <linearGradient id="logoBg" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#2c3e50;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#34495e;stop-opacity:1" />
                </linearGradient>
                <linearGradient id="logoGlass" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#ecf0f1;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#bdc3c7;stop-opacity:1" />
                </linearGradient>
                <linearGradient id="logoHandle" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#8b4513;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#654321;stop-opacity:1" />
                </linearGradient>
                <linearGradient id="logoText" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:#ecf0f1;stop-opacity:1" />
                    <stop offset="50%" style="stop-color:#f39c12;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#e67e22;stop-opacity:1" />
                </linearGradient>
            </defs>
            
            <!-- Background -->
            <rect x="2" y="2" width="196" height="56" rx="8" ry="8" fill="url(#logoBg)" stroke="#1a252f" stroke-width="1"/>
            
            <!-- Magnifying glass -->
            <circle cx="25" cy="30" r="12" fill="url(#logoGlass)" stroke="#95a5a6" stroke-width="0.5"/>
            <path d="M 37 42 L 45 50 L 48 47 L 40 39 Z" fill="url(#logoHandle)"/>
            
            <!-- Detective hat -->
            <ellipse cx="25" cy="20" rx="8" ry="3" fill="#2c3e50"/>
            <path d="M 17 20 Q 25 12 33 20 L 31 20 Q 25 15 19 20 Z" fill="#2c3e50"/>
            
            <!-- Detective badge -->
            <circle cx="40" cy="20" r="4" fill="#e74c3c" stroke="#c0392b" stroke-width="0.3"/>
            <text x="40" y="22" text-anchor="middle" font-family="Arial, sans-serif" font-size="4" fill="white" font-weight="bold">D</text>
            
            <!-- Text -->
            <text x="60" y="25" font-family="Georgia, serif" font-size="14" font-weight="bold" fill="url(#logoText)">
                Scotland Yard
            </text>
            <text x="60" y="40" font-family="Arial, sans-serif" font-size="8" fill="#bdc3c7">
                Detective Game
            </text>
        </svg>
    </div>
</div>

<style>
.game-logo {
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
    transition: transform 0.3s ease;
}

.game-logo:hover {
    transform: scale(1.05);
}

.logo-container {
    padding: 10px;
}
</style> 