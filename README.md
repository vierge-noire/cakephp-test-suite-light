# cakephp-test-suite-light
A fast test suite for CakePHP applications

<!-- TOC depthFrom:2 depthTo:6 withLinks:1 updateOnSave:1 orderedList:0 -->

- [Installation](#installation)
	- [For CakePHP 3.x](#for-cakephp-3x)
	- [For CakePHP 4.x](#for-cakephp-4x)
	- [Basic setup](#basic-setup)
- [In-depth configuration](#in-depth-configuration)
	- [Ignoring connections](#ignoring-connections)
	- [Configure sniffers used for dirty tables detection](#configure-sniffers-used-for-dirty-tables-detection)
	- [Fine-tuning the truncation](#fine-tuning-the-truncation)
		- [Permanently disable truncation for a whole run](#permanently-disable-truncation-for-a-whole-run)
		- [Change global truncation policy](#change-global-truncation-policy)
		- [Truncation policy per connection](#truncation-policy-per-connection)
		- [Overriding truncation policies in a test case](#overriding-truncation-policies-in-a-test-case)
		- [Using the tables truncation trait](#using-the-tables-truncation-trait)
	- [Temporary vs non-temporary dirty table collector](#temporary-vs-non-temporary-dirty-table-collector)
	- [Using CakePHP fixtures](#using-cakephp-fixtures)
	- [Statistic tool](#statistic-tool)
- [Authors](#authors)
- [Support](#support)
- [License](#license)

<!-- /TOC -->

## Installation

### For CakePHP 3.x
composer require --dev vierge-noire/cakephp-test-suite-light "^1.0"

### For CakePHP 4.x
composer require --dev vierge-noire/cakephp-test-suite-light "^2.0"

### Basic setup
Make sure you *replace* the native CakePHP listener by the following one inside your `phpunit.xml` (or `phpunit.xml.dist`) config file, per default located in the root folder of your application:

```xml
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

## In-depth configuration

Internally, the fixture manager is relying on a "sniffer" to detect dirty tables and truncate them before each test. This is the default behavior that will suffice in most cases.

As default, the fixture manager is using triggers in each table that will mark them as dirty whenever data is inserted. These sniffers are available for :
* Sqlite
* MySQL
* Postgres

You can also choose to [fallback on previous sniffer](#configure-sniffers-used-for-dirty-tables-detection) that detects table where insertions were made (only work if primary key has AUTO_INCREMENT flag) and truncate them.

If you use a different database engine, you will have to [provide your own](#configure-sniffers-used-for-dirty-tables-detection). It should extend the `BaseTableSniffer` class.

### Ignoring connections

The package will empty the tables found in **all test databases**. If you wish to permanently ignore a given connection, you may
provide the `skipInTestSuiteLight` key set to `true` in your `config/app.php`. E.g.:

```php
// In config/app.php or config/app_local.php
<?php
[
    // [...]
    'Datasources' => [
        'test_wathever' => [
            'className' => Connection::class,
            'driver' => Mysql::class,
            'persistent' => false,
            // [...]
            'skipInTestSuiteLight' => true
        ],
        // [...]
    ],
    // [...]
]
?>
```

This can be useful for example if you have connections to a third party server in the cloud that should be ignored.

### Configure sniffers used for dirty tables detection

You can specify, per connection, the sniffer that will be used by mapping it in your `config/app.php` file the driver to
the custom table sniffer for each relevant connection. E.g.:
```php
// In config/app.php or config/app_local.php
<?php
[
// [...]
    'Datasources' => [
        'test' => [
            'className' => Connection::class,
            'driver' => Mysql::class,
            'persistent' => false,
            // [...]
            // Fallback to sniffer based on auto increment
            'tableSniffer' => '\CakephpTestSuiteLight\Sniffer\MysqlTableSniffer'
        ],
        'test_cloud' => [
            'className' => Connection::class,
            'driver' => Whatever::class,
            'persistent' => false,
            // [...]
            // Custom sniffer for specific database engine
            'tableSniffer' => '\Your\Custom\Table\Sniffer'
        ],
        // [...]
    ],
]
?>
```

### Fine-tuning the truncation
To speed up things, you may want to fine-tune the truncation behavior. `cakephp-test-suite-light` provides many ways to do it.

The main idea is that truncation can be enabled or disabled :
- For all tests
- For a set of connections and override global setting
- For a whole test case and override previous settings

**Important: Tuning will have no effect on ignored connections**

#### Permanently disable truncation for a whole run
In this mode, no database monitoring and truncation will never occurs. It guarantees the fastest start time but, obviously, it is only meant for specific cases (focusing on some tests while writing code for instance) and eventually leave the cleaning up to you.

If you use a different database engine, you will have to provide your own. It should extend
the `BaseTableSniffer` class.

Truncation can be permanently disabled by setting up `CTSL_DISABLE_TRUNCATION` to any value in environment, **before the first test starts**. It can be done through [PHPUnit configuration](https://phpunit.readthedocs.io/en/latest/configuration.html?highlight=env#the-env-element), test bootstrap, in the shell... You can also use this snippet in yout test bootstrap to achieve the same goal :

```php
<?php
	// in tests/bootstrap.php
	CakephpTestSuiteLight\TablesTruncation::disable();
?>
```

**This setting will never be overriden by any further configuration.**

#### Change global truncation policy
Default global policy is to performs truncation in all tests databases.

You can revert this behavior by setting up `CTSL_SKIP_ALL_TRUNCATIONS` to any value in environment before running tests.

To be active at start, it can be done through the [PHPUnit configuration](https://phpunit.readthedocs.io/en/latest/configuration.html?highlight=env#the-env-element), test bootstrap, in the shell... You can also use this snippet in test bootstrap to disable automatic truncations :

```php
<?php
	// in tests/bootstrap.php
	CakephpTestSuiteLight\TablesTruncation::skipAllTruncations();
?>
```

At runtime, the best way to enable or disable truncations **for next test** is to use one of [the provided traits](#tuning-truncation-per-test-case).

Even if globally disabled, truncation can hopefully still be triggered at runtime (see below).

#### Truncation policy per connection
As stated before, a connection can simply be ignored once and for all (see [ignoring connections](#ignoring-connections)). In that case, truncation should and will never be occur. **This is the only reliable way to guarantee that a test database won't be emptied as policies can always be overidden**.

:warning: Setting up a connection policy will override global policy for this connection

You can tell the fixture manager to skip truncation for any connections by setting up `CTSL_SKIP_TRUNCATION=<connection1>[,<connection2>...]` in environment using connection names. At the opposite, you can tell the fixture manager to always perform truncation for any connections by setting up `CTSL_FORCE_TRUNCATION=<connection1>[,<connection2>...]` in environment using connection names. You can also use these snippets in test bootstrap to set it up from start :

```php
<?php
	// in tests/bootstrap.php

	// Always skip connection "cloud" when doing autotruncations as default
	CakephpTestSuiteLight\TablesTruncation::skipTruncation('cloud');

	// OR
	// Disable autotruncations for all connections
	CakephpTestSuiteLight\TablesTruncation::skipAllTruncations();
	// except for testA and testB connections
	CakephpTestSuiteLight\TablesTruncation::forceTruncation('testA', 'testB');
?>
```

You may also use the [`CakephpTestSuiteLight\TablesTruncationTrait`](#using-the-tables-truncation-trait) to tweak settings at runtime.

#### Overriding truncation policies in a test case
It can be useful if you know in advance that your tests do not interact with the database, or if you do not mind having a dirty DB at the beginning of each your tests. This can be quicky settled by letting your test class using the trait `CakephpTestSuiteLight\SkipTablesTruncation`.

At the opposite, if you wish to always perform truncation, use the `CakephpTestSuiteLight\ForceTablesTruncation` trait.

You can also use the [`CakephpTestSuiteLight\TablesTruncationTrait`](#using-the-tables-truncation-trait) for more fine-grained control when dealing with multiple connections.

:warning: This will override global and per connection policies

#### Using the tables truncation trait
To ease runtime handling of truncation and override any policies, you can use `CakephpTestSuiteLight\TablesTruncationTrait` which exposes some convenient methods :

- `TablesTruncationTrait::doAllTruncations` : Activate automatic truncation for all connections (except the ignored ones)
- `TablesTruncationTrait::skipAllTruncations` : Disable automatic truncation for all connections
- `TablesTruncationTrait::forceTruncation(string $conn1[, string $conn2,...])` : Force truncation for all provided connections. Pass `false` to clear configuration. Pass `'*'` to register all connections.
- `TablesTruncationTrait::getForcedConnections()` : Returns all the connections names with truncation enabled.
- `TablesTruncationTrait::skipTruncation(string $conn1[, string $conn2,...])` : Skip truncation for all provided connections. Pass `false` to clear configuration.  Pass `'*'` to register all connections.
- `TablesTruncationTrait::getSkippedConnections()` : Returns all the connections names with truncation disabled.
- `TablesTruncationTrait::truncateTables([string $conn1,...])` : Requests manual truncation. If connection(s) name(s) are provided, truncation will be **forced** done in database(s) regardless all policies. This is most designed for very specific cases when you may want to perform truncations at runtime for debugging purposes.

 Always remember that truncation is done **before** each test. Therefore, to have overriden policies applied for the first one of the test case, things must be done in `setUpBeforeClass` or `setUp` hooks. Global and per-connection policies are then automatically restored at the end of the test case.

```php
<?php
use CakephpTestSuiteLight\TablesTruncationTrait;

class YourTest extends TestCase {
  use TablesTruncationTrait;

  public function setUp(): void
  {
      // Always truncate dirty tables in connection test
      $this->enableTruncation('test');

      // avoid truncating tables in connections cloud1 and cloud2
      $this->disableTruncation('cloud1', 'cloud2');
  }
}
?>
```

### Temporary vs non-temporary dirty table collector

One of the advantage of the present test suite, consists in the fact that the test database is cleaned before each test,
rather than after. This enables the developer to perform queries in the test database and observe the state in which
a given test left the database.

The present plugin collects the dirty tables in a dedicated table with the help of triggers.
This table is per default permanent, but it can be set to temporary in order to keep it invisible to the code.

In ordert to do so, in your test DB settings, set the key `'dirtyTableCollectorMode'` to `'TEMP'`.

### Temporary vs non-temporary dirty table collector

The present plugin collects the dirty tables in a dedicated table with the help of triggers.
This table is per default temporary in order to keep it invisible to the code.

One of the advantage of the present test suite, consists in the fact that the test database is cleaned before each test,
rather than after. This enables the developer to perform queries in the test database and observe the state in which
a given test left the database.

Due to the fact that triggers are created on all tables creating inserts in the temporary dirty table collector,
the developer will not be able to perform any manual inserts in the test database outside the test suite.

If needed, one solution consists in dropping the test database and re-running the migrations. A second solution
consists in having the dirty table collector non-temporary. This is possible at the connection level, by
calling in `tests/bootstrap.php` `CakephpTestSuiteLight\Sniffer\SnifferRegistry::get('your_test_connection_name')->activateMainMode();`
with `your_test_connection_name` being typically `test`. 

### Using CakePHP fixtures

It is still possible to use the native CakePHP fixtures. To this aim, you may simply load them as described [here](https://book.cakephp.org/3/en/development/testing.html#creating-fixtures).

### Statistic tool

The suite comes with a statistic tool. This will store the execution time, the test name, the number and the list
of the dirty tables for each test.

In order to activate it, add a second argument set to true to the `FixtureInjector` in the following manner:

```
<!-- Setup a listener for fixtures -->
     <listeners>
         <listener class="CakephpTestSuiteLight\FixtureInjector">
             <arguments>
                 <object class="CakephpTestSuiteLight\FixtureManager" />
                 <boolean>true</boolean>
             </arguments>
         </listener>
     </listeners>
```

The statistics will be store after each suite in `tmp/test_suite_light/test_suite_statistics.csv`.

With the help of your IDE, you can easily order the results and track the slow tests, and improve their respective performance.

Not the that the statistic tool does not perform any query in the database. It uses information
that is being gathered regardless of its actvation. It has no significant impact on the
overall speed of your tests.

***Note: you should not add the [CakePHP native listener](https://book.cakephp.org/3/en/development/testing.html#phpunit-configuration)*** to your `phpunit.xml` file.
Only one listener is required, which is the one described in the section *Installation*.

## Authors
* Juan Pablo Ramirez
* Nicolas Masson


## Support
Contact us at vierge.noire.info@gmail.com for professional assistance.

You like our work? [![ko-fi](https://www.ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/L3L52P9JA)


## License

The CakephpTestSuiteLight plugin is offered under an [MIT license](https://opensource.org/licenses/mit-license.php).

Copyright 2020 Juan Pablo Ramirez and Nicolas Masson

Licensed under The MIT License Redistributions of files must retain the above copyright notice.
