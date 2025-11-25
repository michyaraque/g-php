FROM php:8.4-cli

# Install sockets extension
RUN docker-php-ext-install sockets

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Default command (can be overridden)
CMD ["php", "examples/extension-test.php"]
