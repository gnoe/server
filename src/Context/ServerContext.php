<?php

/*
 * This file is part of the Drift Server
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Marc Morera <yuhu@mmoreram.com>
 */

declare(strict_types=1);

namespace Drift\Server\Context;

use Drift\Server\Adapter\DriftKernelAdapter;
use Drift\Server\Adapter\KernelAdapter;
use Exception;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class ServerContext.
 */
final class ServerContext
{
    private $environment;
    private $silent;
    private $staticFolder;
    private $debug;
    private $printHeader;
    private $disableCookies;
    private $disableFileUploads;
    private $adapter;
    private $host;
    private $port;
    private $exchanges;

    private $limitConcurrentRequests;
    private $requestBodyBuffer;
    private $allowedLoopStops;

    /**
     * @param InputInterface $input
     *
     * @return ServerContext
     *
     * @throws Exception Invalid kernel adapter
     */
    public static function buildByInput(InputInterface $input): ServerContext
    {
        $serverContext = new self();
        $serverContext->environment = $input->getOption('dev')
            ? 'dev'
            : $input->getOption('env');
        $serverContext->silent = $input->getOption('quiet');
        $serverContext->debug = $input->getOption('debug');
        $serverContext->printHeader = !$input->getOption('no-header');
        $serverContext->disableCookies = (bool) $input->getOption('no-cookies');
        $serverContext->disableFileUploads = (bool) $input->getOption('no-file-uploads');

        $adapter = $input->getOption('adapter');
        $adapter = [
                'drift' => DriftKernelAdapter::class,
            ][$adapter] ?? $adapter;

        if (!is_a($adapter, KernelAdapter::class, true)) {
            die('You must define an existing kernel adapter, or by an alias or my a namespace. This class MUST implement KernelAdapter'.PHP_EOL);
        }

        $serverContext->adapter = $adapter;

        $staticFolder = $input->getOption('static-folder', '');
        $staticFolder = $input->getOption('no-static-folder') ? null : $staticFolder;
        if (!is_null($staticFolder)) {
            $staticFolder = empty($staticFolder)
                ? $adapter::getStaticFolder()
                : $staticFolder;
        }

        if (is_string($staticFolder) && !empty($staticFolder)) {
            $staticFolder = '/'.trim($staticFolder, '/').'/';
        }

        $serverContext->staticFolder = $staticFolder;

        $path = $input->getArgument('path');
        $serverArgs = explode(':', $path, 2);
        if (2 !== count($serverArgs)) {
            throw new Exception('The path should have a host:port format - 0.0.0.0:80');
        }

        list($host, $port) = $serverArgs;
        $serverContext->host = $host;
        $serverContext->port = \intval($port);
        $serverContext->exchanges = self::buildQueueArray($input);
        $serverContext->limitConcurrentRequests = intval($input->getOption('concurrent-requests'));
        $serverContext->requestBodyBuffer = intval($input->getOption('request-body-buffer'));

        $serverContext->allowedLoopStops = intval($input->getOption('allowed-loop-stops'));

        return $serverContext;
    }

    /**
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * @return bool
     */
    public function isSilent(): bool
    {
        return $this->silent;
    }

    /**
     * @return string|null
     */
    public function getStaticFolder(): ? string
    {
        return empty($this->staticFolder)
            ? null
            : $this->staticFolder;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @return bool
     */
    public function printHeader(): bool
    {
        return $this->printHeader;
    }

    /**
     * @return bool
     */
    public function areCookiesDisabled(): bool
    {
        return $this->disableCookies;
    }

    /**
     * @return bool
     */
    public function areFileUploadsDisabled(): bool
    {
        return $this->disableFileUploads;
    }

    /**
     * @return string
     */
    public function getAdapter(): string
    {
        return $this->adapter;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return array
     */
    public function getExchanges(): array
    {
        return $this->exchanges;
    }

    /**
     * @return array
     */
    public function getPlainExchanges(): array
    {
        $array = [];
        foreach ($this->exchanges as $exchange => $queue) {
            $array[] = trim("$exchange:$queue", ':');
        }

        return $array;
    }

    /**
     * @return bool
     */
    public function hasExchanges(): bool
    {
        return !empty($this->exchanges);
    }

    /**
     * @return int
     */
    public function getLimitConcurrentRequests(): int
    {
        return $this->limitConcurrentRequests;
    }

    /**
     * @return int
     */
    public function getRequestBodyBufferInBytes(): int
    {
        return $this->requestBodyBuffer * 1024;
    }

    /**
     * @return int
     */
    public function getAllowedLoopStops(): int
    {
        return $this->allowedLoopStops;
    }

    /**
     * Build queue architecture from array of strings.
     *
     * @param InputInterface $input
     *
     * @return array
     */
    private static function buildQueueArray(InputInterface $input): array
    {
        if (!$input->hasOption('exchange')) {
            return [];
        }

        $exchanges = [];
        foreach ($input->getOption('exchange') as $exchange) {
            $exchangeParts = explode(':', $exchange, 2);
            $exchanges[$exchangeParts[0]] = $exchangeParts[1] ?? '';
        }

        return $exchanges;
    }
}
