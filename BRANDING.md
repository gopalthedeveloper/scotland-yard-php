# Scotland Yard Game - Branding Assets

This directory contains the branding assets for the Scotland Yard Detective Game.

## Files Included

### 1. `favicon.svg` (32x32)
- **Purpose**: Browser favicon and small icon usage
- **Features**: 
  - Detective hat silhouette
  - Magnifying glass (detective theme)
  - Police badge with "D" for Detective
  - Dark blue gradient background
  - Optimized for small sizes

### 2. `logo.svg` (400x120)
- **Purpose**: Full logo with text for headers and branding
- **Features**:
  - Complete Scotland Yard branding
  - "Scotland Yard Detective Game" text
  - London skyline silhouette
  - Police badge elements
  - Professional gradient design

### 3. `logo-component.php`
- **Purpose**: Reusable PHP component for displaying the logo
- **Usage**: Include this file in any page to display the logo
- **Features**:
  - Responsive design
  - Hover effects
  - Drop shadow styling
  - Optimized for web display

### 4. `favicon.ico` (Placeholder)
- **Note**: This is a placeholder file. For production use:
  1. Convert `favicon.svg` to ICO format using online tools
  2. Or use the SVG directly (supported by modern browsers)

## Usage Instructions

### Adding Favicon to Pages
```html
<!-- Add to <head> section -->
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="alternate icon" type="image/x-icon" href="favicon.ico">
```

### Displaying Logo in Pages
```php
<!-- Include the logo component -->
<?php include 'logo-component.php'; ?>
```

### Custom Styling
The logo component includes CSS for:
- Hover effects (scale on hover)
- Drop shadows
- Smooth transitions
- Responsive sizing

## Design Elements

### Color Scheme
- **Primary**: Dark Blue (#2c3e50, #34495e)
- **Accent**: Orange (#f39c12, #e67e22)
- **Text**: Light Gray (#ecf0f1, #bdc3c7)
- **Badge**: Red (#e74c3c)

### Theme Elements
- **Magnifying Glass**: Represents detective work and investigation
- **Detective Hat**: Classic detective imagery
- **Police Badge**: Authority and law enforcement
- **London Skyline**: Geographic context (Scotland Yard is in London)

## Browser Compatibility

- **SVG Favicon**: Supported in all modern browsers
- **Fallback ICO**: Recommended for older browsers
- **Logo Component**: Works in all browsers with CSS3 support

## Customization

To customize the branding:
1. Edit the SVG files directly
2. Modify colors in the gradient definitions
3. Adjust text content in the logo files
4. Update the component styling in `logo-component.php`

## Production Notes

For production deployment:
1. Convert SVG favicon to ICO format
2. Optimize SVG files for web (remove unnecessary elements)
3. Consider creating multiple favicon sizes (16x16, 32x32, 48x48)
4. Test across different browsers and devices 