# CampaignBridge

A WordPress plugin for managing email campaigns and post type configurations.

## Features

### Post Type Management
- **Post Type Configuration**: Configure which post types are available for campaigns
- **Settings Management**: Manage plugin settings and provider configurations
- **REST API Integration**: Modern WordPress REST API for all operations

## Installation

1. Upload the plugin to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to CampaignBridge > Post Types in your admin menu

## Usage

### Managing Post Types
1. Go to CampaignBridge > Post Types
2. Select which post types should be available for campaigns
3. Save your configuration

### Plugin Settings
1. Go to CampaignBridge > Settings
2. Configure your email provider settings
3. Save your configuration

## Technical Details

- **WordPress Version**: Requires WordPress 5.0+
- **PHP Version**: PHP 7.4+
- **JavaScript**: Modern ES6+ with WordPress admin integration
- **Styling**: Custom CSS with WordPress admin theme compatibility
- **REST API**: Full REST API support for post type operations

## Development

### Building Assets
```bash
# Install dependencies
pnpm install

# Build all assets
pnpm build

# Build specific components
pnpm build:blocks
pnpm build:assets
pnpm build:interactivity

# Development mode
pnpm start
```

### File Structure
```
src/
├── scripts/
│   ├── core/                  # Core services and utilities
│   ├── managers/              # Feature managers
│   └── services/              # Business logic services
└── styles/                    # CSS and styling
```

## Support

For issues and feature requests, please check the plugin's GitHub repository or contact the development team.
