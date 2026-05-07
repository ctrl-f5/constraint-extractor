<?php

declare(strict_types=1);

namespace Test\CtrlF5\ConstraintExtractor;

use CtrlF5\ConstraintExtractor\AttributeConstraintExtractor;
use CtrlF5\ConstraintExtractor\MapRequestPayload;
use CtrlF5\ConstraintExtractor\PayloadArgumentResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;
use Test\CtrlF5\ConstraintExtractor\Model\PayloadResolverTestDto;

class PayloadArgumentResolverTest extends TestCase
{
    private HttpKernel $kernel;

    protected function setUp(): void
    {
        $serializer = new Serializer(
            [new ObjectNormalizer(propertyTypeExtractor: new ReflectionExtractor())],
            [new JsonEncoder()],
        );
        $validator = Validation::createValidator();
        $extractor = new AttributeConstraintExtractor();

        $payloadResolver = new PayloadArgumentResolver($serializer, $validator, $extractor);

        $argumentResolver = new ArgumentResolver(argumentValueResolvers: [$payloadResolver]);
        $controllerResolver = new ControllerResolver();
        $dispatcher = new EventDispatcher();

        $this->kernel = new HttpKernel($dispatcher, $controllerResolver, null, $argumentResolver);
    }

    public function testResolverTriggersForControllerWithMapRequestPayload(): void
    {
        $request = Request::create('/test', 'POST', [], [], [], [], json_encode(['name' => 'hello', 'nested' => ['value' => 'x']]));
        $request->attributes->set('_controller', [TestController::class, 'action']);

        $response = $this->kernel->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('hello', $response->getContent());
    }

    public function testResolverRejectsInvalidPayload(): void
    {
        $request = Request::create('/test', 'POST', [], [], [], [], json_encode(['name' => '']));
        $request->attributes->set('_controller', [TestController::class, 'action']);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/name: This value should not be blank./');

        $this->kernel->handle($request);
    }

    public function testNestedValidationErrorsUseDotNotation(): void
    {
        $request = Request::create('/test', 'POST', [], [], [], [], json_encode([
            'name' => 'hello',
            'nested' => ['value' => ''],
        ]));
        $request->attributes->set('_controller', [TestController::class, 'action']);

        try {
            $this->kernel->handle($request);
            self::fail('Expected BadRequestHttpException');
        } catch (BadRequestHttpException $e) {
            $validationFailedException = $e->getPrevious();
            self::assertInstanceOf(ValidationFailedException::class, $validationFailedException);
            self::assertCount(1, $validationFailedException->getViolations());
            self::assertSame('nested.value', $validationFailedException->getViolations()[0]->getPropertyPath());
        }
    }
}

class TestController
{
    public function action(#[MapRequestPayload] PayloadResolverTestDto $payload): Response
    {
        return new Response($payload->name);
    }
}


