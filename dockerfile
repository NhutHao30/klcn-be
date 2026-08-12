# Đổi từ php8.2 sang php8.4 để trùng khớp với máy tính của bạn
FROM dunglas/frankenphp:latest-php8.4-alpine

# Cài đặt các extension PHP cần thiết cho Laravel (MySQL, Redis, GD...)
RUN install-php-extensions \
    pdo_mysql \
    gd \
    intl \
    zip \
    opcache \
    redis \
    amqp

# Cài đặt công cụ Composer vào trong container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Thiết lập thư mục làm việc bên trong container
WORKDIR /app

# Copy toàn bộ mã nguồn của bạn vào container
COPY . .

# Cài đặt các thư viện PHP qua bộ cài Composer (Bỏ qua scripts để tránh lỗi kết nối DB lúc build)
RUN composer install --no-interaction --optimize-autoloader --no-scripts

# Cấp quyền ghi dữ liệu cho các thư mục cache của Laravel
RUN chown -R www-data:www-data storage bootstrap/cache

# Chạy Laravel Octane bằng FrankenPHP khi container khởi động
CMD ["php", "artisan", "octane:start", "--host=0.0.0.0", "--port=8000", "--workers=2", "--max-requests=250"]
