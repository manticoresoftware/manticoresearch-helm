FROM manticoresearch/manticore:3.6.0

RUN echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections && apt --fix-broken install -y && \
apt-get update && apt-get install -y nano curl unzip git php-cli php-mysqlnd php-curl supervisor && rm -rf /var/lib/apt/lists/* && \
mkdir /var/www && mkdir /var/www/localhost

COPY code starter.sh job_runner.sh /var/www/localhost/
COPY supervisord.conf /etc/supervisord.conf

RUN cd /var/www/localhost && \
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
php -r "if (hash_file('sha384', 'composer-setup.php') === '756890a4488ce9024fc62c56153228907f1545c228516cbf63f885e036d37e9a59d27d63f46af1d4d07ee0f76181c7d3') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); exit(1); } echo PHP_EOL;" && \
php composer-setup.php && \
php -r "unlink('composer-setup.php');" && \
php composer.phar update && \
chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/ /var/log/supervisor /dev/stdout && \
chown -R www-data /var/www/localhost/ /etc/manticoresearch/manticore.conf /dev/stdout

WORKDIR /var/www/localhost/

CMD ["./starter.sh"]

