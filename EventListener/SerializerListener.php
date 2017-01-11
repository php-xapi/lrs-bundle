<?php

namespace XApi\LrsBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface as BaseSerializerException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class SerializerListener
{
    private $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        try {
            switch ($request->attributes->get('xapi_serializer')) {
                case 'statement':
                    $request->attributes->set('statement', $this->serializer->deserialize($request->getContent(), 'Xabbuh\XApi\Model\Statement', 'json'));
                    break;
            }
        } catch (BaseSerializerException $e) {
            throw new BadRequestHttpException(
                sprintf('The content of the request cannot be deserialized into a valid xAPI %s.', $request->attributes->get('xapi_serializer')),
                $e
            );
        }
    }
}
