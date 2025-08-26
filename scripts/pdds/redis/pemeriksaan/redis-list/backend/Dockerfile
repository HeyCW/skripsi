FROM php:8.2-apache

WORKDIR /var/www/html

# Copy source code
COPY ./src/ /var/www/html/

# Install ekstensi DB driver
RUN docker-php-ext-install mysqli pdo pdo_mysql

EXPOSE 80
