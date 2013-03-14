<?php

namespace Igorw\CgiHttpKernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Process\ProcessBuilder;

class CgiHttpKernel implements HttpKernelInterface
{
    private $rootDir;
    private $frontController;
    private $phpCgiBin;

    public function __construct($rootDir, $frontController = null, $phpCgiBin = 'php-cgi')
    {
        $this->rootDir = $rootDir;
        $this->frontController = $frontController;
        $this->phpCgiBin = $phpCgiBin;
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $filename = $this->frontController ?: ltrim($request->getPathInfo(), '/');

        if (!file_exists($this->rootDir.'/'.$filename)) {
            return new Response('The requested file could not be found.', 404);
        }

        $requestBody = $this->getRequestBody($request);

        if (count($request->files)) {
            $boundary = $this->getMimeBoundary();
            $request->headers->set('Content-Type', 'multipart/form-data; boundary='.$boundary);
            $requestBody = $this->encodeMultipartFiles($boundary, $request->files);
        }

        $builder = ProcessBuilder::create()
            ->add($this->phpCgiBin)
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
            ->setEnv('SYMFONY_ATTRIBUTES', serialize($request->attributes->all()))
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

        list($headerList, $body) = explode("\r\n\r\n", $process->getOutput(), 2);
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

    private function getRequestBody(Request $request)
    {
        return $request->getContent() ?: $this->getUrlEncodedParameterBag($request->request);
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

    private function encodeMultipartFiles($boundary, FileBag $files)
    {
        $mimeBoundary = '--'.$boundary."\r\n";

        $data = '';
        foreach ($files->all() as $name => $file) {
            $data .= $mimeBoundary;
            $data .= $this->encodeMultipartFile($name, $file);
            $data .= $mimeBoundary;
        }
        $data .= "\r\n";

        return $data;
    }

    private function encodeMultipartFile($name, UploadedFile $file)
    {
        $eol = "\r\n";

        $content = file_get_contents($file);

        $data = '';
        $data .= sprintf('Content-Disposition: form-data; name="%s"; filename="%s"'.$eol,
                         $name,
                         $file->getClientOriginalName());
        $data .= sprintf('Content-Type: %s'.$eol,
                         $file->getClientMimeType());
        $data .= 'Content-Transfer-Encoding: base64'.$eol.$eol;
        $data .= chunk_split(base64_encode($content)).$eol;

        return $data;
    }

    private function getMimeBoundary()
    {
        return md5('cgi-http-kernel');
    }
}
