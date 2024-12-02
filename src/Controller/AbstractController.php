<?php

declare(strict_types=1);

namespace JsonApi\ServerBundle\Controller;

use JsonApi\Server\JsonApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

abstract readonly class AbstractController
{
    abstract public function handler(ServerRequestInterface $request): ResponseInterface;

    /**
     * @return MiddlewareInterface[]
     */
    protected function middlewares(): array
    {
        return [];
    }

    final public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return (new JsonApi(...$this->middlewares()))->__invoke($request, \Closure::fromCallable([$this, 'handler']));
    }
}
