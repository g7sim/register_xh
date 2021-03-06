<?php

/**
 * Copyright (c) 2007 Carsten Heinelt (http://cmsimple.heinelt.eu)
 * Copyright (c) 2010-2012 Gert Ebersbach (http://www.ge-webdesign.de/cmsimpleplugins/)
 * Copyright (c) 2012-2017 Christoph M. Becker
 *
 * This file is part of Register_XH.
 */

namespace Register;

class DbService
{
    /**
     * @var string
     */
    private $dirname;

    /**
     * @param string $dirname
     */
    public function __construct($dirname)
    {
        $this->dirname = $dirname;
    }

    /**
     * @param int $mode
     */
    public function lock($mode)
    {
        static $fp;
    
        $fn = $this->dirname . '/.lock';
        touch($fn);
        if ($mode != LOCK_UN) {
            $fp = fopen($fn, 'r');
            flock($fp, $mode);
        } else {
            flock($fp, $mode);
            fclose($fp);
            unset($fp);
        }
    }

    /**
     * @return array
     */
    public function readGroups()
    {
        $filename = "{$this->dirname}groups.csv";
        $groupArray = array();
        if (is_file($filename)) {
            $fp = fopen($filename, "r");
            while (!feof($fp)) {
                $line = rtrim(fgets($fp, 4096));
                if ($entry = $this->readGroupLine($line)) {
                    $groupArray[] = $entry;
                }
            }
            fclose($fp);
        }
        return $groupArray;
    }

    /**
     * @var string $line
     * @return ?array
     */
    private function readGroupLine($line)
    {
        if (!empty($line) && strpos($line, '//') !== 0) {
            $parts = explode('|', $line, 2);
            $groupname = $parts[0];
            $loginpage = isset($parts[1]) ? $parts[1] : '';
            // line must not start with '//' and all fields must be set
            if (strpos($groupname, "//") === false && $groupname != "") {
                return (object) compact('groupname', 'loginpage');
            }
        }
    }

    /**
     * @return bool
     */
    public function writeGroups(array $array)
    {
        $filename = "{$this->dirname}groups.csv";
        // remove old backup
        if (is_file($filename . ".bak")) {
            unlink($filename . ".bak");
        }
        // create new backup
        $permissions = false;
        if (is_file($filename)) {
            $permissions = fileperms($filename) & 0777;
            rename($filename, $filename . ".bak");
        }

        $fp = fopen($filename, "w");
        if ($fp === false) {
            return false;
        }

        // write comment line to file
        $line = '// Register Plugin Group Definitions'."\n" . '// Line Format:'."\n" . '// groupname|loginpage'."\n";
        if (!fwrite($fp, $line)) {
            fclose($fp);
            return false;
        }

        foreach ($array as $entry) {
            $groupname = $entry->groupname;
            $line = "$groupname|$entry->loginpage\n";
            if (!fwrite($fp, $line)) {
                fclose($fp);
                return false;
            }
        }
        fclose($fp);

        // change permissions of new file to same as backup file
        if ($permissions !== false) {
            chmod($filename, $permissions);
        }
        return true;
    }

    /**
     * @return object[]
     */
    public function readUsers()
    {
        $filename = "{$this->dirname}users.csv";
        $userArray = array();

        if (is_file($filename)) {
            $fp = fopen($filename, "r");
            while (!feof($fp)) {
                $line = fgets($fp, 4096);
                if ($line != "" && strpos($line, '//')=== false) {
                    if ($entry = $this->readUserLine($line)) {
                        $userArray[] = $entry;
                    }
                }
            }
            fclose($fp);
        }
        return $userArray;
    }

    /**
     * @param string $line
     * @return ?object
     */
    private function readUserLine($line)
    {
        list($username,$password,$accessgroups,$name,$email,$status) = explode(':', rtrim($line));
        // line must not start with '//' and all fields must be set
        if ($username != "" && $password != "" && $accessgroups != ""
                && $name != "" && $email != ""/* && $status != ""*/) {
            return (object) array(
                'username' => $username,
                'password' => $password,
                'accessgroups' => explode(',', $accessgroups),
                'name' => $name,
                'email' => $email,
                'status' => $status
            );
        }
    }

    /**
     * @return bool
     */
    public function writeUsers(array $array)
    {
        $filename = "{$this->dirname}users.csv";
        // remove old backup
        if (is_file($filename . ".bak")) {
            unlink($filename . ".bak");
        }

        // create new backup
        $permissions = false;
        if (is_file($filename)) {
            $permissions = fileperms($filename) & 0777;
            rename($filename, $filename . ".bak");
        }

        $fp = fopen($filename, "w");
        if ($fp === false) {
            return false;
        }

        // write comment line to file
        $line = '// Register Plugin user Definitions'."\n"
            . '// Line Format:'."\n"
            . '// login:password:accessgroup1,accessgroup2,...:fullname:email:status'."\n";
        if (!fwrite($fp, $line)) {
            fclose($fp);
            return false;
        }

        foreach ($array as $entry) {
            $username = $entry->username;
            $password = $entry->password;
            $accessgroups = implode(',', $entry->accessgroups);
            $fullname = $entry->name;
            $email = $entry->email;
            $status = $entry->status;
            $line = "$username:$password:$accessgroups:$fullname:$email:$status"."\n";
            if (!fwrite($fp, $line)) {
                fclose($fp);
                return false;
            }
        }
        fclose($fp);

        // change permissions of new file to same as backup file
        if ($permissions !== false) {
            chmod($filename, $permissions);
        }
        return true;
    }
}
