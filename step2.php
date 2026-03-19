<?php
require_once 'session_init.php';

// 在 step2.php 顶部 require_once 'session_init.php' 之后添加：
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    if (file_exists($user_perf_file)) unlink($user_perf_file);
    header("Location: step2.php");
    exit;
}

// 1. 读取该用户的私有费率配置 (由 save_step1.php 生成)
$configData = file_exists($user_config_file) ? file_get_contents($user_config_file) : '[]';

// 2. 读取该用户的业绩录入草稿 (回显核心：如果之前保存过，刷新后依然存在)
$perfData = file_exists($user_perf_file) ? file_get_contents($user_perf_file) : 'null';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>Step 2 - 数据录入</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --brand-color: #4da1ff; --bg-gray: #f4f7f6; }
        body { background-color: var(--bg-gray); font-size: 12px; scroll-behavior: smooth; }
        
        /* 侧边导航 */
        .level-nav { position: fixed; left: 20px; top: 150px; display: flex; flex-direction: column; gap: 8px; z-index: 1000; }
        .level-nav a { 
            width: 42px; height: 42px; border-radius: 8px; background: #fff; border: 1px solid #ddd;
            display: flex; align-items: center; justify-content: center; text-decoration: none;
            color: #666; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: 0.2s;
        }
        .level-nav a:hover { background: var(--brand-color); color: #fff; border-color: var(--brand-color); }

        .agent-block { background: #fff; margin: 20px 20px 40px 80px; border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; }
        .agent-header { background: #fff; padding: 15px 20px; border-bottom: 1px solid #eee; font-weight: bold; }
        
        .perf-table thead th { 
            position: sticky; top: -1px; z-index: 10; 
            background: #f8f9fa !important; border-top: none;
            box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);
        }

        .my-cyan { background-color: #e3fcf2 !important; color: #0d6832; }
        .blue-zone { background-color: #eaf4ff !important; color: #0056b3; }
        .input-sm { font-size: 11px; height: 28px; border-radius: 4px; }
        
        .form-control:focus { background-color: #fff9db !important; border-color: #ffda6a; box-shadow: 0 0 0 0.2rem rgba(255, 218, 106, 0.25); }
        
        .btn-add-perf { border: 1px dashed var(--brand-color); color: var(--brand-color); background: #fff; width: 100%; padding: 8px; border-radius: 6px; transition: 0.3s; }
        .btn-add-perf:hover { background: #f0f7ff; }
        .top-bar { max-width: 1400px; margin: 0 auto 10px; display: flex; justify-content: space-between; align-items: center; }
        .btn-back { background: #4da1ff; color: #fff; text-decoration: none; padding: 8px 20px; border-radius: 4px; font-weight: bold; }
        .sticky-bottom-bar { position: fixed; bottom: 0; width: 100%; background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); padding: 15px; box-shadow: 0 -5px 15px rgba(0,0,0,0.1); z-index: 1000; }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="step1_config.php" class="btn-back">⬅ 返回修改费率</a>
</div>

<div class="level-nav d-none d-xl-flex">
    <?php for($i=0; $i<6; $i++): ?>
    <a href="#agt_anchor_<?=$i?>">L<?=$i?></a>
    <?php endfor; ?>
</div>

<div class="container-fluid pb-5">
    <form id="perfForm" action="calculate_final.php" method="POST">
        <div class="d-flex justify-content-between align-items-center p-3 ms-5">
            <h4>Step 2: 业绩录入 <small class="text-muted" style="font-size:11px;"></small></h4>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearDraft()">重置清空</button>
            </div>
        </div>

        <?php 
        $levels = ["总代(L0)", "一级(L1)", "二级(L2)", "三级(L3)", "四级(L4)", "五级(L5)"];
        foreach ($levels as $lIdx => $levelName): 
        ?>
        <div class="agent-block" id="agt_anchor_<?=$lIdx?>">
            <div class="agent-header d-flex align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">L<?=$lIdx?></span>
                <input type="text" name="agt[<?=$lIdx?>][name]" class="form-control form-control-sm" style="width:120px" placeholder="代理账号" required>
                
                <div class="input-group input-group-sm w-auto shadow-sm">
                    <span class="input-group-text my-cyan">游戏%</span>
                    <input type="number" step="0.01" name="agt[<?=$lIdx?>][ryx]" class="form-control input-sm" style="width:55px" value="0">
                </div>
                <div class="input-group input-group-sm w-auto shadow-sm">
                    <span class="input-group-text my-cyan">支付%</span>
                    <input type="number" step="0.01" name="agt[<?=$lIdx?>][rzf]" class="form-control input-sm" style="width:55px" value="0">
                </div>
                <div class="input-group input-group-sm w-auto shadow-sm">
                    <span class="input-group-text my-cyan">代付%</span>
                    <input type="number" step="0.01" name="agt[<?=$lIdx?>][rdf]" class="form-control input-sm" style="width:55px" value="1.0">
                </div>
                <div class="input-group input-group-sm w-auto shadow-sm">
                    <span class="input-group-text my-cyan">优惠%</span>
                    <input type="number" step="0.01" name="agt[<?=$lIdx?>][ryh]" class="form-control input-sm" style="width:55px" value="0">
                </div>
                <div class="input-group input-group-sm w-auto shadow-sm">
                    <span class="input-group-text bg-warning text-dark fw-bold">代理成本%</span>
                    <input type="number" step="0.1" name="agt[<?=$lIdx?>][costRate]" class="form-control input-sm fw-bold" style="width:55px; color:red;" value="10">
                </div>
            </div>

            <div class="p-3">
                <table class="table table-bordered perf-table mb-0">
                    <thead>
                        <tr>
                            <th width="12%">分类</th>
                            <th width="12%">品牌</th>
                            <th width="12%">游戏</th>
                            <th class="blue-zone">玩家输赢(GGR)</th>
                            <th class="blue-zone">投注金额</th>
                            <th class="my-cyan">充值金额</th>
                            <th class="my-cyan">提现金额</th>
                            <th class="my-cyan">优惠金额</th>
                            <th width="40"></th>
                        </tr>
                    </thead>
                    <tbody id="perf_body_<?=$lIdx?>"></tbody>
                </table>
                <button type="button" class="btn-add-perf btn-sm mt-2" onclick="addPerfRow(<?=$lIdx?>)">+ 添加一行</button>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="sticky-bottom-bar text-center">
            <button type="submit" class="btn btn-primary px-5 shadow-lg" style="font-weight:bold; height:48px; border-radius: 24px;">
                <i class="bi bi-calculator-fill me-2"></i>保存并执行结算
            </button>
        </div>
    </form>
</div>

<script>
    const GLOBAL_CONFIG = <?php echo $configData; ?>;
    const SAVED_DRAFT = <?php echo $perfData; ?>;
</script>

<script src="step2_logic.js"></script>

</body>
</html>