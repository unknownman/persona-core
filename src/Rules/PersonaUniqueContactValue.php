<?php

namespace Persona\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Persona\Models\Contact;
use Persona\Support\PersonaHasher;
use Persona\Support\PersonaNormalizer;

class PersonaUniqueContactValue implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param  string  $type  The contact type, e.g. 'email' or 'phone'.
     * @param  Model|int|string  $personable  The owner the uniqueness check is
     *                                        scoped to. Pass a Model to derive
     *                                        both parts of the morph pair, or a
     *                                        personable id together with
     *                                        $personableType.
     * @param  int|string|null  $ignorePersonableId  Optionally exclude a single
     *                                        owner id from the check. This is
     *                                        meant ONLY for the update scenario,
     *                                        where the row being edited belongs
     *                                        to the current owner and must not
     *                                        count against itself.
     * @param  string|null  $personableType  The morph type (class or alias) used
     *                                       when $personable is given as a bare
     *                                       id rather than a Model.
     */
    public function __construct(
        protected string $type,
        protected Model|int|string $personable,
        protected int|string|null $ignorePersonableId = null,
        protected ?string $personableType = null,
    ) {
        if (! $this->personable instanceof Model && $this->personableType === null) {
            throw new \InvalidArgumentException(
                'A personable type must be provided when PersonaUniqueContactValue is given a non-model personable.'
            );
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $normalizedValue = PersonaNormalizer::resolve($this->type, $value);

        $hash = PersonaHasher::hash($normalizedValue);

        [$personableType, $personableId] = $this->personableScope();

        $query = Contact::query()
            ->where('personable_type', $personableType)
            ->where('personable_id', $personableId)
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
     * Resolve the morph pair (type, id) the uniqueness check is scoped to.
     *
     * A Model derives both from itself; a bare id must be accompanied by an
     * explicit personable type, which is enforced at construction time.
     *
     * @return array{string, int|string}
     */
    protected function personableScope(): array
    {
        if ($this->personable instanceof Model) {
            return [$this->personable->getMorphClass(), $this->personable->getKey()];
        }

        return [$this->personableType, $this->personable];
    }
}