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

    public function __construct(StatementRepositoryInterface $repository, StatementSerializerInterface $statementSerializer, StatementResultSerializerInterface $statementResultSerializer)
    {
        $this->repository = $repository;
        $this->statementSerializer = $statementSerializer;
        $this->statementResultSerializer = $statementResultSerializer;
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

        $statementId = $query->get('statementId');
        $voidedStatementId = $query->get('voidedStatementId');
        $hasStatementId = $statementId !== null;
        $hasVoidedStatementId = $voidedStatementId !== null;

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

        try {
            if ($hasStatementId) {
                $statements = $this->repository->findStatementById(StatementId::fromString($statementId));
            } elseif ($hasVoidedStatementId) {
                $statements = $this->repository->findVoidedStatementById(StatementId::fromString($voidedStatementId));
            } else {
                $statements = new StatementResult($this->repository->findStatementsBy($this->buildStatementsFilter($query)));
            }
        } catch (NotFoundException $e) {
            $statements = new StatementResult(array());
        }

        if ($statements instanceof Statement) {
            $json = $this->statementSerializer->serializeStatement($statements);
        } else {
            $json = $this->statementResultSerializer->serializeStatementResult($statements);
        }

        return new JsonResponse($json, 200, array(), true);
    }

    /**
     * @param ParameterBag $query
     *
     * @return StatementsFilter
     */
    private function buildStatementsFilter(ParameterBag $query)
    {
        $filter = new StatementsFilter();

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

        if (($timestamp = $query->get('since')) !== null) {
            $since = new \DateTime();
            $since->setTimestamp($timestamp);

            $filter->since($since);
        }

        if (($timestamp = $query->get('until')) !== null) {
            $until = new \DateTime();
            $until->setTimestamp($timestamp);

            $filter->until($until);
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
