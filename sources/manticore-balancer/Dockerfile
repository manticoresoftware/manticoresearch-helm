FROM manticoresearch/manticore:10.1.0-hf

ARG GITHUB_TOKEN

RUN echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections && apt --fix-broken install -y && \
apt-get update && apt -y install software-properties-common && add-apt-repository -y ppa:ondrej/php && apt-get update && \
    apt-get install -y nano curl unzip git php8.1-cli php8.1-mysqlnd php8.1-curl php8.1-dom php8.1-mbstring \
    supervisor && rm -rf /var/lib/apt/lists/* && mkdir /var/www && mkdir /var/www/localhost

COPY composer.json observer.php optimize.php starter.sh observer_runner.sh optimize_runner.sh /var/www/localhost/
COPY supervisord.conf /etc/supervisord.conf
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN cd /var/www/localhost && \
    composer config --global github-oauth.github.com "${GITHUB_TOKEN}" && \
    composer config --global repos.packagist composer https://repo.packagist.org && \
    for i in {1..3}; do \
      echo "Attempt $i of 3"; \
      composer update --prefer-dist --no-progress --verbose && break; \
      echo "Failed, waiting 10 seconds before retrying..."; \
      sleep 10; \
    done && \
    chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore \
      /etc/manticoresearch/ /usr/share/manticore/ /var/log/supervisor /dev/stdout && \
    chown -R www-data /var/www/localhost/ /etc/manticoresearch/manticore.conf /dev/stdout
WORKDIR /var/www/localhost/

CMD ["./starter.sh"]

