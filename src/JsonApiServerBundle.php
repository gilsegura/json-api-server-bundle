<?php

declare(strict_types=1);

namespace JsonApi\ServerBundle;

use JsonApi\ServerBundle\DependencyInjection\JsonApiServerExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class JsonApiServerBundle extends AbstractBundle
{
    #[\Override]
    public function getContainerExtension(): ExtensionInterface
    {
        return new JsonApiServerExtension();
    }
}
