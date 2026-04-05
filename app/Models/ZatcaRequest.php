<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ZatcaRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'client_ip',
        'client_id',
        'type',
        'payload',
        'status',
        'response',
        'zatca_uuid',
        'zatca_hash',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Scopes
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeError($query)
    {
        return $query->where('status', 'error');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByClient($query, string $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Accessors
     */
    public function getIsSuccessfulAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsFailedAttribute(): bool
    {
        return in_array($this->status, ['failed', 'error']);
    }

    public function getProcessingTimeAttribute(): ?int
    {
        if (!$this->processed_at) {
            return null;
        }

        return $this->created_at->diffInMilliseconds($this->processed_at);
    }
}