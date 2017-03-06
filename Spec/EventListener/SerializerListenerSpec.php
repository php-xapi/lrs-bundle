<?php

namespace Spec\XApi\LrsBundle\EventListener;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use XApi\Fixtures\Json\StatementJsonFixtures;

class SerializerListenerSpec extends ObjectBehavior
{
    function it_sets_unserialized_data_as_request_attributes(StatementSerializerInterface $statementSerializer, GetResponseEvent $event, Request $request, ParameterBag $attributes)
    {
        $jsonString = StatementJsonFixtures::getTypicalStatement();

        $statementSerializer->deserializeStatement($jsonString)->shouldBeCalled();
        $this->beConstructedWith($statementSerializer);

        $attributes->get('xapi_serializer')->willReturn('statement');
        $attributes->set('statement', null)->shouldBeCalled();
        $attributes->has('xapi_lrs.route')->willReturn(true);

        $request->attributes = $attributes;
        $request->getContent()->shouldBeCalled()->willReturn($jsonString);

        $event->getRequest()->willReturn($request);

        $this->onKernelRequest($event);
    }

    function it_throws_a_badrequesthttpexception_if_the_serializer_fails(StatementSerializerInterface $statementSerializer, GetResponseEvent $event, Request $request, ParameterBag $attributes)
    {
        $statementSerializer->deserializeStatement(null)->shouldBeCalled()->willThrow('\Symfony\Component\Serializer\Exception\InvalidArgumentException');
        $this->beConstructedWith($statementSerializer);

        $attributes->get('xapi_serializer')->willReturn('statement');
        $attributes->has('xapi_lrs.route')->willReturn(true);

        $request->attributes = $attributes;

        $event->getRequest()->willReturn($request);

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\BadRequestHttpException')
            ->during('onKernelRequest', array($event));
    }
}
