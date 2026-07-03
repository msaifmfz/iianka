import { useCallback, useEffect, useRef, useState } from 'react';

export type RecordingState = 'idle' | 'recording' | 'saving';

type UseAudioRecorderOptions = {
    maxRecordingSeconds: number;
    onSave: (
        file: File,
        durationSeconds: number,
        displayName: string,
    ) => Promise<void>;
    onError: (message: string) => void;
};

function preferredRecordingMimeType(): string {
    if (typeof MediaRecorder === 'undefined') {
        return '';
    }

    const candidates = [
        'audio/mp4;codecs=mp4a.40.2',
        'audio/mp4',
        'video/mp4;codecs=mp4a.40.2',
        'video/mp4',
        'audio/webm;codecs=opus',
        'video/webm;codecs=opus',
        'audio/webm',
        'video/webm',
        'audio/ogg;codecs=opus',
        'audio/ogg',
    ];

    return (
        candidates.find((candidate) =>
            MediaRecorder.isTypeSupported(candidate),
        ) ?? ''
    );
}

function extensionForRecording(mimeType: string): string {
    const normalizedMimeType = mimeType.toLowerCase();

    if (normalizedMimeType.includes('ogg')) {
        return 'ogg';
    }

    if (normalizedMimeType.includes('mp4')) {
        return 'm4a';
    }

    return 'webm';
}

/**
 * Encapsulates the MediaRecorder lifecycle: microphone access, mime detection,
 * the elapsed-seconds timer with a hard cap, and turning the captured chunks
 * into a `File` handed to `onSave`. Fully self-contained and cleaned up on
 * unmount.
 */
export function useAudioRecorder({
    maxRecordingSeconds,
    onSave,
    onError,
}: UseAudioRecorderOptions) {
    const [recordingState, setRecordingState] =
        useState<RecordingState>('idle');
    const [recordingSeconds, setRecordingSeconds] = useState(0);

    const recorderRef = useRef<MediaRecorder | null>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const chunksRef = useRef<BlobPart[]>([]);
    const recordingStartedAtRef = useRef<number>(0);
    const shouldSaveRecordingRef = useRef(true);
    const recordingTimerRef = useRef<number | null>(null);
    const isUnmountingRef = useRef(false);

    // Keep the latest callbacks/config without re-binding the recorder listeners.
    const onSaveRef = useRef(onSave);
    const onErrorRef = useRef(onError);
    const maxRecordingSecondsRef = useRef(maxRecordingSeconds);

    useEffect(() => {
        onSaveRef.current = onSave;
        onErrorRef.current = onError;
        maxRecordingSecondsRef.current = maxRecordingSeconds;
    }, [onSave, onError, maxRecordingSeconds]);

    const stopRecordingStream = useCallback(() => {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        recorderRef.current = null;
    }, []);

    const stopRecording = useCallback((save: boolean) => {
        shouldSaveRecordingRef.current = save;

        if (recordingTimerRef.current !== null) {
            window.clearInterval(recordingTimerRef.current);
            recordingTimerRef.current = null;
        }

        const recorder = recorderRef.current;

        if (recorder && recorder.state !== 'inactive') {
            recorder.stop();
        }
    }, []);

    const saveStoppedRecording = useCallback(
        async (recorder: MediaRecorder) => {
            const shouldSave = shouldSaveRecordingRef.current;
            const durationSeconds = Math.min(
                maxRecordingSecondsRef.current,
                Math.max(
                    1,
                    Math.ceil(
                        (Date.now() - recordingStartedAtRef.current) / 1000,
                    ),
                ),
            );

            stopRecordingStream();

            if (!shouldSave || isUnmountingRef.current) {
                chunksRef.current = [];

                if (!isUnmountingRef.current) {
                    setRecordingSeconds(0);
                    setRecordingState('idle');
                }

                return;
            }

            setRecordingState('saving');

            try {
                const mimeType =
                    recorder.mimeType || preferredRecordingMimeType();
                const blob = new Blob(chunksRef.current, {
                    type: mimeType || 'audio/webm',
                });
                // Avoid the ja-JP "/" date separator so the name is a valid filename.
                const recordedAt = new Intl.DateTimeFormat('ja-JP', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                })
                    .format(new Date())
                    .replace(/\//g, '-');
                const extension = extensionForRecording(blob.type);
                const file = new File(
                    [blob],
                    `recording-${Date.now()}.${extension}`,
                    { type: blob.type },
                );

                await onSaveRef.current(
                    file,
                    durationSeconds,
                    `録音 ${recordedAt}`,
                );
            } catch {
                onErrorRef.current('録音の保存に失敗しました。');
            } finally {
                chunksRef.current = [];
                setRecordingSeconds(0);
                setRecordingState('idle');
            }
        },
        [stopRecordingStream],
    );

    const startRecording = useCallback(async () => {
        if (
            typeof navigator === 'undefined' ||
            !navigator.mediaDevices?.getUserMedia ||
            typeof MediaRecorder === 'undefined'
        ) {
            onErrorRef.current('このブラウザでは録音を開始できません。');

            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: true,
            });
            const mimeType = preferredRecordingMimeType();
            const recorder = mimeType
                ? new MediaRecorder(stream, { mimeType })
                : new MediaRecorder(stream);

            streamRef.current = stream;
            recorderRef.current = recorder;
            chunksRef.current = [];
            shouldSaveRecordingRef.current = true;
            recordingStartedAtRef.current = Date.now();

            recorder.addEventListener('dataavailable', (event) => {
                if (event.data.size > 0) {
                    chunksRef.current.push(event.data);
                }
            });

            recorder.addEventListener('stop', () => {
                void saveStoppedRecording(recorder);
            });

            recorder.start();
            setRecordingState('recording');
            setRecordingSeconds(0);
            recordingTimerRef.current = window.setInterval(() => {
                const elapsed = Math.min(
                    maxRecordingSecondsRef.current,
                    Math.floor(
                        (Date.now() - recordingStartedAtRef.current) / 1000,
                    ),
                );

                setRecordingSeconds(elapsed);

                if (elapsed >= maxRecordingSecondsRef.current) {
                    stopRecording(true);
                }
            }, 500);
        } catch {
            stopRecordingStream();
            setRecordingState('idle');
            onErrorRef.current('マイクの使用が許可されませんでした。');
        }
    }, [saveStoppedRecording, stopRecording, stopRecordingStream]);

    useEffect(() => {
        return () => {
            isUnmountingRef.current = true;
            stopRecording(false);
            stopRecordingStream();
        };
    }, [stopRecording, stopRecordingStream]);

    return { recordingState, recordingSeconds, startRecording, stopRecording };
}
