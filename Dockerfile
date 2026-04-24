FROM php:8.2-apache

# Enable curl (used for Supabase calls)
RUN docker-php-ext-install curl || true

# Copy your app into Apache's web root
COPY . /var/www/html/

# Make sure Apache can read the files
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80