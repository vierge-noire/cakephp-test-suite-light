#!/bin/bash

DRIVER=$1;

echo "Starting PHPUNIT tests"
export DB_DRIVER=$DRIVER

#######################
#### Tests with non temporary sniffers
#######################
./vendor/bin/phpunit

#### DEPRECATED #####
#### Tests with temporary sniffers
#### Skip MySQL
#######################
if [ $DRIVER != 'Mysql' ]; then
  export SNIFFERS_IN_TEMP_MODE="true"
  ./vendor/bin/phpunit
fi

