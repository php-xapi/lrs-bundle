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
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementResult;
use Xabbuh\XApi\Serializer\StatementResultSerializerInterface;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use XApi\LrsBundle\Model\StatementsFilterFactory;
use XApi\LrsBundle\Response\AttachmentResponse;
use XApi\LrsBundle\Response\MultipartResponse;
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
    private $statementsFilterFactory;

    public function __construct(StatementRepositoryInterface $repository, StatementSerializerInterface $statementSerializer, StatementResultSerializerInterface $statementResultSerializer, StatementsFilterFactory $statementsFilterFactory)
    {
        $this->repository = $repository;
        $this->statementSerializer = $statementSerializer;
        $this->statementResultSerializer = $statementResultSerializer;
        $this->statementsFilterFactory = $statementsFilterFactory;
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

        $includeAttachments = $query->filter('attachments', false, FILTER_VALIDATE_BOOLEAN);
        try {
            if (($statementId = $query->get('statementId')) !== null) {
                $statement = $this->repository->findStatementById(StatementId::fromString($statementId));

                $response = $this->buildSingleStatementResponse($statement, $includeAttachments);
            } elseif (($voidedStatementId = $query->get('voidedStatementId')) !== null) {
                $statement = $this->repository->findVoidedStatementById(StatementId::fromString($voidedStatementId));

                $response = $this->buildSingleStatementResponse($statement, $includeAttachments);
            } else {
                $statements = $this->repository->findStatementsBy($this->statementsFilterFactory->createFromParameterBag($query));

                $response = $this->buildMultiStatementsResponse($statements, $includeAttachments);
            }
        } catch (NotFoundException $e) {
            $response = $this->buildMultiStatementsResponse(array());
        }

        $now = new \DateTime();
        $response->headers->set('X-Experience-API-Consistent-Through', $now->format(\DateTime::ATOM));

        return $response;
    }

    protected function buildSingleStatementResponse(Statement $statement, $includeAttachments = false)
    {
        $json = $this->statementSerializer->serializeStatement($statement);

        $response = new JsonResponse($json, 200, array(), true);

        if ($includeAttachments) {
            $response = $this->buildMultipartResponse($response, array($statement));
        }

        $response->headers->set('Last-Modified', $statement->getStored()->format(\DateTime::ATOM));

        return $response;
    }

    protected function buildMultiStatementsResponse(array $statements, $includeAttachments = false)
    {
        $json = $this->statementResultSerializer->serializeStatementResult(new StatementResult($statements));

        $response = new JsonResponse($json, 200, array(), true);

        if ($includeAttachments) {
            $response = $this->buildMultipartResponse($response, $statements);
        }

        return $response;
    }

    protected function buildMultipartResponse(JsonResponse $statementResponse, array $statements)
    {
        $attachmentsParts = array();

        foreach ($statements as $statement) {
            foreach ((array) $statement->getAttachments() as $attachment) {
                $attachmentsParts[] = new AttachmentResponse($attachment);
            }
        }

        return new MultipartResponse($statementResponse, $attachmentsParts);
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
}
