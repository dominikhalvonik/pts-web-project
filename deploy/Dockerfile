FROM php:8.4-apache
RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers setenvif \
    && sed -ri 's!/var/www/html!/var/www/testlab/public!g' /etc/apache2/sites-available/000-default.conf
COPY deploy/apache-directory.conf /etc/apache2/conf-available/testlab.conf
RUN a2enconf testlab
WORKDIR /var/www/testlab
COPY app ./app
COPY public ./public
COPY database ./database
COPY bin ./bin
COPY tests ./tests
# No private configuration or secrets are copied; Compose supplies environment variables.
