<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Serializer\ActivitySerializerInterface;
use XApi\Repository\Api\ActivityRepositoryInterface;

/**
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 */
final class GetActivityController
{
    private $repository;
    private $serializer;

    public function __construct(ActivityRepositoryInterface $repository, ActivitySerializerInterface $serializer)
    {
        $this->repository = $repository;
        $this->serializer = $serializer;
    }

    public function getActivity(Request $request)
    {
        if (null === $activityId = $request->query->get('activityId')) {
            throw new BadRequestHttpException('Required activityId parameter is missing.');
        }

        try {
            $activity = $this->repository->findActivityById(IRI::fromString($activityId));
        } catch (NotFoundException $e) {
            throw new NotFoundHttpException(sprintf('No activity matching the following id "%s" has been found.', $activityId), $e);
        }

        return new JsonResponse($this->serializer->serializeActivity($activity), 200, array(), true);
    }
}
