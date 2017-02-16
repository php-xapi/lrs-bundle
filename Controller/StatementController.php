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
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Activity;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementResult;
use Xabbuh\XApi\Model\StatementsFilter;
use Xabbuh\XApi\Model\Verb;
use Xabbuh\XApi\Serializer\ActorSerializerInterface;
use Xabbuh\XApi\Serializer\StatementResultSerializerInterface;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
final class StatementController
{
    private static $getParameters = array(
        'statementId' => true,
        'voidedStatementId' => true,
        'agent' => true,
        'verb' => true,
        'activity' => true,
        'registration' => true,
        'related_activities' => true,
        'related_agents' => true,
        'since' => true,
        'until' => true,
        'limit' => true,
        'format' => true,
        'attachments' => true,
        'ascending' => true,
    );

    private $repository;
    private $statementSerializer;
    private $statementResultSerializer;
    private $actorSerializer;

    public function __construct(StatementRepositoryInterface $repository, StatementSerializerInterface $statementSerializer, StatementResultSerializerInterface $statementResultSerializer, ActorSerializerInterface $actorSerializer)
    {
        $this->repository = $repository;
        $this->statementSerializer = $statementSerializer;
        $this->statementResultSerializer = $statementResultSerializer;
        $this->actorSerializer = $actorSerializer;
    }

    public function putStatement(Request $request, Statement $statement)
    {
        if (null === $statementId = $request->query->get('statementId')) {
            throw new BadRequestHttpException('Required statementId parameter is missing.');
        }

        try {
            $id = StatementId::fromString($statementId);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException(sprintf('Parameter statementId ("%s") is not a valid UUID.', $statementId), $e);
        }

        if (null !== $statement->getId() && !$id->equals($statement->getId())) {
            throw new ConflictHttpException(sprintf('Id parameter ("%s") and statement id ("%s") do not match.', $id->getValue(), $statement->getId()->getValue()));
        }

        try {
            $existingStatement = $this->repository->findStatementById($id);

            if (!$existingStatement->equals($statement)) {
                throw new ConflictHttpException('The new statement is not equal to an existing statement with the same id.');
            }
        } catch (NotFoundException $e) {
            $this->repository->storeStatement($statement, true);
        }

        return new Response('', 204);
    }

    public function postStatement(Request $request, Statement $statement)
    {
    }

    /**
     * @param Request     $request
     * @param Statement[] $statements
     */
    public function postStatements(Request $request, array $statements)
    {
    }

    /**
     * @param Request $request
     */
    public function getStatement(Request $request)
    {
        $query = new ParameterBag(\array_intersect_key($request->query->all(), self::$getParameters));

        $this->validate($query);

        try {
            if (($statementId = $query->get('statementId')) !== null) {
                $statements = $this->repository->findStatementById(StatementId::fromString($statementId));
            } elseif (($voidedStatementId = $query->get('voidedStatementId')) !== null) {
                $statements = $this->repository->findVoidedStatementById(StatementId::fromString($voidedStatementId));
            } else {
                $statements = new StatementResult($this->repository->findStatementsBy($this->buildStatementsFilter($query)));
            }
        } catch (NotFoundException $e) {
            $statements = new StatementResult(array());
        }

        $now = new \DateTime();
        $headers = array(
            'X-Experience-API-Consistent-Through' => $now->format(\DateTime::ATOM),
        );
        if ($statements instanceof Statement) {
            $json = $this->statementSerializer->serializeStatement($statements);

            $headers['Last-Modified'] = $statements->getStored()->format(\DateTime::ATOM);
        } else {
            $json = $this->statementResultSerializer->serializeStatementResult($statements);
        }

        return new JsonResponse($json, 200, $headers, true);
    }

    private function validate(ParameterBag $query)
    {
        $hasStatementId = $query->has('statementId');
        $hasVoidedStatementId = $query->has('voidedStatementId');

        if ($hasStatementId && $hasVoidedStatementId) {
            throw new BadRequestHttpException('Request must not have both statementId and voidedStatementId parameters at the same time.');
        }

        $hasAttachments = $query->has('attachments');
        $hasFormat = $query->has('format');
        $queryCount = $query->count();

        if (($hasStatementId || $hasVoidedStatementId) && $hasAttachments && $hasFormat && $queryCount > 3) {
            throw new BadRequestHttpException('Request must not contain statementId or voidedStatementId parameters, and also any other parameter besides "attachments" or "format".');
        }

        if (($hasStatementId || $hasVoidedStatementId) && ($hasAttachments || $hasFormat) && $queryCount > 2) {
            throw new BadRequestHttpException('Request must not contain statementId or voidedStatementId parameters, and also any other parameter besides "attachments" or "format".');
        }

        if (($hasStatementId || $hasVoidedStatementId) && $queryCount > 1) {
            throw new BadRequestHttpException('Request must not contain statementId or voidedStatementId parameters, and also any other parameter besides "attachments" or "format".');
        }
    }

    /**
     * @param ParameterBag $query
     *
     * @return StatementsFilter
     */
    private function buildStatementsFilter(ParameterBag $query)
    {
        $filter = new StatementsFilter();

        if (($actor = $query->get('agent')) !== null) {
            $filter->byActor($this->actorSerializer->deserializeActor($actor));
        }

        if (($verbId = $query->get('verb')) !== null) {
            $filter->byVerb(new Verb(IRI::fromString($verbId)));
        }

        if (($activityId = $query->get('activity')) !== null) {
            $filter->byActivity(new Activity(IRI::fromString($activityId)));
        }

        if (($registration = $query->get('registration')) !== null) {
            $filter->byRegistration($registration);
        }

        if ($query->filter('related_activities', false, FILTER_VALIDATE_BOOLEAN)) {
            $filter->enableRelatedActivityFilter();
        } else {
            $filter->disableRelatedActivityFilter();
        }

        if ($query->filter('related_agents', false, FILTER_VALIDATE_BOOLEAN)) {
            $filter->enableRelatedAgentFilter();
        } else {
            $filter->disableRelatedAgentFilter();
        }

        if (($since = $query->get('since')) !== null) {
            $filter->since(\DateTime::createFromFormat(\DateTime::ATOM, $since));
        }

        if (($until = $query->get('until')) !== null) {
            $filter->until(\DateTime::createFromFormat(\DateTime::ATOM, $until));
        }

        if ($query->filter('ascending', false, FILTER_VALIDATE_BOOLEAN)) {
            $filter->ascending();
        } else {
            $filter->descending();
        }

        $filter->limit($query->getInt('limit'));

        return $filter;
    }
}
