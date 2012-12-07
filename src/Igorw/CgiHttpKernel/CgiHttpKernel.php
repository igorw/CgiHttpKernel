<?php

namespace Igorw\CgiHttpKernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Process\ProcessBuilder;

class CgiHttpKernel implements HttpKernelInterface
{
    private $rootDir;
    private $frontController;

    public function __construct($rootDir, $frontController = null)
    {
        $this->rootDir = $rootDir;
        $this->frontController = $frontController;
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $filename = $this->frontController ?: ltrim($request->getPathInfo(), '/');

        if (!file_exists($this->rootDir.'/'.$filename)) {
            return new Response('The requested file could not be found.', 404);
        }

        $requestBody = $request->getContent() ?: $this->getUrlEncodedParameterBag($request->request);

        $builder = ProcessBuilder::create()
            ->add('php-cgi')
            ->add('-d expose_php=Off')
            ->add('-d cgi.force_redirect=Off')
            ->add($filename)
            ->setInput($requestBody)
            ->setEnv('SCRIPT_NAME', '/'.$filename)
            ->setEnv('SCRIPT_FILENAME', $this->rootDir.'/'.$filename)
            ->setEnv('PATH_INFO', $request->getPathInfo())
            ->setEnv('QUERY_STRING', $request->getQueryString())
            ->setEnv('REQUEST_URI', $request->getRequestUri())
            ->setEnv('REQUEST_METHOD', $request->getMethod())
            ->setEnv('CONTENT_LENGTH', strlen($requestBody))
            ->setEnv('CONTENT_TYPE', $request->headers->get('Content-Type'))
            ->setWorkingDirectory($this->rootDir);

        foreach ($request->headers->all() as $name => $values) {
            $name = 'HTTP_'.strtoupper(str_replace('-', '_', $name));
            $builder->setEnv($name, array_shift($values));
        }

        $cookie = $this->getUrlEncodedParameterBag($request->cookies);
        $builder->setEnv('HTTP_COOKIE', $cookie);

        $process = $builder->getProcess();
        $process->start();
        $process->wait();

        list($headerList, $body) = explode("\r\n\r\n", $process->getOutput());
        $headers = $this->getHeaderMap(explode("\r\n", $headerList));
        unset($headers['Cookie']);

        $cookies = $this->getCookies(explode("\r\n", $headerList));
        $status = $this->getStatusCode($headers);

        $response = new Response($body, $status, $headers);
        foreach ($cookies as $cookie) {
            $response->headers->setCookie($cookie);
        }
        return $response;
    }

    private function getStatusCode(array $headers)
    {
        if (isset($headers['Status'])) {
            list($code) = explode(' ', $headers['Status']);
            return (int) $code;
        }

        return 200;
    }

    private function getHeaderMap(array $headerList)
    {
        $headerMap = array();
        foreach ($headerList as $item) {
            list($name, $value) = explode(': ', $item);
            $headerMap[$name] = $value;
        }
        return $headerMap;
    }

    private function getCookies(array $headerList)
    {
        $cookies = array();
        foreach ($headerList as $item) {
            list($name, $value) = explode(': ', $item);
            if ('set-cookie' === strtolower($name)) {
                $cookies[] = $this->cookieFromResponseHeaderValue($value);
            }
        }
        return $cookies;
    }

    private function cookieFromResponseHeaderValue($value)
    {
        $cookieParts = preg_split('/;\s?/', $value);
        $cookieMap = array();
        foreach ($cookieParts as $part) {
            preg_match('/(\w+)(?:=(.*)|)/', $part, $capture);
            $name = $capture[1];
            $value = isset($capture[2]) ? $capture[2] : '';

            $cookieMap[$name] = $value;
        }

        $firstKey = key($cookieMap);

        $cookieMap = array_merge($cookieMap, array(
            'secure'    => isset($cookieMap['secure']),
            'httponly'  => isset($cookieMap['httponly']),
        ));

        $cookieMap = array_merge(array(
            'expires' => 0,
            'path' => '/',
            'domain' => null,
        ), $cookieMap);

        return new Cookie(
            $firstKey,
            $cookieMap[$firstKey],
            $cookieMap['expires'],
            $cookieMap['path'],
            $cookieMap['domain'],
            $cookieMap['secure'],
            $cookieMap['httponly']
        );
    }

    private function getUrlEncodedParameterBag(ParameterBag $bag)
    {
        return http_build_query($bag->all());
    }
}
