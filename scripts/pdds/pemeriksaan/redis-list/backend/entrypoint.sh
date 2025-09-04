#!/bin/sh
# Start PHP built-in server in background
php -S 0.0.0.0:80 -t /var/www/html &

# Tunggu sebentar supaya server siap
sleep 3

# Jalankan test script
php /var/www/html/redis-list-test.php http://localhost:80

# Supaya container nggak langsung mati
tail -f /dev/null
