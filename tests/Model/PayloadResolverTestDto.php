<?php

declare(strict_types=1);

namespace Test\CtrlF5\ConstraintExtractor\Model;

use Symfony\Component\Validator\Constraints as Assert;

class PayloadResolverTestDto
{
    #[Assert\NotBlank]
    public string $name;

    public ?string $optional = null;

    #[Assert\Valid]
    public PayloadResolverTestNestedDto $nested;
}
