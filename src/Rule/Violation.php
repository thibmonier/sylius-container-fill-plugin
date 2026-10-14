<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

/** Why a rule is not met; the code is the translation key suffix (tmonier_container_fill.violation.<code>). */
final readonly class Violation
{
    public const INCOMPLETE_DATA = 'incomplete_data';

    public const UNDER_FILLED = 'under_filled';

    public const NOT_FULL = 'not_full';

    public const NOT_MULTIPLE = 'not_multiple';

    public const TOO_MANY_CONTAINERS = 'too_many_containers';

    public const OVERLOADED = 'overloaded';

    /** An enabled rule applies but none of its container types is enabled: never ignored silently. */
    public const NO_CONTAINER_TYPE = 'no_container_type';

    /** @param array<string, string|int> $parameters */
    public function __construct(
        public string $code,
        public array $parameters = [],
    ) {
    }

    /** @return array<string, string|int> parameters with the %name% keys of the translator */
    public function translationParameters(): array
    {
        $parameters = [];
        foreach ($this->parameters as $name => $value) {
            $parameters['%' . $name . '%'] = $value;
        }

        return $parameters;
    }
}
