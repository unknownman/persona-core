<?php

namespace Persona\Support;

use Illuminate\Container\Container;

class PersonaNormalizer
{
    /**
     * Normalize a raw value through the bound normalizer for its type.
     *
     * The contract for each type is resolved from the shared
     * `persona.normalizers` config map — the same map both ContactManager
     * and PersonaUniqueContactValue read — so storage and uniqueness
     * checks always canonicalize values identically.
     */
    public static function resolve(string $type, string $value): string
    {
        $contract = config("persona.normalizers.{$type}");

        $container = Container::getInstance();

        if ($contract !== null && $container->bound($contract)) {
            return $container->make($contract)->normalize($value);
        }

        return $value;
    }
}