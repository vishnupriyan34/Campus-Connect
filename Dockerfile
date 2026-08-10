FROM php:8.2-apache

# Enable mysqli extension for MySQL connectivity
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy your app code into the Apache web root
COPY . /var/www/html/

# Fix: php:8.2-apache sometimes ships with more than one MPM (multi-processing
# module) enabled, which crashes Apache on boot with:
# "AH00534: apache2: Configuration error: More than one MPM loaded."
# Force only mpm_prefork to be active.
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* \
    && a2enmod mpm_prefork

EXPOSE 80

# Fix: bind Apache to whatever $PORT Railway assigns at runtime (not baked
# in at build time), falling back to 80 if none is set.
CMD bash -c "sed -i \"s/80/\${PORT:-80}/g\" /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf && apache2-foreground"
