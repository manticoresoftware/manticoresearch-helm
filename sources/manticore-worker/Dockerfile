FROM manticoresearch/helm-balancer:dev AS builder

FROM manticoresearch/manticore:4.0.2

RUN apt-get update && \
    apt-get install -y wget && \
    wget http://repo.manticoresearch.com/manticore-dev-repo.noarch.deb && \
    dpkg -i manticore-dev-repo.noarch.deb && \
    apt update && \
    apt install manticore=4.0.3-211016-d1baeb72d

RUN apt --fix-broken install -y && echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections && \
apt-get update && apt-get install -y curl php-cli php-mysqlnd php-curl wget && rm -rf /var/lib/apt/lists/*

COPY --from=builder /var/www/localhost/ /etc/manticoresearch/

RUN rm /etc/manticoresearch/observer.php

COPY manticore.conf shutdown.sh starter.sh replica.php /etc/manticoresearch/

RUN wget https://repo.manticoresearch.com/repository/morphology/en.pak.tgz https://repo.manticoresearch.com/repository/morphology/de.pak.tgz https://repo.manticoresearch.com/repository/morphology/ru.pak.tgz -P /tmp && \
tar -xvzf /tmp/en.pak.tgz -C /usr/share/manticore/ && tar -xvzf /tmp/ru.pak.tgz -C /usr/share/manticore/ && tar -xvzf /tmp/de.pak.tgz -C /usr/share/manticore/ && \
chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/ /dev/stdout

WORKDIR /etc/manticoresearch/

CMD ["./starter.sh"]
