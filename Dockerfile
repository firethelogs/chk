# Use an official PHP Apache image
FROM php:8.2-apache

# Copy all files to the Apache document root
COPY . /var/www/html/

# Give appropriate permissions
RUN chown -R www-data:www-data /var/www/html

# Enable Apache mod_rewrite if needed
RUN a2enmod rewrite
