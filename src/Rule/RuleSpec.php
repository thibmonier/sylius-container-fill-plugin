<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Rule;

use Tmonier\SyliusContainerFillPlugin\Entity\ContainerRuleInterface;

/** What a rule requires (mode + threshold), without its scope. */
final readonly class RuleSpec
{
    public function __construct(
        public string $mode,
        public ?int $threshold = null,
        public ?int $id = null,
        public string $name = '',
    ) {
        if (!\in_array($mode, ContainerRuleInterface::MODES, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown rule mode "%s".', $mode));
        }
    }

    public static function fromRule(ContainerRuleInterface $rule): self
    {
        return new self($rule->getMode(), $rule->getThreshold(), $rule->getId(), (string) $rule->getName());
    }

    /** Fill required for every container (0..1), null when the mode does not require one. */
    public function requiredFill(): ?float
    {
        return match ($this->mode) {
            ContainerRuleInterface::MODE_FULL_ONLY => 1.0,
            ContainerRuleInterface::MODE_MIN_FILL_PERCENT => min(100, max(0, $this->threshold ?? 100)) / 100,
            default => null,
        };
    }
}
