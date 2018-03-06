<?php

require '../vendor/autoload.php';
require 'db.php';

srand(microtime(true));

$app = new \Slim\Slim();

$db = $adapter = new Zend\Db\Adapter\Adapter($DB_CONFIG);

$app->get('/import', function () use ($app, $db) {
//        $db->query('DELETE FROM mcr_review')->execute();
    $lastImportedChange = 418;
    $firstTaskId = 5;
    $secondTaskId = $firstTaskId + 1;
    $db->query('INSERT IGNORE INTO mcr_review (id, task, start) SELECT change_id, IF(topic="task1",' . $firstTaskId . ',' . $secondTaskId . '), created_on FROM changes WHERE change_id > ' . $lastImportedChange . ' AND status != "A" AND dest_project_name = "mcr"')->execute();
    $db->query('INSERT IGNORE INTO mcr_comment(uid, file, review_id, created, text, line) SELECT uuid, file_name, change_id, written_on, message, line_nbr FROM patch_comments WHERE change_id > ' . $lastImportedChange . ' AND change_id IN (SELECT id FROM mcr_review)')->execute();
    $db->query('UPDATE mcr_review SET end=(SELECT MAX(created) FROM mcr_comment WHERE review_id = mcr_review.id) WHERE end IS NULL')->execute();
    $db->query('DELETE FROM mcr_review WHERE end IS NULL')->execute(); // no-comments reviews
    $db->query('UPDATE mcr_review SET is_mobile = 1 WHERE is_horizontal IS NULL AND id IN(SELECT DISTINCT review_id FROM mcr_comment WHERE text LIKE "Screen size: %" AND line = 1 AND file = "/COMMIT_MSG")')->execute();
    $db->query('UPDATE mcr_comment SET is_predefined=1 WHERE review_id IN(SELECT id FROM mcr_review WHERE is_mobile=1) AND is_predefined IS NULL AND text IN(
                      "Magic value",
                      "Find a better name",
                      "Extract method",
                      "Train wrecks",
                      "Far too complicated",
                      "Syntax error",
                      "Duplicated code",
                      "Unhandled exception",
                      "Unused variable",
                      "Unreachable code",
                      "Typo",
                      "Useless comment",
                      "Inconsistent format"
                    )')->execute();
    $db->query('UPDATE mcr_comment SET is_predefined=0 WHERE is_predefined IS NULL')->execute();
    $db->query('UPDATE mcr_comment SET smell_task = ((SELECT task FROM mcr_review WHERE mcr_review.id = review_id) + 1) % 2 + 1 WHERE smell_task IS NULL')->execute();
    $newMobileReviews = $db->query('SELECT id FROM mcr_review WHERE is_horizontal IS NULL AND is_mobile = 1')->execute();
    foreach ($newMobileReviews as $reviewId) {
        $id = $reviewId['id'];
        $firstCommitMsgComment = $db->query('SELECT `text` FROM mcr_comment WHERE `text` LIKE "Screen size: %"
                                                  AND line = 1 AND file = "/COMMIT_MSG"
                                                  AND review_id = ' . $id . ' ORDER BY created DESC')->execute()->current();
        preg_match('#Screen size: (\d+)x(\d+)#', $firstCommitMsgComment['text'], $screenSize);
        preg_match('#SDK: (\d+)#', $firstCommitMsgComment['text'], $sdk);
        preg_match('#Release: ([\d\.]+)#', $firstCommitMsgComment['text'], $android);
        $isHorizontal = intval($screenSize[1]) > intval($screenSize[2]) ? 1 : 0;
        $db->query("UPDATE mcr_review SET is_horizontal=$isHorizontal, screen_width=$screenSize[1], screen_height=$screenSize[2]
                        , sdk=$sdk[1], android_version='$android[1]' WHERE id=$id")->execute();
    }
});


$app->get('/review/authors', function () use ($app, $db) {
    $reviews = $db->query('SELECT * FROM mcr_review')->execute();
    $result = [];
    foreach ($reviews as $index => $review) {
        $res = $review;
        $res['comments'] = [];
        $res['is_mobile'] = (bool)$res['is_mobile'];
        $res['is_horizontal'] = (bool)$res['is_horizontal'];
        $res['start'] = strtotime($res['start']);
        $res['time'] = strtotime($res['end']) - $res['start'];
        $comments = $db->query('SELECT * FROM mcr_comment WHERE file != "/COMMIT_MSG" AND review_id=' . $review['id'] . ' ORDER BY created ASC')->execute();
        foreach ($comments as $comment) {
            $comment['file'] = basename($comment['file']);
            $comment['created'] = strtotime($comment['created']);
            $comment['is_predefined'] = (bool)$comment['is_predefined'];
            $res['comments'][] = $comment;
        }
        $encoded = json_encode($res);
        $result[] = $encoded;
    }
    $app->response()->headers->set('Content-Type', 'application/json');
    echo '[', implode(',', $result), ']';
});

$app->get('/review/comments', function () use ($app, $db) {
    $comments = $db->query('SELECT mcr_comment.*, is_mobile, task, author FROM mcr_review INNER JOIN mcr_comment ON review_id = mcr_review.id
WHERE file != "/COMMIT_MSG"
ORDER BY task, file, line;')->execute();
    $files = [];
    foreach ($comments as $comment) {
        $fid = $comment['file'] . $comment['task'];
        if (!isset($files[$fid])) $files[$fid] = [
            'task' => $comment['task'],
            'file' => basename($comment['file']),
            'comments' => [],
        ];
        $comment['is_mobile'] = (bool)$comment['is_mobile'];
        $comment['is_predefined'] = (bool)$comment['is_predefined'];
        $files[$fid]['comments'][] = $comment;
    }
    $app->response()->headers->set('Content-Type', 'application/json');
    echo json_encode(array_values($files));
});

$app->put('/review/:id', function ($id) use ($app, $db) {
    $id = intval($id);
    $body = json_decode($app->request()->getBody());
    $db->query('UPDATE mcr_review SET author = ? WHERE id = ?')->execute([$body->author, $id]);
});

$app->delete('/review/:id', function ($id) use ($app, $db) {
    $id = intval($id);
    $db->query('DELETE FROM mcr_review WHERE id = ?')->execute([$id]);
});

$app->put('/comment/:id', function ($id) use ($app, $db) {
    $id = intval($id);
    $body = json_decode($app->request()->getBody());
    $db->query('UPDATE mcr_comment SET smell=?, quality=?, remarks=?, smell_task=? WHERE id = ?')
        ->execute([$body->smell, $body->quality, $body->remarks, $body->smell_task, $id]);
});

$app->group('/results', function () use ($app, $db) {
    $fetchData = function ($query, $return = false) use ($app, $db) {
        $body = json_decode($app->request()->getBody());
        $conditions = $body->conditions;
        $query = str_replace('%conditions%', $conditions, $query);
        $result = $db->query($query)->execute();
        if ($body->debug) {
            echo $query;
        }
        $data = iterator_to_array($result);
        if ($return) {
            return $data;
        }
        echo json_encode($data);
    };

    $app->post('/', function () use ($app, $fetchData) {
        $body = json_decode($app->request()->getBody());
        $fetchData("SELECT $body->columns FROM mcr_analysis WHERE %conditions%");
    });

    $app->post('/smells-per-review', function () use ($fetchData) {
        $fetchData("SELECT is_mobile, AVG(qty) avg FROM (SELECT id, is_mobile, COUNT(*) qty FROM (SELECT DISTINCT smell_task, smell, id, is_mobile FROM mcr_analysis WHERE smell IS NOT NULL AND %conditions%) as t GROUP BY id) AS t2 GROUP BY is_mobile");
    });

    $app->post('/time-to-first-comment/:type', function ($type) use ($fetchData) {
        $fetchData("SELECT is_mobile, {$type}(mintime) count FROM (SELECT id, is_mobile, MIN(TIME_TO_SEC(TIMEDIFF(created,start))) mintime FROM mcr_analysis WHERE %conditions% GROUP BY id) AS t GROUP BY is_mobile");
    });

    $app->post('/mobile-orientation', function () use ($fetchData) {
        $fetchData("SELECT is_horizontal, COUNT(*) count FROM mcr_review WHERE is_mobile = 1 AND %conditions% GROUP BY is_horizontal ORDER BY is_horizontal;");
    });

    $app->post('/first-smell/:pc/:mobile', function ($pc, $mobile) use ($fetchData) {
        $pcMobile = [];
        if ($pc) {
            $pcMobile[] = 0;
        }
        if ($mobile) {
            $pcMobile[] = 1;
        }
        $pcMobile = implode(',', $pcMobile);
        $fetchData("SELECT smell_no, COUNT(*) count FROM (SELECT id, CONCAT_WS('.', smell_task, IF(smell < 10, CONCAT('0', smell), smell)) smell_no, smell, smell_task, MIN(TIME_TO_SEC(TIMEDIFF(created, start))) FROM mcr_analysis WHERE smell IS NOT NULL AND smell > 0 AND is_mobile IN($pcMobile) AND %conditions% GROUP BY id) AS t GROUP BY smell_no ORDER BY smell_task, smell;");
    });

    $app->post('/apriori/:freqSupport/:freqMax/:rulesSupport/:rulesConf', function ($freqSupport, $freqMax, $rulesSupport, $rulesConf) use ($fetchData) {
        $lock = new \TH\Lock\FileLock(__DIR__ . '/apriori/apriori.lock');
        $acquired = false;
        while (!$acquired) {
            try {
                $lock->acquire();
                $acquired = true;
            } catch (Exception $e) {
                sleep(1);
            }
        }
        $data = $fetchData("SELECT DISTINCT id, CONCAT_WS('.', smell_task, IF(smell < 10, CONCAT('0', smell), smell)) smell_no FROM mcr_analysis WHERE smell > 0 AND %conditions% ORDER BY id, created", true);
        $dataset = [];
        foreach ($data as $row) {
            $id = $row['id'];
            $dataset[$id][] = $row['smell_no'];
        }
        $dataset = array_values($dataset);
        $dataset = array_map(function ($e) {
            return implode(',', $e);
        }, $dataset);
        $dir = __DIR__ . '/apriori/';
        file_put_contents($dir . 'in.txt', implode(PHP_EOL, $dataset));
        $program = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'apriori.exe' : 'apriori';
        $freqSupport = intval($freqSupport) ? intval($freqSupport) : 40;
        $freqMethod = $freqMax ? 'm' : 's';
        $rulesSupport = intval($rulesSupport) ? intval($rulesSupport) : 25;
        $rulesConf = intval($rulesConf) ? intval($rulesConf) : 90;
        exec($dir . $program . ' ' . $dir . 'in.txt ' . $dir . 'itemsets.txt -v"/%S" -k", " -I"-" -s' . $freqSupport . ' -t' . $freqMethod);
        exec($dir . $program . ' ' . $dir . 'in.txt ' . $dir . 'rules.txt -v"/%S,%C" -k", " -I"-" -s' . $rulesSupport . ' -c' . $rulesConf . ' -tr');
        $frequent = explode(PHP_EOL, file_get_contents($dir . 'itemsets.txt'));
        $frequent = array_map(function ($e) {
            $data = explode('/', $e);
            return [
                'support' => $data[1],
                'itemset' => $data[0]
            ];
        }, array_filter($frequent));
        $rules = explode(PHP_EOL, file_get_contents($dir . 'rules.txt'));
        $rules = array_map(function ($e) {
            $data = explode('/', $e);
            $rule = explode('-', $data[0]);
            $params = explode(',', $data[1]);
            return [
                'support' => $params[0],
                'confidence' => $params[1],
                'ruleFrom' => $rule[1],
                'ruleTo' => $rule[0],
            ];
        }, array_filter($rules));
        echo json_encode([
            'frequent' => $frequent,
            'rules' => $rules,
        ]);
    });
});

$app->run();
