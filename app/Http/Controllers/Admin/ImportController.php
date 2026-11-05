<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CommitImport;
use App\Actions\RecordAudit;
use App\Actions\RollbackImport;
use App\Actions\StageImport;
use App\Http\Controllers\Controller;
use App\Imports\ImportFormatException;
use App\Models\ImportBatch;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ImportController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-imports');

        return view('admin.imports.index', [
            'batches' => ImportBatch::query()->with('uploader')->latest('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manage-imports');

        return view('admin.imports.create');
    }

    public function store(Request $request, StageImport $stage): RedirectResponse
    {
        Gate::authorize('manage-imports');

        $request->validate([
            // Hanya csv dan txt. Allowlist tipe berkas sesuai FILE 16 tahap 1.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.config('archive.import.max_kb')],
        ]);

        try {
            $batch = $stage($request->file('file'), $request->user());
        } catch (ImportFormatException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('admin.imports.show', $batch->id)
            ->with('status', 'Berkas dibaca. Periksa laporan di bawah sebelum menyetujui. Belum ada data yang dibuat.');
    }

    public function show(Request $request, ImportBatch $batch): View
    {
        Gate::authorize('manage-imports');

        $filter = in_array($request->query('status'), ['error', 'warning', 'valid'], true) ? $request->query('status') : null;

        return view('admin.imports.show', [
            'batch' => $batch->load('uploader'),
            'rows' => $batch->rows()
                ->when($filter, fn ($q) => $q->where('status', $filter))
                ->paginate(50)
                ->withQueryString(),
            'filter' => $filter,
        ]);
    }

    public function commit(Request $request, ImportBatch $batch, CommitImport $commit): RedirectResponse
    {
        Gate::authorize('manage-imports');

        try {
            $counts = $commit($batch, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['import' => $e->getMessage()]);
        }

        return redirect()->route('admin.imports.show', $batch->id)->with(
            'status',
            "Dibuat {$counts['created_places']} tempat dan {$counts['created_hotels']} hotel sebagai draf. Dilewati {$counts['skipped']}, tidak diproses karena error {$counts['errors']}.",
        );
    }

    public function rollback(Request $request, ImportBatch $batch, RollbackImport $rollback): RedirectResponse
    {
        Gate::authorize('rollback-import');

        try {
            $result = $rollback($batch, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['import' => $e->getMessage()]);
        }

        $message = "Rollback selesai. {$result['removed']} draf dihapus.";
        if ($result['kept'] !== []) {
            $message .= ' Dipertahankan '.count($result['kept']).' karena sudah dikerjakan editor: '
                .collect($result['kept'])->take(5)->map(fn ($k) => "{$k['name']} ({$k['reason']})")->implode(', ')
                .(count($result['kept']) > 5 ? ', dan lainnya.' : '.');
        }

        return redirect()->route('admin.imports.show', $batch->id)->with('status', $message);
    }

    /**
     * Menghapus riwayat batch dan baris stagingnya. Tempat dan hotel yang sudah dibuat tidak ikut terhapus.
     * Batch yang sudah di-commit hanya boleh dihapus super-admin, karena setelah itu Rollback tidak tersedia.
     */
    public function destroy(Request $request, ImportBatch $batch, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('manage-imports');

        if ($batch->isCommitted()) {
            Gate::authorize('rollback-import');
        }

        $audit('import_batch.deleted', $batch, [
            'file' => $batch->original_filename,
            'status' => $batch->status,
            'rows' => $batch->totals['rows'] ?? null,
        ]);

        $message = $batch->isCommitted()
            ? 'Riwayat impor dihapus. Tempat dan hotel yang sudah dibuat tetap ada.'
            : 'Impor dibatalkan dan dihapus. Tidak ada data yang dibuat.';

        $batch->delete();

        return redirect()->route('admin.imports.index')->with('status', $message);
    }
}
