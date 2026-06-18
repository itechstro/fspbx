<?php

namespace App\Services\Provisioning;

use App\Models\Devices;

class FanvilTemplateVarBuilder
{
  private const SUPPORTED_VENDORS = ['fanvil', 'intrade', 'ibratro'];

  public static function enrich(array $vars, Devices $device): array
  {
    $vendor = self::normalizeVendor(strtolower((string) ($device->device_vendor ?? '')));
    if (! in_array($vendor, self::SUPPORTED_VENDORS, true)) {
      return $vars;
    }

    $settings = self::normalizeIntradeSettings($vars['settings'] ?? []);
    $lines = self::buildAccountLines($vars['lines'] ?? [], $vendor);
    $keys = self::buildLegacyKeys($device, $vars, $vendor);

    if ($vendor === 'intrade') {
      return self::enrichIntrade($vars, $settings, $lines, $keys, $device);
    }

    $provisionUrl = self::resolveProvisionUrl($settings, (string) ($vars['domain_name'] ?? ''), $vendor);

    $enriched = array_merge($vars, [
      'account' => $lines,
      'user' => $lines,
      'keys' => $keys,
      'voicemail_number' => $settings['voicemail_number'] ?? '*97',
      'sip_port' => $lines[1]['sip_port'] ?? ($settings['sip_port'] ?? '5060'),
      'dns_server_primary' => $settings['dns_server_primary'] ?? '8.8.8.8',
      'dns_server_secondary' => $settings['dns_server_secondary'] ?? '208.67.222.222',
      'fanvil_provision_url' => $provisionUrl,
      'fanvil_stun_server' => $settings['fanvil_stun_server'] ?? 'stun.l.google.com',
      'fanvil_stun_port' => $settings['fanvil_stun_port'] ?? '19302',
      'fanvil_greeting' => $settings['fanvil_greeting'] ?? null,
    ]);

    foreach ($settings as $key => $value) {
      $enriched[$key] = $value;
    }

    foreach (['fanvil_stun_server' => 'stun.l.google.com', 'fanvil_stun_port' => '19302'] as $key => $default) {
      if (empty($enriched[$key])) {
        $enriched[$key] = $default;
      }
    }

    return self::appendLineAliases($enriched, $lines);
  }

  private static function enrichIntrade(array $vars, array $settings, array $lines, array $keys, Devices $device): array
  {
    $profile = IntradeModelProfiles::profileForTemplate(
      (string) ($device->device_template ?? $vars['template'] ?? ''),
    );
    $settings = IntradeModelProfiles::mergeProfileDefaults($profile, $settings);

    $provisionUrl = self::resolveProvisionUrl($settings, (string) ($vars['domain_name'] ?? ''), 'intrade');
    $mac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) ($vars['mac'] ?? '')) ?: '');
    $directoryUrls = self::buildIntradeDirectoryUrls($provisionUrl, $mac, $settings);
    foreach ($directoryUrls as $key => $url) {
      $settings[$key] = $url;
    }

    $enriched = array_merge($vars, [
      'modelProfile' => $profile,
      'account' => $lines,
      'user' => $lines,
      'keys' => $keys,
      'voicemail_number' => $settings['voicemail_number'] ?? '*97',
      'sip_port' => $lines[1]['sip_port'] ?? ($settings['sip_port'] ?? '5060'),
      'dns_server_primary' => $settings['dns_server_primary'] ?? '8.8.8.8',
      'dns_server_secondary' => $settings['dns_server_secondary'] ?? '208.67.222.222',
      'intrade_provision_url' => $provisionUrl,
    ], $directoryUrls);

    $enriched['settings'] = $settings;

    foreach ($settings as $key => $value) {
      if (str_starts_with($key, 'fanvil_')) {
        continue;
      }

      $enriched[$key] = $value;
    }

    $globalDefaults = [
      'intrade_greeting' => 'InTrade',
      'intrade_country_toneset' => '13',
      'intrade_video_codec' => 'H264',
      'intrade_directory_contacts' => 'users',
    ];

    if (($settings['intrade_enable_stun'] ?? '1') !== '0') {
      $globalDefaults['intrade_stun_server'] = 'stun.l.google.com';
      $globalDefaults['intrade_stun_port'] = '19302';
    }

    foreach ($globalDefaults as $key => $default) {
      if (! isset($enriched[$key]) || $enriched[$key] === '' || $enriched[$key] === null) {
        $enriched[$key] = $default;
      }
    }

    foreach (array_keys($settings) as $key) {
      if (array_key_exists($key, $enriched)) {
        $settings[$key] = $enriched[$key];
      }
    }
    $enriched['settings'] = $settings;

    if (empty($enriched['intrade_provision_url'])) {
      $enriched['intrade_provision_url'] = $provisionUrl;
    }

    return self::appendLineAliases($enriched, $lines);
  }

  private static function normalizeVendor(string $vendor): string
  {
    return $vendor === 'ibratro' ? 'intrade' : $vendor;
  }

  /**
   * @param  array<string, mixed>  $settings
   * @return array<string, mixed>
   */
  private static function normalizeIntradeSettings(array $settings): array
  {
    foreach ($settings as $key => $value) {
      if (! str_starts_with((string) $key, 'ibratro_')) {
        continue;
      }

      $newKey = 'intrade_' . substr((string) $key, 8);
      if (! array_key_exists($newKey, $settings)) {
        $settings[$newKey] = $value;
      }
    }

    return $settings;
  }

  /**
   * @return array<string, string>
   */
  private static function buildIntradeDirectoryUrls(string $provisionUrl, string $mac, array $settings): array
  {
    $urls = [];

    for ($slot = 1; $slot <= 5; $slot++) {
      $urlKey = $slot === 1 ? 'intrade_directory_url' : 'intrade_directory_url_' . $slot;
      $contactsKey = $slot === 1 ? 'intrade_directory_contacts' : 'intrade_directory_contacts_' . $slot;
      $customUrl = trim((string) ($settings[$urlKey] ?? ''));

      if ($customUrl !== '') {
        $urls[$urlKey] = $customUrl;
        continue;
      }

      $contacts = trim((string) ($settings[$contactsKey] ?? ''));
      if ($contacts === '' || $mac === '' || $provisionUrl === '') {
        $urls[$urlKey] = '';
        continue;
      }

      $urls[$urlKey] = rtrim($provisionUrl, '/')
        . '/directory.xml?contacts=' . rawurlencode($contacts)
        . '&mac=' . $mac;
    }

    return $urls;
  }

  private static function appendLineAliases(array $enriched, array $lines): array
  {
    if (isset($lines[1])) {
      foreach ([
        'server_address',
        'outbound_proxy',
        'outbound_proxy_primary',
        'outbound_proxy_secondary',
        'display_name',
        'auth_id',
        'user_id',
        'password',
        'sip_transport',
        'sip_port',
        'register_expires',
        'shared_line',
      ] as $field) {
        $enriched[$field] = $lines[1][$field] ?? ($enriched[$field] ?? null);
      }

      $enriched['outbound_proxy'] = $lines[1]['outbound_proxy_primary'] ?? null;
    }

    foreach ($lines as $lineNumber => $line) {
      foreach ([
        'server_address',
        'outbound_proxy',
        'outbound_proxy_primary',
        'outbound_proxy_secondary',
        'display_name',
        'auth_id',
        'user_id',
        'user_password',
        'sip_transport',
        'sip_port',
        'register_expires',
        'shared_line',
      ] as $field) {
        $enriched[$field . '_' . $lineNumber] = $line[$field] ?? null;
      }
    }

    return $enriched;
  }

  private static function buildAccountLines(array $lines, string $vendor = 'fanvil'): array
  {
    $account = [];

    foreach ($lines as $lineNumber => $line) {
      $n = (int) $lineNumber;
      if ($n <= 0) {
        continue;
      }

      $registerExpires = $line['register_expires'] ?? null;
      if ($registerExpires === null || $registerExpires === '') {
        $registerExpires = '120';
      }

      $sipTransport = strtolower((string) ($line['sip_transport'] ?? 'tcp'));
      if ($sipTransport === '') {
        $sipTransport = 'tcp';
      }

      $sipPort = $line['sip_port'] ?? null;
      if ($sipPort === null || $sipPort === '') {
        $sipPort = $n === 1 ? '5060' : (string) (5060 + $n);
      }

      $account[$n] = array_merge($line, [
        'line_number' => $n,
        'user_id' => $line['user_id'] ?? $line['auth_id'] ?? null,
        'user_password' => $line['password'] ?? null,
        'register_expires' => $registerExpires,
        'sip_transport' => $sipTransport,
        'transport_code' => self::transportCode($vendor, $sipTransport),
        'sip_port' => $sipPort,
        'server_address' => $line['server_address'] ?? null,
        'outbound_proxy' => $line['outbound_proxy_primary'] ?? null,
        'outbound_proxy_primary' => $line['outbound_proxy_primary'] ?? null,
        'outbound_proxy_secondary' => $line['outbound_proxy_secondary'] ?? null,
      ]);
    }

    return $account;
  }

  private static function buildLegacyKeys(Devices $device, array $vars, string $vendor): array
  {
    $keysByArea = $vars['keys_by_area'] ?? [];
    $lines = $vars['lines'] ?? [];
    $categories = [
      'line' => [],
      'memory' => [],
      'expansion' => [],
      'programmable' => [],
    ];

    $areaCategoryMap = [
      'main' => 'line',
      'multi_purpose' => 'memory',
      'expansion' => 'expansion',
    ];

    foreach ($areaCategoryMap as $area => $category) {
      $areaKeys = $keysByArea[$area] ?? [];
      if (! is_array($areaKeys)) {
        continue;
      }

      ksort($areaKeys, SORT_NUMERIC);

      $sequence = 1;
      foreach ($areaKeys as $key) {
        if (! is_array($key)) {
          continue;
        }

        $row = self::mapKeyToLegacyRow($key, $category, $sequence++, $vendor, $lines);
        if ($row === null) {
          continue;
        }

        $categories[$category][$row['device_key_id']] = $row;
      }
    }

    return $categories;
  }

  private static function mapKeyToLegacyRow(
    array $key,
    string $category,
    int $sequence,
    string $vendor,
    array $lines
  ): ?array {
    $logicalType = self::normalizeLogicalType((string) ($key['type'] ?? ''));
    $deviceKeyType = fspbx_vendor_key_type_code($vendor, $logicalType, $category);

    $lineNumber = 1;
    if ($logicalType === 'line') {
      $lineNumber = (int) ($key['line'] ?? $key['value'] ?? 1);
      if ($lineNumber <= 0) {
        $lineNumber = 1;
      }
    } elseif (isset($key['line']) && $key['line'] !== null && $key['line'] !== '') {
      $lineNumber = max(1, (int) $key['line'] + 1);
    }

    $value = $key['value'] ?? null;
    $label = $key['label'] ?? null;

    if ($logicalType === 'line') {
      $acct = (int) ($value ?? $lineNumber);
      if ($acct <= 0) {
        $acct = 1;
      }

      if (($label === null || $label === '') && isset($lines[$acct])) {
        $label = $lines[$acct]['display_name']
          ?? $lines[$acct]['auth_id']
          ?? $lines[$acct]['user_id']
          ?? null;
      }

      $lineNumber = $acct;
      $value = '';
    } elseif ($logicalType === 'check_voicemail') {
      $value = (string) ($value ?? '');
      if ($value !== '' && ctype_digit($value)) {
        $value = 'vm' . $value;
      }
      $label = $label ?: 'Voicemail';
    } elseif ($logicalType === 'park') {
      $value = (string) ($value ?? '');
      if ($value !== '' && ctype_digit($value)) {
        $value = 'park+*' . $value;
      }
      $label = $label ?: 'Park';
    } else {
      $value = $value !== null ? (string) $value : '';
      $label = $label !== null ? (string) $label : '';
    }

    return [
      'device_key_id' => $sequence,
      'device_key_category' => $category,
      'device_key_vendor' => $vendor,
      'device_key_type' => $deviceKeyType,
      'device_key_subtype' => '',
      'device_key_line' => $lineNumber,
      'device_key_value' => $value,
      'device_key_extension' => $key['extension'] ?? '',
      'device_key_protected' => '',
      'device_key_label' => $label ?? '',
      'device_key_icon' => '',
    ];
  }

  private static function normalizeLogicalType(string $type): string
  {
    $type = strtolower(trim($type));

    return match ($type) {
      '1' => 'line',
      '3', '0' => '',
      'f' => 'speed_dial',
      'bc' => 'blf',
      'c' => 'park',
      'dtmf' => 'dtmf',
      default => $type,
    };
  }

  private static function transportCode(string $vendor, string $transport): string
  {
    $transport = strtolower(trim($transport));

    if ($vendor === 'intrade' || $vendor === 'ibratro') {
      return match ($transport) {
        'tcp' => '1',
        'tls' => '3',
        'dns srv', 'dnssrv', 'dnsnaptr' => '2',
        default => '0',
      };
    }

    return match ($transport) {
      'tcp' => '1',
      'tls' => '2',
      'dns srv', 'dnssrv', 'dnsnaptr' => '3',
      default => '0',
    };
  }

  private static function resolveProvisionUrl(array $settings, string $domainName, string $vendor = 'fanvil'): string
  {
    $urlKey = in_array($vendor, ['intrade', 'ibratro'], true) ? 'intrade_provision_url' : 'fanvil_provision_url';

    if (! empty($settings[$urlKey])) {
      return (string) $settings[$urlKey];
    }

    if ($vendor === 'intrade' && ! empty($settings['ibratro_provision_url'])) {
      return (string) $settings['ibratro_provision_url'];
    }

    $base = rtrim((string) ($settings['provision_base_url'] ?? ''), '/');
    if ($base !== '') {
      return $base;
    }

    if ($domainName !== '') {
      return 'https://' . $domainName . '/prov';
    }

    return '';
  }
}
