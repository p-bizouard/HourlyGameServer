# Dev target
FROM php:8-fpm AS dev

RUN apt update
RUN curl -sL https://deb.nodesource.com/setup_14.x | bash -;
RUN apt install -y libcurl4-gnutls-dev zlib1g-dev libicu-dev g++ libxml2-dev libpq-dev libonig-dev libzip-dev libpng-dev \
    software-properties-common \
    vim nodejs git unzip procps \
    && apt-get autoremove && apt-get autoclean \
    && rm -rf /var/lib/apt/lists/*
    
RUN pecl install redis xdebug
RUN docker-php-ext-install intl
RUN docker-php-ext-install mbstring
RUN docker-php-ext-install pdo
RUN docker-php-ext-install  pdo_mysql
RUN docker-php-ext-install zip
RUN docker-php-ext-install bcmath
RUN docker-php-ext-install sockets
RUN docker-php-ext-install gd
RUN docker-php-ext-install opcache
 
RUN docker-php-ext-enable redis
RUN docker-php-ext-enable xdebug
RUN echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini;

RUN php -r "readfile('https://getcomposer.org/installer');" | php -- --install-dir=/usr/local/bin --filename=composer

RUN echo "date.timezone = Europe/Paris" >> /usr/local/etc/php/conf.d/timezone.ini

RUN npm install --global yarn gamedig

# Install ansible & terraform=1.2.9 because the swift backend is not supported in terraform ^1.3
RUN curl -fsSL https://apt.releases.hashicorp.com/gpg | apt-key add - \
    && apt-add-repository "deb [arch=amd64] https://apt.releases.hashicorp.com $(lsb_release -cs) main" \
    && apt update && apt install -y terraform=1.2.9 python3-pip restic wget openssh-client \
    && apt-get autoremove && apt-get autoclean \
    && rm -rf /var/lib/apt/lists/*

# Copy ansible and terraform requirements
COPY terraform/requirements.txt terraform-requirement.txt
COPY ansible/requirements.txt ansible-requirement.txt

RUN pip3 install -r terraform-requirement.txt \
    && pip3 install -r ansible-requirement.txt \
    && rm terraform-requirement.txt ansible-requirement.txt

RUN wget https://get.symfony.com/cli/installer -O - | bash && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

RUN mkdir /var/www/.ssh && chown www-data:www-data /var/www/.ssh && \
    mkdir /var/www/.ansible && chown www-data:www-data /var/www/.ansible

WORKDIR /app

ADD docker/php/symfony-entry-point.sh /
ENTRYPOINT  ["/bin/bash", "/symfony-entry-point.sh"]

# Test image
FROM dev AS test

COPY front/composer.json \
    front/composer.lock \
    ./

# Install php dependencies
RUN APP_ENV=prod composer install \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts \
    --no-cache


# Install node dependencies and build assets
COPY front/assets assets
COPY front/public public
COPY front/package.json \
    front/yarn.lock \
    front/webpack.config.js \
    ./

RUN yarn install \
    && yarn build \
    && rm -r node_modules

## Copy project files to workdir
COPY front/bin bin
COPY front/config config
COPY front/fixtures fixtures
COPY front/src src
COPY front/migrations migrations
COPY front/templates templates
COPY front/tests tests
COPY front/translations translations

# Copy ansible and terraform
COPY ansible ansible
COPY terraform terraform

# Prod image
FROM test AS prod

# Disable xdebug on production
RUN rm /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# php-fpm is run with www-data
RUN mkdir var && chown -R www-data:www-data var/
USER www-data

CMD  ["php-fpm"]

