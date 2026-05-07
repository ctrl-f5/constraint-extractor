<?php

namespace CtrlF5\ConstraintExtractor;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PayloadArgumentResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly AttributeConstraintExtractor $extractor,
    ) {
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return 1 === count($argument->getAttributes(MapRequestPayload::class));
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        /** @var class-string|null $type */
        $type = $argument->getType();

        if (null === $type || !class_exists($type)) {
            throw new \RuntimeException('parameters that use MapRequestPayload must have a class type');
        }

        $attributes = $argument->getAttributes(MapRequestPayload::class);

        if (1 !== count($attributes)) {
            throw new \RuntimeException('parameter must have exactly 1 MapRequestPayload attribute');
        }

        /** @var MapRequestPayload $config */
        $config = array_pop($attributes);

        if ($config->validateRawData) {
            try {
                $rawData = json_decode(
                    (string) $request->getContent(),
                    associative: true,
                    flags: JSON_THROW_ON_ERROR,
                );
            } catch (\Throwable $e) {
                throw new BadRequestHttpException('Invalid JSON payload', $e);
            }

            $constraints = $this->extractor->extractConstraints($type);
            $errors = $this->validator->validate($rawData, $constraints);

            if (count($errors) > 0) {

                if ($config->violationPropertyPathDotNotation) {
                    $errors = $this->rebuildListWithDotNotationPaths($errors);
                }

                throw new BadRequestHttpException(
                    $this->getFormattedErrors($errors),
                    new ValidationFailedException(null, $errors),
                );
            }
        }

        try {
            $value = $this->serializer->deserialize($request->getContent(), $type, 'json');
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Invalid JSON', $e);
        }

        if ($config->validate && !$config->validateRawData) {
            $errors = $this->validator->validate($value);

            if (count($errors) > 0) {
                throw new BadRequestHttpException(
                    $this->getFormattedErrors($errors),
                    new ValidationFailedException(null, $errors),
                );
            }
        }

        yield $value;
    }

    private function getFormattedErrors(ConstraintViolationListInterface $errors): string
    {
        $formatted = [];

        /** @var  $error */
        foreach($errors as $error) {
            $formatted[] = "{$error->getPropertyPath()}: {$error->getMessage()}";
        }

        return implode(', ', $formatted);
    }

    private function rebuildListWithDotNotationPaths(ConstraintViolationListInterface $errors): ConstraintViolationList
    {
        $corrected = new ConstraintViolationList();
        foreach ($errors as $error) {
            $corrected->add(new ConstraintViolation(
                $error->getMessage(),
                $error->getMessageTemplate(),
                $error->getParameters(),
                $error->getRoot(),
                str_replace(['][', '[', ']'], ['.', '', ''], $error->getPropertyPath()),
                $error->getInvalidValue(),
                $error->getPlural(),
                $error->getCode(),
                $error->getConstraint(),
                $error->getCause(),
            ));
        }

        return $corrected;
    }
}