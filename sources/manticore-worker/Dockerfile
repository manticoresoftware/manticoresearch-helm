FROM manticoresearch/helm-balancer:3.6.0.0 AS builder

FROM manticoresearch/manticore:3.6.0

RUN apt --fix-broken install -y


RUN echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections
RUN apt-get update && apt-get install -y curl php-cli php-mysqlnd php-curl && rm -rf /var/lib/apt/lists/*

COPY --from=builder /var/www/localhost/ /etc/manticoresearch/

RUN rm /etc/manticoresearch/observer.php

COPY manticore.conf /etc/manticoresearch/
COPY shutdown.sh /etc/manticoresearch/
COPY starter.sh /etc/manticoresearch/
COPY replica.php /etc/manticoresearch/

COPY *.pak /usr/share/manticore/

WORKDIR /etc/manticoresearch/

RUN chown -R manticore:manticore /var/log/manticore/ /var/lib/manticore/ /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/ /dev/stdout

CMD ["./starter.sh"]
