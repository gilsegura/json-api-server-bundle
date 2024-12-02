<?php

declare(strict_types=1);

namespace JsonApi\ServerBundle\EventSubscriber;

use JsonApi\Server\Definition\Error\Error;
use JsonApi\Server\Definition\ErrorDocument;
use JsonApi\Server\Definition\Link\Link;
use JsonApi\Server\Definition\Meta\Meta;
use JsonApi\Server\Negotiation\Exception\NegotiationExceptionInterface;
use JsonApi\Server\Response\Response;
use Psr\Server\ResponseFactory\Status;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private string $environment,
        private bool $debug,
        private HttpFoundationFactoryInterface $httpFoundationFactory,
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!in_array('application/vnd.api+json', $request->getAcceptableContentTypes())) {
            return;
        }

        $exception = $event->getThrowable();

        $event->setResponse($this->httpFoundationFactory->createResponse(
            Response::error(
                ErrorDocument::document(...$this->errors($exception))
                    ->withMeta(...$this->meta($exception))
                    ->withLinks(Link::self($event->getRequest()->getRequestUri())),
                $this->code($exception)
            )
        ));
    }

    /**
     * @return Error[]
     */
    private function errors(\Throwable $exception): array
    {
        if ($exception instanceof NegotiationExceptionInterface) {
            return $exception->errors();
        }

        $formatter = static function (\Throwable $e, string $replacement): string {
            $className = explode('\\', $e::class);

            return mb_strtolower((string) preg_replace('/(?<!^)[A-Z]/', $replacement, $className[count($className) - 1]));
        };

        return [Error::error(
            (string) $exception->getCode(),
            call_user_func($formatter, $exception, '_$0'),
            mb_ucfirst(call_user_func($formatter, $exception, ' $0')),
            $exception->getMessage()
        )];
    }

    /**
     * @return Meta[]
     */
    private function meta(\Throwable $exception): array
    {
        if ($exception instanceof NegotiationExceptionInterface) {
            return [...$exception->meta(), ...$this->trace($exception)];
        }

        return [...$this->trace($exception)];
    }

    private function code(\Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        $codes = array_keys(Status::REASON_PHRASES);

        if (!in_array($exception->getCode(), $codes)) {
            return Status::INTERNAL_SERVER_ERROR;
        }

        return $exception->getCode();
    }

    /**
     * @return Meta[]
     */
    private function trace(\Throwable $exception): array
    {
        if (
            \in_array($this->environment, ['dev', 'test'], true)
            || $this->debug
        ) {
            return [Meta::kv('exception', [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => array_map(fn (array $item): array => array_intersect_key($item, array_flip(['function', 'line', 'file', 'class'])), $exception->getTrace()),
            ])];
        }

        return [];
    }
}
