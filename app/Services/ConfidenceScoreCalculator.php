<?php

namespace App\Services;

class ConfidenceScoreCalculator
{
    public function __construct(private readonly TorDetectionService $torDetection) {}

    /**
     * @return array{score: int, breakdown: array{timing: int, timezone: int, tor: int, disposable: int, captcha: int}}
     */
    public function calculate(array $input, string $ip, string $geoCountryCode): array
    {
        $breakdown = ['timing' => 0, 'timezone' => 0, 'tor' => 0, 'disposable' => 0, 'captcha' => 0];

        $timing = isset($input['_timing']) ? (int) $input['_timing'] : 0;
        if ($timing < 2000) {
            $breakdown['timing'] = 50;
        }

        $tz = trim($input['_tz'] ?? '');
        if ($tz && $geoCountryCode && !$this->timezoneMatchesCountry($tz, $geoCountryCode)) {
            $breakdown['timezone'] = 20;
        }

        if ($this->torDetection->isKnownTorOrVpnIp($ip)) {
            $breakdown['tor'] = 15;
        }

        $email = trim($input['email'] ?? '');
        if ($email && $this->isDisposableEmail($email)) {
            $breakdown['disposable'] = 40;
        }

        $captchaSubmitted  = (int) ($input['_captcha_solved'] ?? 0) === 1;
        $captchaRotation   = (int) ($input['_captcha_rotation'] ?? -1);
        $captchaTargetZone = (int) ($input['_captcha_target_zone'] ?? -1);
        $actualZone = (int) floor((($captchaRotation % 360) + 360) % 360 / 30) % 12;
        $captchaSolved = $captchaSubmitted
            && $captchaTargetZone >= 0 && $captchaTargetZone <= 11
            && $actualZone === $captchaTargetZone;
        if (!$captchaSolved) {
            $breakdown['captcha'] = 30;
        }

        return [
            'score' => array_sum($breakdown),
            'breakdown' => $breakdown,
        ];
    }

    private function timezoneMatchesCountry(string $timezone, string $countryCode): bool
    {
        try {
            $zones = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, strtoupper($countryCode));
            return in_array($timezone, $zones, true);
        } catch (\Throwable) {
            return true;
        }
    }

    private function isDisposableEmail(string $email): bool
    {
        return !validator(['email' => $email], ['email' => 'indisposable'])->passes();
    }
}
