FROM manticoresearch/manticore:4.0.2

RUN apt-get update && \
    apt-get install -y wget && \
    wget http://repo.manticoresearch.com/manticore-dev-repo.noarch.deb && \
    dpkg -i manticore-dev-repo.noarch.deb && \
    apt update && \
    apt install manticore=4.0.3-211016-d1baeb72d

RUN echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections && apt --fix-broken install -y && \
apt-get update && apt-get install -y nano curl unzip git php-cli php-mysqlnd php-curl supervisor && rm -rf /var/lib/apt/lists/* && \
mkdir /var/www && mkdir /var/www/localhost

COPY code starter.sh job_runner.sh optimize_runner.sh /var/www/localhost/
COPY supervisord.conf /etc/supervisord.conf

RUN cd /var/www/localhost && \
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
php composer-setup.php && \
php -r "unlink('composer-setup.php');" && \
php composer.phar update && \
chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/ /var/log/supervisor /dev/stdout && \
chown -R www-data /var/www/localhost/ /etc/manticoresearch/manticore.conf /dev/stdout

WORKDIR /var/www/localhost/

CMD ["./starter.sh"]

