<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Aws\TranscribeService\TranscribeServiceClient;
use Illuminate\Support\Facades\Storage;

class TranscriptionController extends Controller
{
    public function uploadAudio(Request $request)
    {
        try {
            $request->validate([
                'audio' => 'required|file|mimes:mp3,wav,m4a',
            ]);

            $path = $request->file('audio')->store('', 's3');

            if (!$path) {
                Log::error('S3 store failed.');
                return response()->json(['error' => 'Failed to store the file.'], 500);
            }

            $url = Storage::disk('s3')->url($path);

            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            Log::error('Upload failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function startTranscription(Request $request)
    {
        $request->validate([
            's3_audio_url' => 'required|url',
        ]);

        $s3AudioUrl = $request->input('s3_audio_url');

        $client = new TranscribeServiceClient([
            'version' => 'latest',
            'region' => config('services.aws.region'),
            'credentials' => [
                'key' => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ],
        ]);

        $jobName = 'transcription-job-' . time();

        try {
            $client->startTranscriptionJob([
                'TranscriptionJobName' => $jobName,
                'LanguageCode' => 'en-US',
                'Media' => [
                    'MediaFileUri' => $s3AudioUrl,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to start transcription: ' . $e->getMessage()], 500);
        }

        return response()->json(['jobName' => $jobName]);
    }

    public function getTranscriptionResult($jobName)
    {
        if (!is_string($jobName) || empty($jobName)) {
            return response()->json(['error' => 'Invalid jobName provided.'], 400);
        }

        $client = new TranscribeServiceClient([
            'version' => 'latest',
            'region' => config('services.aws.region'),
            'credentials' => [
                'key' => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ],
        ]);

        try {
            $result = $client->getTranscriptionJob([
                'TranscriptionJobName' => $jobName,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get transcription job: ' . $e->getMessage()], 500);
        }

        $job = $result['TranscriptionJob'];
        $status = $job['TranscriptionJobStatus'];

        if ($status === 'COMPLETED') {
            $transcriptUrl = $job['Transcript']['TranscriptFileUri'];
            $json = file_get_contents($transcriptUrl);
            $data = json_decode($json, true);
            $transcriptText = $data['results']['transcripts'][0]['transcript'];

            return response()->json([
                'status' => $status,
                'transcript' => $transcriptText,
            ]);
        } elseif ($status === 'FAILED') {
            return response()->json(['status' => $status, 'error' => 'Transcription job failed.'], 500);
        } else {
            return response()->json(['status' => $status]);
        }
    }


}