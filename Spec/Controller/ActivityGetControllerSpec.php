<?php

namespace Spec\XApi\LrsBundle\Controller;

use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\ActivityFixtures;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Serializer\ActivitySerializerInterface;
use XApi\Fixtures\Json\ActivityJsonFixtures;
use XApi\Repository\Api\ActivityRepositoryInterface;

class ActivityGetControllerSpec extends ObjectBehavior
{
    function let(ActivityRepositoryInterface $repository, ActivitySerializerInterface $serializer)
    {
        $this->beConstructedWith($repository, $serializer);
    }

    function it_should_throws_a_badrequesthttpexception_if_an_activityid_is_not_part_of_a_get_request()
    {
        $request = new Request();

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\BadRequestHttpException')
            ->during('getActivity', array($request));
    }

    function it_should_throws_a_notfoundhttpexception_if_no_activity_matches_activityid(ActivityRepositoryInterface $repository)
    {
        $activityId = 'http://tincanapi.com/conformancetest/activityid';

        $request = new Request();
        $request->query->set('activityId', $activityId);

        $repository->findActivityById(IRI::fromString($activityId))->shouldBeCalled()->willThrow(new NotFoundException(''));

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\NotFoundHttpException')
            ->during('getActivity', array($request));
    }

    function it_should_returns_a_jsonresponse(ActivityRepositoryInterface $repository, ActivitySerializerInterface $serializer)
    {
        $activityId = 'http://tincanapi.com/conformancetest/activityid';
        $activity = ActivityFixtures::getTypicalActivity();

        $request = new Request();
        $request->query->set('activityId', $activityId);

        $repository->findActivityById(IRI::fromString($activityId))->shouldBeCalled()->willReturn($activity);
        $serializer->serializeActivity($activity)->shouldBeCalled()->willReturn(ActivityJsonFixtures::getTypicalActivity());

        $this->getActivity($request)->shouldReturnAnInstanceOf('\Symfony\Component\HttpFoundation\JsonResponse');
    }
}
