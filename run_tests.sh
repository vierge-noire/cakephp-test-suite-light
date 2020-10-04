#!/bin/bash

DRIVER=$1;
USER=$2;
PWD=$3;
DRIVER_NAMESPACE='Cake\Database\Driver\'

echo "Starting PHPUNIT tests"

if [ -n "$DRIVER" ]; then
  DRIVER="$DRIVER_NAMESPACE$DRIVER"
  export DB_DRIVER=$DRIVER
  echo "With driver: $DRIVER"
else
  echo "Using default driver $DB_DRIVER"
fi

if [ -n "$USER" ]; then
  export DB_USER=$USER
  echo "With user: $USER"
else
  echo "Using default user $DB_USER"
fi

if [ -n "$PWD" ]; then
  export DB_PWD=$PWD
  echo "With password: $PWD"
else
  echo "Using default password $DB_PWD"
fi

# Test Cases where tables get dropped are put separately,
# since they are giving a hard time to the fixtures
# These can be put all together again once the migrations
# get required in the dependencies
./vendor/bin/phpunit --testsuite Default --stop-on-fail
./vendor/bin/phpunit --testsuite DropCities --stop-on-fail
./vendor/bin/phpunit --testsuite DropCountries --stop-on-fail
./vendor/bin/phpunit --testsuite DropTables --stop-on-fail

