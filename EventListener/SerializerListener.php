<?php

namespace XApi\LrsBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface as BaseSerializerException;
use Xabbuh\XApi\Serializer\SerializerRegistryInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class SerializerListener
{
    private $serializerRegistry;

    public function __construct(SerializerRegistryInterface $serializerRegistry)
    {
        $this->serializerRegistry = $serializerRegistry;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        try {
            switch ($request->attributes->get('xapi_serializer')) {
                case 'statement':
                    $request->attributes->set('statement', $this->serializerRegistry->getStatementSerializer()->deserializeStatement($request->getContent()));
                    break;
            }
        } catch (BaseSerializerException $e) {
            throw new BadRequestHttpException(sprintf('The content of the request cannot be deserialized into a valid xAPI %s.', $request->attributes->get('xapi_serializer')), $e);
        }
    }
}
