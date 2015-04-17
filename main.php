<?php

/* ========== */
/* 執行環境設定 */
/* ========== */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '4095M');
set_time_limit(600);
mb_internal_encoding('UTF-8');

/* ========== */
/* 載入相關檔案 */
/* ========== */
require_once('db_config.php');
require_once('Timer.php');
require_once('Gram.php');

/* ========== */
/* 參數設定    */
/* ========== */

/**
 * 要查詢的資料表名稱: ke2014_sample_news_201403, ke2015_sample_news
 */
$tableName = 'ke2015_sample_news';

/**
 * 要過濾的特殊字元清單
 */
$specialCharList = " 　\r\n\t\0\x0B\xc2\xa0\"\'\\\/,,.<>+-*/~!@#$%^&()_，。；：、「」★0123456789０１２３４５６７８９";

/**
 * N-Grams的上下限
 */
$minGram = 2;
$maxGram = 8;

/**
 * 要列出排名前多少的關鍵詞
 */
$topNGram = 50;

/**
 * SQL查詢的清單，定義各個主題的SQL查詢指令
 */
$sqlMap = array(
    '影劇娛樂' => "SELECT * FROM `{$tableName}` WHERE ((`section` LIKE '%影%') OR (`section` LIKE '%劇%') OR (`section` LIKE '%娛%') OR (`section` LIKE '%樂%') OR (`section` LIKE '%名采人間事總覽%'));",
    '運動' => "SELECT * FROM `{$tableName}` WHERE ((`section` LIKE '%棒球%') OR (`section` LIKE '%運動%') OR (`section` LIKE '%體壇%') OR (`section` LIKE '%體育%'));",
    '兩岸' => "SELECT * FROM `{$tableName}` WHERE ((`section` LIKE '%兩岸%'));",
    '財經' => "SELECT * FROM `{$tableName}` WHERE ((`section` LIKE '%財經%') OR (`section` LIKE '%股市%') OR (`section` LIKE '%房市%'));",
    '保健' => "SELECT * FROM `{$tableName}` WHERE ((`section` LIKE '%醫藥%') OR (`section` LIKE '%健康%'));",
    '政治' => "SELECT * FROM `{$tableName}` WHERE ((`section` LIKE '%政治%'));",
    '社會' => "SELECT * FROM `{$tableName}` WHERE ((`section` LIKE '%社會%'));",
);

/**
 * 印出文字
 * @param type $text 要印出的文字
 */
function showText($text = '') {
    echo "{$text}<br>";
    ob_flush();
    flush();
}

/**
 * 檢查關鍵詞是否包含特殊字元
 * @global string $specialCharList 特殊字元清單
 * @param type $word 關鍵詞
 * @return boolean 是否包含特殊字元
 */
function checkWord($word) {
    global $specialCharList;
    $length = mb_strlen($word);
    for ($i = 0; $i < $length; $i++) {
        $char = mb_substr($word, $i, 1);
        if (mb_strpos($specialCharList, $char) !== false) {
            return false;
        }
    }
    return true;
}

/**
 * 根據字串長度做遞減排序
 * @param type $a
 * @param type $b
 * @return type
 */
function sortByStrLen($a, $b) {
    return mb_strlen($b) - mb_strlen($a);
}

/* ========== */
/* 程式主要流程 */
/* ========== */
try {
    $timer = new Timer();

    //資料庫連線
    $dbh = new PDO("mysql:host={$DB_CONFIG['hostname']};dbname={$DB_CONFIG['dbname']};charset=utf8", $DB_CONFIG['username'], $DB_CONFIG['password']);
    //將錯誤模式設定為拋出例外
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    showText('mysql資料庫連線成功');
    showText();

    $topic = (isset($_GET['topic']) ? $_GET['topic'] : null);
    if (!isset($sqlMap[$topic])) {
        $topic = '影劇娛樂';
    }
    $sql = $sqlMap[$topic];
    showText("SQL: {$sql}");
    showText();

    $timer->Start(); //開始計時
    $stmt = $dbh->query($sql);
    $rowCount = $stmt->rowCount();
    $spentTime = $timer->StopAndReset(); //算出耗時
    showText("查詢成功，共有{$rowCount}筆資料，耗時: {$spentTime}");
    showText();

    //一次取出所有資料
    //$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //對每一筆資料切gram
    $timer->Start(); //開始計時
    $gramMap = array();
    for ($index = 0; $index < $rowCount; $index++) {
        //$row = &$result[$index];
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        //取出一些文字欄位
        $id = $row['id'];
        $content = $row['content'];
        showText("{$index}\t{$id}\t{$row['title']}\t{$row['post_time']}");
        //開始切gram
        $length = mb_strlen($content);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($content, $i, 1);
            //檢查是否有符號
            if (!checkWord($char)) {
                continue;
            }
            for ($j = $minGram; $j <= $maxGram; $j++) {
                //使用mb_substr切字，解決中文切字的問題
                $word = mb_substr($content, $i, $j);
                //檢查字數是否足夠
                if (mb_strlen($word) < $j) {
                    break;
                }
                //檢查是否有符號
                if (!checkWord($word)) {
                    continue;
                }
                //產生出gram紀錄
                if (!isset($gramMap[$word])) {
                    $gramMap[$word] = new Gram($word);
                }
                //檢查文件是否有算過，如果沒有算過的話則DF+1
//                if (!isset($gramMap[$word]->documents[$id])) {
//                    //$gramMap[$word]->documents[$id] = 1;
//                    $gramMap[$word]->df += 1;
//                }
                if (!in_array($id, $gramMap[$word]->documents)) {
                    //$gramMap[$word]->documents[$id] = 1;
                    $gramMap[$word]->documents[] = $id;
                    $gramMap[$word]->df += 1;
                }
                $gramMap[$word]->tf += 1;
            }
        }
        $row = null;
//        $gramCount = count($gramMap);
//        showText("目前有{$gramCount}個gram");
    }
    $gramCount = count($gramMap);
    $spentTime = $timer->StopAndReset(); //算出耗時
    showText("總共有{$gramCount}個gram，耗時: {$spentTime}");
    showText();

    //計算IDF、TF-IDF
    $timer->Start(); //開始計時
    $gramWords = array(); //此處開兩個陣列是為了後面的排序
    $gramValues = array();
    foreach ($gramMap as $gram) {
        $gram->idf = $rowCount / $gram->df;
        $gram->tf_idf = (1 + log($gram->tf)) * (log($gram->idf));
        $gramWords[] = $gram->word;
        $gramValues[] = $gram->tf_idf;
    }
    $spentTime = $timer->StopAndReset(); //算出耗時
    showText("計算IDF耗時: {$spentTime}");

    //排序陣列
    $timer->Start(); //開始計時
    //此處用array_multisort進行排序，因為用usort的速度太慢了
    array_multisort($gramValues, SORT_DESC, $gramWords);
    $spentTime = $timer->StopAndReset(); //算出耗時
    showText("排序耗時: {$spentTime}");
    showText();

    //檢查要列出的關鍵詞數量是否超過總數
    if ($topNGram > $gramCount) {
        $topNGram = $gramCount;
    }

    //移除子關鍵詞
    $timer->Start(); //開始計時
    $i = 0;
    while ($i < $topNGram) {
        //找出相同value的關鍵詞
        $value = $gramValues[$i];
        $words = array(); //相同value的所有關鍵詞
        $indexMap = array(); //所有被記錄的關鍵詞在整體$gramWords中的索引
        for ($j = $i; $j < $gramCount; $j++) {
            //檢查value是否相同，如果相同就把文字跟整體索引值紀錄起來
            if ($gramValues[$j] === $value) {
                $words[] = $gramWords[$j];
                $indexMap[$gramWords[$j]] = $j;
            } else {
                break;
            }
        }
        //檢查相同value的關鍵詞數量是否大於1，大於1才需要檢查是否有子關鍵詞
        $count = count($words);
        if ($count > 1) {
            //根據關鍵詞的文字長度排序，由長排到短
            usort($words, 'sortByStrLen');
            //紀錄子關鍵詞的整體索引
            $deleteIndexes = array();
            //用雙層迴圈去檢查是否有子關鍵詞
            for ($j = 0; $j < $count; $j++) {
                for ($k = $count - 1; $k > $j; $k--) {
                    if (mb_strpos($words[$j], $words[$k]) !== false) {
                        //將要刪除的整體索引紀錄起來，留到後面一次刪除
                        $index = $indexMap[$words[$k]];
                        $deleteIndexes[] = $index;
                        //在$words中因為這個子關鍵詞已經被發現到了，所以需要刪除他
                        array_splice($words, $k, 1);
                    }
                }
                $count = count($words);
            }
            //檢查是否要在整體中刪除子關鍵詞
            $deleteCount = count($deleteIndexes);
            if ($deleteCount) {
                //將索引由大到小排序，才能確保在移除時能按照正確的順序
                rsort($deleteIndexes);
                for ($j = 0; $j < $deleteCount; $j++) {
                    //在整體的排序結果中移除子關鍵詞
                    $index = $deleteIndexes[$j];
                    array_splice($gramValues, $index, 1);
                    array_splice($gramWords, $index, 1);
                    $gramCount--;
                }
            }
        }
        //因為此處使用while迴圈，所以要讓$i值增加
        $i += $count;
    }

    $spentTime = $timer->StopAndReset(); //算出耗時
    showText("移除子關鍵詞耗時: {$spentTime}");
    showText();

    //列出排名前N筆結果
    echo "<table border=\"1\">";
    echo "<thead><tr><th>index</th><th>word</th><th>tf</th><th>idf</th><th>tf-idf</th></tr></thead>";
    echo "<tbody>";
    for ($i = 0; ($i < $topNGram) and ( $i < $gramCount); $i++) {
        $word = $gramWords[$i];
        $gram = $gramMap[$word];
        $value = $gram->tf_idf;

        //showText("{$i},{$gram->word},{$gram->tf},{$gram->idf},{$gram->tf_idf}");
        echo "<tr><td>{$i}</td><td>{$gram->word}</td><td>{$gram->tf}</td><td>{$gram->idf}</td><td>{$gram->tf_idf}</td></tr>";
    }
    echo "</tbody>";
    echo "</table>";

    //關閉資料庫連線
    $dbh = null;
} catch (PDOException $e) {
    die($e->getMessage());
}
?>