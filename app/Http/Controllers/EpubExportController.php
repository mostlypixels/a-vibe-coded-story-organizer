<?php

namespace App\Http\Controllers;

use App\Exceptions\EpubExportException;
use App\Http\Requests\EpubExportRequest;
use App\Models\Book;
use App\Services\EpubExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exports one book to a downloadable .epub from Admin -> Export & import.
 *
 * Thin: resolve -> authorize -> delegate -> respond, mirroring
 * {@see ExportController}. The heavy lifting (tree filtering, XHTML rendering,
 * packaging, structural validation) lives in the HTTP-agnostic EpubExporter.
 *
 * The one user-facing failure — a book with nothing to export — surfaces from
 * the service as an EpubExportException; it is caught here and turned into a
 * redirect-back-with-error rather than being allowed to bubble as a 500. Every
 * other failure inside EpubExporter is a generator bug and is left to 500/log.
 */
class EpubExportController extends Controller
{
    public function store(EpubExportRequest $request, EpubExporter $exporter): BinaryFileResponse|RedirectResponse
    {
        // The admin gate is "any authenticated user"; authorize ownership too so a
        // foreign book_id 403s (mirrors EpubExportRequest::authorize()). book_id
        // is validated as an existing id, so findOrFail is a belt-and-braces guard.
        $book = Book::findOrFail($request->integer('book_id'));
        $this->authorize('view', $book->project);

        try {
            $epubPath = $exporter->export($book);
        } catch (EpubExportException $exception) {
            // "Nothing to export" is a user problem (no scenes yet), not a bug —
            // send them back to the form with the message rather than a 500.
            return redirect()->back()->withErrors(['book_id' => $exception->getMessage()]);
        }

        $filename = Str::slug($book->displayName()).'-'.now()->format('Ymd-His').'.epub';

        // deleteFileAfterSend cleans up the temp epub once the response is streamed.
        return response()
            ->download($epubPath, $filename, ['Content-Type' => 'application/epub+zip'])
            ->deleteFileAfterSend(true);
    }
}
