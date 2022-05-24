FROM manticoresearch/manticore:5.0.0

RUN echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections && apt --fix-broken install -y && \
apt-get update && apt-get install -y nano curl unzip git php-cli php-mysqlnd php-curl supervisor && rm -rf /var/lib/apt/lists/* && \
mkdir /var/www && mkdir /var/www/localhost

COPY code starter.sh job_runner.sh optimize_runner.sh /var/www/localhost/
COPY supervisord.conf /etc/supervisord.conf

RUN cd /var/www/localhost && \
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
php -r "if (hash_file('sha384', 'composer-setup.php') === '55ce33d7678c5a611085589f1f3ddf8b3c52d662cd01d4ba75c0ee0459970c2200a51f492d557530c71c15d8dba01eae') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
php composer-setup.php && \
php -r "unlink('composer-setup.php');" && \
php composer.phar update && \
chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/ /var/log/supervisor /dev/stdout && \
chown -R www-data /var/www/localhost/ /etc/manticoresearch/manticore.conf /dev/stdout

WORKDIR /var/www/localhost/

CMD ["./starter.sh"]

