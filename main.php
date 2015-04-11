<?php

/* ========== */
/* 執行環境設定 */
/* ========== */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '2048M');
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
$specialCharList = "\r\n\t 　,,.<>+-*/~!@#$%^&()_，。；：、「」★0123456789０１２３４５６７８９";

/**
 * N-Grams的上下限
 */
$minGram = 2;
$maxGram = 8;

/**
 * 要列出排名前多少的關鍵字
 */
$topNGram = 50;

/**
 * SQL查詢的清單，定義各個主題的SQL查詢指令
 */
$sqlMap = array(
    '影劇娛樂' => "SELECT * FROM `{$tableName}` WHERE ((`section` LIKE '%影%') OR (`section` LIKE '%劇%') OR (`section` LIKE '%娛%') OR (`section` LIKE '%樂%') OR (`section` LIKE '%名采人間事總覽%'));",
    '運動' => "SELECT * FROM `{$tableName}` WHERE ((`section` LIKE '%棒球%') OR (`section` LIKE '%運動%') OR (`section` LIKE '%體壇%') OR (`section` LIKE '%體育%'));",
    '兩岸' => "SELECT * FROM `{$tableName}` WHERE ((`section` LIKE '%兩岸%'));",
    '財經' => "SELECT * FROM `{$tableName}` WHERE ((`section` LIKE '%財經%') OR (`section` LIKE '%產經%') OR (`section` LIKE '%股市%') OR (`section` LIKE '%房市%'));",
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
 * 檢查關鍵字是否包含特殊字元
 * @global string $specialCharList 特殊字元清單
 * @param type $word 關鍵字
 * @return boolean 是否包含特殊字元
 */
function checkWord($word) {
    global $specialCharList;
    $length = mb_strlen($word);
    for ($i = 0; $i < $length; $i++) {
        $char = mb_substr($word, $i, 1);
        if (mb_strpos($specialCharList, $char) > -1) {
            return false;
        }
    }
    return true;
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
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //對每一筆資料切gram
    $timer->Start(); //開始計時
    $gramMap = array();
    for ($index = 0; $index< $rowCount; $index++) {
        $row = $result[$index];
        //取出一些文字欄位
        $id = $row['id'];
        $content = $row['content'];
        showText("{$index}\t{$id}\t{$row['title']}\t{$row['post_time']}");
        //開始切gram
        $length = mb_strlen($content);
        for ($i = 0; $i < $length; $i++) {
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
                if (!isset($gramMap[$word]->documents[$id])) {
                    $gramMap[$word]->documents[$id] = 1;
                    $gramMap[$word]->df += 1;
                }
                $gramMap[$word]->tf += 1;
            }
        }
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

    //列出排名前N筆結果
    for ($i = 0; ($i < $topNGram) and ( $i < $gramCount); $i++) {
        $word = $gramWords[$i];
        $gram = $gramMap[$word];
        $value = $gram->tf_idf;

        showText("{$i},{$gram->word},{$gram->tf},{$gram->df},{$gram->tf_idf}");
    }

    $dbh = null;
} catch (PDOException $e) {
    die($e->getMessage());
}
?>