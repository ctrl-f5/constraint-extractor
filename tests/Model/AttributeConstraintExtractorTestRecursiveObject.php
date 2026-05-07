<?php

namespace Test\CtrlF5\ConstraintExtractor\Model;

use Symfony\Component\Validator\Constraints as Assert;

class AttributeConstraintExtractorTestRecursiveObject
{
    #[Assert\Length(10)]
    #[Assert\Type('int')]
    public mixed $recursiveProp;
}