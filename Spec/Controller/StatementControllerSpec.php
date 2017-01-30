<?php

namespace Spec\XApi\LrsBundle\Controller;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementResult;
use Xabbuh\XApi\Model\StatementsFilter;
use Xabbuh\XApi\Serializer\StatementResultSerializerInterface;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use XApi\Fixtures\Json\StatementJsonFixtures;
use XApi\Fixtures\Json\StatementResultJsonFixtures;
use XApi\LrsBundle\Model\StatementsFilterFactory;
use XApi\Repository\Api\StatementRepositoryInterface;

class StatementControllerSpec extends ObjectBehavior
{
    function let(StatementRepositoryInterface $repository, StatementSerializerInterface $statementSerializer, StatementResultSerializerInterface $statementResultSerializer, StatementsFilterFactory $statementsFilterFactory)
    {
        $statement = StatementFixtures::getAllPropertiesStatement();
        $voidedStatement = StatementFixtures::getVoidingStatement()->withStored(new \DateTime());
        $statementCollection = StatementFixtures::getStatementCollection();
        $statementsFilter = new StatementsFilter();

        $statementsFilterFactory->createFromParameterBag(Argument::type('\Symfony\Component\HttpFoundation\ParameterBag'))->willReturn($statementsFilter);

        $repository->findStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->willReturn($statement);
        $repository->findVoidedStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->willReturn($voidedStatement);
        $repository->findStatementsBy($statementsFilter)->willReturn($statementCollection);

        $statementSerializer->serializeStatement(Argument::type('\Xabbuh\XApi\Model\Statement'))->willReturn(StatementJsonFixtures::getTypicalStatement());

        $statementResultSerializer->serializeStatementResult(Argument::type('\Xabbuh\XApi\Model\StatementResult'))->willReturn(StatementResultJsonFixtures::getStatementResult());

        $this->beConstructedWith($repository, $statementSerializer, $statementResultSerializer, $statementsFilterFactory);
    }

    function it_throws_a_badrequesthttpexception_if_a_statement_id_is_not_part_of_a_put_request()
    {
        $statement = StatementFixtures::getTypicalStatement();
        $request = new Request();

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\BadRequestHttpException')
            ->during('putStatement', array($request, $statement));
    }

    function it_throws_a_badrequesthttpexception_if_the_given_statement_id_as_part_of_a_put_request_is_not_a_valid_uuid()
    {
        $statement = StatementFixtures::getTypicalStatement();
        $request = new Request();
        $request->query->set('statementId', 'invalid-uuid');

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\BadRequestHttpException')
            ->during('putStatement', array($request, $statement));
    }

    function it_stores_a_statement_and_returns_a_204_response_if_the_statement_did_not_exist_before(StatementRepositoryInterface $repository)
    {
        $statement = StatementFixtures::getTypicalStatement();
        $request = new Request();
        $request->query->set('statementId', $statement->getId()->getValue());

        $repository->findStatementById($statement->getId())->willThrow(new NotFoundException(''));
        $repository->storeStatement($statement, true)->shouldBeCalled();

        $response = $this->putStatement($request, $statement);

        $response->shouldHaveType('Symfony\Component\HttpFoundation\Response');
        $response->getStatusCode()->shouldReturn(204);
    }

    function it_throws_a_conflicthttpexception_if_the_id_parameter_and_the_statement_id_do_not_match_during_a_put_request()
    {
        $statement = StatementFixtures::getTypicalStatement();
        $statementId = StatementId::fromString('39e24cc4-69af-4b01-a824-1fdc6ea8a3af');
        $request = new Request();
        $request->query->set('statementId', $statementId->getValue());

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\ConflictHttpException')
            ->during('putStatement', array($request, $statement));
    }

    function it_uses_id_parameter_in_put_request_if_statement_id_is_null(StatementRepositoryInterface $repository)
    {
        $statement = StatementFixtures::getTypicalStatement();
        $statementId = $statement->getId();
        $statement = $statement->withId(null);
        $request = new Request();
        $request->query->set('statementId', $statementId->getValue());

        $repository->findStatementById($statementId)->willReturn($statement);
        $repository->findStatementById($statementId)->shouldBeCalled();

        $this->putStatement($request, $statement);
    }

    function it_does_not_override_an_existing_statement(StatementRepositoryInterface $repository)
    {
        $statement = StatementFixtures::getTypicalStatement();
        $request = new Request();
        $request->query->set('statementId', $statement->getId()->getValue());

        $repository->findStatementById($statement->getId())->willReturn($statement);
        $repository->storeStatement($statement, true)->shouldNotBeCalled();

        $this->putStatement($request, $statement);
    }

    function it_throws_a_conflicthttpexception_if_an_existing_statement_with_the_same_id_is_not_equal_during_a_put_request(StatementRepositoryInterface $repository)
    {
        $statement = StatementFixtures::getTypicalStatement();
        $existingStatement = StatementFixtures::getAttachmentStatement()->withId($statement->getId());
        $request = new Request();
        $request->query->set('statementId', $statement->getId()->getValue());

        $repository->findStatementById($statement->getId())->willReturn($existingStatement);

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\ConflictHttpException')
            ->during('putStatement', array($request, $statement));
    }

    function it_throws_a_badrequesthttpexception_if_the_request_has_given_statement_id_and_voided_statement_id()
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\BadRequestHttpException')
            ->during('getStatement', array($request));
    }

    function it_throws_a_badrequesthttpexception_if_the_request_has_statement_id_and_format_and_attachements_and_any_other_parameters()
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('format', 'ids');
        $request->query->set('attachments', false);
        $request->query->set('related_agents', false);

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\BadRequestHttpException')
            ->during('getStatement', array($request));
    }

    function it_throws_a_badrequesthttpexception_if_the_request_has_voided_statement_id_and_format_and_any_other_parameters_except_attachments()
    {
        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('format', 'ids');
        $request->query->set('related_agents', false);

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\BadRequestHttpException')
            ->during('getStatement', array($request));
    }

    function it_throws_a_badrequesthttpexception_if_the_request_has_statement_id_and_attachments_and_any_other_parameters_except_format()
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('attachments', false);
        $request->query->set('related_agents', false);

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\BadRequestHttpException')
            ->during('getStatement', array($request));
    }

    function it_throws_a_badrequesthttpexception_if_the_request_has_voided_statement_id_and_any_other_parameters_except_format_and_attachments()
    {
        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);
        $request->query->set('related_agents', false);

        $this
            ->shouldThrow('\Symfony\Component\HttpKernel\Exception\BadRequestHttpException')
            ->during('getStatement', array($request));
    }

    function it_sets_a_X_Experience_API_Consistent_Through_header_to_the_response()
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $response = $this->getStatement($request);

        /** @var ResponseHeaderBag $headers */
        $headers = $response->headers;

        $headers->has('X-Experience-API-Consistent-Through')->shouldBe(true);
    }

    function it_includes_a_Last_Modified_Header_if_a_single_statement_is_fetched()
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $response = $this->getStatement($request);

        /** @var ResponseHeaderBag $headers */
        $headers = $response->headers;

        $headers->has('Last-Modified')->shouldBe(true);

        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $response = $this->getStatement($request);

        /** @var ResponseHeaderBag $headers */
        $headers = $response->headers;

        $headers->has('Last-Modified')->shouldBe(true);
    }

    function it_returns_a_multipart_response_if_attachments_parameter_is_true()
    {
        $request = new Request();
        $request->query->set('attachments', true);

        $this->getStatement($request)->shouldReturnAnInstanceOf('XApi\LrsBundle\Response\MultipartResponse');
    }

    function it_returns_a_jsonresponse_if_attachments_parameter_is_false_or_not_set()
    {
        $request = new Request();

        $this->getStatement($request)->shouldReturnAnInstanceOf('\Symfony\Component\HttpFoundation\JsonResponse');

        $request->query->set('attachments', false);

        $this->getStatement($request)->shouldReturnAnInstanceOf('\Symfony\Component\HttpFoundation\JsonResponse');
    }

    function it_should_fetch_a_statement(StatementRepositoryInterface $repository)
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $repository->findStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->shouldBeCalled();

        $this->getStatement($request);
    }

    function it_should_fetch_a_voided_statement_id(StatementRepositoryInterface $repository)
    {
        $request = new Request();
        $request->query->set('voidedStatementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $repository->findVoidedStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->shouldBeCalled();

        $this->getStatement($request);
    }

    function it_should_filter_all_statements_if_no_statement_id_or_voided_statement_id_is_provided(StatementRepositoryInterface $repository)
    {
        $request = new Request();

        $repository->findStatementsBy(Argument::type('\Xabbuh\XApi\Model\StatementsFilter'))->shouldBeCalled();

        $this->getStatement($request);
    }

    function it_should_build_an_empty_statement_result_response_if_no_statement_is_found(StatementRepositoryInterface $repository, StatementResultSerializerInterface $statementResultSerializer)
    {
        $request = new Request();
        $request->query->set('statementId', StatementFixtures::DEFAULT_STATEMENT_ID);

        $repository->findStatementById(StatementId::fromString(StatementFixtures::DEFAULT_STATEMENT_ID))->willThrow('\Xabbuh\XApi\Common\Exception\NotFoundException');

        $statementResultSerializer->serializeStatementResult(new StatementResult(array()))->shouldBeCalled()->willReturn(StatementResultJsonFixtures::getStatementResult());

        $this->getStatement($request);
    }
}
