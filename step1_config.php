<?php
/**
 * step1_config.php - 费率配置中心 (稳定回显版)
 */
require_once 'session_init.php';

// 读取已有的配置数据
$configData = [];
if (file_exists($user_config_file)) {
    $configData = json_decode(file_get_contents($user_config_file), true);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>费率配置中心</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        body { background-color: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; font-size: 12px; }
        .main-card { background: #fff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 20px; padding: 20px; }
        .rate-table { border: 1px solid #dee2e6; width: 100%; border-collapse: collapse; table-layout: fixed; }
        .rate-table thead th { background-color: #f8f9fa; border: 1px solid #dee2e6; text-align: center; padding: 6px; font-weight: 500; }
        .rate-table tbody td { border: 1px solid #efefef; padding: 4px; vertical-align: middle; }
        .name-input-box { border: 1px solid #dcdcdc !important; text-align: center; border-radius: 2px; width: 100%; padding: 4px; transition: all 0.2s; }
        .name-input-box:focus { border-color: #4da1ff !important; outline: none; box-shadow: 0 0 3px rgba(77,161,255,0.3); }
        .error-border { border: 2px solid #ff4d4f !important; background-color: #fff2f0 !important; }
        .rate-group { display: flex; align-items: center; border: 1px solid #dcdcdc; border-radius: 2px; background: #fff; width: 75px; margin: 0 auto; }
        .rate-input { border: none; width: 100%; text-align: center; height: 24px; outline: none; font-size: 12px; }
        .unit { color: #999; padding-right: 3px; font-size: 10px; }
        .menu-btn { cursor: pointer; color: #4da1ff; font-size: 18px; }
        .btn-add-row { border: 1px dashed #4da1ff; color: #4da1ff; background: #fff; width: 100%; padding: 10px; margin-top: 15px; border-radius: 4px; }
        .brands-json, .games-json { display: none; }
    </style>
</head>
<body>

<div class="main-card">
    <h5 class="mb-4"><i class="bi bi-gear-fill me-2"></i>费率配置中心</h5>

    <form id="configForm" action="save_step1.php" method="POST" onsubmit="return handleFormSubmit(event)">
        <div class="table-responsive">
            <table class="rate-table">
                <thead id="mainHeader"></thead>
                <tbody id="categoryBody"></tbody>
            </table>
        </div>
        <button type="button" class="btn-add-row" onclick="addMainCategory()">+ 插入新游戏分类</button>
        <div class="text-center mt-4 border-top pt-4">
            <button type="submit" class="btn btn-primary px-5 shadow-sm" style="background:#4da1ff; border:none; height: 45px; font-weight: bold;">
                确认并保存所有配置
            </button>
        </div>
    </form>
</div>

<div class="modal fade" id="brandModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h6>设置 [ <span id="curCatName" class="text-primary"></span> ] 的品牌特例</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="rate-table mb-3">
                    <thead id="brandHeader"></thead>
                    <tbody id="brandBody"></tbody>
                </table>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow('brand')">+ 插入新品牌行</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-sm px-4" onclick="saveBrandData()">暂存品牌配置</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="gameModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h6>设置品牌 [ <span id="curBrandName" class="text-primary"></span> ] 的具体游戏特例</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="rate-table mb-3">
                    <thead id="gameHeader"></thead>
                    <tbody id="gameBody"></tbody>
                </table>
                <button type="button" class="btn btn-outline-success btn-sm" onclick="addRow('game')">+ 插入具体游戏行</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success btn-sm px-4" onclick="saveGameData()">暂存游戏配置</button>
            </div>
        </div>
    </div>
</div>

<script>
    const SAVED_CONFIG = <?php echo json_encode($configData, JSON_UNESCAPED_UNICODE); ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="step1_logic.js"></script>

</body>
</html>