FROM php:8.1-apache

# Cài extension mysqli
RUN docker-php-ext-install mysqli

# Copy toàn bộ mã nguồn
COPY . /var/www/html/

# Mở cổng 80
EXPOSE 80
