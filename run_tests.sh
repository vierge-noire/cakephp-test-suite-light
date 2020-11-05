#!/bin/bash

DRIVER=$1;

echo "Starting PHPUNIT tests"
export DB_DRIVER=$DRIVER

# Test Cases where tables get dropped are put separately,
# since they are giving a hard time to the fixtures
# These can be put all together again once the migrations
# get required in the dependencies
./vendor/bin/phpunit --testsuite Default --stop-on-fail
./vendor/bin/phpunit --testsuite DropCities --stop-on-fail
./vendor/bin/phpunit --testsuite DropCountries --stop-on-fail
./vendor/bin/phpunit --testsuite DropTables --stop-on-fail

# Run the tests again using non-triggered based sniffers
export TABLE_SNIFFER="CakephpTestSuiteLight\Sniffer\\${DRIVER}TableSniffer"

./vendor/bin/phpunit --testsuite Default --stop-on-fail
./vendor/bin/phpunit --testsuite DropCities --stop-on-fail
./vendor/bin/phpunit --testsuite DropCountries --stop-on-fail
./vendor/bin/phpunit --testsuite DropTables --stop-on-fail
