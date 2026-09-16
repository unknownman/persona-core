<?php

namespace Persona\Managers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Persona\Events\PersonaDataWiped;
use Persona\Support\DocumentFileCleaner;

class PersonaManager
{
    /**
     * The tables holding data keyed to "personable" morph columns.
     */
    public const PERSONABLE_TABLES = [
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
     * Physical document files are removed from storage AFTER the database
     * transaction commits, so a rollback cannot leave orphaned rows pointing
     * at already-deleted files. (The document_file rows themselves cascade
     * away with their parent document.)
     */
    public function forgetAll(Model $personable): void
    {
        $type = $personable->getMorphClass();
        $id = $personable->getKey();
        $tables = config('persona.tables', []);

        $pendingFiles = $this->collectPhysicalDocumentFiles($tables, $type, $id);

        DB::transaction(function () use ($personable, $tables, $type, $id) {
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

        DocumentFileCleaner::deleteFilesFor($pendingFiles);
    }

    /**
     * Collect the physical file paths of every document owned by the entity.
     *
     * Queries the document_files table via the documents join so the paths
     * can be removed from storage AFTER the database transaction commits.
     * Running this inside the transaction would mean a rollback cannot
     * recover the already-deleted physical files.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function collectPhysicalDocumentFiles(array $tables, string $type, int|string $id)
    {
        if (! isset($tables['documents']) || ! isset($tables['document_files'])) {
            return collect();
        }

        $documents = $tables['documents'];
        $documentFiles = $tables['document_files'];

        return DB::table($documentFiles)
            ->join($documents, "{$documentFiles}.document_id", '=', "{$documents}.id")
            ->where("{$documents}.personable_type", $type)
            ->where("{$documents}.personable_id", $id)
            ->select("{$documentFiles}.file_path", "{$documentFiles}.disk")
            ->get();
    }
}