<?php
/**
 * Copyright (c) Enalean, 2011. All Rights Reserved.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * ---------------------------------------------------------------------------
 *
 * Dispatch jenkins jobs depending of svn logs.
 *
 * This script parse logs from a svn repository and trigger jenkins/hudson jobs
 * depending of files modified in each commit.
 * It aims to be used together with svnsync for maximum efficiency.
 */

// Parse arguments
/*
$options = getopt('', array('repository:', 'jenkins:'));
if (!isset($options['repository']) || !isset($options['jenkins'])) {
    die('Usage: '.$argv[0].' --repository=schema://path/to/repo --jenkins=http://jenkins'.PHP_EOL);
}
*/
if (!isset($argv[1]) && !isset($argv[2])) {
    die('Usage: '.$argv[0].' schema://path/to/svnrepo http://jenkins/'.PHP_EOL);
}
$options = array('repository' => $argv[1], 'jenkins' => $argv[2]);

$jobs   = getJobs($options['repository']);
$ok     = true;
foreach ($jobs as $job => $nop) {
    $ok = $ok && httpTrigger($options['jenkins'], $job);
}

if (!$ok) {
    exit(1);
}

//
// functions
//

function getJobs($repository) {
    $jobs = array();

    // Last synchro
    if (is_file('last_sync')) {
        $lastSync = (int) file_get_contents('last_sync');
        $svnRev = '--revision '.$lastSync.':HEAD';
    } else {
        $lastSync = 0;
        $svnRev = '--limit 1';
    }

    // Only look at logs if new revision
    $info = simplexml_load_string(shell_exec('svn info --xml '.$repository));
    $maxRev = $info->entry['revision'];
    if ($maxRev <= $lastSync) {
        echo "Nothing to dispatch, maxrev (".$maxRev.") not newer than lastsync (".$lastSync.")".PHP_EOL;
        return $jobs;
    }

    // fetch svn log
    $fullLog = simplexml_load_string(shell_exec('svn log --xml -v '.$repository.' '. $svnRev));
    //var_dump($log);

    foreach($fullLog->logentry as $log) {
        // Keep rev
        $revision = (int) $log['revision'];

        foreach ($log->xpath('paths/path') as $path) {
            $p = (string) $path;
            //echo $p.PHP_EOL;

            if (preg_match('%^/contrib/st/([^/]+)/([^/]+)/.*%', $p, $match)) {
                switch ($match[1]) {
                case 'bugfix':
                case 'enhancement':
                case 'plugins':
                case 'stonly':
                    $jobs['ut_'.$match[2]] = true;
                    break;
                }
            }

            if (preg_match('%^/contrib/st/intg/Codendi-ST-4.0/([^/]+)/(.*)$%', $p, $match)) {
                switch ($match[1]) {
                case 'trunk':
                    $jobs['Codendi-4.0-ST'] = true;
                    break;
                    //case 'tag':
                case 'branches':
                    //$jobs[$match[2]] = true;
                    break;
                }
            }
        }
    }
    //var_dump($jobs);
    //var_dump($revision);

    // Save last point
    file_put_contents('last_sync', $revision);

    return $jobs;
}

function httpTrigger($server, $job) {
    // $buildUrl = "http://crx2106.server.com:8080/job/$job/build?token=9aa7f36b8d8b30c7bb5e85595757b534";
    $buildUrl = $server.'/job/'.$job.'/build';
    echo 'Trigger '.$buildUrl.PHP_EOL;
    //file_get_contents($buildUrl);
}

function cliTrigger($job) {
    $cmd    = 'java -jar /opt/tomcat/webapps/ROOT/WEB-INF/hudson-cli.jar -s '.escapeshellarg($hudsonServer).' build '.escapeshellarg($job);
    $result = false;
    $output = array();
    exec($cmd, $output, $result);

    if ($result !== 0) {
        echo "*** ERROR with $cmd:\n";
        var_dump($output);
        return false;
    }
    return true;
}
