<?php

namespace App\Services\Readings;

use App\Enums\ReadingType;
use App\Exceptions\ApiException;
use App\Http\Paging\ListQuery;
use App\Http\Paging\Page;
use App\Models\Reading;
use App\Models\User;
use App\Services\Subscriptions\Entitlements;
use Carbon\CarbonImmutable;

/**
 * Readings taken at home (app handover §14): the patient's own, newest first, within the window
 * the account is entitled to.
 */
class Readings
{
    private const MEASURES = [
        'blood_pressure' => ['systolic', 'diastolic', 'pulse'],
        'blood_glucose' => ['mmol_l', 'context'],
        'weight' => ['kg'],
    ];

    public function __construct(
        private readonly Entitlements $entitlements,
    ) {}

    public function allowedDays(User $patient, int $asked): int
    {
        $limit = $this->entitlements->readingsDays($patient);

        return $limit === null ? $asked : min($asked, $limit);
    }

    public function of(User $patient, ?ReadingType $type, int $windowDays, ListQuery $list): Page
    {
        $query = Reading::query()
            ->where('user_id', $patient->id)
            ->when($type !== null, fn($q) => $q->where('type', $type))
            ->where('taken_at', '>=', CarbonImmutable::now()->subDays($windowDays))
            ->orderByDesc('taken_at')
            ->orderByDesc('id');

        return $list->paginate($query);
    }

    /**
     * @param  array<string, mixed>  $data  `zLogReadingRequest`, validated
     */
    public function record(User $patient, array $data): Reading
    {
        $now = CarbonImmutable::now();
        $takenAt = isset($data['taken_at']) ? CarbonImmutable::parse($data['taken_at'])->utc() : $now;

        if ($takenAt->greaterThan($now->addMinutes(config('elevenmed.medications.future_tolerance_minutes')))) {
            throw ApiException::badRequest('VALIDATION_FAILED', 'taken_at');
        }

        $reading = new Reading([
            'user_id' => $patient->id,
            'type' => $data['type'],
            'taken_at' => $takenAt,
            'note' => $data['note'] ?? null,
        ]);

        foreach (self::MEASURES[$data['type']] as $field) {
            $reading->{$field} = $data[$field] ?? null;
        }

        $reading->save();

        return $reading;
    }

    public function delete(User $patient, string $readingId): void
    {
        $reading = Reading::query()
            ->where('user_id', $patient->id)
            ->where('uuid', $readingId)
            ->first() ?? throw ApiException::notFound(Reading::NOT_FOUND);

        $reading->delete();
    }
}
