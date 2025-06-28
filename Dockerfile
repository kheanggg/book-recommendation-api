FROM php:8.2

# Install system dependencies
RUN apt-get update -y && apt-get install -y \
    openssl zip unzip git curl libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo mbstring pdo_mysql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /app

# Copy the project files into the container
COPY . .

# Install PHP dependencies
RUN composer install

# Expose the Laravel development server port
EXPOSE 8000

# Run the Laravel dev server
CMD ["php", "artisan", "serve", "--host=0.0.0.0"]