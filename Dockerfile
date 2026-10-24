FROM php:8.2-apache

# Install MySQL extensions for PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure Apache port to 10000 for Render
ENV PORT=10000
RUN sed -i "s/80/\${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Copy source code into web root
COPY . /var/www/html/

# Expose Render port
EXPOSE 10000
