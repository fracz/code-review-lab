<?php
define('REPO', __DIR__ . '/labcr');
define('URL', 'http://apps.iisg.agh.edu.pl:12345');

require 'vendor/autoload.php';

$lock = new \TH\Lock\FileLock('lock.lock');

$acquired = false;

$task = 1;//intval($_GET['task']) - 1;
in_array($task, [0, 1]) or die();
$task += 1;
$author = 'AA';//str_replace('"', '', $_GET['author']);
// uncomment to have diacritic characters work on windows
// $author = iconv('UTF-8', 'windows-1250', $author);

while (!$acquired) {
    try {
        $lock->acquire();
        $acquired = true;
    } catch (Exception $e) {
        sleep(1);
    }
}

$commands = [
    'git reset --hard origin/master',
    'cp -R ' . __DIR__ . '/tasks/task' . $task . '/* .',
    'git add -A',
    'git commit -m "Task ' . $task . ' - ' . $author . '"',
    'git push origin HEAD:refs/for/master',
];

$changeIdRegex = '#remote:\s*http://.+/(\d+)#';

foreach ($commands as $command) {
    $result = [];
    exec('cd ' . REPO . ' && ' . $command . ' 2>&1', $result);
    if (php_sapi_name() == 'cli') {
        echo implode(PHP_EOL, $result) . PHP_EOL;
    }
    foreach ($result as $line) {
        if (preg_match($changeIdRegex, $line, $match)) {
            echo URL . '/#/c/' . $match[1] . '/';
        }
    }
}
