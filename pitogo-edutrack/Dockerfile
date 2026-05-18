FROM php:8.2-cli

RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /app

COPY . /app

EXPOSE 8080

CMD php -S 0.0.0.0:$PORT -t /app/pitogo-edutrack