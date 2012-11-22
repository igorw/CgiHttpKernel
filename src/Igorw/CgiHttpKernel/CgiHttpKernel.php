<?php

namespace Igorw\CgiHttpKernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Process\ProcessBuilder;

class CgiHttpKernel implements HttpKernelInterface
{
    private $rootDir;

    public function __construct($rootDir)
    {
        $this->rootDir = $rootDir;
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $filename = ltrim($request->getPathInfo(), '/');

        if (!file_exists($this->rootDir.'/'.$filename)) {
            return new Response('The requested file could not be found.', 404);
        }

        $process = ProcessBuilder::create()
            ->add('php-cgi')
            ->add('-d expose_php=Off')
            ->add($filename)
            ->setWorkingDirectory($this->rootDir)
            ->getProcess();

        $process->start();
        $process->wait();

        list($headerList, $body) = explode("\r\n\r\n", $process->getOutput());
        $headers = $this->getHeaderMap(explode("\n", $headerList));

        return new Response($body, 200, $headers);
    }

    public function getHeaderMap(array $headerList)
    {
        $headerMap = array();
        foreach ($headerList as $item) {
            list($name, $value) = explode(': ', $item);
            $headerMap[$name] = $value;
        }
        return $headerMap;
    }
}
