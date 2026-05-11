<?php
/**
 * 代理结算对账单 - 全百分比成本核算版 (私有隔离持久化版)
 * 逻辑说明：保持原计算逻辑与 UI 完全一致，仅修改数据源为 Session 私有文件
 */

require_once 'session_init.php';

// --- 【持久化逻辑开始】 ---

// 1. 获取输入数据
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agt'])) {
    $input = $_POST['agt'];
    file_put_contents($user_perf_file, json_encode($input, JSON_UNESCAPED_UNICODE));
} else {
    if (file_exists($user_perf_file)) {
        $input = json_decode(file_get_contents($user_perf_file), true);
    } else {
        echo "<script>alert('未检测到数据，请先录入。'); window.location.href='step2.php';</script>";
        exit;
    }
}

// 2. 加载私有费率配置
$config = file_exists($user_config_file) ? json_decode(file_get_contents($user_config_file), true) : [];

// --- 【持久化逻辑结束】 ---


$agentReports = [];

/**
 * 核心费率获取函数：穿透 分类 -> 品牌 -> 游戏 (代码逻辑未动)
 */
function getRate($lIdx, $p, $config) {
    $idx_fy = $lIdx * 2;
    $idx_zc = $lIdx * 2 + 1;
    $f_fy = 0; $f_zc = 0;
    $info = ['cat' => '未知分类', 'brand' => '-', 'game' => '-'];

    if ($p['cat_idx'] !== "" && isset($config[$p['cat_idx']])) {
        $c = $config[$p['cat_idx']];
        $info['cat'] = $c['category_name'];
        $f_fy = (float)($c['category_rates'][$idx_fy] ?? 0) / 100;
        $f_zc = (float)($c['category_rates'][$idx_zc] ?? 0) / 100;

        if ($p['brand_idx'] !== "" && isset($c['brands'][$p['brand_idx']])) {
            $b = $c['brands'][$p['brand_idx']];
            $info['brand'] = $b['brand_name'] ?: '-';
            $b_rates = $b['brand_rates'] ?? $b['rates'] ?? null;
            if ($b_rates) {
                $f_fy = (float)($b_rates[$idx_fy] ?? $f_fy * 100) / 100;
                $f_zc = (float)($b_rates[$idx_zc] ?? $f_zc * 100) / 100;
            }
            if ($p['game_idx'] !== "" && isset($b['games'][$p['game_idx']])) {
                $g = $b['games'][$p['game_idx']];
                $info['game'] = $g['name'] ?: '-';
                if (isset($g['rates']) && is_array($g['rates'])) {
                    $f_fy = (float)($g['rates'][$idx_fy] ?? $f_fy * 100) / 100;
                    $f_zc = (float)($g['rates'][$idx_zc] ?? $f_zc * 100) / 100;
                }
            }
        }
    }
    return ['fy' => $f_fy, 'zc' => $f_zc, 'info' => $info];
}

// 1. 初始计算各代理直属所得 (代码逻辑未动)
foreach ($input as $lIdx => $agt) {
    $details = [];
    $directPerf = [];
    $r_sx = (float)($agt['ryx'] ?? 0);
    $r_zf = (float)($agt['rzf'] ?? 0);
    $r_df = (float)($agt['rdf'] ?? 0);
    $r_yh = (float)($agt['ryh'] ?? 0);

    if (isset($agt['p']) && is_array($agt['p'])) {
        foreach ($agt['p'] as $p) {
            $rates = getRate($lIdx, $p, $config);
            $ggr = (float)($p['ggr'] ?? 0);
            $bet = (float)($p['bet'] ?? 0);
            $cz  = (float)($p['cz']  ?? 0);
            $tx  = (float)($p['tx']  ?? 0);
            $yh  = (float)($p['yh']  ?? 0);

            $cost_game = abs($ggr) * ($r_sx / 100);
            $cost_pay  = $cz * ($r_zf / 100);
            $cost_df   = $tx * ($r_df / 100);
            $cost_yh   = $yh * ($r_yh / 100);

            $total_cost  = $cost_game + $cost_pay + $cost_df + $cost_yh;
            $item_profit = ((-$ggr * $rates['zc']) + ($bet * $rates['fy'])) - $total_cost;

            $details[] = [
                'name'    => $rates['info']['cat'] . " / " . $rates['info']['brand'] . " / " . $rates['info']['game'],
                'ggr'     => $ggr, 'bet' => $bet, 'cz' => $cz, 'tx' => $tx, 'yh' => $yh,
                'rate_zc' => ($rates['zc'] * 100) . "%",
                'rate_fs' => ($rates['fy'] * 100) . "%",
                'costs'   => [
                    'sx'   => $cost_game, 'r_sx' => $r_sx,
                    'zf'   => $cost_pay,  'r_zf' => $r_zf,
                    'df'   => $cost_df,   'r_df' => $r_df,
                    'yh'   => $cost_yh,   'r_yh' => $r_yh
                ],
                'profit' => $item_profit
            ];

            $key = $p['cat_idx'] . "_" . $p['brand_idx'] . "_" . $p['game_idx'];
            if (!isset($directPerf[$key])) $directPerf[$key] = ['ggr' => 0, 'bet' => 0, 'source' => $p];
            $directPerf[$key]['ggr'] += $ggr;
            $directPerf[$key]['bet'] += $bet;
        }
    }
    $agentReports[$lIdx] = [
        'name'         => $agt['name'] ?: "代理 L" . $lIdx,
        'details'      => $details,
        'directPerf'   => $directPerf,
        'teamTotalPerf'=> [],
        'costRate'     => (float)($agt['costRate'] ?? 0),
        'subTotalProfit' => 0, 'finalSubEarn' => 0,
        'subDiffDetails' => []
    ];
}

// 2. 向上汇总业绩 (代码逻辑未动)
for ($i = count($agentReports) - 1; $i >= 0; $i--) {
    foreach ($agentReports[$i]['directPerf'] as $k => $v) {
        if (!isset($agentReports[$i]['teamTotalPerf'][$k])) $agentReports[$i]['teamTotalPerf'][$k] = ['ggr' => 0, 'bet' => 0, 'source' => $v['source']];
        $agentReports[$i]['teamTotalPerf'][$k]['ggr'] += $v['ggr'];
        $agentReports[$i]['teamTotalPerf'][$k]['bet'] += $v['bet'];
    }
    if ($i > 0) {
        foreach ($agentReports[$i]['teamTotalPerf'] as $k => $v) {
            if (!isset($agentReports[$i - 1]['teamTotalPerf'][$k])) $agentReports[$i - 1]['teamTotalPerf'][$k] = ['ggr' => 0, 'bet' => 0, 'source' => $v['source']];
            $agentReports[$i - 1]['teamTotalPerf'][$k]['ggr'] += $v['ggr'];
            $agentReports[$i - 1]['teamTotalPerf'][$k]['bet'] += $v['bet'];
        }
    }
}

// 3. 计算下级差点 (代码逻辑未动)
for ($i = 0; $i < count($agentReports) - 1; $i++) {
    $parent = &$agentReports[$i];
    $child  = &$agentReports[$i + 1];
    foreach ($child['teamTotalPerf'] as $k => $v) {
        $p_r     = getRate($i,     $v['source'], $config);
        $c_r     = getRate($i + 1, $v['source'], $config);
        $diff_zc = $p_r['zc'] - $c_r['zc'];
        $diff_fs = $p_r['fy'] - $c_r['fy'];

        $gain_zc = (-$v['ggr'] * $diff_zc);
        $gain_fs = ($v['bet']  * $diff_fs);
        $gain    = $gain_zc + $gain_fs;

        if ($gain != 0 || $v['ggr'] != 0) {
            $parent['subDiffDetails'][] = [
                'name'    => $p_r['info']['cat'] . " / " . $p_r['info']['brand'] . " / " . $p_r['info']['game'],
                'ggr'     => $v['ggr'], 'bet' => $v['bet'],
                'gain_zc' => $gain_zc, 'diff_zc' => ($diff_zc * 100) . "%",
                'gain_fs' => $gain_fs, 'diff_fs' => ($diff_fs * 100) . "%",
                'gain'    => $gain
            ];
            $parent['subTotalProfit'] += $gain;
        }
    }
    $parent['finalSubEarn'] = $parent['subTotalProfit'] * (1 - $parent['costRate'] / 100);
}

function money($val, $isGgr = false) {
    $formatted = number_format(abs($val), 2);
    if ($isGgr) {
        if ($val > 0) return "<span style='color:#d73a49; font-weight:bold;'>+" . $formatted . "</span>";
        if ($val < 0) return "<span style='color:#28a745;'>-" . $formatted . "</span>";
        return "0.00";
    }
    return ($val < 0 ? "<span style='color:red;'>-{$formatted}</span>" : $formatted);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>结算报表</title>
    <style>
        body { font-family: "Microsoft YaHei", sans-serif; font-size: 11px; background: #f4f7f6; padding: 20px; }
        .card { background: #fff; border: 1px solid #ccc; max-width: 1400px; margin: 0 auto 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header { background: #24292e; color: #fff; padding: 10px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #dfe2e5; padding: 8px 4px; text-align: right; }
        th { background: #f6f8fa; text-align: center; }
        .rate-tag { font-size: 9px; color: #0366d6; display: block; line-height: 1.2; }
        .calc-note { font-size: 8px; color: #999; display: block; font-style: italic; }
        .cost-red { color: #d73a49; }
        .total-row { background: #fffbdd; font-weight: bold; }
        .sub-title { background: #f0f9eb !important; text-align: left !important; color: #67c23a; font-weight: bold; }
        .final-row { background: #e8f5e9; font-size: 14px; font-weight: bold; }
        .top-bar { max-width: 1400px; margin: 0 auto 10px; display: flex; justify-content: space-between; align-items: center; }
        .btn-back { background: #4da1ff; color: #fff; text-decoration: none; padding: 8px 20px; border-radius: 4px; font-weight: bold; }

        /* ── Toggle 开关 ── */
        .toggle-wrap {
            display: flex; align-items: center; gap: 10px;
            background: #fff; border: 1px solid #ddd;
            padding: 6px 14px; border-radius: 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            user-select: none; cursor: pointer;
        }
        .toggle-wrap .toggle-label { font-size: 12px; color: #555; font-weight: bold; }
        .toggle-switch {
            position: relative; width: 40px; height: 22px;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #ccc; border-radius: 22px;
            transition: 0.3s;
        }
        .toggle-slider:before {
            position: absolute; content: "";
            height: 16px; width: 16px; left: 3px; bottom: 3px;
            background: white; border-radius: 50%;
            transition: 0.3s;
        }
        .toggle-switch input:checked + .toggle-slider { background: #4da1ff; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(18px); }

        /* 当 toggle 开启时，GGR < 0 的行手续费格标记 */
        .waived-cell { color: #aaa !important; text-decoration: line-through; }
        .waived-badge {
            display: none;
            font-size: 8px; color: #fff; background: #52c41a;
            border-radius: 3px; padding: 1px 4px; margin-left: 4px;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="step2.php" class="btn-back">⬅ 返回修改业绩</a>

    <!-- Toggle 按钮 -->
    <label class="toggle-wrap" title="开启后：玩家赢钱(GGR<0)的项目，游戏手续费自动归零">
        <span class="toggle-label">🛡️ 玩家赢钱免手续费</span>
        <div class="toggle-switch">
            <input type="checkbox" id="feeWaiveToggle" onchange="applyFeeWaive()">
            <span class="toggle-slider"></span>
        </div>
    </label>
</div>

<?php foreach ($agentReports as $lIdx => $rpt): ?>
<div class="card" data-lIdx="<?= $lIdx ?>" data-cost-rate="<?= $rpt['costRate'] ?>">
    <div class="header">代理结算单：<?= htmlspecialchars($rpt['name']) ?> (L<?= $lIdx ?>)</div>
    <table>
        <thead>
            <tr>
                <th style="text-align:left;">项目</th>
                <th>玩家输赢(GGR)</th>
                <th>总投注金额</th>
                <th>占成收益</th>
                <th>返水收益</th>
                <th>游戏手续费</th>
                <th>支付手续费</th>
                <th>代付手续费</th>
                <th>优惠成本</th>
                <th>直属所得</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $ownTotal = 0;
            foreach ($rpt['details'] as $dIdx => $d):
                $ownTotal += $d['profit'];
                $zc_val = -$d['ggr'] * (float)str_replace('%', '', $d['rate_zc']) / 100;
                $fs_val = $d['bet']  * (float)str_replace('%', '', $d['rate_fs']) / 100;
            ?>
            <tr class="detail-row"
                data-ggr="<?= $d['ggr'] ?>"
                data-bet="<?= $d['bet'] ?>"
                data-zc="<?= $zc_val ?>"
                data-fs="<?= $fs_val ?>"
                data-cost-sx="<?= $d['costs']['sx'] ?>"
                data-cost-zf="<?= $d['costs']['zf'] ?>"
                data-cost-df="<?= $d['costs']['df'] ?>"
                data-cost-yh="<?= $d['costs']['yh'] ?>"
                data-r-sx="<?= $d['costs']['r_sx'] ?>"
                data-r-zf="<?= $d['costs']['r_zf'] ?>"
                data-r-df="<?= $d['costs']['r_df'] ?>"
                data-r-yh="<?= $d['costs']['r_yh'] ?>"
                data-rate-zc="<?= $d['rate_zc'] ?>"
                data-rate-fs="<?= $d['rate_fs'] ?>"
            >
                <td style="text-align:left; font-weight:bold;"><?= $d['name'] ?></td>
                <td class="cell-ggr"><?= money($d['ggr'], true) ?></td>
                <td><?= money($d['bet']) ?></td>
                <td><?= money($zc_val) ?><span class="rate-tag">占: <?= $d['rate_zc'] ?></span></td>
                <td><?= money($fs_val) ?><span class="rate-tag">返: <?= $d['rate_fs'] ?></span></td>

                <td class="cell-sx cost-red">
                    <span class="sx-val">-<?= number_format($d['costs']['sx'], 2) ?></span>
                    <span class="waived-badge">已免除</span>
                    <span class="calc-note">(<?= number_format(abs($d['ggr']), 0) ?> * <?= $d['costs']['r_sx'] ?>%)</span>
                </td>

                <td class="cost-red">-<?= number_format($d['costs']['zf'], 2) ?>
                    <span class="calc-note">(<?= number_format($d['cz'], 0) ?> * <?= $d['costs']['r_zf'] ?>%)</span>
                </td>
                <td class="cost-red">-<?= number_format($d['costs']['df'], 2) ?>
                    <span class="calc-note">(<?= number_format($d['tx'], 0) ?> * <?= $d['costs']['r_df'] ?>%)</span>
                </td>
                <td class="cost-red">-<?= number_format($d['costs']['yh'], 2) ?>
                    <span class="calc-note">(<?= number_format($d['yh'], 0) ?> * <?= $d['costs']['r_yh'] ?>%)</span>
                </td>

                <td class="cell-profit" style="font-weight:bold; background:#fafafa;"><?= money($d['profit']) ?></td>
            </tr>
            <?php endforeach; ?>

            <tr class="total-row">
                <td colspan="9" style="text-align:right;">代理直属会员收益：</td>
                <td class="cell-own-total"><?= money($ownTotal) ?></td>
            </tr>

            <?php if (!empty($rpt['subDiffDetails'])): ?>
            <tr><td colspan="10" class="sub-title">下级代理收益计算</td></tr>
            <?php foreach ($rpt['subDiffDetails'] as $sub): ?>
            <tr style="font-size:10px; color:#666;">
                <td style="text-align:left;"><?= $sub['name'] ?></td>
                <td><?= money($sub['ggr'], true) ?></td>
                <td><?= money($sub['bet']) ?></td>
                <td><?= money($sub['gain_zc']) ?><span class="rate-tag">占: <?= $sub['diff_zc'] ?></span></td>
                <td><?= money($sub['gain_fs']) ?><span class="rate-tag">返: <?= $sub['diff_fs'] ?></span></td>
                <td colspan="4" style="text-align:left; color:#999;">(GGR + 投注)</td>
                <td style="font-weight:bold; color:#409eff;"><?= money($sub['gain']) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="9" style="text-align:right;">下级代理收益总计：</td>
                <td class="cell-sub-total"><?= money($rpt['subTotalProfit']) ?></td>
            </tr>
            <tr>
                <td colspan="9" style="text-align:right;">代理成本费用扣除 (<?= $rpt['costRate'] ?>%)：</td>
                <td class="cost-red cell-cost-deduct">-<?= number_format($rpt['subTotalProfit'] * ($rpt['costRate'] / 100), 2) ?></td>
            </tr>
            <tr style="background:#f0faff;">
                <td colspan="9" style="text-align:right; font-weight:bold;">下级代理贡献：</td>
                <td style="color:#0366d6;" class="cell-sub-earn"><?= money($rpt['finalSubEarn']) ?></td>
            </tr>
            <?php endif; ?>

            <tr class="final-row">
                <td colspan="9" style="text-align:right;">本期汇总金额：</td>
                <td style="color:#28a745;" class="cell-grand-total"><?= money($ownTotal + ($rpt['finalSubEarn'] ?? 0)) ?></td>
            </tr>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<script>
/**
 * 截断到小数点后2位，不四舍五入
 */
function truncate2(val) {
    return Math.trunc(Math.round(val * 10000) / 100) / 100;
}

/**
 * 格式化显示金额（截断F2）
 */
function formatMoney(val, isGgr = false) {
    const abs = Math.abs(truncate2(val));
    const str = abs.toFixed(2);
    if (isGgr) {
        if (val > 0) return `<span style="color:#d73a49; font-weight:bold;">+${str}</span>`;
        if (val < 0) return `<span style="color:#28a745;">-${str}</span>`;
        return "0.00";
    }
    return val < 0 ? `<span style="color:red;">-${str}</span>` : str;
}

/**
 * 核心：切换逻辑
 * 开启时：GGR < 0 的行，游戏手续费归零，重算直属所得、汇总、贡献、总计
 * 关闭时：还原所有原始值
 */
function applyFeeWaive() {
    const isOn = document.getElementById('feeWaiveToggle').checked;

    document.querySelectorAll('.card').forEach(card => {
        const costRate = parseFloat(card.dataset.costRate) || 0;
        let newOwnTotal = 0;

        // ── 逐行处理直属明细 ──
        card.querySelectorAll('.detail-row').forEach(row => {
            const ggr     = parseFloat(row.dataset.ggr);
            const zc      = parseFloat(row.dataset.zc);
            const fs      = parseFloat(row.dataset.fs);
            const costSx  = parseFloat(row.dataset.costSx);
            const costZf  = parseFloat(row.dataset.costZf);
            const costDf  = parseFloat(row.dataset.costDf);
            const costYh  = parseFloat(row.dataset.costYh);

            // 判断是否触发免除：toggle开启 且 GGR > 0（玩家赢钱，代理亏损）
            const waived = isOn && ggr > 0;

            const effectiveSx = waived ? 0 : costSx;
            const totalCost   = effectiveSx + costZf + costDf + costYh;
            const profit      = truncate2(zc + fs - totalCost);

            // 更新游戏手续费单元格
            const sxCell   = row.querySelector('.cell-sx');
            const sxVal    = sxCell.querySelector('.sx-val');
            const sxBadge  = sxCell.querySelector('.waived-badge');

            if (waived) {
                sxVal.textContent = '0.00';
                sxVal.classList.add('waived-cell');
                sxBadge.style.display = 'inline';
            } else {
                sxVal.textContent = '-' + Math.abs(truncate2(costSx)).toFixed(2);
                sxVal.classList.remove('waived-cell');
                sxBadge.style.display = 'none';
            }

            // 更新直属所得
            row.querySelector('.cell-profit').innerHTML = formatMoney(profit);

            newOwnTotal = truncate2(newOwnTotal + profit);
        });

        // ── 更新直属合计行 ──
        const ownTotalCell = card.querySelector('.cell-own-total');
        if (ownTotalCell) ownTotalCell.innerHTML = formatMoney(newOwnTotal);

        // ── 更新下级差点汇总（下级差点本身不受toggle影响，只影响最终合计）──
        const subTotalCell  = card.querySelector('.cell-sub-total');
        const subEarnCell   = card.querySelector('.cell-sub-earn');
        const costDeductCell = card.querySelector('.cell-cost-deduct');
        const grandTotalCell = card.querySelector('.cell-grand-total');

        // 读取下级差点的原始值（直接从DOM读取，不受toggle影响）
        let subEarn = 0;
        if (subEarnCell) {
            // 从原始 data 属性读（首次执行时存一下）
            if (!card.dataset.origSubEarn) {
                card.dataset.origSubEarn = subEarnCell.innerText.replace(/[^0-9.\-]/g, '') || '0';
            }
            subEarn = parseFloat(card.dataset.origSubEarn) || 0;
        }

        if (grandTotalCell) {
            grandTotalCell.innerHTML = formatMoney(truncate2(newOwnTotal + subEarn));
        }
    });
}
</script>

</body>
</html>
