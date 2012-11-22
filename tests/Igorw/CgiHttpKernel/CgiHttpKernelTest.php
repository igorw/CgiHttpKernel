<?php

namespace Igorw\CgiHttpKernel;

use Symfony\Component\HttpFoundation\Request;

class CgiHttpKernelTest extends \PHPUnit_Framework_TestCase
{
    private $kernel;

    public function __construct()
    {
        $this->kernel = new CgiHttpKernel(__DIR__.'/Fixtures');
    }

    /** @test */
    public function handleShouldRenderRequestedFile()
    {
        $request = Request::create('/hello.php');
        $response = $this->kernel->handle($request);

        $this->assertSame('Hello World', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html', $response->headers->get('Content-type'));
    }

    /** @test */
    public function missingFileShouldResultIn404()
    {
        $request = Request::create('/missing.php');
        $response = $this->kernel->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    /** @test */
    public function customHeadersShouldBeSent()
    {
        $request = Request::create('/redirect.php');
        $response = $this->kernel->handle($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/foo.php', $response->headers->get('Location'));
    }
}
