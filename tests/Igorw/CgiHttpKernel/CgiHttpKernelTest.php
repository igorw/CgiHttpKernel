<?php

namespace Igorw\CgiHttpKernel;

class CgiHttpKernelTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function kernelShouldImplementKernelInterface()
    {
        $kernel = new CgiHttpKernel();
        $this->assertInstanceOf('Symfony\Component\HttpKernel\HttpKernelInterface', $kernel);
    }
}
