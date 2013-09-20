#!/usr/bin/php
<?php

class PackageBuilder {
    public function __construct()
    {
        // General (setup) constants
        //define('PATH_build',          '/noc/build');
        define('PATH_build',          '/Users/tylers/Sites/hydrogen/scripts');
        define('PATH_packages',       PATH_build.'/packages');
        define('PATH_site_directory', '/opt/sites');
        define('CREATE_permission',   0755);
        define('FILE_remotes',        'makedeb-remotes.ini.php');

        // Build stage constants
        define('STAGE_init',               'init');
        define('STAGE_general',            'general');
        define('STAGE_building_structure', 'building-structure');
        define('STAGE_remotes',            'remotes');
        define('STAGE_cleanup',            'cleanup');
        define('STAGE_building_package',   'building-package');

        $this->stages = array(
            STAGE_init               => '[               Init ]',
            STAGE_general            => '[            General ]',
            STAGE_building_structure => '[ Building Structure ]',
            STAGE_remotes            => '[            Remotes ]',
            STAGE_cleanup            => '[  Cleaning Up Files ]',
            STAGE_building_package   => '[   Building Package ]',
        );

        if (!file_exists(FILE_remotes)) {
            $this->_log_message($this->stages[STAGE_init], 'Error: '.FILE_remotes.' is missing, quiting...');
            exit(0);
        }
        $this->remotes = require_once(FILE_remotes);
    }

    /**
     * Function that logs messages to the screen in a consistent fashion. Takes a
     * build stage label to prefix the message with, along with the actual message.
     *
     * @param  $stage_id     string
     * @param  $message      string
     * @return void
     */
    function log_message($stage_id, $message) {
        echo $stage_id.': '.$message.PHP_EOL;
    }

    /**
     * Builds the directory structure for the package based on package type. Site
     * versus Setup packages have differnet structures.
     *
     * @param $site         string
     * @param $site_data    array
     * @return void
     */
    function build_structure($site, $site_data) {
        $status = true;

        if (is_dir($site)) {
            $this->log_message($this->stages[STAGE_building_structure], $site.' |    Directory \''.$site.'\' already exists, unlinking');
            $this->recursive_remove_directory($site);
        }

        // Making DEBIAN directory for control and pre/post files
        $status = mkdir($site.'/DEBIAN', CREATE_permission, true);
        if ($status === false) {
            throw new Exception('Creating '.$site.'/DEBIAN');
        }

        switch ($site_data['type']) {
            case 'site':
                $status = mkdir($site.PATH_site_directory.'/'.$site_data['label'], CREATE_permission, true);
                if ($status === false) {
                    throw new Exception('Creating '.$site.PATH_site_directory.'/',$site_data['label']);
                }
                break;
        }
    }

    function build_control_file($site, $site_data) {
        $this->log_message($this->stages[STAGE_building_structure], $site.' |    Creating control file');

        $fp = fopen($site.'/DEBIAN/control', 'w');
        fwrite($fp, "Package: dg-".$site_data['type']."-".$site_data['label']."
Version: ".date("ymd").(isset($site_data['tag']) ? '-'.$site_data['tag'] : '')."
Section: base
Priority: optional
Architecture: all
Depends: apache2, php5
Maintainer: ts@daxiangroup.com
Description: ".(isset($site_data['description']) ? $site_data['description'] : 'No Description')."
");
        fclose($fp);
        chmod($site.'/DEBIAN/control', CREATE_permission);
    }

    function get_remote($site, $site_data, $type) {
        $this->log_message($this->stages[STAGE_remotes], $site.' | Getting remote locally');

        switch ($type) {
            case 'site':  return $this->get_remote_site($site, $site_data); break;
            case' setup': return $this->get_remote_setup(); break;
        }
    }

    function get_remote_site($site, $site_data) {

        $filename = explode('/', $site_data['url']);
        $filename = $site.'/'.$filename[sizeof($filename)-1];

        $out = fopen($filename, 'wb');
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_FILE, $out);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, $site_data['url']);

        $this->log_message($this->stages[STAGE_remotes], $site.' |    Saving '.$site_data['url'].' to '.$filename);
        curl_exec($ch);

        curl_close($ch);

        return $filename;
    }

    function unzip_remote($site, $site_data, $filename) {
        $this->log_message($this->stages[STAGE_remotes], $site.' |    Unzipping '.$filename);

        $zip = new ZipArchive;
        if ($zip->open($filename) === true) {
            // We're starting at 1 to avoid creating the .zip's top level directory
            for($i=1; $i<$zip->numFiles; $i++) {
                $tmp_filename = $zip->getNameIndex($i);
                $tmp_fileinfo = pathinfo($tmp_filename);
                copy("zip://".$filename."#".$tmp_filename, $site.PATH_site_directory.'/'.$site_data['label'].'/'.$tmp_fileinfo['basename']);
                $this->log_message($this->stages[STAGE_remotes], $site.' |    + Expanding '.$site.PATH_site_directory.'/'.$site_data['label'].'/'.$tmp_fileinfo['basename']);
            }

            $this->log_message($this->stages[STAGE_remotes], $site.' |    Unzip was successful!');
            $zip->close();
        }

        $this->log_message($this->stages[STAGE_remotes], $site.' |    - Unlinking '.$filename);
        unlink($filename);
    }

    function clean_admin_files($site, $directory) {
        $delete_files = array(
            '.gitignore',
            'readme.md',
        );

        if (is_readable($directory)) {
            $handle = opendir($directory);
            while (FALSE !== ($item = readdir($handle))) {
                if ($item != '.' && $item != '..') {
                    $path = $directory.'/'.$item;
                    if (is_dir($path)) {
                        $this->clean_admin_files($site, $path);
                    } elseif (in_array($item, $delete_files)) {
                        $this->log_message($this->stages[STAGE_cleanup], $site.' |    - Unlinking '.$path);
                        unlink($path);
                    }
                }
            }

            closedir($handle);
        }
    }

    function build_package($site) {
        $this->log_message($this->stages[STAGE_building_package], $site.' | Building package \''.$site.'\'');
        passthru('dpkg --build '.$site.' '.PATH_packages);
        $this->log_message($this->stages[STAGE_building_package], $site.' | Package \''.$site.'\' Complete');
    }


    function iterate_site_remotes() {
        if (!count($this->remotes['site'])) {
            return false;
        }

        $this->log_message($this->stages[STAGE_general], '========( Iterating SITE Remotes )========');

        foreach ($this->remotes['site'] AS $site => $site_data) {
            $this->log_message($this->stages[STAGE_building_structure], $site.' | Setting up package structure');

            // Trying to build the remote package structure. If it doesn't work, we need
            // to continue, as there's nothing to build from.
            try {
                $this->build_structure($site, $site_data);
            } catch (Exception $e) {
                // Output the message and continue to the next element in the array.
                $this->log_message($this->stages[STAGE_building_structure], $site.' | *** Error: '.$e->getMessage());
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
                $this->log_message($this->stages[STAGE_cleanup], $site.' | Cleaning up admin files');
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

    function prompt($message) {
        echo $message;
        $fp = fopen('php://stdin', 'r');
        return trim(fgets($fp));
    }

    function recursive_remove_directory($directory, $empty=FALSE) {
        if (substr($directory,-1) == '/') {
            $directory = substr($directory,0,-1);
        }

        if (!file_exists($directory) || !is_dir($directory)) {
            return FALSE;
        } elseif (is_readable($directory)) {
            $handle = opendir($directory);
            while (FALSE !== ($item = readdir($handle))) {
                if ($item != '.' && $item != '..') {
                    $path = $directory.'/'.$item;
                    if (is_dir($path)) {
                        $this->recursive_remove_directory($path);
                    } else {
                        unlink($path);
                    }
                }
            }

            closedir($handle);

            if ($empty == FALSE) {
                if(!rmdir($directory)) {
                    return FALSE;
                }
            }
        }

        return TRUE;
    }
}

// MAIN
$opts = getopt('', array('help::', 'ref:', 'csv:', 'pause::', 'offset::', 'limit::'));

$packageBuilder = new PackageBuilder();
$packageBuilder->iterate_site_remotes();

#for i in `ls -l $PATH_setup | grep ^d | awk {'print $9'}`; do
#    dpkg --build $i $PATH_packages;
#done