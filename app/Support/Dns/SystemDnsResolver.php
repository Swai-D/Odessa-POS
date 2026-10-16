<?php

namespace App\Support\Dns;

use Throwable;

class SystemDnsResolver implements DnsResolver
{
    public function txt(string $host): array
    {
        $values = [];
        foreach ($this->records($host, DNS_TXT) as $record) {
            if (isset($record['txt'])) {
                $values[] = trim((string) $record['txt']);
            }
        }

        return $values;
    }

    public function cname(string $host): array
    {
        $targets = [];
        foreach ($this->records($host, DNS_CNAME) as $record) {
            if (isset($record['target'])) {
                $targets[] = strtolower(rtrim((string) $record['target'], '.'));
            }
        }

        return $targets;
    }

    public function addresses(string $host): array
    {
        $addresses = [];
        foreach ($this->records($host, DNS_A | DNS_AAAA) as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = strtolower($address);
            }
        }

        return $addresses;
    }

    /** @return list<array<string, mixed>> */
    private function records(string $host, int $type): array
    {
        try {
            $records = @dns_get_record($host, $type);
        } catch (Throwable) {
            return [];
        }

        return is_array($records) ? array_values($records) : [];
    }
}
