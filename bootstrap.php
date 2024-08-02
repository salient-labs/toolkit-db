<?php declare(strict_types=1);

use Composer\InstalledVersions;

if (InstalledVersions::isInstalled('adodb/adodb-php')) {
    require InstalledVersions::getInstallPath('adodb/adodb-php') . '/adodb-exceptions.inc.php';
}
