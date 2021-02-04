# cakephp-test-suite-light
A fast test suite for CakePHP applications

#### For CakePHP 3.x
composer require --dev vierge-noire/cakephp-test-suite-light "^1.0"

#### For CakePHP 4.x
composer require --dev vierge-noire/cakephp-test-suite-light "^2.0"

## Installation

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
```

### Fine-tuning the truncation
To speed up things, you may want to fine-tune the truncation behavior to have it applyed only when you want to. `cakephp-test-suite-light` provides many ways to do it.

The main idea is that truncation can be enabled or disabled :
- For all tests
- For a set of connections and override global setting
- For a whole test case and override previous settings

**Important: Tuning will have no effect on ignored connections**

#### Disabling truncation at once for all connections
You can turn off default truncation behavior by setting up `CTSL_ALWAYS_SKIP_TRUNCATE` to whatever value in environment before running tests (in the [PHPUnit configuration](https://phpunit.readthedocs.io/en/latest/configuration.html?highlight=env#the-env-element), test bootstrap, in the shell...).

Even if globally disabled, truncation can be forced per connection or test case.

The environment variables are evaluated before each test and my be tweaked at any time before next truncation should be decided.

If for some reasons, you need to adjust this in a test case or a test, you can use the `TunerTrait` helper :

```php
<?php
use CakephpTestSuiteLight\TunerTrait;

class YourTest extends TestCase {
    use TunerTrait;

    public function setUp(): void
    {
        // Disable global truncation behavior
        $this->disableTruncation();
    }

    public function tearDown(): void
    {
        // Restore global truncation behavior
        $this->enableTruncation();
    }
}
```

```php
<?php
use CakephpTestSuiteLight\TunerTrait;

class YourTest extends TestCase {
    use TunerTrait;

    public function testSomething(): void
    {
        // Do something to data and wanna keep it
        $this->disableTruncation();
    }

    public function testSomethingAfter(): void
    {
        // Do something to data and wanna clear the data
        $this->enableTruncation();
    }
}
```

#### Tuning truncation per connection(s)
As stated before, a connection can simply be ignored once and for all (see [Ignoring connections](#ignoring-connections)). In that case, truncation will never occurs.

If truncation are globally enabled, you can tell the fixture manager to skip truncation for some connections by setting up `CTSL_SKIP_TRUNCATE=<connection1>[,<connection2>...]` using connection names.

If truncation are globally disabled, you can tell the fixture manager to perform truncation for some connections by setting up `CTSL_FORCE_TRUNCATE=<connection1>[,<connection2>...]` using connection names.

The environment variables are evaluated before each test and my be tweaked at any time before next truncation should be decided.

#### Tuning truncation per test case
You may wish to skip the truncation in a whole test case. For example if you know in advance that
your tests do not interact with the database, or if you do not mind having a dirty DB at the beginning of your tests.
This is made at the test class level, by letting your test class using the trait `CakephpTestSuiteLight\SkipTablesTruncation`.

At the opposite, if you wish to always perform truncation, use the `CakephpTestSuiteLight\ForceTablesTruncation` trait.

**Caution : This will override global and per connection settings**

If you need more fine-grained control, you can use `CakephpTestSuiteLight\TunerTrait` :

```php
<?php
use CakephpTestSuiteLight\TunerTrait;

class YourTest extends TestCase {
  use TunerTrait;

  public function setUp(): void
  {
      // always truncate dirty tables in connection test
      $this->forceTruncation('test');
      // avoid truncating tables in connections cloud1 and cloud2
      $this->skipTruncation('cloud1', 'cloud2');
      // ...
  }

  // ...
}
```

Or manually set up env vars in test case `setUp` and `tearDown` :

```php
<?php
public function setUp(): void
{
    // always truncate dirty tables in connection test
    putenv('CTSL_FORCE_TRUNCATE=test');
    // never truncate tables in connections cloud1 and cloud2
    putenv('CTSL_SKIP_TRUNCATE=cloud1,cloud2');
    // ...
}

public function tearDown(): void
{
    // We need to clear env vars as they will remain active for following test cases
    putenv('CTSL_FORCE_TRUNCATE');
    putenv('CTSL_SKIP_TRUNCATE');
    // ...
}
```

### Temporary vs non-temporary dirty table collector

One of the advantage of the present test suite, consists in the fact that the test database is cleaned before each test,
rather than after. This enables the developer to perform queries in the test database and observe the state in which
a given test left the database.

The present plugin collects the dirty tables in a dedicated table with the help of triggers.
This table is per default permanent, but it can be set to temporary in order to keep it invisible to the code.

In ordert to do so, in your test DB settings, set the key `dirtyTableCollectorMode` to `TEMP`.

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
