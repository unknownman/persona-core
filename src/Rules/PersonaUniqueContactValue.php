<?php

namespace Persona\Rules;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Persona\Contracts\EmailNormalizerContract;
use Persona\Contracts\HandleNormalizerContract;
use Persona\Contracts\PhoneNormalizerContract;
use Persona\Models\Contact;
use RuntimeException;

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

        $hashKey = config('persona.hash_key');

        if (is_null($hashKey) || $hashKey === '') {
            throw new RuntimeException(
                'PERSONA_HASH_KEY is missing or empty. Please generate a unique key and set it in your .env file to secure sensitive Persona hashes.'
            );
        }

        $normalizedValue = $this->normalize($this->type, $value);

        $hash = hash_hmac('sha256', $normalizedValue, $hashKey);

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
     */
    protected function normalize(string $type, string $value): string
    {
        $contract = match ($type) {
            'email' => EmailNormalizerContract::class,
            'phone' => PhoneNormalizerContract::class,
            'handle', 'username' => HandleNormalizerContract::class,
            default => null,
        };

        $container = $this->container ?? \Illuminate\Container\Container::getInstance();

        if ($contract !== null && $container && $container->bound($contract)) {
            return $container->make($contract)->normalize($value);
        }

        return $value;
    }
}