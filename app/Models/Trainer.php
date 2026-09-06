<?php

namespace App\Models;

use Database\Factories\TrainerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'active'])]
class Trainer extends Model
{
    /** @use HasFactory<TrainerFactory> */
    use HasFactory;

    protected $attributes = ['active' => true];

    public function personalTrainingMembers(): HasMany
    {
        return $this->hasMany(PersonalTrainingMember::class);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
