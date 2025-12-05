# =============================================================================
# Stage 1: Build frontend assets
# =============================================================================
FROM denoland/deno:2.1.4 AS frontend-builder

WORKDIR /app

# Copy package files first for better caching
COPY deno.json deno.lock* ./
COPY vite.config.js tailwind.config.js postcss.config.js ./

# Install dependencies
RUN deno install

# Copy frontend source files
COPY resources/ resources/

# Build assets
RUN deno task build

# =============================================================================
# Stage 2: Install PHP dependencies
# =============================================================================
FROM composer:2.8 AS composer-builder

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies (no dev for production)
# Ignore platform reqs since ext-intl/pcntl are in the production image, not composer image
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

# Copy app source for autoloader optimization
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative

# =============================================================================
# Stage 3: Production image
# =============================================================================
FROM dunglas/frankenphp:php8.4-bookworm AS production

ARG UID=1000
ARG GID=1000
ARG TZ=America/Chicago

ENV DEBIAN_FRONTEND=noninteractive \
    TERM=xterm-color \
    LOG_CHANNEL=stderr \
    TZ=${TZ} \
    APP_ENV=production \
    PATH="/home/app/bin:${PATH}"

WORKDIR /app

SHELL ["/bin/bash", "-euo", "pipefail", "-c"]

# Install system dependencies in a single layer
# Note: -dev packages are only needed for PHP extension compilation and are cleaned up
RUN apt-get update && apt-get install -yqq --no-install-recommends --show-progress \
    # Required runtime dependencies
    curl \
    procps \
    unzip \
    sqlite3 \
    ca-certificates \
    python3 \
    ffmpeg \
    atomicparsley \
    imagemagick \
    ghostscript \
    tzdata \
    supervisor \
    p7zip-full \
    unar \
    # Temporary: dev libraries for PHP extension compilation
    libsqlite3-dev \
    libmagickwand-dev \
    libmagickcore-dev \
    libmagick++-dev \
    libgs-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    libtiff-dev \
    librsvg2-dev \
    libheif-dev \
    libavif-dev \
    && install-php-extensions \
    bz2 \
    exif \
    pcntl \
    mbstring \
    bcmath \
    opcache \
    zip \
    intl \
    gd \
    igbinary \
    imagick \
    redis \
    # Remove dev libraries after extensions are compiled (saves ~200MB)
    && apt-get purge -y --auto-remove \
    libsqlite3-dev \
    libmagickwand-dev \
    libmagickcore-dev \
    libmagick++-dev \
    libgs-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    libtiff-dev \
    librsvg2-dev \
    libheif-dev \
    libavif-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Set system timezone from build arg
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Configure ImageMagick security policy
RUN sed -i 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read|write" pattern="PDF" \/>/g' /etc/ImageMagick-6/policy.xml && \
    sed -i 's/<policy domain="coder" rights="none" pattern="EPS" \/>/<policy domain="coder" rights="read|write" pattern="EPS" \/>/g' /etc/ImageMagick-6/policy.xml && \
    sed -i 's/<policy domain="coder" rights="none" pattern="PS" \/>/<policy domain="coder" rights="read|write" pattern="PS" \/>/g' /etc/ImageMagick-6/policy.xml && \
    sed -i 's/<policy domain="coder" rights="none" pattern="XPS" \/>/<policy domain="coder" rights="read|write" pattern="XPS" \/>/g' /etc/ImageMagick-6/policy.xml && \
    sed -i 's/<policy domain="resource" name="memory" value="256MiB"\/>/<policy domain="resource" name="memory" value="1GiB"\/>/g' /etc/ImageMagick-6/policy.xml && \
    sed -i 's/<policy domain="resource" name="map" value="512MiB"\/>/<policy domain="resource" name="map" value="2GiB"\/>/g' /etc/ImageMagick-6/policy.xml && \
    sed -i 's/<policy domain="resource" name="disk" value="1GiB"\/>/<policy domain="resource" name="disk" value="8GiB"\/>/g' /etc/ImageMagick-6/policy.xml && \
    sed -i 's/<policy domain="resource" name="area" value="128MB"\/>/<policy domain="resource" name="area" value="1GB"\/>/g' /etc/ImageMagick-6/policy.xml

# Install yt-dlp
RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp && \
    chmod a+rx /usr/local/bin/yt-dlp && \
    ln -sf /usr/local/bin/yt-dlp /usr/bin/youtube-dl

# Copy and configure PHP
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY .docker/php.ini ${PHP_INI_DIR}/conf.d/custom.ini

# Create app user before copying files
RUN groupadd -g ${GID} app && \
    useradd -u ${UID} -g app -m app

# Copy supervisor configuration
COPY .docker/supervisord.conf /etc/supervisor/supervisord.conf

# Create required directories with proper permissions
RUN mkdir -p \
    storage/framework/{sessions,views,cache,testing} \
    storage/logs \
    storage/caddy \
    bootstrap/cache \
    database \
    public/build \
    /home/app/.composer/cache \
    && chown -R ${UID}:${GID} storage bootstrap/cache database public /home/app/.composer \
    && chmod -R 775 storage bootstrap/cache database public/build

# Copy vendor from composer builder
COPY --from=composer-builder --chown=app:app /app/vendor ./vendor

# Copy built frontend assets from frontend builder
COPY --from=frontend-builder --chown=app:app /app/public/build ./public/build

# Copy application files
COPY --chown=app:app . .

# Make start script executable
RUN chmod +x .docker/start.sh

USER app

# Add utilities to user bashrc
RUN cat .docker/utilities.sh >> ~/.bashrc

ENTRYPOINT [".docker/start.sh"]

# =============================================================================
# Stage 4: Development image (extends production)
# =============================================================================
FROM production AS development

USER root

# Install development tools and PCOV for code coverage
RUN apt-get update && apt-get install -yqq --no-install-recommends \
    vim \
    git \
    wget \
    && install-php-extensions pcov \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Add composer and deno for development
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY --from=denoland/deno:bin-2.1.4 /deno /usr/bin/deno

ENV APP_ENV=local

USER app

ENTRYPOINT [".docker/start.sh"]
