<?php

declare(strict_types=1);

namespace CtrlF5\ConstraintExtractor;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapRequestPayload
{
    public function __construct(
        public readonly bool $validate = true,
        public readonly bool $validateRawData = true,
        public readonly bool $violationPropertyPathDotNotation = true,
    ) {
    }
}