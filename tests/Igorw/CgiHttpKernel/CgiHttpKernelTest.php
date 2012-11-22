<?php

namespace Igorw\CgiHttpKernel;

use Symfony\Component\HttpFoundation\Request;

class CgiHttpKernelTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function handleShouldRenderRequestedFile()
    {
        $kernel = new CgiHttpKernel(__DIR__.'/Fixtures');
        $request = Request::create('/hello.php');
        $response = $kernel->handle($request);

        $this->assertSame('Hello World', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }
}
