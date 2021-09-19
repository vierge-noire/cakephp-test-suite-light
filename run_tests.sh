#!/bin/bash

DRIVER=$1;

echo "Starting PHPUNIT tests"
export DB_DRIVER=$DRIVER

#######################
#### Tests with non temporary sniffers
#######################
./vendor/bin/phpunit
