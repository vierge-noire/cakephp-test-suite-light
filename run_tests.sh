#!/bin/bash

DRIVER=$1;

echo "Starting PHPUNIT tests"
export DB_DRIVER=$DRIVER

#######################
#### Tests with temporary sniffers
#######################
./vendor/bin/phpunit

#######################
#### Tests with non temporary sniffers
#######################
export SNIFFERS_IN_TEMP_MODE="true"
./vendor/bin/phpunit

#### DEPRECATED #####
# Run the tests using
# non-triggered based sniffers
#####################
export TABLE_SNIFFER="CakephpTestSuiteLight\Sniffer\\${DRIVER}TableSniffer"
export USE_NON_TRIGGERED_BASED_SNIFFERS="true"
./vendor/bin/phpunit

