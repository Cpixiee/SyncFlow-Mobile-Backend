#!/bin/bash
# Script untuk clear cache di server Laravel

echo "Clearing Laravel cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "Clearing OPcache..."
# Untuk PHP-FPM
php -r "opcache_reset();"

# Atau restart PHP-FPM (pilih salah satu sesuai server Anda)
# sudo systemctl restart php-fpm
# atau
# sudo service php7.4-fpm restart
# atau
# sudo service php8.0-fpm restart
# atau
# sudo service php8.1-fpm restart
# atau
# sudo service php8.2-fpm restart

echo "Cache cleared successfully!"
