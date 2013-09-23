#!/usr/bin/php
<?php namespace DG;

use \Exception;
use \ZipArchive;

define('PAD1', '   ');
define('PAD2', '      ');
define('PAD3', '         ');

date_default_timezone_set('America/New_York');

class PackageBuilder
{
    //const PATH_build          = '/noc/build';
    const PATH_BUILD          = '/Users/tylers/Sites/DG-setup/';
    const PATH_PACKAGES       = '/Users/tylers/Sites/DG-setup/packages/';
    const PATH_BINARIES       = '/Users/tylers/Sites/DG-setup/binaries/';
    const PATH_SITE_DIRECTORY = 'opt/sites';
    const CREATE_PERMISSION   = 0755;
    const FILE_REMOTES        = 'makedeb-remotes.ini.php';

    // Build stage constants
    const STAGE_VALIDATION      = '[.Validation........]';
    const STAGE_BUILD           = '[.Build.............]';
    const STAGE_BUILD_STRUCTURE = '[.Build - Structure.]';
    const STAGE_BUILD_PACKAGE   = '[.Build - Package...]';
    const STAGE_GENERAL         = '[.General...........]';
    const STAGE_REMOTES         = '[.Remotes...........]';
    const STAGE_CLEANUP         = '[.Cleaning Files....]';

    private $remotes      = array();
    private $buildRemotes = array();

    public function __construct()
    {
        // General (setup) constants
        $this->remotes = $this->buildRemotes = require_once(self::FILE_REMOTES);
        $this->buildPaths();
    }

    private function buildPaths()
    {
        if (is_dir(self::PATH_PACKAGES)) {
            $this->logMessage(self::STAGE_BUILD, 'Unlinking previous packages directory...', false);
            $this->recursiveRemoveDirectory(self::PATH_PACKAGES);
            $this->logMessage('', ' OK');
        }

        $this->logMessage(self::STAGE_BUILD, 'Creating packages directory...', false);
        if (!mkdir(self::PATH_PACKAGES, self::CREATE_PERMISSION, true)) {
            $this->logMessage(self::STAGE_BUILD, '   Error: Could not create packages directory', true, true);
            exit(3);
        }
        $this->logMessage('', ' OK');

        $this->logMessage(self::STAGE_BUILD, 'Creating binaries directory...', false);
        if (!is_dir(self::PATH_BINARIES)) {
            mkdir(self::PATH_BINARIES, self::CREATE_PERMISSION, true);
        }
        $this->logMessage('', ' OK');
    }

    /**
     * Function that logs messages to the screen in a consistent fashion. Takes a
     * build stage label to prefix the message with, along with the actual message.
     *
     * @param  $stage        string
     * @param  $message      string
     * @return void
     */
    public static function logMessage($stage = '', $message, $appendNewLine = true, $prependNewLine = false)
    {
        echo ($prependNewLine ? PHP_EOL : '').$stage.($stage == '' ? '' : ' ').$message.($appendNewLine ? PHP_EOL : '');
    }

    // The M&P!
    public function buildPackages($packages)
    {
        $this->logMessage(self::STAGE_BUILD, 'Beginning build process!');

        foreach ($packages as $package) {
            $this->logMessage(self::STAGE_BUILD, PAD1.'---[ '.$package.' ]---', true, true);

            $tmp = explode('/', $package);
            $package = $tmp[0];
            if (count($tmp) > 1) {
                $this->remotes[$package]['tag'] = $tmp[1];
            }

            // Let's first make sure we have a definition for the supplied package
            if (!isset($this->remotes[$package])) {
                $this->logMessage(self::STAGE_BUILD, PAD2.'Error: The package \''.$package.'\' has no definition, skipping!');
                unset($this->buildRemotes[$package]);
                continue;
            }
            // Ensure that it is an array in the definition
            if (!is_array($this->remotes[$package])) {
                $this->logMessage(self::STAGE_BUILD, PAD2.'Error: The package \''.$package.'\' has incomplete definition, skipping!');
                unset($this->buildRemotes[$package]);
                continue;
            }

            if (!$this->buildStructure($package)) {
                $this->logMessage(self::STAGE_BUILD_STRUCTURE, $package.' | Couldn\'t properly build structure, skipping');
                unset($this->buildRemotes[$package]);
                continue;
            }

            if (!$packageName = $this->getRemote($package)) {
                $this->logMessage(self::STAGE_REMOTES, $package.' | Couldn\'t properly transfer remote, skipping');
                unset($this->buildRemotes[$package]);
                continue;
            }

            if (!$this->unzipRemote($package, $packageName)) {
                $this->logMessage(self::STAGE_REMOTES, $package.' | Couldn\'t unzip remote, skipping');
                unset($this->buildRemotes[$package]);
                continue;
            }

            //$this->cleanAdminFiles($package);
            $this->buildControlFile($package);
            $this->buildPackage($package);
        }
    }

    /**
     * Builds the directory structure for the package based on package type. Site
     * versus Setup packages have differnet structures.
     *
     * @param $site         string
     * @param $site_data    array
     * @return void
     */
    private function buildStructure($package)
    {
        $packageData = $this->remotes[$package];

        $this->logMessage(self::STAGE_BUILD_STRUCTURE, PAD2.'Bulding package directory structure...', false);

        if (is_dir(self::PATH_PACKAGES.$package)) {
            $this->logMessage(STAGE_BUILD_STRUCTURE, $package.' |    Directory \''.$package.'\' already exists, unlinking');
            $this->recursiveRemoveDirectory(self::PATH_PACKAGES.$package);
        }

        // Making DEBIAN directory for control and pre/post files
        $path = self::PATH_PACKAGES.$package.'/DEBIAN';
        if (!mkdir($path, self::CREATE_PERMISSION, true)) {
            $this->logMessage(self::STAGE_BUILD_STRUCTURE, $package.' | Couldn\'t create '.$path);
            return false;
        }

        switch ($packageData['type']) {
            case 'site':
                $path = self::PATH_PACKAGES.$package.'/'.self::PATH_SITE_DIRECTORY.'/'.$packageData['label'];
                if (!mkdir($path, self::CREATE_PERMISSION, true)) {
                    $this->logMessage(self::STAGE_BUILD_STRUCTURE, $package.' | Couldn\'t create '.$path);
                    return false;
                }
                break;
        }

        $this->logMessage('', ' OK');
        return true;
    }

    private function buildControlFile($package)
    {
        $packageData = $this->remotes[$package];
        $deps = (isset($packageData['deps']) ? $packageData['deps'] : 'apache2, php5');

        $this->logMessage(self::STAGE_BUILD_PACKAGE, PAD2.'Creating control file...', false);
        //Version: 1.".date("ymd").(isset($packageData['tag']) ? '-'.$packageData['tag'] : '')."
        //Version: 1.".date("ymdHis")."

        $contents = "Package: dgrp-".$packageData['type']."-".$packageData['label']."
Version: 1.".date("ymdHi").'.'.$packageData['tag']."
Section: base
Priority: optional
Architecture: all
Depends: ".$deps."
Maintainer: ts@daxiangroup.com
Description: ".(isset($packageData['description']) ? $packageData['description'] : 'No Description')."
";

        $fp = fopen(self::PATH_PACKAGES.$package.'/DEBIAN/control', 'w');
        fwrite($fp, $contents);
        fclose($fp);
        chmod(self::PATH_PACKAGES.$package.'/DEBIAN/control', self::CREATE_PERMISSION);
        $this->logMessage('', ' OK');
    }

    private function getRemote($package)
    {
        $verbose = false;
        $this->logMessage(self::STAGE_REMOTES, PAD2.'Transfering remote to local machine...', ($verbose ? true : false));

        switch ($this->remotes[$package]['type']) {
            case 'site':
                return $this->getRemoteSite($package);
                break;
            case 'setup':
                return $this->get_remote_setup();
                break;
        }
    }

    private function getRemoteSite($package)
    {
        $verbose = false;
        $packageData = $this->remotes[$package];

        //$filename = explode('/', $packageData['url']);
        //$filename = self::PATH_PACKAGES.$package.'/'.self::PATH_SITE_DIRECTORY.'/'.$packageData['label'].$filename[sizeof($filename)-1];
        $filename = self::PATH_PACKAGES.$package.'/'.self::PATH_SITE_DIRECTORY.'/'.$packageData['label'].'/'.$packageData['tag'].'.zip';

        if ($verbose) {
            $this->logMessage(self::STAGE_REMOTES, PAD3.'Remote: '.$packageData['url'].$packageData['tag'].'.zip');
            $this->logMessage(self::STAGE_REMOTES, PAD3.'Local: '.$filename);
            $this->logMessage(self::STAGE_REMOTES, PAD3.'Status:', false);
        }

        $out = fopen($filename, 'wb');
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_FILE, $out);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, $packageData['url'].$packageData['tag'].'.zip');

        if (!curl_exec($ch)) {
            $this->logMessage('', 'Error');
            return false;
        }

        curl_close($ch);

        $this->logMessage('', ' OK');
        return $filename;
    }

    private function unzipRemote($package, $packageName)
    {
        $packageData = $this->remotes[$package];
        $path        = self::PATH_PACKAGES.$package.'/'.self::PATH_SITE_DIRECTORY.'/'.$packageData['label'].'/';
        $ignoreFiles = array(
            '.gitignore',
            'readme.md',
        );

        $this->logMessage(self::STAGE_REMOTES, PAD2.'Unzipping '.$packageName);

        $zip = new ZipArchive;
        if ($zip->open($packageName) === true) {
            // We're starting at 1 to avoid creating the .zip's top level directory
            $column  = 0;
            $verbose = false;
            $max = 80;

            if (!$verbose) {
                $this->logMessage(self::STAGE_REMOTES, PAD3.'', false);
            }

            for ($i=1; $i<$zip->numFiles; $i++) {
                $prepend      = false;
                $tmp_filename = $zip->getNameIndex($i);
                $src          = 'zip://'.$packageName.'#'.$tmp_filename;
                $tmp_fileinfo = pathinfo($tmp_filename);
                //echo $tmp_filename.PHP_EOL;
                //print_r($tmp_fileinfo);

                $dst  = $path.preg_replace('/^'.$packageData['label'].'-'.$packageData['tag'].'\/?/', '', $tmp_fileinfo['dirname']);
                $dst .= '/'.$tmp_fileinfo['basename'];

                //echo "BASENAME: ".basename($dst).PHP_EOL;
                if (array_search(basename($dst), $ignoreFiles) !== false) {
                    if ($verbose) {
                        $this->logMessage(self::STAGE_REMOTES, PAD3.'i Ignoring '.$dst);
                    }
                    else {
                        if ($column == $max) {
                            $column = 0;
                            $this->logMessage(self::STAGE_REMOTES, PAD3.'', false, true);
                        }
                        $this->logMessage('', 'i', false);
                    }

                    $column++;

                    continue;
                }

                // If the file we're parsing from the ZipArchive is a dirctory,
                // we should make the directory in the destination.
                if (!isset($tmp_fileinfo['extension']) && !is_dir($dst)) {
                    mkdir($dst, self::CREATE_PERMISSION, true);
                }

                // If there is an extension in the pathinfo array, it's a file
                // we're parsing and should copy it from the ZipArchive to the
                // destination.
                if (isset($tmp_fileinfo['extension'])) {
                    copy($src, $dst);
                }

                //echo 'src: '.$src.PHP_EOL.'dst: '.$dst.PHP_EOL.'base: '.dirname($dst).PHP_EOL;
                //copy("zip://".$packageName."#".$tmp_filename, self::PATH_PACKAGES.$package.'/'.self::PATH_SITE_DIRECTORY.'/'.$packageData['label'].'/'.$tmp_fileinfo['basename']);

                if ($verbose) {
                    $this->logMessage(self::STAGE_REMOTES, PAD3.'+ Expanding '.$dst);
                }
                else {
                    if ($column == $max) {
                        $column = 0;
                        $this->logMessage(self::STAGE_REMOTES, PAD3.'', false, true);
                    }
                    $this->logMessage('', '.', false);
                }

                $column++;
            }

            $zip->close();
        }

        if ($verbose) {
            $this->logMessage(self::STAGE_REMOTES, PAD3.'- Unlinking '.$packageName);
        }
        else {
            if ($column == $max) {
                $column = 0;
                $this->logMessage(self::STAGE_REMOTES, PAD3.'', false, true);
            }
            $this->logMessage('', '-');
        }
        unlink($packageName);

        return true;
    }

    private function buildPackage($package)
    {
        $this->logMessage(self::STAGE_BUILD_PACKAGE, PAD2.'Building package \''.$package.'\'...', false);
        ob_start();
        @system('dpkg --build '.self::PATH_PACKAGES.$package.' '.self::PATH_BINARIES);
        ob_end_clean();
        $this->logMessage('', ' OK');
    }

    /*
    public function iterateSiteRemotes()
    {
        if (!count($this->remotes['site'])) {
            return false;
        }

        $this->log_message($this->stages[STAGE_GENERAL], '========( Iterating SITE Remotes )========');

        foreach ($this->remotes['site'] as $site => $site_data) {
            $this->log_message($this->stages[STAGE_BUILDING_STRUCTURE], $site.' | Setting up package structure');

            // Trying to build the remote package structure. If it doesn't work, we need
            // to continue, as there's nothing to build from.
            try {
                $this->build_structure($site, $site_data);
            } catch (Exception $e) {
                // Output the message and continue to the next element in the array.
                $this->log_message($this->stages[STAGE_BUILDING_STRUCTURE], $site.' | *** Error: '.$e->getMessage());
                continue;
            }

            try {
                $this->build_control_file($site, $site_data);
            } catch (Exception $e) {
                echo 'Error: '.$e->getMessage();
            }

            // Get the remote to the local machine.
            try {
                $filename = $this->get_remote($site, $site_data, 'site');
            } catch (Exception $e) {
                echo 'Error: '.$e->getMessage();
            }

            try {
                $this->unzip_remote($site, $site_data, $filename);
            } catch (Exception $e) {
                echo 'Error: '.$e->getMessage();
            }

            try {
                $this->log_message($this->stages[STAGE_CLEANUP], $site.' | Cleaning up admin files');
                $this->clean_admin_files($site, $site);
            } catch (Exception $e) {
                echo 'Error: '.$e->getMessage();
            }

            try {
                $this->build_package($site);
            } catch (Expanding $e) {
                echo 'Error: '.$e->getMessage();
            }

            switch ($this->prompt('Are you done [Y/n]: ')) {
                case '':
                case 'Y':
                case 'y':
                    echo 'ok!!';
                    break;
                case 'N':
                case 'n':
                    echo 'loser';
                    break;
            }
        }
    }
    */

    private function prompt($message)
    {
        echo $message;
        $fp = fopen('php://stdin', 'r');
        return trim(fgets($fp));
    }

    private function recursiveRemoveDirectory($directory, $empty = false)
    {
        if (substr($directory, -1) == '/') {
            $directory = substr($directory, 0, -1);
        }

        if (!file_exists($directory) || !is_dir($directory)) {
            return false;
        } elseif (is_readable($directory)) {
            $handle = opendir($directory);
            while (false !== ($item = readdir($handle))) {
                if ($item != '.' && $item != '..') {
                    $path = $directory.'/'.$item;
                    if (is_dir($path)) {
                        $this->recursiveRemoveDirectory($path);
                    } else {
                        unlink($path);
                    }
                }
            }

            closedir($handle);

            if ($empty == false) {
                if (!rmdir($directory)) {
                    return false;
                }
            }
        }

        return true;
    }
}

// MAIN
$optsShort = 'v';
$optsLong  = array('farts', 'verbose');

$opts = getopt($optsShort, $optsLong);

$arguments = $argv;
print_r($arguments);

foreach ($optsLong as $opt) {
    echo $opt.PHP_EOL;
    if ($idx = array_search('--'.$opt, $arguments) !== false) {
        echo $idx.PHP_EOL;
        unset($arguments[$idx]);
    }
}

array_shift($arguments);
print_R($arguments);
die();

$PackageBuilder = new PackageBuilder();

// VALIDATE!
if (count($arguments) === 0) {
    // No packages passed in to build
    PackageBuilder::logMessage($PackageBuilder::STAGE_VALIDATION, 'Error: Missing packages to build!');
    exit(1);
}

if (!file_exists(PackageBuilder::FILE_REMOTES)) {
    PackageBuilder::logMessage($PackageBuilder::STAGE_VALIDATION, 'Error: '.$PackageBuilder::FILE_REMOTES.' is missing, quiting...');
    exit(3);
}

// BUILD!
$PackageBuilder->buildPackages($arguments);

//$PackageBuilder->iterate_site_remotes();

#for i in `ls -l $PATH_setup | grep ^d | awk {'print $9'}`; do
#    dpkg --build $i $PATH_packages;
#done