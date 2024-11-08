<?php

declare(strict_types=1);

namespace JsonApi\ServerBundle\EventSubscriber;

use JsonApi\Error\Error;
use JsonApi\ErrorDocument;
use JsonApi\Exception\JsonApiExceptionInterface;
use JsonApi\Link\Link;
use JsonApi\Server\Response\Response;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
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

        if ($exception instanceof JsonApiExceptionInterface) {
            $document = ErrorDocument::document(...$exception->errors())
                ->withLinks(Link::self($event->getRequest()->getRequestUri()));

            if ([] !== $exception->meta()) {
                $document = $document->withMeta(...$exception->meta());
            }

            $event->setResponse(
                $this->httpFoundationFactory->createResponse(
                    Response::error($document, $exception->getCode())
                )
            );

            return;
        }

        $error = Error::error(
            (string) $exception->getCode(),
            (string) preg_replace('/\\\\/', '_', mb_strtolower($exception::class)),
            (string) preg_replace('/\\\\/', '_', mb_ucfirst($exception::class)),
            $exception->getMessage()
        );

        $document = ErrorDocument::document($error)
            ->withLinks(Link::self($event->getRequest()->getRequestUri()));

        $event->setResponse(
            $this->httpFoundationFactory->createResponse(
                Response::error($document)
            )
        );
    }
}
