FROM php:7.1-apache

WORKDIR /var/www

RUN apt-get update \
    && apt-get install -y gnupg \
    && curl -sL https://deb.nodesource.com/setup_8.x | bash - \
    && apt-get install -y --no-install-recommends \
      libicu-dev \
      libpq-dev \
      ca-certificates \
      ssl-cert \
      libcurl4-gnutls-dev \
      git \
      zlib1g-dev \
      unzip \
      nodejs \
    && update-ca-certificates \
    && docker-php-ext-install \
      pdo_mysql \
      opcache \
      curl \
      zip \
    && apt-get autoremove \
    && rm -r /var/lib/apt/lists/*

RUN a2enmod rewrite expires deflate ssl cgi alias env headers
