<?php
/*
    This file is here as an example and to make sure the github actions runner has a test to run.
    It does not actually test anything.
*/
    
namespace Pdsinterop\Dummy;

use PHPUnit\Framework\TestCase;

class DummyTest extends TestCase
{
    /**
     * @coversNothing
     */
    public function testDummy(): void
    {
        $this->assertTrue(true);
    }
}