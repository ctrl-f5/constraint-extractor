<?php

declare(strict_types=1);

namespace Test\CtrlF5\ConstraintExtractor;

use CtrlF5\ConstraintExtractor\AttributeConstraintExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Test\CtrlF5\ConstraintExtractor\Model\AttributeConstraintExtractorTestObject;

class AttributeConstraintExtractorTest extends TestCase
{
    private AttributeConstraintExtractor $extractor;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new AttributeConstraintExtractor();
        $this->validator = Validation::createValidator();
    }

    public function testCanExtractConstraints(): void
    {
        $collection = $this->extractor->extractConstraints(AttributeConstraintExtractorTestObject::class);
        $constraints = $collection->fields;

        self::assertSame([
            'noConstraintsString',
            'noConstraintsStringWithDefault',
            'noType',
            'singleOptionalConstraint',
            'singleRequiredConstraint',
            'multipleConstraints',
            'recursiveObject',
            'recursiveOptionalObject',
        ], array_keys($constraints));

        self::assertInstanceOf(Assert\Optional::class, $constraints['noType']);
        self::assertCount(1, $constraints['noType']->constraints);
        self::assertInstanceOf(Assert\NotBlank::class, $constraints['noType']->constraints[0]);

        self::assertInstanceOf(Assert\Required::class, $constraints['noConstraintsString']);
        self::assertCount(1, $constraints['noConstraintsString']->constraints);
        self::assertInstanceOf(Assert\Type::class, $constraints['noConstraintsString']->constraints[0]);
        self::assertSame('string', $constraints['noConstraintsString']->constraints[0]->type);

        self::assertInstanceOf(Assert\Optional::class, $constraints['noConstraintsStringWithDefault']);
        self::assertCount(1, $constraints['noConstraintsStringWithDefault']->constraints);
        self::assertInstanceOf(Assert\Type::class, $constraints['noConstraintsStringWithDefault']->constraints[0]);
        self::assertSame('string', $constraints['noConstraintsStringWithDefault']->constraints[0]->type);

        self::assertInstanceOf(Assert\Optional::class, $constraints['singleOptionalConstraint']);
        self::assertCount(2, $constraints['singleOptionalConstraint']->constraints);
        self::assertInstanceOf(Assert\NotBlank::class, $constraints['singleOptionalConstraint']->constraints[0]);
        self::assertInstanceOf(Assert\Type::class, $constraints['singleOptionalConstraint']->constraints[1]);
        self::assertSame('string', $constraints['singleOptionalConstraint']->constraints[1]->type);

        self::assertInstanceOf(Assert\Required::class, $constraints['singleRequiredConstraint']);
        self::assertCount(2, $constraints['singleRequiredConstraint']->constraints);
        self::assertInstanceOf(Assert\NotBlank::class, $constraints['singleRequiredConstraint']->constraints[0]);
        self::assertInstanceOf(Assert\Type::class, $constraints['singleRequiredConstraint']->constraints[1]);
        self::assertSame('string', $constraints['singleRequiredConstraint']->constraints[1]->type);

        self::assertInstanceOf(Assert\Optional::class, $constraints['multipleConstraints']);
        self::assertCount(2, $constraints['multipleConstraints']->constraints);
        self::assertInstanceOf(Assert\NotBlank::class, $constraints['multipleConstraints']->constraints[0]);
        self::assertInstanceOf(Assert\Positive::class, $constraints['multipleConstraints']->constraints[1]);

        self::assertInstanceOf(Assert\Required::class, $constraints['recursiveObject']);
        self::assertCount(1, $constraints['recursiveObject']->constraints);
        self::assertInstanceOf(Assert\Collection::class, $constraints['recursiveObject']->constraints[0]);
        self::assertInstanceOf(Assert\Optional::class, $constraints['recursiveObject']->constraints[0]->fields['recursiveProp']);
        self::assertCount(2, $constraints['recursiveObject']->constraints[0]->fields['recursiveProp']->constraints);
        self::assertInstanceOf(Assert\Length::class, $constraints['recursiveObject']->constraints[0]->fields['recursiveProp']->constraints[0]);
        self::assertInstanceOf(Assert\Type::class, $constraints['recursiveObject']->constraints[0]->fields['recursiveProp']->constraints[1]);

        self::assertInstanceOf(Assert\Optional::class, $constraints['recursiveOptionalObject']);
        self::assertCount(1, $constraints['recursiveOptionalObject']->constraints);
        self::assertInstanceOf(Assert\Collection::class, $constraints['recursiveOptionalObject']->constraints[0]);
    }

    public function testCanValidateFromExtractedConstraints(): void
    {
        $constraints = $this->extractor->extractConstraints(AttributeConstraintExtractorTestObject::class);
        $value = [
            'noConstraintsString' => '',
            'noType' => 'not blank',
            'singleRequiredConstraint' => 'not blank',
            'multipleConstraints' => 2,
            'recursiveObject' => [
                'recursiveProp' => 1234567890,
            ],
        ];

        $result = $this->validator->validate($value, $constraints);

        $this->assertEmpty($result);

        $value['recursiveOptionalObject'] = ['recursiveProp' => 'test'];
        $result = $this->validator->validate($value, $constraints);

        $this->assertCount(2, $result);
        $this->assertSame('This value should have exactly 10 characters.', $result->get(0)->getMessage());
        $this->assertSame('This value should be of type int.', $result->get(1)->getMessage());
    }
}