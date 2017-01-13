<?php

namespace Spec\XApi\LrsBundle\EventListener;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Serializer\SerializerInterface;
use XApi\Fixtures\Json\StatementJsonFixtures;

class SerializerListenerSpec extends ObjectBehavior
{
    function it_sets_unserialized_data_as_request_attributes(
        SerializerInterface $serializer,
        GetResponseEvent $event,
        Request $request,
        ParameterBag $attributes
    ) {
        $jsonString = StatementJsonFixtures::getTypicalStatement();

        $serializer->deserialize($jsonString, 'Xabbuh\XApi\Model\Statement', 'json')->shouldBeCalled();
        $this->beConstructedWith($serializer);

        $attributes->get('xapi_serializer')->willReturn('statement');
        $attributes->set('statement', null)->shouldBeCalled();

        $request->attributes = $attributes;
        $request->getContent()->shouldBeCalled()->willReturn($jsonString);

        $event->getRequest()->willReturn($request);

        $this->onKernelRequest($event);
    }

    function it_throws_a_badrequesthttpexception_if_the_serializer_fails(
        SerializerInterface $serializer,
        GetResponseEvent $event,
        Request $request,
        ParameterBag $attributes
    ) {
        $serializer->deserialize(null, 'Xabbuh\XApi\Model\Statement', 'json')->shouldBeCalled()->willThrow('\Symfony\Component\Serializer\Exception\InvalidArgumentException');
        $this->beConstructedWith($serializer);

        $attributes->get('xapi_serializer')->willReturn('statement');

        $request->attributes = $attributes;

        $event->getRequest()->willReturn($request);

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\BadRequestHttpException')
            ->during('onKernelRequest', array($event));
    }
}
