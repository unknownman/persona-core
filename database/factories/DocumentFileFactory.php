<?php

namespace Persona\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Persona\Models\Document;
use Persona\Models\DocumentFile;

class DocumentFileFactory extends Factory
{
    protected $model = DocumentFile::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'file_path'   => 'persona/documents/' . fake()->uuid() . '.pdf',
            'disk'        => 'local',
            'side'        => fake()->optional(0.5)->randomElement(['front', 'back']),
        ];
    }
}
