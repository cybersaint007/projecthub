FROM trafex/php-nginx:latest

# Switch to root to install packages
USER root

# Install PostgreSQL PHP extensions, composer, and Node.js
RUN apk add --no-cache \
    php84-pdo_pgsql \
    php84-pgsql \
    php84-tokenizer \
    php84-fileinfo \
    php84-dom \
    php84-xmlwriter \
    php84-xmlreader \
    composer \
    nodejs \
    npm

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY --chown=nobody:nobody . .

# Copy nginx config
COPY projecthub-nginx.conf /etc/nginx/conf.d/default.conf

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Build frontend assets
RUN npm install && npm run build && rm -rf node_modules

# Create storage structure, storage symlink, and set permissions
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions \
    storage/framework/views storage/app/public bootstrap/cache \
    && php artisan storage:link \
    && chown -R nobody:nobody storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Cache config, routes, and views for production
RUN php artisan optimize

# Switch back to non-root user
USER nobody

EXPOSE 8080
