FROM dunglas/frankenphp:php8.4-bookworm

ARG TZ=America/Chicago
ARG UID=1000
ARG GID=1000

ENV DEBIAN_FRONTEND=noninteractive \
    TERM=xterm-color

WORKDIR /app

SHELL ["/bin/bash", "-eou", "pipefail", "-c"]

RUN ln -snf /usr/share/zoneinfo/${TZ} /etc/localtime \
    && echo ${TZ} > /etc/timezone

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
    igbinary

RUN apt-get -y autoremove \
    && apt-get clean

RUN cp ${PHP_INI_DIR}/php.ini-production ${PHP_INI_DIR}/php.ini

RUN echo "upload_max_filesize = 5G" > ${PHP_INI_DIR}/conf.d/upload-limits.ini && \
    echo "post_max_size = 5G" >> ${PHP_INI_DIR}/conf.d/upload-limits.ini && \
    echo "memory_limit = 2G" >> ${PHP_INI_DIR}/conf.d/upload-limits.ini && \
    echo "max_execution_time = 300" >> ${PHP_INI_DIR}/conf.d/upload-limits.ini && \
    echo "max_input_time = 300" >> ${PHP_INI_DIR}/conf.d/upload-limits.ini

COPY --link --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY --link --from=oven/bun:latest /usr/local/bin/bun /usr/bin/bun

COPY . .

RUN mkdir -p \
    storage/framework/{sessions,views,cache,testing} \
    storage/logs \
    bootstrap/cache && chmod -R a+rw storage

RUN cat ./utilities.sh >> ~/.bashrc

RUN groupadd -g ${GID} app \
    && useradd -u ${UID} -g app -m app \
    && usermod -aG sudo app \
    && echo "app ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers

USER app

RUN cat ./utilities.sh >> ~/.bashrc

ENTRYPOINT [ ".docker/start.sh" ]
