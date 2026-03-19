<?php
// 必须在所有输出之前开启
session_start();

// 1. 用户隔离：若无 ID 则生成一个唯一标识（如 user_65f8abc123）
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = uniqid('user_');
}
$user_id = $_SESSION['user_id'];

// 2. 建立私有数据目录 (data/sessions/)
$session_base = __DIR__ . '/data/sessions/';
if (!is_dir($session_base)) {
    mkdir($session_base, 0777, true);
}

// 3. 定义该用户的私有文件路径变量
// 费率配置路径 (从 step1 传来的)
$user_config_file = $session_base . $user_id . '_config.json';
// 业绩录入草稿路径 (在 step2/step3 之间流转的)
$user_perf_file   = $session_base . $user_id . '_perf.json';