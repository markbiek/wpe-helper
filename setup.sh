#!/bin/bash

if [ ! -f .env ]; then
    echo "Setup could not run because the .env wasn't found."
    exit 1
fi

APP_ENV=`grep APP_ENV .env | awk -F= '{print $2}'`

composer install
composer dumpautoload
npm install
./artisan migrate
./artisan cache:clear
./artisan config:clear
./artisan installs:cache