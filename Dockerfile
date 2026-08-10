FROM php:8.2-apache

# Enable mysqli extension for MySQL connectivity
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy your app code into the Apache web root
COPY . /var/www/html/

EXPOSE 80

# Fix: on Railway, php:8.2-apache can end up with more than one MPM
# (multi-processing module) active at container start, crashing Apache with:
# "AH00534: apache2: Configuration error: More than one MPM loaded."
# This must be corrected at container START (not at build time) — Railway's
# runtime re-applies enabled modules after the image is built.
# We also bind Apache to whatever $PORT Railway assigns at runtime.
CMD ["bash", "-lc", "\
    set -eux; \
    a2dismod mpm_event mpm_worker || true; \
    rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* || true; \
    a2enmod mpm_prefork; \
    sed -i \"s/80/${PORT:-80}/g\" /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf; \
    apache2ctl -t; \
    exec apache2-foreground \
"]
