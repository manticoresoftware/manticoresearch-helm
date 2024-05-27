FROM manticoresearch/manticore:6.3.0

RUN echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections && apt --fix-broken install -y && \
apt-get update && apt -y install software-properties-common && add-apt-repository -y ppa:ondrej/php && apt-get update && \
    apt-get install -y nano curl unzip git php8.1-cli php8.1-mysqlnd php8.1-curl php8.1-dom php8.1-mbstring \
    supervisor && rm -rf /var/lib/apt/lists/* && mkdir /var/www && mkdir /var/www/localhost

COPY composer.json observer.php optimize.php starter.sh observer_runner.sh optimize_runner.sh /var/www/localhost/
COPY supervisord.conf /etc/supervisord.conf
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN cd /var/www/localhost && composer update && \
chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/ /var/log/supervisor /dev/stdout && \
chown -R www-data /var/www/localhost/ /etc/manticoresearch/manticore.conf /dev/stdout

WORKDIR /var/www/localhost/

CMD ["./starter.sh"]

