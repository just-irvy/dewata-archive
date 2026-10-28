<?php

namespace App\Actions;

use App\Content\CollectionReadiness;
use App\Content\HotelReadiness;
use App\Content\PlaceReadiness;
use App\Content\Readiness;
use App\Enums\PublishStatus;
use App\Exceptions\NotReadyToPublish;
use App\Models\Collection;
use App\Models\Hotel;
use App\Models\Place;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Satu-satunya jalur mengubah status terbit. Menerbitkan wajib lolos pemeriksaan kesiapan. */
final class ChangeStatus
{
    public function __construct(private readonly RecordAudit $audit) {}

    public static function readinessFor(Model $record): Readiness
    {
        return match (true) {
            $record instanceof Place => new PlaceReadiness,
            $record instanceof Hotel => new HotelReadiness,
            $record instanceof Collection => new CollectionReadiness,
        };
    }

    public function __invoke(Model $record, PublishStatus $to): void
    {
        $from = $record->status;

        if ($from === $to) {
            return;
        }

        if ($to === PublishStatus::Published) {
            $report = self::readinessFor($record)->check($record);

            if (! $report->passes()) {
                throw new NotReadyToPublish($report);
            }
        }

        DB::transaction(function () use ($record, $from, $to) {
            $record->forceFill(['status' => $to]);

            if ($to === PublishStatus::Published && $record->published_at === null) {
                $record->forceFill(['published_at' => now()]);
            }
            if ($record instanceof Place) {
                $record->forceFill(['updated_by' => request()->user()?->getKey()]);
            }

            $record->save();

            ($this->audit)(
                Str::snake(class_basename($record)).'.'.$to->value,
                $record,
                ['status' => $from->value],
                ['status' => $to->value],
            );
        });
    }
}
