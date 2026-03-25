<?php

namespace Tests\Unit\Jobs;

use App\Domains\Users\Jobs\ExportUserDataJob;
use App\Domains\Users\Mail\DataExportReady;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\DataExportService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportUserDataJobTest extends TestCase
{
    public function test_job_exports_data_saves_file_and_sends_email(): void
    {
        Mail::fake();
        Storage::fake('local');

        $user = User::factory()->make(['id' => 42, 'email' => 'test@example.com', 'locale' => 'en']);

        $exportService = $this->mock(DataExportService::class);
        $exportService->shouldReceive('export')
            ->once()
            ->with($user)
            ->andReturn(['user' => ['name' => 'Test User']]);

        $job = new ExportUserDataJob($user);
        $job->handle($exportService);

        // Assert file was stored
        $files = Storage::disk('local')->files('exports');
        $this->assertCount(1, $files);
        $this->assertStringStartsWith('exports/user-42-', $files[0]);
        $this->assertStringEndsWith('.json', $files[0]);

        // Assert file content is valid JSON
        $content = Storage::disk('local')->get($files[0]);
        $decoded = json_decode($content, true);
        $this->assertEquals('Test User', $decoded['user']['name']);

        // Assert email was sent
        Mail::assertSent(DataExportReady::class, function (DataExportReady $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->user->id === $user->id
                && str_contains($mail->downloadUrl, 'profile/export/download');
        });
    }
}
