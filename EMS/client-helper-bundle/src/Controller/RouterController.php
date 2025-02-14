<?php

declare(strict_types=1);

namespace EMS\ClientHelperBundle\Controller;

use EMS\ClientHelperBundle\Helper\Cache\CacheHelper;
use EMS\ClientHelperBundle\Helper\Request\Handler;
use EMS\CommonBundle\Storage\Processor\Processor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Twig\Environment;
use Twig\Error\RuntimeError;

final class RouterController
{
    public function __construct(private readonly Handler $handler, private readonly Environment $templating, private readonly Processor $processor, private readonly CacheHelper $cacheHelper)
    {
    }

    public function handle(Request $request): Response
    {
        $result = $this->handler->handle($request);

        try {
            $response = new Response($this->templating->render($result['template'], $result['context']));
        } catch (RuntimeError $e) {
            throw $e->getPrevious() instanceof HttpException ? $e->getPrevious() : $e;
        }
        $this->cacheHelper->makeResponseCacheable($request, $response);

        return $response;
    }

    public function redirect(Request $request): Response
    {
        $result = $this->handler->handle($request);
        try {
            $json = $this->templating->render($result['template'], $result['context']);
        } catch (RuntimeError $e) {
            throw $e->getPrevious() instanceof HttpException ? $e->getPrevious() : $e;
        }

        $data = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (isset($data['path'])) {
            return new BinaryFileResponse($data['path']);
        }
        if (!isset($data['url'])) {
            throw new HttpException($data['message'] ?? 'Page not found');
        }

        return new RedirectResponse($data['url'], $data['status'] ?? 302);
    }

    public function asset(Request $request): Response
    {
        $result = $this->handler->handle($request);
        try {
            $json = $this->templating->render($result['template'], $result['context']);
        } catch (RuntimeError $e) {
            throw $e->getPrevious() instanceof HttpException ? $e->getPrevious() : $e;
        }

        $data = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (\is_string($data['config'] ?? false)) {
            $response = $this->processor->getResponse($request, $data['hash'], $data['config'], $data['filename'], $data['immutable'] ?? false);
        } else {
            $config = $this->processor->configFactory($data['hash'], $data['config'] ?? []);
            $response = $this->processor->getStreamedResponse($request, $config, $data['filename'], $data['immutable'] ?? false);
        }

        $headers = $data['headers'] ?? null;
        if (\is_array($headers)) {
            $this->applyHeaders($response, $headers);
        }

        return $response;
    }

    public function makeResponse(Request $request): Response
    {
        $result = $this->handler->handle($request);
        try {
            $json = $this->templating->render($result['template'], $result['context']);
        } catch (RuntimeError $e) {
            throw $e->getPrevious() instanceof HttpException ? $e->getPrevious() : $e;
        }

        $data = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (false === $data) {
            throw new \RuntimeException('JSON is expected with at least a content field as a string');
        }

        if (!\is_string($data['content'] ?? null)) {
            throw new \RuntimeException('JSON requires at least a content field as a string');
        }

        $response = new Response();
        $response->setContent($data['content']);

        $headers = $data['headers'] ?? ['Content-Type' => 'text/plain'];
        if (!\is_array($headers)) {
            throw new \RuntimeException('Unexpected non-array headers parameter');
        }

        $this->applyHeaders($response, $headers);

        return $response;
    }

    /**
     * @param array<string, string> $headers
     */
    private function applyHeaders(Response $response, array $headers): void
    {
        foreach ($headers as $key => $value) {
            $response->headers->add([$key => $value]);
        }
    }
}
