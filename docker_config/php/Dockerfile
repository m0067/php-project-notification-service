FROM php:8.4-fpm

COPY composer-setup.sh /composer-setup.sh
RUN chmod +x /composer-setup.sh
RUN /composer-setup.sh
RUN rm /composer-setup.sh
RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer
RUN chown 1000:1000 /usr/local/bin/composer
RUN rm composer-setup.php

RUN apt-get update \
    && apt-get install -y libzip-dev libpq-dev git zip unzip

RUN apt-get update --fix-missing
RUN apt-get install -y build-essential libssl-dev zlib1g-dev libpng-dev libjpeg-dev libfreetype6-dev

RUN docker-php-ext-install bcmath calendar exif gettext pgsql pcntl gd pdo_pgsql shmop sockets sysvmsg sysvsem sysvshm zip

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions uuid gmp soap intl amqp redis

RUN groupadd dev -g 1000
RUN useradd dev -g dev -d /home/dev -m

RUN mkdir /application
RUN chown -R dev:dev /application

