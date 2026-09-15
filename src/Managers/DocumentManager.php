<?php

namespace Persona\Managers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Persona\Contracts\DocumentVerificationProvider;
use Persona\Events\DocumentAdded;
use Persona\Events\DocumentStatusUpdated;
use Persona\Events\DocumentVerificationRequested;
use Persona\Models\Document;
use Persona\Models\DocumentFile;
use Persona\Notifications\DocumentStatusNotification;
use Persona\Support\DocumentFileCleaner;
use Persona\Support\PersonaHasher;

class DocumentManager
{
    public function __construct(
        protected ?Container $app = null,
    ) {
        $this->app ??= \Illuminate\Container\Container::getInstance();
    }

    /**
     * Add a document (passport, national ID, ...) for a personable model.
     *
     * The raw $number is passed to the model; the encrypted and lookup-hash
     * casts take care of storage and unique lookups respectively. Documents
     * always start with the configured initial status (see
     * `persona.document_statuses.initial`) — callers can never dictate the
     * verification status through $metadata.
     *
     * @param  array<string, mixed>  $metadata  Extra columns, e.g.
     *                                          ['country_code' => 'US',
     *                                           'issued_at' => '2024-01-01'].
     *
     * @throws \InvalidArgumentException  When the type is not in the configured vocabulary.
     */
    public function add(
        Model $personable,
        string $type,
        string $number,
        array $metadata = [],
    ): Document {
        $this->assertAllowedType($type);

        $properties = array_merge(
            $this->map($metadata),
            [
                'type' => $type,
                'number' => $number,
                'number_hash' => $number,
                'status' => config('persona.document_statuses.initial', 'pending'),
            ],
        );

        return DB::transaction(function () use ($personable, $properties, $type, $number) {
            // The unique index is (personable_type, personable_id, type,
            // country_code, number_hash) and it covers soft-deleted rows too.
            // A previously-trashed twin must therefore be matched (and
            // restored) instead of attempting a fresh insert.
            $countryCode = $properties['country_code'] ?? null;

            $existing = Document::withTrashed()
                ->where('personable_type', $personable->getMorphClass())
                ->where('personable_id', $personable->getKey())
                ->where('type', $type)
                ->where('number_hash', $this->lookupHash($number))
                ->when(
                    $countryCode === null,
                    fn ($query) => $query->whereNull('country_code'),
                    fn ($query) => $query->where('country_code', $countryCode),
                )
                ->first();

            if ($existing) {
                if (! $existing->trashed()) {
                    throw new \InvalidArgumentException(
                        __('This document is already registered for this entity.')
                    );
                }

                $existing->restore();
                $existing->fill($properties)->save();

                DocumentAdded::dispatch($existing);

                return $existing;
            }

            $document = new Document($properties);

            $document->personable_type = $personable->getMorphClass();
            $document->personable_id = $personable->getKey();
            $document->save();

            DocumentAdded::dispatch($document);

            return $document;
        });
    }

    /**
     * Associate a stored file with a document.
     *
     * @param  Model        $personable  The entity that owns the document.
     * @param  Document     $document    A document that belongs to $personable.
     * @param  string       $filePath    Path of the physical file on the given disk.
     * @param  string|null  $disk        Storage disk the file lives on (defaults to config).
     * @param  string|null  $side        e.g. 'front' / 'back' for identity documents.
     *
     * @throws \InvalidArgumentException  When the document does not belong to the given entity.
     */
    public function attachFile(
        Model $personable,
        Document $document,
        string $filePath,
        ?string $disk = null,
        ?string $side = null,
    ): DocumentFile {
        $this->assertOwnership($personable, $document);

        $disk ??= config('persona.storage.disk', 'local');

        $file = new DocumentFile([
            'file_path' => $filePath,
            'disk' => $disk,
            'side' => $side,
        ]);

        $file->document_id = $document->getKey();
        $file->save();

        return $file;
    }

    /**
     * Delete a document (soft-delete) after removing its physical files from
     * storage, verifying ownership first.
     *
     * @throws \InvalidArgumentException  When the document does not belong to the given entity.
     */
    public function delete(Model $personable, Document $document): bool
    {
        $this->assertOwnership($personable, $document);

        DocumentFileCleaner::deleteFilesFor($document->files);

        return (bool) $document->delete();
    }

    /**
     * Assert that the document type is part of the configured vocabulary.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertAllowedType(string $type): void
    {
        if (! in_array($type, config('persona.document_types', []), true)) {
            throw new \InvalidArgumentException(
                __("The document type '{$type}' is not allowed by the persona configuration.")
            );
        }
    }

    /**
     * Assert that a Document is owned by the given personable entity.
     *
     * Must be called before ANY mutation that accepts an external Document
     * instance to prevent cross-entity IDOR attacks.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertOwnership(Model $personable, Document $document): void
    {
        if (
            $document->personable_type !== $personable->getMorphClass()
            || (string) $document->personable_id !== (string) $personable->getKey()
        ) {
            throw new \InvalidArgumentException(
                __('The given document does not belong to this entity.')
            );
        }
    }

    /**
     * Compute the lookup hash for a raw document number.
     *
     * Delegates to PersonaHasher so the restore-on-duplicate query matches
     * the hash that will actually be persisted by the cast.
     */
    protected function lookupHash(string $number): string
    {
        return PersonaHasher::hash($number);
    }

    /**
     * Reduce the incoming metadata to the explicit whitelist, protecting
     * internal columns from raw array mass-assignment.
     *
     * The whitelist lives in `persona.fillable.document` so the host
     * application can extend it without touching this manager.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function map(array $metadata): array
    {
        return array_intersect_key($metadata, array_flip(config('persona.fillable.document', [])));
    }

    /**
     * Update the status of a document, dispatching status events and notifications.
     *
     * This method does not perform authorization. Hosts MUST guard access to
     * this method themselves (e.g. via a Policy) before calling it.
     */
    public function updateStatus(Document $document, string $status): void
    {
        DocumentVerificationRequested::dispatch($document, $status);

        $oldStatus = (string) $document->status;
        $document->update(['status' => $status]);

        DocumentStatusUpdated::dispatch($document, $oldStatus, $status);

        $notifiable = $document->personable;
        if ($notifiable && method_exists($notifiable, 'notify')) {
            $notifiable->notify(new DocumentStatusNotification($document));
        } elseif ($notifiable) {
            Notification::send($notifiable, new DocumentStatusNotification($document));
        }
    }

    /**
     * Verify a document against the bound DocumentVerificationProvider contract.
     *
     * This method does not perform authorization. Hosts MUST guard access to
     * this method themselves (e.g. via a Policy) before calling it.
     */
    public function verify(Document $document): bool
    {
        DocumentVerificationRequested::dispatch($document);

        $provider = $this->app->make(DocumentVerificationProvider::class);
        $verified = $provider->verify($document);

        $this->updateStatus(
            $document,
            $verified
                ? config('persona.document_statuses.verified', 'verified')
                : config('persona.document_statuses.rejected', 'rejected')
        );

        return $verified;
    }
}