<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposalDocument extends Model
{
    protected $table = 'proposal_documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'show_institutional' => 'boolean',
            'show_services' => 'boolean',
            'show_extra_services' => 'boolean',
            'show_contacts_page' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function proposta(): BelongsTo { return $this->belongsTo(Proposal::class, 'proposta_id'); }
    public function template(): BelongsTo { return $this->belongsTo(ProposalTemplate::class, 'template_id'); }
    public function options(): HasMany { return $this->hasMany(ProposalDocumentOption::class, 'proposal_document_id')->orderBy('sort_order'); }
    public function assets(): HasMany { return $this->hasMany(ProposalDocumentAsset::class, 'proposal_document_id'); }
}
