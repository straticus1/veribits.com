FROM php:8.2-apache
WORKDIR /var/www/html
COPY app/public/ /var/www/html/
COPY app/src/ /var/www/src/
COPY app/config/.env.example /var/www/.env
RUN a2enmod rewrite
EXPOSE 80
