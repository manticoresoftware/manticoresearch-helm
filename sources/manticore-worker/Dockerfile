FROM manticoresearch/manticore:6.3.0

RUN apt-get --fix-broken install -y && echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections && \
apt-get update && apt-get -y install software-properties-common && add-apt-repository -y ppa:ondrej/php && apt-get update && \
    apt-get install -y curl php8.1-cli php8.1-mysqlnd php8.1-curl php8.1-zip php8.1-dom php8.1-mbstring wget git \
    supervisor dnsutils && rm -rf /var/lib/apt/lists/*

COPY composer.json manticore.conf shutdown.sh starter.sh replica.php quorum_fixer.sh quorum.php env_reader.php /etc/manticoresearch/
COPY supervisord.conf /etc/supervisord.conf
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/ /dev/stdout

RUN cd /etc/manticoresearch/ && composer update

WORKDIR /etc/manticoresearch/

CMD ["./starter.sh"]
