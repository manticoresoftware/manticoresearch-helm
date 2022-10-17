FROM manticoresearch/manticore:5.0.2-extra

RUN apt-get --fix-broken install -y && echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections && \
apt-get update && apt-get -y install software-properties-common && add-apt-repository -y ppa:ondrej/php && apt-get update && \
    apt-get install -y curl php7.4-cli php7.4-mysqlnd php7.4-curl php7.4-zip wget git supervisor && rm -rf /var/lib/apt/lists/*

COPY composer.json manticore.conf shutdown.sh starter.sh replica.php quorum_fixer.sh quorum.php env_reader.php /etc/manticoresearch/
COPY supervisord.conf /etc/supervisord.conf

RUN wget https://repo.manticoresearch.com/repository/morphology/en.pak.tgz https://repo.manticoresearch.com/repository/morphology/de.pak.tgz https://repo.manticoresearch.com/repository/morphology/ru.pak.tgz -P /tmp && \
tar -xvzf /tmp/en.pak.tgz -C /usr/share/manticore/ && tar -xvzf /tmp/ru.pak.tgz -C /usr/share/manticore/ && tar -xvzf /tmp/de.pak.tgz -C /usr/share/manticore/ && \
chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/ /dev/stdout

RUN cd /etc/manticoresearch/ && \
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
php -r "if (hash_file('sha384', 'composer-setup.php') === '55ce33d7678c5a611085589f1f3ddf8b3c52d662cd01d4ba75c0ee0459970c2200a51f492d557530c71c15d8dba01eae') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
php composer-setup.php && \
php -r "unlink('composer-setup.php');" && \
php composer.phar update

WORKDIR /etc/manticoresearch/

CMD ["./starter.sh"]
