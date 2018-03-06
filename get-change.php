<?php
define('REPO', __DIR__ . '/labcr');
define('URL', 'http://apps.iisg.agh.edu.pl:12345');
define('STUDENT_PASSWORD', 'cOrh8T2mOLHtlXpJhxfxtwNv83wsSJHTeoZOzMkFZw');

require 'vendor/autoload.php';

$lock = new \TH\Lock\FileLock('lock.lock');


$task = intval($_GET['task']) - 1;
$author = str_replace('"', '', $_GET['author']);
if (php_sapi_name() == 'cli') {
    $task = 0;
    $author = 'Wojciech Frącz';
}
in_array($task, [0, 1]) or die("Invalid task");
$task += 1;
// uncomment to have diacritic characters work on windows
// $author = iconv('UTF-8', 'windows-1250', $author);

$acquired = false;
while (!$acquired) {
    try {
        $lock->acquire();
        $acquired = true;
    } catch (Exception $e) {
        sleep(1);
    }
}

$commands = [];

if (!file_exists(REPO)) {
    exec('mkdir labcr');
    $commands = [
        'cp -R ../labcr-template .git',
        'git init',
        'git remote add origin http://student:' . STUDENT_PASSWORD . '@gerrit:8080/labcr',
        'git fetch',
        'git reset --hard origin/master'
    ];
}

$commands = array_merge($commands, [
    'git reset --hard origin/master',
    'cp -R ' . __DIR__ . '/tasks/task' . $task . '/* .',
    'git add -A',
    'git commit -m "Task ' . $task . ' - ' . $author . '"',
    'git push origin HEAD:refs/for/master',
]);

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
