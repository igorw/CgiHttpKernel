<?php

use Igorw\CgiHttpKernel\CgiHttpKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CgiHttpKernelTest extends \PHPUnit_Framework_TestCase
{
    private $phpCgiBin;
    private $kernel;

    public function __construct()
    {
        $this->phpCgiBin = getenv('CGI_HTTP_KERNEL_BIN');
        $this->kernel = new CgiHttpKernel(__DIR__.'/Fixtures', null, $this->phpCgiBin);
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

    /** @test */
    public function customErrorStatusCodeShouldBeSent()
    {
        $request = Request::create('/custom-error.php');
        $response = $this->kernel->handle($request);

        $this->assertSame(500, $response->getStatusCode());
    }

    /** @test */
    public function frontControllerShouldLoadPathInfo()
    {
        $this->kernel = new CgiHttpKernel(__DIR__.'/Fixtures', 'silex.php', $this->phpCgiBin);

        $request = Request::create('/foo');
        $response = $this->kernel->handle($request);

        $this->assertSame('bar', $response->getContent());
    }

    /** @test */
    public function frontControllerShouldConvertRequestMethod()
    {
        $this->kernel = new CgiHttpKernel(__DIR__.'/Fixtures', 'silex.php', $this->phpCgiBin);

        $request = Request::create('/baz', 'POST');
        $response = $this->kernel->handle($request);

        $this->assertSame('qux', $response->getContent());
    }

    /** @test */
    public function frontControllerShouldSupportPut()
    {
        $this->kernel = new CgiHttpKernel(__DIR__.'/Fixtures', 'silex.php', $this->phpCgiBin);

        $request = Request::create('/put-target', 'PUT');
        $response = $this->kernel->handle($request);

        $this->assertSame('putted', $response->getContent());
    }

    /** @test */
    public function frontControllerShouldSupportDelete()
    {
        $this->kernel = new CgiHttpKernel(__DIR__.'/Fixtures', 'silex.php', $this->phpCgiBin);

        $request = Request::create('/delete-target', 'DELETE');
        $response = $this->kernel->handle($request);

        $this->assertSame('deleted', $response->getContent());
    }

    /** @test */
    public function itShouldForwardRequestParameters()
    {
        $request = Request::create('/post-params.php', 'POST', array('foo' => 'bar'));
        $response = $this->kernel->handle($request);

        $this->assertSame('bar', $response->getContent());
    }

    /** @test */
    public function itShouldForwardRequestBody()
    {
        $content = 'bazinga';

        $request = Request::create('/post-body.php', 'POST', array(), array(), array(), array(), $content);
        $response = $this->kernel->handle($request);

        $this->assertSame('bazinga', $response->getContent());
    }

    /** @test */
    public function itShouldForwardHostHeader()
    {
        $request = Request::create('http://localhost/host-header.php');
        $response = $this->kernel->handle($request);

        $this->assertSame('localhost', $response->getContent());
    }

    /** @test */
    public function itShouldForwardCookies()
    {
        $request = Request::create('/cookie-get.php', 'GET', array(), array('foo' => 'bar'));
        $response = $this->kernel->handle($request);

        $this->assertSame('bar', $response->getContent());
    }

    /** @test */
    public function isShouldSetReturnedCookiesOnResponse()
    {
        $request = Request::create('/cookie-set.php');
        $response = $this->kernel->handle($request);

        $cookies = $response->headers->getCookies();
        $this->assertSame('foo', $cookies[0]->getName());
        $this->assertSame('bar', $cookies[0]->getValue());
    }

    /** @test */
    public function isShouldParseMultipleCookiesFromResponse()
    {
        $request = Request::create('/cookie-set-many.php');
        $response = $this->kernel->handle($request);

        $cookies = $response->headers->getCookies();
        $this->assertSame('foo', $cookies[0]->getName());
        $this->assertSame('baz', $cookies[0]->getValue());
        $this->assertSame('qux', $cookies[1]->getName());
        $this->assertSame('quux', $cookies[1]->getValue());
    }

    /** @test */
    public function isShouldParseEmptyCookieValue()
    {
        $request = Request::create('/cookie-set-empty.php');
        $response = $this->kernel->handle($request);

        $cookies = $response->headers->getCookies();
        $this->assertSame('foo', $cookies[0]->getName());
        $this->assertSame('', $cookies[0]->getValue());
    }

    /** @test */
    public function isShouldParseFullCookieValue()
    {
        $request = Request::create('/cookie-set-full.php');
        $response = $this->kernel->handle($request);

        $cookies = $response->headers->getCookies();
        $this->assertSame('foo', $cookies[0]->getName());
        $this->assertSame('bar', $cookies[0]->getValue());
        $this->assertSame(1353842823, $cookies[0]->getExpiresTime());
        $this->assertSame('/baz', $cookies[0]->getPath());
        $this->assertSame('example.com', $cookies[0]->getDomain());
    }

    /** @test */
    public function itShouldSetHttpAuth()
    {
        $request = Request::create('http://igorw:secret@localhost/auth.php');
        $response = $this->kernel->handle($request);

        $this->assertSame('igorw:secret', $response->getContent());
    }

    /** @test */
    public function scriptNameShouldBeFrontController()
    {
        $request = Request::create('/script-name.php');
        $response = $this->kernel->handle($request);

        $this->assertSame('/script-name.php', $response->getContent());
    }

    /** @test */
    public function scriptNameShouldBeFrontControllerWithCustomFrontController()
    {
        $this->kernel = new CgiHttpKernel(__DIR__.'/Fixtures', 'silex.php', $this->phpCgiBin);

        $request = Request::create('/script-name');
        $response = $this->kernel->handle($request);

        $this->assertSame('/silex.php', $response->getContent());
    }

    /** @test */
    public function uploadShouldPutFileInFiles()
    {
        $file = new UploadedFile(__DIR__.'/Fixtures/sadkitten.gif', 'sadkitten.gif', 'image/gif');

        $request = Request::create('/upload.php', 'POST');
        $request->files->add(array('kitten' => $file));
        $response = $this->kernel->handle($request);

        $expected = implode("\n", array(
            'sadkitten.gif',
            'image/gif',
            '1304444',
            '1',
        ));
        $this->assertSame($expected."\n", $response->getContent());
    }

    /** @test */
    public function attributesShouldBeSerializedToEnv()
    {
        $attributes = array(
            'foo'     => 'bar',
            'baz.qux' => array(
                'one'   => 'two',
                'three' => 'four'
            ),
        );

        $request = Request::create('/attributes.php');
        $request->attributes->replace($attributes);
        $response = $this->kernel->handle($request);

        $expected = json_encode($attributes);
        $this->assertSame($expected, $response->getContent());
    }

    /** @test */
    public function doubleCrlfResponseBodyShouldBeDecodedProperly()
    {
        $request = Request::create('/double-crlf-response-body.php');
        $response = $this->kernel->handle($request);

        $expected = "foo\r\n\r\nbar";
        $this->assertSame($expected, $response->getContent());
    }
}
