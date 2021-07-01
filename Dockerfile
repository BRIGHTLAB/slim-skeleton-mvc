FROM 684393151490.dkr.ecr.eu-west-1.amazonaws.com/apache-php74

WORKDIR /var/www/html
COPY . /var/www/html
EXPOSE 80

RUN composer install

RUN mkdir -p /var/www/html/var/log
RUN chown -R www-data:www-data /var/www
RUN find /var/www -type f -exec chmod 644 {} +
RUN find /var/www -type d -exec chmod 755 {} +
CMD ["apache2-foreground"]
