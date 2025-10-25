<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;

class BGPController
{
    /**
     * Lookup prefix/IP information in BGP tables
     */
    public function prefixLookup(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $query = $input['query'] ?? '';

        if (empty($query)) {
            Response::error('IP address or prefix is required', 400);
            return;
        }

        try {
            // Validate input - IP or CIDR prefix
            $isIP = filter_var($query, FILTER_VALIDATE_IP);
            $isCIDR = preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $query);

            if (!$isIP && !$isCIDR) {
                Response::error('Invalid IP address or CIDR prefix', 400);
                return;
            }

            $url = "https://api.bgpview.io/prefix/" . urlencode($query);
            $data = $this->fetchBGPViewAPI($url);

            if (!$data || !isset($data['data'])) {
                Response::error('No BGP data found for this prefix', 404);
                return;
            }

            $prefixData = $data['data'];

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('BGP prefix lookup completed', [
                'prefix' => $prefixData['prefix'] ?? $query,
                'name' => $prefixData['name'] ?? null,
                'description' => $prefixData['description_short'] ?? $prefixData['description_full'] ?? null,
                'country_code' => $prefixData['country_code'] ?? null,
                'asns' => $prefixData['asns'] ?? [],
                'rir_name' => $prefixData['rir_allocation']['rir_name'] ?? null,
                'allocation_date' => $prefixData['rir_allocation']['date_allocated'] ?? null,
                'rpki_validation' => $prefixData['rpki_validation'] ?? null
            ]);

        } catch (\Exception $e) {
            Response::error('BGP prefix lookup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lookup AS (Autonomous System) information
     */
    public function asLookup(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $asn = $input['asn'] ?? '';

        if (empty($asn)) {
            Response::error('ASN is required', 400);
            return;
        }

        // Strip "AS" prefix if present
        $asn = preg_replace('/^AS/i', '', $asn);

        if (!is_numeric($asn)) {
            Response::error('Invalid ASN format', 400);
            return;
        }

        try {
            $url = "https://api.bgpview.io/asn/" . urlencode($asn);
            $data = $this->fetchBGPViewAPI($url);

            if (!$data || !isset($data['data'])) {
                Response::error('No BGP data found for this ASN', 404);
                return;
            }

            $asData = $data['data'];

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('BGP AS lookup completed', [
                'asn' => $asData['asn'] ?? $asn,
                'name' => $asData['name'] ?? null,
                'description' => $asData['description_short'] ?? $asData['description_full'] ?? null,
                'country_code' => $asData['country_code'] ?? null,
                'website' => $asData['website'] ?? null,
                'email_contacts' => $asData['email_contacts'] ?? [],
                'abuse_contacts' => $asData['abuse_contacts'] ?? [],
                'looking_glass' => $asData['looking_glass'] ?? null,
                'traffic_estimation' => $asData['traffic_estimation'] ?? null,
                'traffic_ratio' => $asData['traffic_ratio'] ?? null,
                'owner_address' => $asData['owner_address'] ?? []
            ]);

        } catch (\Exception $e) {
            Response::error('BGP AS lookup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get AS prefixes (both IPv4 and IPv6)
     */
    public function asPrefixes(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $asn = $input['asn'] ?? '';

        if (empty($asn)) {
            Response::error('ASN is required', 400);
            return;
        }

        // Strip "AS" prefix if present
        $asn = preg_replace('/^AS/i', '', $asn);

        if (!is_numeric($asn)) {
            Response::error('Invalid ASN format', 400);
            return;
        }

        try {
            $url = "https://api.bgpview.io/asn/" . urlencode($asn) . "/prefixes";
            $data = $this->fetchBGPViewAPI($url);

            if (!$data || !isset($data['data'])) {
                Response::error('No prefix data found for this ASN', 404);
                return;
            }

            $prefixData = $data['data'];

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('BGP AS prefixes lookup completed', [
                'asn' => $asn,
                'ipv4_prefixes' => $prefixData['ipv4_prefixes'] ?? [],
                'ipv6_prefixes' => $prefixData['ipv6_prefixes'] ?? [],
                'ipv4_count' => count($prefixData['ipv4_prefixes'] ?? []),
                'ipv6_count' => count($prefixData['ipv6_prefixes'] ?? [])
            ]);

        } catch (\Exception $e) {
            Response::error('BGP AS prefixes lookup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get AS peers (upstream and downstream)
     */
    public function asPeers(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $asn = $input['asn'] ?? '';

        if (empty($asn)) {
            Response::error('ASN is required', 400);
            return;
        }

        // Strip "AS" prefix if present
        $asn = preg_replace('/^AS/i', '', $asn);

        if (!is_numeric($asn)) {
            Response::error('Invalid ASN format', 400);
            return;
        }

        try {
            $url = "https://api.bgpview.io/asn/" . urlencode($asn) . "/peers";
            $data = $this->fetchBGPViewAPI($url);

            if (!$data || !isset($data['data'])) {
                Response::error('No peer data found for this ASN', 404);
                return;
            }

            $peerData = $data['data'];

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('BGP AS peers lookup completed', [
                'asn' => $asn,
                'ipv4_peers' => $peerData['ipv4_peers'] ?? [],
                'ipv6_peers' => $peerData['ipv6_peers'] ?? [],
                'ipv4_peer_count' => count($peerData['ipv4_peers'] ?? []),
                'ipv6_peer_count' => count($peerData['ipv6_peers'] ?? [])
            ]);

        } catch (\Exception $e) {
            Response::error('BGP AS peers lookup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get AS upstreams (transit providers)
     */
    public function asUpstreams(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $asn = $input['asn'] ?? '';

        if (empty($asn)) {
            Response::error('ASN is required', 400);
            return;
        }

        // Strip "AS" prefix if present
        $asn = preg_replace('/^AS/i', '', $asn);

        if (!is_numeric($asn)) {
            Response::error('Invalid ASN format', 400);
            return;
        }

        try {
            $url = "https://api.bgpview.io/asn/" . urlencode($asn) . "/upstreams";
            $data = $this->fetchBGPViewAPI($url);

            if (!$data || !isset($data['data'])) {
                Response::error('No upstream data found for this ASN', 404);
                return;
            }

            $upstreamData = $data['data'];

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('BGP AS upstreams lookup completed', [
                'asn' => $asn,
                'ipv4_upstreams' => $upstreamData['ipv4_upstreams'] ?? [],
                'ipv6_upstreams' => $upstreamData['ipv6_upstreams'] ?? [],
                'ipv4_upstream_count' => count($upstreamData['ipv4_upstreams'] ?? []),
                'ipv6_upstream_count' => count($upstreamData['ipv6_upstreams'] ?? [])
            ]);

        } catch (\Exception $e) {
            Response::error('BGP AS upstreams lookup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get AS downstreams (customers)
     */
    public function asDownstreams(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $asn = $input['asn'] ?? '';

        if (empty($asn)) {
            Response::error('ASN is required', 400);
            return;
        }

        // Strip "AS" prefix if present
        $asn = preg_replace('/^AS/i', '', $asn);

        if (!is_numeric($asn)) {
            Response::error('Invalid ASN format', 400);
            return;
        }

        try {
            $url = "https://api.bgpview.io/asn/" . urlencode($asn) . "/downstreams";
            $data = $this->fetchBGPViewAPI($url);

            if (!$data || !isset($data['data'])) {
                Response::error('No downstream data found for this ASN', 404);
                return;
            }

            $downstreamData = $data['data'];

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('BGP AS downstreams lookup completed', [
                'asn' => $asn,
                'ipv4_downstreams' => $downstreamData['ipv4_downstreams'] ?? [],
                'ipv6_downstreams' => $downstreamData['ipv6_downstreams'] ?? [],
                'ipv4_downstream_count' => count($downstreamData['ipv4_downstreams'] ?? []),
                'ipv6_downstream_count' => count($downstreamData['ipv6_downstreams'] ?? [])
            ]);

        } catch (\Exception $e) {
            Response::error('BGP AS downstreams lookup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Search for AS by name
     */
    public function searchAS(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $query = $input['query'] ?? '';

        if (empty($query) || strlen($query) < 2) {
            Response::error('Search query must be at least 2 characters', 400);
            return;
        }

        try {
            $url = "https://api.bgpview.io/search?query_term=" . urlencode($query);
            $data = $this->fetchBGPViewAPI($url);

            if (!$data || !isset($data['data'])) {
                Response::error('No results found', 404);
                return;
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('BGP search completed', [
                'query' => $query,
                'results' => $data['data']
            ]);

        } catch (\Exception $e) {
            Response::error('BGP search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fetch data from BGPView API
     */
    private function fetchBGPViewAPI(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'VeriBits/1.0 BGP Intelligence Portal'
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \Exception('Failed to fetch BGP data from API');
        }

        $data = json_decode($response, true);

        if (!$data) {
            throw new \Exception('Invalid response from BGP API');
        }

        if (isset($data['status']) && $data['status'] === 'error') {
            throw new \Exception($data['status_message'] ?? 'BGP API returned an error');
        }

        return $data;
    }
}
