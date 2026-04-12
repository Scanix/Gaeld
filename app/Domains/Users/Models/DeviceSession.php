<?php

namespace App\Domains\Users\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'device_name',
        'is_desktop',
        'is_mobile',
        'platform',
        'browser',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'is_desktop' => 'boolean',
            'is_mobile' => 'boolean',
            'last_active_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array{browser: string, platform: string, is_desktop: bool, is_mobile: bool, device_name: string} */
    public static function parseUserAgent(string $userAgent): array
    {
        $browser = 'Unknown';
        $platform = 'Unknown';
        $isDesktop = true;
        $isMobile = false;
        $deviceName = 'Desktop';

        // Platform detection
        if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
            $platform = 'iOS';
            $isMobile = true;
            $isDesktop = false;
            $deviceName = preg_match('/iPad/i', $userAgent) ? 'iPad' : 'iPhone';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $platform = 'Android';
            $isMobile = ! preg_match('/Tablet/i', $userAgent);
            $isDesktop = false;
            $deviceName = $isMobile ? 'Android Phone' : 'Android Tablet';
        } elseif (preg_match('/Macintosh|Mac OS X/i', $userAgent)) {
            $platform = 'macOS';
            $deviceName = 'Mac';
        } elseif (preg_match('/Windows/i', $userAgent)) {
            $platform = 'Windows';
            $deviceName = 'Windows PC';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $platform = 'Linux';
            $deviceName = 'Linux PC';
        }

        // Browser detection
        if (preg_match('/Edg\//i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/OPR\/|Opera/i', $userAgent)) {
            $browser = 'Opera';
        } elseif (preg_match('/Chrome\//i', $userAgent) && ! preg_match('/Edg\//i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari\//i', $userAgent) && ! preg_match('/Chrome\//i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Firefox\//i', $userAgent)) {
            $browser = 'Firefox';
        }

        return [
            'browser' => $browser,
            'platform' => $platform,
            'is_desktop' => $isDesktop,
            'is_mobile' => $isMobile,
            'device_name' => $deviceName.' — '.$browser,
        ];
    }
}
