<?php declare(strict_types=1);

const RELEASES_INC = __DIR__ . '/../../php/web-php/include/releases.inc';
const DISTRO_PATH  = __DIR__ . '/../../php/php-distributions/';

require(RELEASES_INC);
$no_such_file = require(__DIR__ . '/no-such-file.php');
$not_signed   = require(__DIR__ . '/not-signed.php');

$total = 0;
each_source(function(string $version, array $release, array $source) use (&$total) {
  if (!empty($source['filename'])) ++$total;
});

$idx = 0;
each_source(function(string $version, array $release, array $source) use($no_such_file, $not_signed, &$idxi, $total) {
  global $no_such_file, $not_signed, $idx;

  if (empty($source['filename'])) return;
  printf("(% 3d/%d) %s     \r", ++$idx, $total, $source['filename']);

  $should_exist = !in_array($source['filename'], $no_such_file);
  $does_exist = file_exists(DISTRO_PATH . "/{$source['filename']}");
  if ($should_exist && !$does_exist) {
      echo "{$source['filename']} Does not exist in archive\n";
      return;
  } elseif ($does_exist && !$should_exist) {
      echo "{$source['filename']} Exists in archive, but shouldn't\n";
      return;
  }
  if (!$does_exist) { return; }

  $should_have_sig = !in_array($source['filename'], $not_signed);
  $does_have_sig = file_exists(DISTRO_PATH . "/{$source['filename']}.asc");
  if ($should_have_sig && !$does_have_sig) {
    echo "{$source['filename']} Missing GPG signature\n";
    return;
  } elseif ($does_have_sig && !$should_have_sig) {
    echo "{$source['filename']} has a GPG signature, but shouldn't\n";
    return;
  }

  if ($does_have_sig && !verify_gpg($source['filename'])) {
    echo "{$source['filename']} GPG verification failure\n";
    return;
  }

  foreach ([ 'md5', 'sha256' ] as $algo) {
    if (isset($source[$algo])) {
      if (!verify_checksum($source['filename'], $algo, $source[$algo])) {
        echo "{$source['filename']} $algo checksum mismatch\n";
      }
    }
  }
});

function verify_checksum(string $filename, string $algo, string $exp): bool {
  $hash = hash_file($algo, DISTRO_PATH . "/$filename");
  return $hash === $exp;
}

function verify_gpg(string $filename): bool {
  $asc = DISTRO_PATH . "/$filename.asc";
  exec('gpg --verify '.escapeshellarg($asc).' 2>&1', $output, $return_code);
  return 0 === $return_code;
}

function each_source(Callable $cb) {
  global $OLDRELEASES;
  foreach ($OLDRELEASES as $versions) {
    uksort($versions, function ($a, $b) { return -version_compare($a, $b); });
    foreach ($versions as $version => $release) {
      if (!empty($release['source'])) {
        foreach ($release['source'] as $source) {
          $cb($version, $release, $source);
        }
      }
    }
  }
}
