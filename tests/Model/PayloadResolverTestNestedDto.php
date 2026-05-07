<?php

declare(strict_types=1);

namespace Test\CtrlF5\ConstraintExtractor\Model;

use Symfony\Component\Validator\Constraints as Assert;

class PayloadResolverTestNestedDto
{
    #[Assert\NotBlank]
    public string $value;
}

