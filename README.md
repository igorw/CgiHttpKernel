# CgiHttpKernel

Adapter from HttpKernelInterface to CGI.

## The HttpKernelInterface is a lie

You thought that you need to rewrite your whole application to use
HttpFoundation in order to benefit from HttpKernelInterface functional
testing?

Well, it turns out that you don't need that at all. Some very smart people
came up with this thing called CGI (Common Gateway Interface) which defines an
interface between a web server and a web application. It's great because it
uses UNIX pipes for communication, which means it is also very easy to pretend
to be a web server, and just call the app with any input and env vars on the
command line by hand.

In PHP that works by using `php-cgi`, which fortunately ships with almost
every PHP distribution. The most basic way of calling it on the command line
is this:

    $ php-cgi hello.php

If you want to learn how to do more advanced stuff, read the fucking CGI spec.

So what does this have to do with HttpKernelInterface? CGI and that interface
do pretty much the same thing, they abstract communication between web server
and app. The kernel interface does this within PHP, CGI does it in a language
agnostic way.

The CgiHttpKernel translates between those two interfaces. As a user of the
library you interact with it as if it were a true HttpKernelInterface app, but
in the background it will actually go ahead and call `php-cgi` on the command
line, parse the output, and return a `Response` instance.

For example:

    $kernel = new CgiHttpKernel(__DIR__.'/../phpBB');

    $request = Request::create('/index.php');
    $response = $kernel->handle($request);

    var_dump($response->getContent());

You can also pass a second argument to the constructor if you want all
requests to go through a front controller.

    $kernel = new CgiHttpKernel(__DIR__.'/../web', 'app.php');

    $request = Request::create('/foo');
    $response = $kernel->handle($request);

The real power however comes from using libraries that integrate with the
HttpKernelInterface, such as `Symfony\Component\HttpKernel\Client`.

    $kernel = new CgiHttpKernel(__DIR__.'/../phpBB');
    $client = new Client($kernel);

    $crawler = $client->request('GET', '/index.php');
    $this->assertGreaterThan(0, $crawler->filter('.topiclist')->count());

## Is it really a lie?

Not really. The CgiHttpKernel only makes sense for functional testing, since
it is quite slow. It is slow because it must spawn a new process for every
request. This is also the reason why some very smart people came up with
FastCGI, which is like CGI but faster.

FastCGI allows the app to start a long-running process that listens on a port
and thus does not have the process spawning overhead. In PHP land this is
usually managed by PHP-FPM, aka FastCGI Process Manager.

FastCGI is good for production but not really practical for testing since it
needs to run in a separate process, listen on a port, requires configuration,
etc.

However, there is an [FcgiHttpKernel](https://github.com/igorw/FcgiHttpKernel)
that ports the idea of this project to FastCGI. It may in fact become useful
for tests.

## When to use CgiHttpKernel?

It is mainly intended to write functional tests for legacy applications. That
will hopefully enable you to refactor your legacy code with some confidence of
not breaking stuff.

Good luck.

## Specifics

### Attributes

Request attributes are serialized and provided to the target script through
the `SYMFONY_ATTRIBUTES` env variable. This means that it can get the original
attributes as follows:

    $attributes = unserialize($_SERVER['SYMFONY_ATTRIBUTES']);
