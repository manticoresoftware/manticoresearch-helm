FROM manticoresearch/manticore:29.9.0

RUN echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections && apt --fix-broken install -y && \
apt-get update && apt -y install software-properties-common && add-apt-repository -y ppa:ondrej/php && apt-get update && \
    apt-get install -y nano curl unzip git php8.5-cli php8.5-mysql php8.5-curl php8.5-xml php8.5-mbstring \
    supervisor && rm -rf /var/lib/apt/lists/* && mkdir /var/www && mkdir /var/www/localhost

# Both CVE-2026-109618 and CVE-2026-109619 were fixed in PHP 8.5.12.
RUN update-alternatives --set php /usr/bin/php8.5 && \
    php -r 'if (PHP_VERSION_ID < 80512 || PHP_VERSION_ID >= 80600) { exit(1); }'

COPY composer.json observer.php optimize.php starter.sh observer_runner.sh optimize_runner.sh /var/www/localhost/
COPY supervisord.conf /etc/supervisord.conf
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN cd /var/www/localhost && composer update && \
chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/ /var/log/supervisor /dev/stdout && \
chown -R www-data /var/www/localhost/ /etc/manticoresearch/manticore.conf /dev/stdout

WORKDIR /var/www/localhost/

CMD ["./starter.sh"]

