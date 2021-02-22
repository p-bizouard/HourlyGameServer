# Dev target
FROM php:7.4-fpm AS dev

RUN apt update
RUN curl -sL https://deb.nodesource.com/setup_14.x | bash -;
RUN apt install -y nodejs libcurl4-gnutls-dev zlib1g-dev libicu-dev g++ libxml2-dev libpq-dev libonig-dev libzip-dev libpng-dev \
    software-properties-common \
    && apt-get autoremove && apt-get autoclean \
    && rm -rf /var/lib/apt/lists/*
    
RUN pecl install redis xdebug
RUN docker-php-ext-install intl json mbstring pdo  pdo_mysql zip bcmath sockets gd opcache 
RUN docker-php-ext-enable redis
RUN docker-php-ext-enable xdebug
RUN echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini;

RUN apt-get autoremove && apt-get autoclean \
 && rm -rf /var/lib/apt/lists/*

RUN php -r "readfile('https://getcomposer.org/installer');" | php -- --install-dir=/usr/local/bin --filename=composer

RUN echo "date.timezone = Europe/Paris" >> /usr/local/etc/php/conf.d/timezone.ini

ADD docker/php/symfony-entry-point.sh /
ENTRYPOINT  ["/bin/bash", "/symfony-entry-point.sh"]

RUN npm install --global yarn

# Install ansible & terraform
RUN curl -fsSL https://apt.releases.hashicorp.com/gpg | apt-key add - \
    && apt-add-repository "deb [arch=amd64] https://apt.releases.hashicorp.com $(lsb_release -cs) main" \
    && apt update && apt install -y terraform python3-pip restic wget openssh-client \
    && apt-get autoremove && apt-get autoclean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
USER 1000


# Test image
FROM dev AS test

## Copy project files to workdir
COPY front/assets assets
COPY front/bin bin
COPY front/config config
# COPY fixtures fixtures
COPY front/public public
COPY front/src src
COPY front/templates templates
COPY front/tests tests
COPY front/translations translations
COPY front/composer.json \
    front/composer.lock \
    front/package.json \
    front/yarn.lock \
    front/webpack.config.js \
    # postcss.config.js \
    ./


# Copy ansible and terraform
COPY ansible ansible
COPY terraform terraform

#  Run build with root
USER root

RUN pip3 install -r terraform/requirements.txt \
    && pip3 install -r ansible/requirements.txt

# Install application dependencies
RUN APP_ENV=prod composer install \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts \
    --no-cache

RUN yarn install \
    && yarn build \
    && rm -r node_modules

RUN eval $(ssh-agent)

# Prod image
FROM test AS prod

WORKDIR /app

# Disable xdebug on production
RUN rm /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# php-fpm is run with www-data
RUN mkdir var && chown -R www-data:www-data var/
USER www-data

CMD  ["php-fpm"]

