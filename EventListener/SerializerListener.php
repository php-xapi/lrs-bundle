<?php

namespace XApi\LrsBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
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

        switch ($request->attributes->get('xapi_serializer')) {
            case 'statement':
                $request->attributes->set('statement', $this->serializer->deserialize($request->getContent(), 'Xabbuh\XApi\Model\Statement', 'json'));
                break;
        }
    }
}
