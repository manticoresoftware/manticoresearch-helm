FROM manticoresearch/manticore:dev-6.0.5-844b1ae8

RUN apt-get --fix-broken install -y && echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections && \
apt-get update && apt-get -y install software-properties-common && add-apt-repository -y ppa:ondrej/php && apt-get update && \
    apt-get install -y curl php8.1-cli php8.1-mysqlnd php8.1-curl php8.1-zip wget git supervisor && rm -rf /var/lib/apt/lists/*

COPY composer.json manticore.conf shutdown.sh starter.sh replica.php quorum_fixer.sh quorum.php env_reader.php /etc/manticoresearch/
COPY supervisord.conf /etc/supervisord.conf

RUN chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/ /dev/stdout

RUN cd /etc/manticoresearch/ && \
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
php -r "if (hash_file('sha384', 'composer-setup.php') === 'e21205b207c3ff031906575712edab6f13eb0b361f2085f1f1237b7126d785e826a450292b6cfd1d64d92e6563bbde02') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
php composer-setup.php && \
php -r "unlink('composer-setup.php');" && \
php composer.phar update

WORKDIR /etc/manticoresearch/

CMD ["./starter.sh"]
