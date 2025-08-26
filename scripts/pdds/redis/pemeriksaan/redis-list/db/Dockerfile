FROM mysql:8

ENV MYSQL_ROOT_PASSWORD=example
ENV MYSQL_DATABASE=skripsi

COPY init.sql /docker-entrypoint-initdb.d/
