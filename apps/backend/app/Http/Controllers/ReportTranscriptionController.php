<?php

namespace App\Http\Controllers;

use App\Services\Governance;
use App\Services\SourceMappingDictionary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReportTranscriptionController extends Controller
{
    public function index(Request $request, SourceMappingDictionary $scope)
    {
        $data = $request->validate(['facility_id' => 'required|uuid|exists:facilities,id']);
        $scope->requireScope($request, 'imports.read', $data['facility_id']);

        return response()->json($this->entries()->where('t.facility_id', $data['facility_id'])
            ->orderByDesc('t.created_at')->orderBy('t.id')->paginate(25));
    }

    public function store(Request $request, SourceMappingDictionary $scope)
    {
        $data = $request->validate([
            'facility_id' => 'required|uuid|exists:facilities,id',
            'source' => 'required|file|mimes:pdf|max:20480',
            'page' => 'required|integer|min:1|max:10000',
            'locator' => 'required|string|max:160',
            'source_text' => 'required|string|max:10000',
        ]);
        $scope->requireScope($request, 'imports.write', $data['facility_id']);
        $upload = $request->file('source');
        $path = $upload->getPathname();
        $hash = hash_file('sha256', $path);
        abort_unless($hash, 422, 'Source could not be read.');
        $folder = storage_path('app/private/sources');
        File::ensureDirectoryExists($folder);
        $destination = $folder.'/'.$hash.'.pdf';
        if (! is_file($destination)) {
            $temporary = tempnam($folder, 'scan-');
            abort_unless($temporary, 500);
            try {
                abort_unless(copy($path, $temporary), 500);
                abort_unless(is_file($destination) || @rename($temporary, $destination) || is_file($destination), 500);
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
        abort_unless(hash_file('sha256', $destination) === $hash, 409, 'Source fingerprint mismatch.');

        return DB::transaction(function () use ($data, $hash, $destination, $upload, $request) {
            DB::table('source_files')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'sha256' => $hash,
                'original_name' => Str::limit(basename($upload->getClientOriginalName()), 250, ''),
                'storage_path' => $destination,
                'bytes' => $upload->getSize(),
                'created_at' => now('UTC'),
            ]);
            $source = DB::table('source_files')->where('sha256', $hash)->lockForUpdate()->first();
            abort_unless($source, 409, 'Source could not be registered.');
            unset($data['source']);
            $previous = DB::table('report_transcriptions')->where('source_file_id', $source->id)
                ->where('facility_id', $data['facility_id'])->where('created_by', $request->user()->id)
                ->where('page', $data['page'])->whereIn('status', ['pending', 'verified'])->get()
                ->first(fn ($row) => $row->locator === $data['locator'] && $row->source_text === $data['source_text']);
            if ($previous) {
                return response()->json(['data' => $this->entries()->where('t.id', $previous->id)->first()]);
            }
            $entry = $data + [
                'id' => (string) Str::uuid(),
                'source_file_id' => $source->id,
                'created_by' => $request->user()->id,
                'status' => 'pending',
                'created_at' => now('UTC'),
            ];
            DB::table('report_transcriptions')->insert($entry);
            Governance::audit($request, 'transcription.submitted', 'report_transcription', $entry['id'], null, [
                'source_file_id' => $source->id, 'page' => $data['page'], 'locator' => $data['locator'],
            ]);

            return response()->json(['data' => $this->entries()->where('t.id', $entry['id'])->first()], 201);
        });
    }

    public function download(Request $request, SourceMappingDictionary $scope, string $id)
    {
        $entry = DB::table('report_transcriptions as t')->join('source_files as s', 's.id', '=', 't.source_file_id')
            ->where('t.id', $id)->first(['t.facility_id', 's.storage_path', 's.sha256']);
        abort_unless($entry, 404);
        $scope->requireScope($request, 'imports.read', $entry->facility_id);
        $path = $this->verifiedPath($entry);

        return response()->download($path, 'source-'.$entry->sha256.'.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function review(Request $request, SourceMappingDictionary $scope, string $id)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['verified', 'rejected'])],
            'reason' => 'required|string|max:2000',
        ]);
        return DB::transaction(function () use ($request, $scope, $id, $data) {
            $entry = DB::table('report_transcriptions')->where('id', $id)->lockForUpdate()->first();
            abort_unless($entry, 404);
            $scope->requireScope($request, 'imports.approve', $entry->facility_id);
            abort_if($entry->created_by === $request->user()->id, 403, 'Independent review is required.');
            abort_unless($entry->status === 'pending', 409, 'This transcription has already been reviewed.');
            if ($data['decision'] === 'verified') {
                $source = DB::table('source_files')->where('id', $entry->source_file_id)->first();
                $this->verifiedPath($source);
            }
            DB::table('report_transcriptions')->where('id', $id)->update([
                'status' => $data['decision'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now('UTC'),
                'review_reason' => $data['reason'],
            ]);
            Governance::audit($request, 'transcription.'.$data['decision'], 'report_transcription', $id,
                ['status' => 'pending'], ['status' => $data['decision']], $data['reason']);

            return response()->json(['data' => $this->entries()->where('t.id', $id)->first()]);
        });
    }

    private function verifiedPath(object $source): string
    {
        foreach ([$source->storage_path, storage_path('app/private/sources/'.$source->sha256.'.pdf')] as $path) {
            if (is_file($path) && is_readable($path) && hash_file('sha256', $path) === $source->sha256) {
                return $path;
            }
        }
        abort(409, 'Source is unavailable or failed verification.');
    }

    private function entries()
    {
        return DB::table('report_transcriptions as t')
            ->join('source_files as s', 's.id', '=', 't.source_file_id')
            ->join('users as author', 'author.id', '=', 't.created_by')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 't.reviewed_by')
            ->select('t.*', 's.original_name', 's.sha256', 'author.name as author_name', 'reviewer.name as reviewer_name');
    }
}
