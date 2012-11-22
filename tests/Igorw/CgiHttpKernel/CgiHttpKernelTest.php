<?php

namespace Igorw\CgiHttpKernel;

use Symfony\Component\HttpFoundation\Request;

class CgiHttpKernelTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function handleShouldReturnResponse()
    {
        $kernel = new CgiHttpKernel();
        $request = Request::create('/');
        $response = $kernel->handle($request);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
    }
}
