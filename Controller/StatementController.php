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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xabbuh\XApi\Common\Exception\ConflictException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use XApi\Repository\Api\StatementRepositoryInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
final class StatementController
{
    private $repository;

    public function __construct(StatementRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function putStatement(Request $request, Statement $statement)
    {
        if (null === $statementId = $request->query->get('statementId')) {
            return new Response('Required statementId parameter is missing.', 400);
        }

        try {
            $id = StatementId::fromString($statementId);
        } catch (\InvalidArgumentException $e) {
            return new Response(sprintf('Parameter statementId ("%s") is not a valid UUID.', $statementId), 400);
        }

        if (null !== $statement->getId() && !$id->equals($statement->getId())) {
            return new Response(sprintf('Id parameter ("%s") and statement id ("%s") do not match.', $id->getValue(), $statement->getId()->getValue()), 409);
        }

        try {
            $existingStatement = $this->repository->findStatementById($id);

            if (!$existingStatement->equals($statement)) {
                return new Response('The new statement is not equal to an existing statement with the same id.', 409);
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

    public function getStatement()
    {
        throw new NotFoundException('');
    }
}
