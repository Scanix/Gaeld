<?php

namespace App\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Validates an hCaptcha widget response token against hCaptcha's
 * siteverify endpoint.
 *
 * Designed to be safe in every environment:
 *  - Returns success when no secret key is configured (self-hosted / dev).
 *  - Returns success during the `testing` environment so feature tests
 *    don't need to mock the HTTP client.
 *  - Logs failures (and network errors) but never throws.
 *
 * Usage:
 *     'h-captcha-response' => HCaptchaRule::rules(),
 */
class HCaptchaRule implements ValidationRule
{
    /**
     * Build the validation rules for an hCaptcha-protected field.
     *
     * When hCaptcha is configured (a site key is present) the field is
     * `required` — both the front-end widget and the back-end token check
     * must succeed. When hCaptcha is not configured, the field is silently
     * skipped so self-hosted and local installs keep working.
     *
     * IMPORTANT: do not inline `['nullable', 'string', new HCaptchaRule]`.
     * The `nullable` modifier causes Laravel to skip subsequent rules when
     * the field is missing or empty, which lets bots bypass the captcha
     * entirely by simply not submitting the token.
     *
     * @return array<int, string|self>
     */
    public static function rules(): array
    {
        $configured = (string) config('services.hcaptcha.site_key', '') !== '';

        return $configured
            ? ['required', 'string', new self]
            : ['nullable', 'string'];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = (string) config('services.hcaptcha.secret_key', '');

        if ($secret === '' || app()->environment('testing')) {
            return;
        }

        if (! is_string($value) || $value === '') {
            $fail(__('captcha_required'));

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post((string) config('services.hcaptcha.verify_url'), [
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);

            $payload = $response->json();

            if (! is_array($payload) || ! ($payload['success'] ?? false)) {
                Log::info('hcaptcha.failed', [
                    'errors' => $payload['error-codes'] ?? null,
                    'ip' => request()->ip(),
                ]);

                $fail(__('captcha_failed'));
            }
        } catch (\Throwable $e) {
            // Fail closed: a captcha provider outage shouldn't let bots
            // through, but we do log so operators can react.
            Log::warning('hcaptcha.unreachable', ['message' => $e->getMessage()]);
            $fail(__('captcha_unavailable'));
        }
    }
}
