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

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
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

    public function __construct(StatementRepositoryInterface $repository)
    {
        $this->repository = $repository;
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
            throw new BadRequestHttpException();
        }

        if (($hasStatementId || $hasVoidedStatementId) && ($hasAttachments || $hasFormat) && $queryCount > 2) {
            throw new BadRequestHttpException();
        }

        if (($hasStatementId || $hasVoidedStatementId) && $queryCount > 1) {
            throw new BadRequestHttpException();
        }
    }
}
