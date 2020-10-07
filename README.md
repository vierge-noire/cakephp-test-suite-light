# cakephp-test-suite-light
A fast test suite for CakePHP applications

#### For CakePHP 3.x
composer require --dev vierge-noire/cakephp-test-suite-light "^1.0"

#### For CakePHP 4.x
composer require --dev vierge-noire/cakephp-test-suite-light "^2.0"

## Installation

### Listeners

Make sure you *replace* the native CakePHP listener by the following one inside your `phpunit.xml` (or `phpunit.xml.dist`) config file, per default located in the root folder of your application:

```
<!-- Setup a listener for fixtures -->
     <listeners>
         <listener class="CakephpTestSuiteLight\FixtureInjector">
             <arguments>
                 <object class="CakephpTestSuiteLight\FixtureManager" />
             </arguments>
         </listener>
     </listeners>
``` 

Between each test, the package will truncate all the test tables that have been used during the previous test.

The fixtures will be created in the test database(s) defined in your [configuration](https://book.cakephp.org/4/en/development/testing.html#test-database-setup).

### Ignoring connections

The package will empty the tables found in all test databases. If you wish to ignore a given connection, you may create a 
`config/test_suite_light.php` file and provide the connections that should be ignored:

```$xslt
<?php

return [   
    'TestSuiteLightIgnoredConnections' => [
        'test_foo_connection_to_be_ignored',
        'test_bar_connection_to_be_ignored',
        ...
    ],
];
```

This can be useful for example if you have connections to a third party server in the cloud that should be ignored.

## Test life cycle

Here is the only step performed by the Fixture Factories Fixture Manager, and how to disable it.

### Truncating tables

The Fixture Manager truncates the dirty tables at the beginning of each test. This is the only action performed.

Dirty tables are tables on which the primary key has been incremented at least one. The detection of dirty tables is made
by SQL queries. These are called `TableSniffers` and there are located in the `src/TestSuite/Sniffer` folder
 of the package. These are provided for:
* Sqlite
* MySQL
* Postgres

If you use a different database engine, you will have to provide your own. It should extend
the `BaseTableSniffer`.

You should then map in your `config/test_suite_light.php` file the driver to
the custom table sniffer. E.g.:

```$xslt
<?php

return [   
    'TestFixtureTableSniffers' => [
        '\Some\Database\Driver' => '\Custom\Table\Sniffer', 
    ],
];
``` 
 

### Disabling the truncation

You may wish to skip the truncation of tables between the tests. For example if you know in advance that
your tests do not interact with the database, or if you do not mind having a dirty DB at the beginning of your tests.
This is made at the test class level, by letting your test class using the trait `CakephpTestSuiteLight\SkipTablesTruncation`.

### Using CakePHP fixtures

It is still possible to use the native CakePHP fixtures. To this aim, you may simply load them as described [here](https://book.cakephp.org/3/en/development/testing.html#creating-fixtures).


***Note: you should not add the [CakePHP native listener](https://book.cakephp.org/3/en/development/testing.html#phpunit-configuration)*** to your `phpunit.xml` file.
Only one listener is required, which is the one described in the section *Installation*.

