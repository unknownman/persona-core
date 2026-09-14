<?php

namespace Persona\Rules;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Persona\Models\Contact;
use Persona\Support\PersonaHasher;

class PersonaUniqueContactValue implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param  string  $type  The contact type, e.g. 'email' or 'phone'.
     * @param  int|string|null  $ignorePersonableId  Optionally ignore an owner
     *                                               (e.g. the current model)
     *                                               during uniqueness checks.
     * @param  \Illuminate\Contracts\Container\Container|null  $container  Optional container instance.
     */
    public function __construct(
        protected string $type,
        protected int|string|null $ignorePersonableId = null,
        protected ?Container $container = null,
    ) {
        $this->container ??= \Illuminate\Container\Container::getInstance();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $normalizedValue = $this->normalize($this->type, $value);

        $hash = PersonaHasher::hash($normalizedValue);

        $query = Contact::query()
            ->where('type', $this->type)
            ->where('value_hash', $hash);

        if ($this->ignorePersonableId !== null) {
            $query->where('personable_id', '!=', $this->ignorePersonableId);
        }

        if ($query->exists()) {
            $fail('The contact value is already taken.');
        }
    }

    /**
     * Normalize the given value through the bound normalizer for its type.
     *
     * The contract for each type is resolved from the shared
     * `persona.normalizers` config map — the same map ContactManager reads —
     * so uniqueness checks always canonicalize values identically to storage.
     */
    protected function normalize(string $type, string $value): string
    {
        $contract = config("persona.normalizers.{$type}");

        $container = $this->container ?? \Illuminate\Container\Container::getInstance();

        if ($contract !== null && $container && $container->bound($contract)) {
            return $container->make($contract)->normalize($value);
        }

        return $value;
    }
}