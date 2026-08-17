import type { SiteGuideFile } from '@/types';

/**
 * What the stored file is, in the terms the site staff use. Derived from the
 * mime type rather than the display name, which is free text and often has no
 * extension at all.
 */
export function guideFileTypeLabel(file: Pick<SiteGuideFile, 'mime_type'>) {
    if (file.mime_type?.includes('pdf')) {
        return 'PDF';
    }

    if (file.mime_type?.startsWith('image/')) {
        return '画像';
    }

    return 'ファイル';
}

export function isPreviewableImage(file: Pick<SiteGuideFile, 'mime_type'>) {
    return file.mime_type?.startsWith('image/') === true;
}
