FROM ulsmith/alpine-apache-php7
MAINTAINER BrightLab

WORKDIR /app
COPY . /app
EXPOSE 80

RUN composer install

ENV docker=true
ENV APPLICATION_ENV=true
ENV PHP_SHORT_OPEN_TAG=On
ENV PHP_ERROR_REPORTING=E_ALL
ENV PHP_DISPLAY_ERRORS=On
ENV PHP_HTML_ERRORS=On
ENV PHP_XDEBUG_ENABLED=true
ENV APACHE_SERVER_NAME=catalogue.docker.localhost
ENV DB_HOST=localhost
