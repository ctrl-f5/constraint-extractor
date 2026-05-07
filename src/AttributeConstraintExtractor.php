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
    public function extractConstraints(string|object $class): Collection
    {
        $constraints = [];
        foreach ((new \ReflectionClass($class))->getProperties() as $property) {
            foreach ($property->getAttributes() as $attribute) {
                if (is_a($attribute->getName(), Constraint::class, true)) {
                    $attributeInstance = $attribute->newInstance();

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
                        $constraints[$property->getName()] = $type->allowsNull()
                            ? new Optional($this->extractConstraints($type->getName()))
                            : new Required($this->extractConstraints($type->getName()));
                        continue;
                    }

                    // handle regular property validation
                    if (!array_key_exists($property->getName(), $constraints)) {
                        $constraints[$property->getName()] = null === $type || $type->allowsNull()
                            ? new Optional($attributeInstance)
                            : new Required($attributeInstance);
                    } else {
                        $constraints[$property->getName()]->constraints[] = $attributeInstance;
                    }
                }
            }
        }

        return new Collection($constraints, allowExtraFields: true);
    }
}