# Media Gallery Downloader

A powerful web application built with Laravel and FrankenPHP for downloading and managing media content from various online sources. This application provides a modern interface for downloading videos, images, and other media files while organizing them in a gallery format.

## ⚠️ AI Assistance Disclaimer

This project was developed with assistance from artificial intelligence tools. AI was used to help generate:

- Code snippets and implementation patterns
- Documentation and README content
- Configuration files and Docker setup
- Some project assets and structure

While AI assistance was utilized, all code has been reviewed, tested, and customized for this specific application. Users should review and understand the code before deployment in production environments.

## Features

- 🎥 **Multi-source Media Downloading**: Download content from various online platforms
- 🖼️ **Gallery Management**: Organize downloaded media in an intuitive gallery interface
- ⚡ **High Performance**: Built on FrankenPHP for optimal performance
- 🔒 **Secure**: Containerized deployment with proper security headers
- 📱 **Responsive Design**: Works seamlessly on desktop and mobile devices
- 🚀 **Modern Stack**: Laravel 11, PHP 8.4, Vite, and modern frontend tooling
- 📊 **Background Processing**: Queue-based processing for downloads
- 🗄️ **SQLite Database**: Lightweight, file-based database for easy deployment

## Technology Stack

- **Backend**: Laravel 11, PHP 8.4, FrankenPHP
- **Frontend**: Vite, Bun, modern JavaScript/CSS
- **Database**: SQLite
- **Web Server**: Caddy with HTTP/3 support
- **Container**: Docker with multi-stage builds
- **Process Management**: Supervisor for background services

## Prerequisites

- Docker and Docker Compose
- Git
- Linux/macOS/Windows with WSL2

## Quick Start

### 1. Clone the Repository

```bash
git clone <repository-url>
cd media-gallery-downloader
```

### 2. Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Set your user ID for proper permissions (Linux/macOS)
export UID=$(id -u)
export GID=$(id -g)
```

### 3. Start the Application

```bash
# Build and start the container
docker-compose up --build

# Or run in background
docker-compose up -d --build
```

### 4. Access the Application

- **HTTPS**: <https://mgd.localhost> (with self-signed certificate)

**⚠️ Certificate Warning**: When first accessing the application, your browser will display a security warning about the self-signed certificate. This is normal for local development. To proceed:

- **Chrome/Edge**: Click "Advanced" → "Proceed to mgd.localhost (unsafe)"
- **Firefox**: Click "Advanced" → "Accept the Risk and Continue"
- **Safari**: Click "Show Details" → "visit this website" → "Visit Website"

**Default Login Credentials**:

- **Email**: <admin@admin.com>
- **Password**: admin

The application will automatically:

- Install PHP dependencies
- Set up the database
- Run migrations and seeders
- Build frontend assets
- Start background services

## Configuration

### Application Settings

Key Laravel configuration options in `.env`:

```env
APP_NAME="Media Gallery Downloader"
APP_ENV=local
APP_DEBUG=true
APP_URL=https://mgd.localhost

DB_CONNECTION=sqlite
DB_DATABASE=/app/database/database.sqlite

# Queue configuration for background processing
QUEUE_CONNECTION=database
```

## Usage

### Basic Operation

1. **Access the Web Interface**: Navigate to <https://mgd.localhost>
2. **Add Media Sources**: Use the interface to add URLs for media download
3. **Monitor Downloads**: Track download progress in the dashboard
4. **Browse Gallery**: View and manage downloaded media in the gallery

## Development

### Local Development Setup

For development with live reloading:

```bash
# Start the stack
docker-compose up -d

# Watch for frontend changes (in container)
docker-compose exec mgd_app bun run dev

# Or run outside container if you have Node.js/Bun installed
bun run dev
```

### File Structure

```bash
media-gallery-downloader/
├── .docker/
│   ├── Dockerfile              # Container definition
│   ├── Caddyfile              # Web server configuration
│   ├── start.sh               # Container startup script
│   └── supervisord.conf       # Process management
├── app/                       # Laravel application code
├── resources/                 # Frontend assets and views
├── database/                  # Database migrations and seeds
├── public/                    # Web-accessible files
├── storage/                   # File storage and logs
└── docker-compose.yml         # Container orchestration
```

### Performance Optimization

- **Memory**: Adjust the memory limits in `docker-compose.yml` based on your system
- **CPU**: The application uses all available CPU cores by default
- **Storage**: Use SSD storage for better I/O performance
- **Network**: Enable HTTP/3 for improved connection performance

## Security Considerations

- The application runs as a non-root user inside the container
- Self-signed certificates are used for HTTPS in development
- Security headers are automatically applied via Caddy
- File permissions are properly managed through Docker user mapping

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## Support

For issues and questions:

- Check the troubleshooting section above
- Review container logs: `docker-compose logs mgd_app`
- Open an issue in the repository

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Legal Notice

This application automatically downloads and utilizes the following third-party software at runtime:

- **yt-dlp**: Licensed under The Unlicense (Public Domain). Automatically downloaded from GitHub releases for media downloading functionality.
- **FFmpeg**: Licensed under LGPL v2.1+ (or GPL v2.1+ depending on build configuration). Installed via system package manager during container build.

**Important**: These tools are not distributed with this application but are automatically obtained during installation/runtime. Users are responsible for:

1. Ensuring compliance with the licensing terms of downloaded components
2. Verifying that their use complies with the terms of service of any websites or platforms from which they download content
3. Compliance with applicable copyright and intellectual property laws in their jurisdiction
4. Understanding that some FFmpeg codecs may have patent restrictions

By using this software, you acknowledge that you are responsible for the legal implications of downloading and using media content.
