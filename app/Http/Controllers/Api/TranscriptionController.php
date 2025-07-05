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
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,m4a',
        ]);

        $path = $request->file('audio')->store('audio-uploads', 's3');
        $url = Storage::disk('s3')->url($path);

        return response()->json(['url' => $url]);
    }

    public function startTranscription($s3AudioUrl)
    {
        $client = new TranscribeServiceClient([
            'version' => 'latest',
            'region' => config('services.aws.region'),
            'credentials' => [
                'key' => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ],
        ]);

        $jobName = 'transcription-job-' . time();

        $result = $client->startTranscriptionJob([
            'TranscriptionJobName' => $jobName,
            'LanguageCode' => 'en-US',
            'Media' => [
                'MediaFileUri' => $s3AudioUrl,
            ],
        ]);

        return response()->json(['jobName' => $jobName]);
    }

    public function getTranscriptionResult($jobName)
    {
        $client = new TranscribeServiceClient([
            'version' => 'latest',
            'region' => config('services.aws.region'),
            'credentials' => [
                'key' => config('services.aws.key'),
                'secret' => config('services.aws.secret'),
            ],
        ]);

        $result = $client->getTranscriptionJob([
            'TranscriptionJobName' => $jobName,
        ]);

        $job = $result['TranscriptionJob'];

        if ($job['TranscriptionJobStatus'] === 'COMPLETED') {
            $transcriptUrl = $job['Transcript']['TranscriptFileUri'];
            $json = file_get_contents($transcriptUrl);
            $data = json_decode($json, true);
            $transcriptText = $data['results']['transcripts'][0]['transcript'];

            return response()->json(['text' => $transcriptText]);
        } elseif ($job['TranscriptionJobStatus'] === 'FAILED') {
            return response()->json(['error' => 'Transcription failed.'], 500);
        } else {
            return response()->json(['status' => $job['TranscriptionJobStatus']]);
        }
    }
}