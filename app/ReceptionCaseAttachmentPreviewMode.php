<?php

declare(strict_types=1);

namespace App;

enum ReceptionCaseAttachmentPreviewMode: string
{
    case Image = 'image';
    case Pdf = 'pdf';
    case Audio = 'audio';
    case Video = 'video';
    case Download = 'download';
}
