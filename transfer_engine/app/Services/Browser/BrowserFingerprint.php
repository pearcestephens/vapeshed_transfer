<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Services\Browser;

/**
 * BrowserFingerprint
 * Generates plausible desktop/mobile browser fingerprint profiles (header set + client hints + timing jitter metadata).
 * NOTE: This does not modify TLS-level fingerprints (JA3 / ALPN) – that would require a lower-level client.
 */
class BrowserFingerprint
{
    private array $chromeVersions = [
        '125.0.6422.112','125.0.6422.76','126.0.6478.55','126.0.6478.114','127.0.6533.72'
    ];
    private array $platforms = [
        ['platform'=>'Windows','arch'=>'Win64','platformVersion'=>'15.0.0','uaPlatform'=>'Windows','secChPlatform'=>'Windows','secChFull'=>'"Not.A/Brand";v="8", "Chromium";v="127", "Google Chrome";v="127"'],
        ['platform'=>'macOS','arch'=>'Intel Mac OS X 10_15_7','platformVersion'=>'13.4.0','uaPlatform'=>'Macintosh','secChPlatform'=>'macOS','secChFull'=>'"Chromium";v="127", "Not.A/Brand";v="8", "Google Chrome";v="127"'],
        ['platform'=>'Linux','arch'=>'X11; Linux x86_64','platformVersion'=>'6.8.0','uaPlatform'=>'X11','secChPlatform'=>'Linux','secChFull'=>'"Chromium";v="127", "Not(A:Brand";v="99", "Google Chrome";v="127"']
    ];
    private array $languages = [
        'en-US,en;q=0.9','en-GB,en-US;q=0.9,en;q=0.8','en-US,en-AU;q=0.8,en;q=0.7','en-NZ,en;q=0.9'
    ];

    /** Build a persistent fingerprint profile */
    public function generateProfile(bool $mobile=false): array
    {
        $plat = $this->platforms[array_rand($this->platforms)];
        $version = $this->chromeVersions[array_rand($this->chromeVersions)];
        $lang = $this->languages[array_rand($this->languages)];
        $tzOffsets = [-720,-660,-600,-540,-480,-420,-360,-300,-240,-180,-120,-60,0,60,120,180,240,300,330,360,420,480,525,540,570,600,630,660,720];
        $tz = $tzOffsets[array_rand($tzOffsets)];
        $viewport = $mobile ? [390,844] : [ random_int(1280,1920), random_int(720,1200) ];
        $deviceMemory = [4,8,16][array_rand([4,8,16])];
        $hardwareConcurrency = [4,8,12,16][array_rand([4,8,12,16])];
        $mobileUAFragment = $mobile ? ' Mobile Safari/537.36' : ' Safari/537.36';

        $ua = sprintf(
            'Mozilla/5.0 (%s) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/%s %s',
            $plat['arch'],
            $version,
            $mobileUAFragment
        );

        $secChUa = $plat['secChFull'];

        return [
            'user_agent' => $ua,
            'accept_language' => $lang,
            'timezone_offset' => $tz,
            'viewport' => ['width'=>$viewport[0],'height'=>$viewport[1]],
            'device_memory' => $deviceMemory,
            'hardware_concurrency' => $hardwareConcurrency,
            'platform' => $plat['platform'],
            'sec_ch_ua' => $secChUa,
            'sec_ch_mobile' => $mobile ? '?1' : '?0',
            'sec_ch_platform' => '"'.$plat['secChPlatform'].'"',
            'mobile' => $mobile
        ];
    }

    /** Build HTTP request headers (order randomized slightly) */
    public function buildHeaders(array $profile, array $extra=[]): array
    {
        $headers = [
            'User-Agent: '.$profile['user_agent'],
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: '.$profile['accept_language'],
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Sec-CH-UA: '.$profile['sec_ch_ua'],
            'Sec-CH-UA-Platform: '.$profile['sec_ch_platform'],
            'Sec-CH-UA-Mobile: '.$profile['sec_ch_mobile'],
            'Sec-Fetch-Site: none',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-User: ?1',
            'Sec-Fetch-Dest: document',
            'Upgrade-Insecure-Requests: 1',
            'Accept-Encoding: gzip, deflate, br'
        ];
        // Slight shuffle but keep UA first
        $fixed = array_shift($headers);
        shuffle($headers);
        array_unshift($headers, $fixed);
        foreach ($extra as $h) { if (is_string($h)) { $headers[] = $h; } }
        return $headers;
    }

    /** Generate JS snippet to override navigator properties (for future CDP evaluate use) */
    public function navigatorOverrideScript(array $profile): string
    {
        $mem = (int)$profile['device_memory'];
        $cores = (int)$profile['hardware_concurrency'];
        $platform = addslashes($profile['platform']);
        $langPrimary = explode(',', $profile['accept_language'])[0];
        return <<<JS
(() => {
  try {
    const override = (obj, prop, value) => Object.defineProperty(obj, prop, { get: () => value, configurable: true });
    override(Navigator.prototype, 'deviceMemory', $mem);
    override(Navigator.prototype, 'hardwareConcurrency', $cores);
    override(Navigator.prototype, 'platform', '$platform');
    override(Navigator.prototype, 'language', '$langPrimary');
    override(Navigator.prototype, 'languages', ['$langPrimary']);
  } catch(e) {}
})();
JS;
    }
}
