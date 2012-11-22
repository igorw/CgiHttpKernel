<?php

namespace Igorw\CgiHttpKernel;

class CgiHttpKernelTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function createKernel()
    {
        $kernel = new CgiHttpKernel();
    }
}
