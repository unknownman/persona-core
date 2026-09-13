<?php

namespace Persona\Managers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Persona\Events\PersonaDataWiped;

class PersonaManager
{
    /**
     * The tables holding data keyed to "personable" morph columns.
     */
    protected const PERSONABLE_TABLES = [
        'profiles',
        'social_accounts',
        'contacts',
        'addresses',
        'documents',
        'physical_attributes',
        'legal_details',
    ];

    public function __construct(
        protected ContactManager $contacts,
        protected DocumentManager $documents,
        protected RelationshipManager $relationships,
        protected ProfileManager $profiles,
        protected AddressManager $addresses,
        protected SocialAccountManager $socialAccounts,
        protected PhysicalAttributeManager $physicalAttributes,
        protected LegalDetailManager $legalDetails,
    ) {}

    public function contacts(): ContactManager
    {
        return $this->contacts;
    }

    public function documents(): DocumentManager
    {
        return $this->documents;
    }

    public function relationships(): RelationshipManager
    {
        return $this->relationships;
    }

    public function profiles(): ProfileManager
    {
        return $this->profiles;
    }

    public function addresses(): AddressManager
    {
        return $this->addresses;
    }

    public function socialAccounts(): SocialAccountManager
    {
        return $this->socialAccounts;
    }

    public function physicalAttributes(): PhysicalAttributeManager
    {
        return $this->physicalAttributes;
    }

    public function legalDetails(): LegalDetailManager
    {
        return $this->legalDetails;
    }

    /**
     * Wipe the complete Persona footprint for a personable model.
     *
     * Deletes every row across the package's tables that references the
     * given model, either as the owner ("personable") or as the target of
     * a relationship ("related_personable").
     *
     * Physical document files are removed from storage before the database
     * rows are dropped, so no orphaned files are ever left behind. (The
     * document_file rows themselves cascade away with their parent document.)
     */
    public function forgetAll(Model $personable): void
    {
        DB::transaction(function () use ($personable) {
            $type = $personable->getMorphClass();
            $id = $personable->getKey();

            $tables = config('persona.tables', []);

            $this->deletePhysicalDocumentFiles($tables, $type, $id);

            foreach (self::PERSONABLE_TABLES as $key) {
                if (isset($tables[$key])) {
                    DB::table($tables[$key])
                        ->where('personable_type', $type)
                        ->where('personable_id', $id)
                        ->delete();
                }
            }

            if (isset($tables['relationships'])) {
                $relationships = $tables['relationships'];

                DB::table($relationships)
                    ->where(function ($query) use ($type, $id) {
                        $query->where('personable_type', $type)
                            ->where('personable_id', $id);
                    })
                    ->orWhere(function ($query) use ($type, $id) {
                        $query->where('related_personable_type', $type)
                            ->where('related_personable_id', $id);
                    })
                    ->delete();
            }

            PersonaDataWiped::dispatch($personable);
        });
    }

    /**
     * Delete the physical files of every document owned by the entity.
     *
     * Queries the document_files table via the documents join and removes
     * each file from its own disk. Runs before any document rows are deleted
     * so the file paths are still resolvable.
     *
     * @param  array<string, mixed>  $tables
     */
    protected function deletePhysicalDocumentFiles(array $tables, string $type, int|string $id): void
    {
        if (! isset($tables['documents']) || ! isset($tables['document_files'])) {
            return;
        }

        $documents = $tables['documents'];
        $documentFiles = $tables['document_files'];

        $files = DB::table($documentFiles)
            ->join($documents, "{$documentFiles}.document_id", '=', "{$documents}.id")
            ->where("{$documents}.personable_type", $type)
            ->where("{$documents}.personable_id", $id)
            ->select("{$documentFiles}.file_path", "{$documentFiles}.disk")
            ->get();

        foreach ($files as $file) {
            Storage::disk($file->disk)->delete($file->file_path);
        }
    }
}