<?php

namespace App\Support\Dns;

/** The few DNS questions custom domains need answered. Replaced by a fake in tests. */
interface DnsResolver
{
    /** @return list<string> every TXT value of a name */
    public function txt(string $host): array;

    /** @return list<string> CNAME targets of a name, lower case, without the trailing dot */
    public function cname(string $host): array;

    /** @return list<string> IPv4 and IPv6 addresses a name resolves to */
    public function addresses(string $host): array;
}
