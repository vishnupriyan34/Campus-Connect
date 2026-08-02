FROM php:8.2-apache

# Enable mysqli extension for MySQL connectivity
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy your app code into the Apache web root
COPY . /var/www/html/

# Apache listens on port 80 by default — Render expects PORT env var,
# so we tell Apache to listen on the port Render provides
ENV PORT 10000
RUN sed -i "s/80/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf

EXPOSE 10000
