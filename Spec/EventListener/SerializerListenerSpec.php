<?php

namespace Spec\XApi\LrsBundle\EventListener;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\SerializerInterface;
use XApi\Fixtures\Json\StatementJsonFixtures;

class SerializerListenerSpec extends ObjectBehavior
{
    function let(SerializerInterface $serializer)
    {
        $this->beConstructedWith($serializer);
    }

    function it_sets_unserialized_data_as_request_attributes()
    {
        $request = new Request();
    }

    function it_returns_a_400_response_if_the_serializer_fails(SerializerInterface $serializer, HttpKernelInterface $httpKernel, ParameterBag $parameterBag)
    {
        $json = StatementJsonFixtures::getTypicalStatement();
        $request = new Request();
        $request->attributes = $parameterBag;
        $event = new GetResponseEvent($httpKernel, $request, HttpKernelInterface::MASTER_REQUEST);
        $serializer->serialize($json, 'json')->willThrow(new InvalidArgumentException());

        $this->onKernelRequest($event);
    }
}
