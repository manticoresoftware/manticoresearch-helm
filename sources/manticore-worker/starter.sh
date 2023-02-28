#!/bin/bash

if [ ! -d "/var/lib/manticore/log" ]; then
  mkdir -p "/var/lib/manticore/log"
fi

if [ ! -d "/var/lib/manticore/data" ]; then
  mkdir -p "/var/lib/manticore/data"
fi

while [ -z "$(ls -A /var/lib/manticore/)" ]
do
  echo "Waiting for volume mount"
  sleep 1;
done

echo "Mount success"


if [[ "${EXTRA}" == "1" ]]; then
  if [[ $(du /usr/bin/manticore-executor | cut -f1) == "0" ]]; then
    if [ ! -f /etc/ssl/cert.pem ]; then
      for cert in "/etc/ssl/certs/ca-certificates.crt" \
        "/etc/pki/tls/certs/ca-bundle.crt" \
        "/etc/ssl/ca-bundle.pem" \
        "/etc/pki/tls/cacert.pem" \
        "/etc/pki/ca-trust/extracted/pem/tls-ca-bundle.pem"; do
        if [ -f "$cert" ]; then
          ln -s "$cert" /etc/ssl/cert.pem
          break
        fi
      done
    fi

    LAST_PATH=$(pwd)
    EXTRA_URL=$(cat /extra.url)
    EXTRA_DIR="/var/lib/manticore/.extra/"

    if [ ! -d $EXTRA_DIR ]; then
      mkdir $EXTRA_DIR
    fi

    if [[ -z $(find $EXTRA_DIR -name 'manticore-executor') ]]; then
      wget -P $EXTRA_DIR $EXTRA_URL
      cd $EXTRA_DIR
      PACKAGE_NAME=$(ls | grep manticore-executor | head -n 1)
      ar -x $PACKAGE_NAME
      tar -xf data.tar.xz
    fi

    find $EXTRA_DIR -name 'manticore-executor' -exec cp {} /usr/bin/manticore-executor \;
    cd $LAST_PATH
  fi

  MCL="1"
fi

if [[ "${MCL}" == "1" ]]; then
  LIB_MANTICORE_COLUMNAR="/var/lib/manticore/.mcl/lib_manticore_columnar.so"
  LIB_MANTICORE_SECONDARY="/var/lib/manticore/.mcl/lib_manticore_secondary.so"

  [ -L /usr/share/manticore/modules/lib_manticore_columnar.so ] || ln -s $LIB_MANTICORE_COLUMNAR /usr/share/manticore/modules/lib_manticore_columnar.so
  [ -L /usr/share/manticore/modules/lib_manticore_secondary.so ] || ln -s $LIB_MANTICORE_SECONDARY /usr/share/manticore/modules/lib_manticore_secondary.so

  searchd -v | grep -i error | egrep "trying to load" &&
    rm $LIB_MANTICORE_COLUMNAR $LIB_MANTICORE_SECONDARY &&
    echo "WARNING: wrong MCL version was removed, installing the correct one"

  if [[ ! -f "$LIB_MANTICORE_COLUMNAR" || ! -f "$LIB_MANTICORE_SECONDARY" ]]; then
    if ! mkdir -p /var/lib/manticore/.mcl/; then
      echo "ERROR: Manticore Columnar Library is inaccessible: couldn't create /var/lib/manticore/.mcl/."
      exit
    fi

    MCL_URL=$(cat /mcl.url)
    wget -P /tmp $MCL_URL

    LAST_PATH=$(pwd)
    cd /tmp
    PACKAGE_NAME=$(ls | grep manticore-columnar | head -n 1)
    ar -x $PACKAGE_NAME
    tar -xf data.tar.gz
    find . -name '*.so' -exec cp {} /var/lib/manticore/.mcl/ \;
    cd $LAST_PATH
  fi
fi

/usr/bin/supervisord -n -c /etc/supervisord.conf
