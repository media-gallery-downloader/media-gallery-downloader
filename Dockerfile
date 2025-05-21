FROM dunglas/frankenphp:php8-bookworm

ARG TZ=UTC

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    DEBIAN_FRONTEND=noninteractive \
    TERM=xterm-color

WORKDIR /app

SHELL ["/bin/bash", "-eou", "pipefail", "-c"]

RUN ln -snf /usr/share/zoneinfo/${TZ} /etc/localtime \
    && echo ${TZ} > /etc/timezone

RUN apt-get update && apt-get upgrade -yqq

RUN apt-get install -yqq --no-install-recommends --show-progress \
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
    ca-certificates \
    && install-php-extensions \
    bz2 \
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

#RUN cp ${PHP_INI_DIR}/php.ini-production ${PHP_INI_DIR}/php.ini

COPY --link --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY --link --from=oven/bun:latest /usr/local/bin/bun /usr/bin/bun

COPY . .

RUN cp .env.example .env

RUN mkdir -p \
    storage/framework/{sessions,views,cache,testing} \
    storage/logs \
    bootstrap/cache && chmod -R a+rw storage

RUN cat ./utilities.sh >> ~/.bashrc

ENTRYPOINT [ "./start.sh" ]
