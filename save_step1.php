<?php
require_once 'session_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawConfig = $_POST['cfg'] ?? [];
    $finalConfig = [];

    foreach ($rawConfig as $cIdx => $cat) {
        if (empty($cat['name'])) continue; // 过滤无标题分类

        // 解析品牌 JSON (由 JS 暂存到 textarea 的)
        $brands = isset($cat['brands']) ? json_decode($cat['brands'], true) : [];
        
        $finalConfig[] = [
            'category_idx'   => (int)$cIdx,
            'category_name'  => $cat['name'],
            'category_rates' => $cat['rates'] ?? array_fill(0, 12, 0),
            'brands'         => $brands // 这里的品牌里面已经嵌套了游戏
        ];
    }

    // 写入该用户的私有文件 (紧凑格式)
    file_put_contents($user_config_file, json_encode($finalConfig, JSON_UNESCAPED_UNICODE));
    
    header("Location: step2.php");
    exit;
}