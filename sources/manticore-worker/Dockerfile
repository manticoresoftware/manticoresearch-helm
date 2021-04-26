FROM manticoresearch/manticore:dev

RUN apt --fix-broken install -y


RUN echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections
RUN apt-get update && apt-get install -y curl tree php-cli php-mysqlnd python-pip && rm -rf /var/lib/apt/lists/*


COPY manticore.conf /etc/manticoresearch/
COPY shutdown.sh /etc/manticoresearch/
COPY starter.sh /etc/manticoresearch/
COPY replica.php /etc/manticoresearch/

COPY *.pak /usr/share/manticore/

WORKDIR /etc/manticoresearch/

RUN chown -R manticore /var/log/manticore/ /var/lib/manticore /var/run/manticore /etc/manticoresearch/ /usr/share/manticore/

RUN ls -la /etc/manticoresearch/
#USER manticore:manticore

CMD ["./starter.sh"]
