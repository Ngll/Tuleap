<?php

// Parse arguments
$options = getopt('', array('repository:', 'jenkins:'));
if (!isset($options['repository']) || !isset($options['jenkins'])) {
    die('Usage: '.$argv[0].' --repository=schema://path/to/repo --jenkins=http://jenkins'.PHP_EOL);
}

$jobs = getJobs($options['repository']);

$error = false;
foreach ($jobs as $job => $nop) {
    httpTrigger($options['jenkins'], $job);
}

//
// functions
//

function getJobs($repository) {
    // Last synchro
    if (is_file('last_sync')) {
        $lastSync = (int) file_get_contents('last_sync');
        $svnRev = '--revision '.$lastSync.':HEAD';
    } else {
        $lastSync = 0;
        $svnRev = '--limit 1';
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
    // $buildUrl = "http://crx2106.cro.st.com:8080/job/$job/build?token=9aa7f36b8d8b30c7bb5e85595757b534";
    $buildUrl = $server.'/job/'.$job.'/build';
    echo 'Trigger '.$buildUrl.PHP_EOL;
    file_get_contents($buildUrl);
}

function cliTrigger($job) {
    $cmd    = 'java -jar /opt/tomcat/webapps/ROOT/WEB-INF/hudson-cli.jar -s '.escapeshellarg($hudsonServer).' build '.escapeshellarg($job);
    $result = false;
    $output = array();
    exec($cmd, $output, $result);

    if ($result !== 0) {
        echo "*** ERROR with $cmd:\n";
        var_dump($output);
        $error = true;
    }
}
