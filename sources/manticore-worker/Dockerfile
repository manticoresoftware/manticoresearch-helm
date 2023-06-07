FROM manticoresearch/manticore:dev-6.0.5-844b1ae8

RUN apt-get --fix-broken install -y && echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections && \
apt-get update && apt-get -y install software-properties-common && add-apt-repository -y ppa:ondrej/php && apt-get update && \
    apt-get install -y curl php8.1-cli php8.1-mysqlnd php8.1-curl php8.1-zip wget git supervisor && rm -rf /var/lib/apt/lists/*

COPY composer.json manticore.conf shutdown.sh starter.sh replica.php quorum_fixer.sh quorum.php env_reader.php /etc/manticoresearch/
COPY supervisord.conf /etc/supervisord.conf

RUN chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/ /dev/stdout

RUN cd /etc/manticoresearch/ && \
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
php -r "if (hash_file('sha384', 'composer-setup.php') === '55ce33d7678c5a611085589f1f3ddf8b3c52d662cd01d4ba75c0ee0459970c2200a51f492d557530c71c15d8dba01eae') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
php composer-setup.php && \
php -r "unlink('composer-setup.php');" && \
php composer.phar update

WORKDIR /etc/manticoresearch/

CMD ["./starter.sh"]
