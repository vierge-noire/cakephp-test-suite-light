<?php
declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) 2020 Juan Pablo Ramirez and Nicolas Masson
 * @link          https://webrider.de/
 * @since         1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace CakephpTestSuiteLight;

use CakephpTestSuiteLight\Annotations\FixturesProvider;
use PHPUnit\Framework\Test;

class AnnotationHandler extends \Doctrine\Common\Annotations\AnnotationReader
{
    public function getFixtureProvider(Test $test): string
    {
        $testName = $test->getName() ?? '';
        $testName = explode(' ', $testName)[0];

        $reflectionMethod = new \ReflectionMethod($test, $testName);
        try {
            $annotation = $this->getMethodAnnotation($reflectionMethod, FixturesProvider::class);
        } catch (\Exception $e) {}

        if (!$annotation) {
            $reflectionClass = new \ReflectionClass($test);
            try {
                $annotation = $this->getClassAnnotation($reflectionClass, FixturesProvider::class);
            } catch (\Exception $e) {}
        }

        $provider = $annotation->provider['value'] ?? '';

        if (strlen($provider) > 0 && !method_exists($test, $provider)) {
            $testClass = get_class($test);
            throw new \RuntimeException("The method $provider was not found in $testClass");
        }

        return $provider;
    }
}