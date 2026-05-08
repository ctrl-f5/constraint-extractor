<?php

declare(strict_types=1);

namespace CtrlF5\ConstraintExtractor;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Valid;

class AttributeConstraintExtractor
{
    public const AUTO_SCALAR_TYPE_ASSERTIONS = 0x1;

    public function extractConstraints(
        string|object $class,
        int $flags = self::AUTO_SCALAR_TYPE_ASSERTIONS,
    ): Collection {

        $constraints = [];

        foreach ((new \ReflectionClass($class))->getProperties() as $property) {

            $propertyHasTypeAssertion = false;

            foreach ($property->getAttributes() as $attribute) {
                if (is_a($attribute->getName(), Constraint::class, true)) {
                    $attributeInstance = $attribute->newInstance();
                    $propertyHasTypeAssertion = $propertyHasTypeAssertion || $attributeInstance instanceof Type;

                    // exclude class types assertions, we want to validate the data as array
                    if ($attributeInstance instanceof Type && class_exists($attributeInstance->type)) {
                        continue;
                    }
                    // convert enum choices to raw values
                    if ($attributeInstance instanceof Choice) {
                        $attributeInstance->choices = array_map(
                            fn ($choice) => $choice instanceof \BackedEnum ? $choice->value : $choice,
                            $attributeInstance->choices,
                        );
                    }

                    $type = $property->getType();
                    // handle recursive object validation
                    if (is_a($attribute->getName(), Valid::class, true)) {
                        if (!$type instanceof \ReflectionNamedType) {
                            throw new \RuntimeException('Recursive validation requires the property to have a single type');
                        }
                        $constraints[$property->getName()] = $type->allowsNull() || $property->hasDefaultValue()
                            ? new Optional($this->extractConstraints($type->getName()))
                            : new Required($this->extractConstraints($type->getName()));
                        continue 2;
                    }

                    // handle regular property validation
                    $this->addPropertyConstraint(
                        $constraints,
                        $property->getName(),
                        $attributeInstance,
                        null === $type || $type->allowsNull() || $property->hasDefaultValue(),
                    );
                }
            }

            if ($flags & self::AUTO_SCALAR_TYPE_ASSERTIONS && !$propertyHasTypeAssertion) {
                $type = $property->getType();
                if ($type instanceof \ReflectionNamedType && in_array($type->getName(), ['int', 'float', 'string', 'bool'], true)) {
                    $this->addPropertyConstraint(
                        $constraints,
                        $property->getName(),
                        new Type($type->getName()),
                        $type->allowsNull() || $property->hasDefaultValue(),
                    );
                }
            }
        }

        return new Collection($constraints, allowExtraFields: true);
    }

    private function addPropertyConstraint(array &$constraints, string $propertyName, Constraint $constraint, bool $optional): void
    {
        if (!array_key_exists($propertyName, $constraints)) {
            $constraints[$propertyName] = $optional ? new Optional($constraint) : new Required($constraint);
        } else {
            $constraints[$propertyName]->constraints[] = $constraint;
        }
    }
}