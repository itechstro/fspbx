<?php

namespace App\Services\Provisioning;

class FanvilSmartyConverter
{
  public static function convert(string $smarty, array $replacements = []): string
  {
    $body = $smarty;

    $body = str_replace('{$aaccount.6.uth_id}', '{$account.6.auth_id}', $body);

    foreach ($replacements as $search => $replace) {
      $body = str_replace($search, $replace, $body);
    }

    $body = preg_replace('/\{\*([^*]*)\*\}/', '{{--$1--}}', $body) ?? $body;
    $body = preg_replace('/\{strip\}(.*?)\{\/strip\}/s', '$1', $body) ?? $body;

    $body = preg_replace_callback(
      '/\{\$account\.(\d+)\.([a-zA-Z0-9_]+)\}/',
      static fn (array $m) => "{{ \$account[{$m[1]}]['{$m[2]}'] ?? '' }}",
      $body
    ) ?? $body;

    $body = preg_replace_callback(
      '/\{if \$account\.(\d+)\.([a-zA-Z0-9_]+) == \'([^\']+)\'\}/',
      static fn (array $m) => "@if ((\$account[{$m[1]}]['{$m[2]}'] ?? '') == '{$m[3]}')",
      $body
    ) ?? $body;

    $body = preg_replace_callback(
      '/\{if isset\(\$account\.(\d+)\.([a-zA-Z0-9_]+)\)\}/',
      static fn (array $m) => "@if (!empty(\$account[{$m[1]}]['{$m[2]}']))",
      $body
    ) ?? $body;

    $body = preg_replace(
      '/\{if \$row\.device_key_id > (\d+) && \$row\.device_key_id <= (\d+)\}/',
      '@if ((($row[\'device_key_id\'] ?? 0) > $1) && (($row[\'device_key_id\'] ?? 0) <= $2))',
      $body
    ) ?? $body;

    $body = preg_replace(
      '/\{if \$row\.device_key_id <= (\d+)\}/',
      '@if ((($row[\'device_key_id\'] ?? 0) <= $1))',
      $body
    ) ?? $body;

    $body = preg_replace(
      '/\{if \$row\.device_key_id < (\d+)\}/',
      '@if ((($row[\'device_key_id\'] ?? 0) < $1))',
      $body
    ) ?? $body;

    $body = preg_replace(
      '/\{if \$row\.device_key_id > (\d+) && \$row\.device_key_id < (\d+)\}/',
      '@if ((($row[\'device_key_id\'] ?? 0) > $1) && (($row[\'device_key_id\'] ?? 0) < $2))',
      $body
    ) ?? $body;

    $body = preg_replace(
      '/\{if \$row\.device_key_type == (\d+)\}/',
      '@if ((($row[\'device_key_type\'] ?? \'\') == \'$1\'))',
      $body
    ) ?? $body;

    $body = preg_replace(
      '/\{if \$([a-zA-Z0-9_]+) == \'([^\']+)\'\}/',
      '@if ((($$1 ?? \'\') == \'$2\'))',
      $body
    ) ?? $body;

    $body = preg_replace_callback(
      '/\{elseif \$row\.device_key_type == (\d+)\}/',
      static fn (array $m) => "@elseif ((\$row['device_key_type'] ?? '') == '{$m[1]}')",
      $body
    ) ?? $body;

    $body = preg_replace_callback(
      '/\{if isset\(\$([a-zA-Z0-9_]+)\)\}\{\$([a-zA-Z0-9_]+)\}\{else\}([^{]+)\{\/if\}/',
      static fn (array $m) => '@if (!empty($' . $m[1] . ')){{ $' . $m[2] . ' }}@else ' . trim($m[3]) . ' @endif',
      $body
    ) ?? $body;

    $body = preg_replace('/\{if isset\(\$([a-zA-Z0-9_]+)\)\}/', '@if (!empty($$1))', $body) ?? $body;
    $body = preg_replace('/\{else\}/', '@else', $body) ?? $body;
    $body = preg_replace('/\{elseif/', '@elseif', $body) ?? $body;
    $body = preg_replace('/\{\/if\}/', '@endif', $body) ?? $body;

    $body = preg_replace_callback(
      '/\{\$row\.([a-zA-Z0-9_]+)-(\d+)\}/',
      static fn (array $m) => "{{ \$row['{$m[1]}'] - {$m[2]} }}",
      $body
    ) ?? $body;

    $body = preg_replace_callback(
      '/\{\$row\.([a-zA-Z0-9_]+)\}/',
      static fn (array $m) => "{{ \$row['{$m[1]}'] ?? '' }}",
      $body
    ) ?? $body;

    $body = preg_replace('/\{foreach \$keys\["([a-z]+)"\] as \$row\}/', '@foreach (($keys[\'$1\'] ?? []) as $row)', $body) ?? $body;
    $body = preg_replace('/\{foreach \$contacts as \$row\}/', '@foreach (($contacts ?? []) as $row)', $body) ?? $body;
    $body = str_replace('{/foreach}', '@endforeach', $body);

    $body = preg_replace('/\{\$([a-zA-Z0-9_]+)\}/', '{{ $$1 }}', $body) ?? $body;

    return trim($body);
  }

  public static function wrapAsBladeTemplate(string $convertedBody, string $version = '1.0.0'): string
  {
    return implode("\n", [
      '{{-- version: ' . $version . ' --}}',
      '',
      '@switch($flavor)',
      '',
      "@case('mac.cfg')",
      '',
      $convertedBody,
      '',
      '    @break',
      '',
      '@endswitch',
      '',
    ]);
  }
}
