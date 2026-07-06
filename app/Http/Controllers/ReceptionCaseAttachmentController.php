<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreReceptionCaseAttachmentRequest;
use App\Models\ReceptionCase;
use App\Models\ReceptionCaseActivity;
use App\Models\ReceptionCaseAttachment;
use App\ReceptionCaseAttachmentPreviewMode;
use App\ReceptionCaseAttachmentSource;
use App\ReceptionCaseStatus;
use App\Services\ReceptionCasePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

class ReceptionCaseAttachmentController extends Controller
{
    public function store(
        StoreReceptionCaseAttachmentRequest $request,
        ReceptionCase $receptionCase,
        ReceptionCasePresenter $presenter,
    ): JsonResponse {
        $file = $request->file('file');

        abort_unless($file instanceof UploadedFile, 422);

        $extension = mb_strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $fallbackName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $source = ReceptionCaseAttachmentSource::from($request->string('source')->toString());

        // Store the file before opening the transaction; if the transaction rolls
        // back we delete it in the catch so no orphan file is left behind.
        $path = $file->store("reception-attachments/{$receptionCase->id}", ReceptionCaseAttachment::DISK);

        abort_if($path === false, 500, 'Failed to store the reception attachment.');

        try {
            $attachment = DB::transaction(function () use ($request, $receptionCase, $extension, $mimeType, $size, $fallbackName, $path, $source): ReceptionCaseAttachment {
                $name = $request->string('name')->trim()->toString() ?: $fallbackName;

                $attachment = $receptionCase->attachments()->create([
                    'uploaded_by_user_id' => $request->user()->id,
                    'kind' => ReceptionCaseAttachment::kindForExtension($extension, $mimeType, $source),
                    'source' => $source,
                    'name' => $name,
                    'disk' => ReceptionCaseAttachment::DISK,
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'size' => $size,
                    'duration_seconds' => $request->integer('duration_seconds') ?: null,
                ]);

                $this->recordAttachmentActivity(
                    $receptionCase,
                    $request,
                    ReceptionCaseActivity::TYPE_ATTACHMENT_ADDED,
                    "添付資料を追加: {$attachment->name}",
                );

                $this->auditSuccess('reception_case_attachments.created', 'A reception case attachment was created.', $attachment, [
                    'reception_case_id' => $receptionCase->id,
                    'name' => $attachment->name,
                ]);

                return $attachment;
            });
        } catch (Throwable $exception) {
            Storage::disk(ReceptionCaseAttachment::DISK)->delete($path);

            throw $exception;
        }

        $attachment->load('uploader');

        return response()->json([
            'attachment' => $presenter->attachment($attachment),
        ], 201);
    }

    public function show(Request $request, ReceptionCaseAttachment $receptionCaseAttachment): BinaryFileResponse
    {
        $this->authorize('view', $receptionCaseAttachment->receptionCase);

        abort_unless($receptionCaseAttachment->fileExists(), 404);

        $isDownload = $request->boolean('download')
            || $receptionCaseAttachment->previewMode() === ReceptionCaseAttachmentPreviewMode::Download;

        if ($isDownload) {
            $this->auditSuccess('reception_case_attachments.downloaded', 'A reception case attachment was downloaded.', $receptionCaseAttachment, [
                'reception_case_id' => $receptionCaseAttachment->reception_case_id,
            ]);
        }

        $response = response()->file($receptionCaseAttachment->absolutePath(), [
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            $isDownload ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
            $receptionCaseAttachment->downloadName(),
            $receptionCaseAttachment->asciiDownloadName(),
        ));

        return $response;
    }

    public function destroy(Request $request, ReceptionCaseAttachment $receptionCaseAttachment): JsonResponse
    {
        $case = $receptionCaseAttachment->receptionCase;

        $this->authorize('attachFiles', $case);

        DB::transaction(function () use ($request, $case, $receptionCaseAttachment): void {
            $name = $receptionCaseAttachment->name;

            $this->recordAttachmentActivity(
                $case,
                $request,
                ReceptionCaseActivity::TYPE_ATTACHMENT_DELETED,
                "添付資料を削除: {$name}",
            );

            $this->auditSuccess('reception_case_attachments.deleted', 'A reception case attachment was deleted.', $case, [
                'reception_case_id' => $case->id,
                'name' => $name,
            ]);

            // Delete last: the model's deleted event removes the stored file, so no
            // statement after this can roll the row back while the file is gone.
            $receptionCaseAttachment->delete();
        });

        return response()->json([
            'deleted_id' => $receptionCaseAttachment->id,
        ]);
    }

    private function recordAttachmentActivity(ReceptionCase $case, Request $request, string $type, string $memo): void
    {
        if ($case->status === ReceptionCaseStatus::Draft) {
            return;
        }

        $case->recordActivity($request->user(), $type, [
            'from_status' => $case->status->value,
            'to_status' => $case->status->value,
            'memo' => $memo,
        ]);
    }
}
