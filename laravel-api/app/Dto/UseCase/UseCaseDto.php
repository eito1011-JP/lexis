<?php

declare(strict_types=1);

namespace App\Dto\UseCase;

abstract class UseCaseDto
{
    public static function fromArray(array $data): static
    {
        $ref = new \ReflectionClass(static::class);
        $ctor = $ref->getConstructor();
        if (! $ctor) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($ctor->getParameters() as $p) {
            $name = $p->getName();

            if (! array_key_exists($name, $data)) {
                if ($p->isDefaultValueAvailable()) {        // 例: ?string $x = null
                    $args[] = $p->getDefaultValue();
                } elseif ($p->allowsNull()) {               // 例: ?int $x
                    $args[] = null;
                } else {
                    throw new \InvalidArgumentException("Missing required field: {$name}");
                }

                continue;
            }

            $args[] = $data[$name];
        }

        return $ref->newInstanceArgs($args);
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
