<?php

namespace Test\CtrlF5\ConstraintExtractor\Model;

use Symfony\Component\Validator\Constraints as Assert;

class AttributeConstraintExtractorTestObject
{
    public mixed $noConstraints;
    public string $noConstraintsString;
    public string $noConstraintsStringWithDefault = 'default';
    public BackedStringEnum $noConstraintsEnum;
    public BackedStringEnum $noConstraintsEnumWithDefault = BackedStringEnum::SECOND;
    public ?BackedStringEnum $noConstraintsEnumNullable;
    #[Assert\NotBlank()]
    public $noType;
    #[Assert\NotBlank]
    public ?string $singleOptionalConstraint;
    #[Assert\NotBlank]
    public string $singleRequiredConstraint;
    #[Assert\NotBlank]
    #[Assert\Positive]
    public mixed $multipleConstraints;
    #[Assert\Valid]
    public AttributeConstraintExtractorTestRecursiveObject $recursiveObject;
    #[Assert\Valid]
    public ?AttributeConstraintExtractorTestRecursiveObject $recursiveOptionalObject;
}