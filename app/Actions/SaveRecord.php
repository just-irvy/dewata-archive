<?php

namespace App\Actions;

use App\Models\User;
use App\Support\Changes;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menyimpan satu record konten beserta catatan audit dalam satu transaksi.
 * Atribut yang tidak mass-assignable (status, pembuat) hanya masuk lewat $forced.
 */
final class SaveRecord
{
    public function __construct(private readonly RecordAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data  Data tervalidasi, masuk lewat fill().
     * @param  array<string, mixed>  $forced  Atribut yang diatur sistem pada setiap simpan.
     * @param  array<string, mixed>  $forcedOnCreate  Atribut yang diatur sistem hanya saat membuat.
     * @param  Closure(Model, array<int, string>): array<string, mixed>|null  $afterSave  Dipanggil
     *                                                                                    di dalam transaksi. Hasilnya (misalnya perubahan pivot) ikut dicatat di audit.
     */
    public function __invoke(
        Model $record,
        array $data,
        User $actor,
        array $forced = [],
        array $forcedOnCreate = [],
        ?Closure $afterSave = null,
    ): Model {
        return DB::transaction(function () use ($record, $data, $forced, $forcedOnCreate, $afterSave) {
            $creating = ! $record->exists;

            $record->fill($data);
            $record->forceFill($forced + ($creating ? $forcedOnCreate : []));

            [$before, $after] = Changes::capture($record);
            $record->save();

            $extra = $afterSave ? ($afterSave($record, array_keys($after)) ?? []) : [];

            if ($creating || $after !== [] || $extra !== []) {
                ($this->audit)(
                    Str::snake(class_basename($record)).'.'.($creating ? 'created' : 'updated'),
                    $record,
                    $creating ? null : $before,
                    $after + $extra,
                );
            }

            return $record;
        });
    }
}
