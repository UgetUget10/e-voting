<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CandidatePhotoImport
{
    public const MAX_FILES = 100;

    /**
     * Attach photos to candidates by file name: "andi-pratama.jpg" goes to the candidate named "Andi Pratama".
     * The match ignores case, spacing and punctuation. A photo already on the candidate is replaced, and the
     * old file is removed by the model. Files that match no candidate are returned so the admin can fix them.
     *
     * @param  list<string>  $paths  Local paths of the uploaded images.
     * @return array{matched: int, unmatched: list<string>}
     */
    public function import(array $paths): array
    {
        $candidatesByName = Candidate::query()
            ->get(['id', 'name', 'photo_path'])
            ->keyBy(fn (Candidate $candidate): string => CandidateNameSimilarity::normalize($candidate->name));

        $matched = 0;
        $unmatched = [];

        Candidate::withoutRecordingAdminActivity(function () use ($paths, $candidatesByName, &$matched, &$unmatched): void {
            foreach ($paths as $path) {
                $fileName = basename($path);
                $candidate = $candidatesByName->get(CandidateNameSimilarity::normalize(pathinfo($fileName, PATHINFO_FILENAME)));

                if ($candidate === null) {
                    $unmatched[] = $fileName;

                    continue;
                }

                $storedPath = Storage::disk('public')->putFileAs(
                    'candidates',
                    new File($path),
                    Str::uuid().'.'.strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
                );

                $candidate->update(['photo_path' => $storedPath]);
                $matched++;
            }
        });

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }
}
