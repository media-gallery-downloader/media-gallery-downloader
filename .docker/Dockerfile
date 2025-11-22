FROM dunglas/frankenphp:php8.4-bookworm

ARG UID=1000
ARG GID=1000
ARG TZ=America/Chicago

ENV DEBIAN_FRONTEND=noninteractive \
    TERM=xterm-color \
    LOG_CHANNEL=stderr \
    TZ=${TZ}

WORKDIR /app

SHELL ["/bin/bash", "-euo", "pipefail", "-c"]

# Install system dependencies in a single layer
RUN apt-get update && apt-get install -yqq --no-install-recommends --show-progress \
    apt-utils \
    gnupg \
    curl \
    wget \
    vim \
    git \
    procps \
    unzip \
    zip \
    rsync \
    sqlite3 \
    libsqlite3-dev \
    ca-certificates \
    sudo \
    python3 \
    python3-pip \
    ffmpeg \
    atomicparsley \
    imagemagick \
    libmagickwand-dev \
    libmagickcore-dev \
    libmagick++-dev \
    ghostscript \
    libgs-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    libtiff-dev \
    librsvg2-dev \
    libheif-dev \
    libavif-dev \
    tzdata \
    supervisor \
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
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

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

# Copy external tools in one layer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY --from=oven/bun:latest /usr/local/bin/bun /usr/bin/bun

# Create app user before copying files
RUN groupadd -g ${GID} app && \
    useradd -u ${UID} -g app -m app && \
    usermod -aG sudo app && \
    echo "app ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers

# Copy supervisor configuration to the main config location
COPY .docker/supervisord.conf /etc/supervisor/supervisord.conf

# Create required directories with proper permissions
RUN mkdir -p \
    storage/framework/{sessions,views,cache,testing} \
    storage/logs \
    bootstrap/cache \
    database \
    public/build \
    /home/app/.composer/cache \
    && chown -R ${UID}:${GID} storage bootstrap/cache database public /home/app/.composer \
    && chmod -R 775 storage bootstrap/cache database public/build

# Copy application files
COPY --chown=app:app . .

# Copy utilities and make start script executable
RUN cat .docker/utilities.sh >> /home/app/.bashrc \
    && chmod +x .docker/start.sh

USER app

# Add utilities to user bashrc
RUN cat .docker/utilities.sh >> ~/.bashrc

ENTRYPOINT [".docker/start.sh"]
