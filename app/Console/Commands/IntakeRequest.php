<?php

namespace App\Console\Commands;

use App\Models\IntakeCase;
use App\Models\User;
use App\Services\Intake\IntakeEvidence;
use App\Services\Intake\IntakeProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('intake:request {email} {message} {--file=}')]
#[Description('Turn a customer message and attachment into a case')]
class IntakeRequest extends Command
{
    public function handle(IntakeProcessor $processor): int
    {
        $user = User::where('email', $this->argument('email'))
            ->firstOrFail();
        $file = $this->option('file');
        $path = $mime = null;

        if ($file) {
            $path = 'intake/'.basename($file);
            Storage::disk('local')
                ->put($path, file_get_contents($file));
            $mime = mime_content_type($file) ?: null;
        }

        $case = $processor->process(IntakeCase::create([
            'user_id' => $user->id,
            'message' => $this->argument('message'),
            'attachment_path' => $path,
            'attachment_mime' => $mime,
            'attachment_kind' => $mime
                ? IntakeEvidence::kindOf($mime) : null,
            'status' => 'pending',
        ]));

        $this->line("{$case->category}, {$case->status}");
        if ($case->evidence) {
            $this->line('  evidence: '.str($case->evidence)->squish()
                ->limit(150));
        }
        $this->line('  '.json_encode($case->fields));
        foreach ($case->issues as $issue) {
            $this->warn("  ! {$issue}");
        }

        return self::SUCCESS;
    }
}
