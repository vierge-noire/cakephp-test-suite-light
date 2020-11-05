<?php


namespace CakephpTestSuiteLight\Test\Traits;


trait ArrayComparerTrait
{
    /**
     * @param array $expected
     * @param array $found
     * @return void
     */
    public function assertArraysHaveSameContent(array $expected, array $found)
    {
        sort($expected);
        sort($found);
        $this->assertSame($expected, $found);
    }
}