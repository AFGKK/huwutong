<?php

namespace App\Services;

class WebrtcConfigService
{
    private const DEFAULT_STUN = 'stun:stun.l.google.com:19302';

    /**
     * @return array<int, array{urls: string|array<int, string>, username?: string, credential?: string}>
     */
    public function getIceServers(): array
    {
        $json = config('webrtc.ice_servers_json');
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && count($decoded) > 0) {
                return $this->normalize($decoded);
            }
        }

        $servers = [];

        foreach (config('webrtc.stun_urls', [self::DEFAULT_STUN]) as $url) {
            $url = trim((string) $url);
            if ($url !== '') {
                $servers[] = ['urls' => $url];
            }
        }

        $username = config('webrtc.turn_username');
        $credential = (string) config('webrtc.turn_credential', '');

        foreach (['turn_url', 'turns_url'] as $key) {
            $url = config("webrtc.{$key}");
            if (!is_string($url) || $url === '') {
                continue;
            }

            $entry = ['urls' => $url];
            if (is_string($username) && $username !== '') {
                $entry['username'] = $username;
                $entry['credential'] = $credential;
            }
            $servers[] = $entry;
        }

        if ($servers === []) {
            return [['urls' => self::DEFAULT_STUN]];
        }

        return $servers;
    }

    public function hasTurn(): bool
    {
        foreach ($this->getIceServers() as $server) {
            $urls = $server['urls'] ?? '';
            $list = is_array($urls) ? $urls : [$urls];

            foreach ($list as $url) {
                $lower = strtolower((string) $url);
                if (str_starts_with($lower, 'turn:') || str_starts_with($lower, 'turns:')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $servers
     * @return array<int, array{urls: string|array<int, string>, username?: string, credential?: string}>
     */
    private function normalize(array $servers): array
    {
        $normalized = [];

        foreach ($servers as $server) {
            if (!is_array($server) || empty($server['urls'])) {
                continue;
            }

            $entry = ['urls' => $server['urls']];
            if (!empty($server['username'])) {
                $entry['username'] = (string) $server['username'];
                $entry['credential'] = (string) ($server['credential'] ?? '');
            }
            $normalized[] = $entry;
        }

        return $normalized !== [] ? $normalized : [['urls' => self::DEFAULT_STUN]];
    }
}
