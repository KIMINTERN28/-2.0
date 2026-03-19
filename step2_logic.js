/**
 * js/step2_logic.js 
 * 职责：处理 Step 2 的动态增行、三级联动、数据回显以及输入交互
 */

let globalRowId = 0;
let isChanged = false;

// 1. 页面初始化
window.onload = () => {
    if (SAVED_DRAFT && Object.keys(SAVED_DRAFT).length > 0) {
        // 如果有保存的草稿，执行回显还原
        hydrateSavedData(SAVED_DRAFT);
    } else {
        // 否则，为 6 个等级各默认添加一行
        for (let i = 0; i < 6; i++) {
            addPerfRow(i);
        }
    }
};

// 2. 数据还原核心函数 (Hydration)
function hydrateSavedData(data) {
    Object.keys(data).forEach(lIdx => {
        const agt = data[lIdx];
        const block = document.getElementById('agt_anchor_' + lIdx);
        if (!block) return;

        // 还原代理基本信息
        block.querySelector(`input[name="agt[${lIdx}][name]"]`).value = agt.name || '';
        block.querySelector(`input[name="agt[${lIdx}][ryx]"]`).value = agt.ryx || 0;
        block.querySelector(`input[name="agt[${lIdx}][rzf]"]`).value = agt.rzf || 0;
        block.querySelector(`input[name="agt[${lIdx}][rdf]"]`).value = agt.rdf || 1.0;
        block.querySelector(`input[name="agt[${lIdx}][ryh]"]`).value = agt.ryh || 0;
        block.querySelector(`input[name="agt[${lIdx}][costRate]"]`).value = agt.costRate || 10;

        // 还原业绩行数据
        if (agt.p && typeof agt.p === 'object') {
            Object.values(agt.p).forEach(rowData => {
                const tr = addPerfRow(lIdx);
                
                // 1. 还原分类并触发品牌更新
                const catSelect = tr.querySelector('select[name*="[cat_idx]"]');
                catSelect.value = rowData.cat_idx;
                updateBrands(catSelect);

                // 2. 还原品牌并触发游戏更新
                const brandSelect = tr.querySelector('select[name*="[brand_idx]"]');
                brandSelect.value = rowData.brand_idx;
                updateGames(brandSelect);

                // 3. 还原游戏
                const gameSelect = tr.querySelector('select[name*="[game_idx]"]');
                gameSelect.value = rowData.game_idx;

                // 4. 还原数值
                tr.querySelector('input[name*="[ggr]"]').value = rowData.ggr || 0;
                tr.querySelector('input[name*="[bet]"]').value = rowData.bet || 0;
                tr.querySelector('input[name*="[cz]"]').value = rowData.cz || 0;
                tr.querySelector('input[name*="[tx]"]').value = rowData.tx || 0;
                tr.querySelector('input[name*="[yh]"]').value = rowData.yh || 0;
            });
        }
    });
}

// 3. 添加业绩行
function addPerfRow(lIdx) {
    const tbody = document.getElementById('perf_body_' + lIdx);
    const rowId = globalRowId++;
    const tr = document.createElement('tr');
    
    let catOptions = '<option value="">请选择分类</option>';
    GLOBAL_CONFIG.forEach((cat, index) => {
        catOptions += `<option value="${index}">${cat.category_name}</option>`;
    });

    tr.innerHTML = `
        <td><select name="agt[${lIdx}][p][${rowId}][cat_idx]" class="form-select form-select-sm" onchange="updateBrands(this)">${catOptions}</select></td>
        <td><select name="agt[${lIdx}][p][${rowId}][brand_idx]" class="form-select form-select-sm" onchange="updateGames(this)"><option value="">全部品牌</option></select></td>
        <td><select name="agt[${lIdx}][p][${rowId}][game_idx]" class="form-select form-select-sm"><option value="">全部游戏</option></select></td>
        <td class="blue-zone"><input type="number" step="0.01" name="agt[${lIdx}][p][${rowId}][ggr]" class="form-control input-sm" value="0"></td>
        <td class="blue-zone"><input type="number" step="0.01" name="agt[${lIdx}][p][${rowId}][bet]" class="form-control input-sm" value="0"></td>
        <td class="my-cyan"><input type="number" step="0.01" name="agt[${lIdx}][p][${rowId}][cz]" class="form-control input-sm" value="0"></td>
        <td class="bg-light"><input type="number" step="0.01" name="agt[${lIdx}][p][${rowId}][tx]" class="form-control input-sm" value="0"></td>
        <td class="my-cyan"><input type="number" step="0.01" name="agt[${lIdx}][p][${rowId}][yh]" class="form-control input-sm" value="0"></td>
        <td class="text-center"><button type="button" class="btn btn-sm text-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
    isChanged = true;
    return tr;
}

// 4. 三级联动：更新品牌
function updateBrands(catSelect) {
    const tr = catSelect.closest('tr');
    const brandSelect = tr.querySelector('select[name*="[brand_idx]"]');
    const gameSelect = tr.querySelector('select[name*="[game_idx]"]');
    const catIdx = catSelect.value;

    brandSelect.innerHTML = '<option value="">全部品牌</option>';
    gameSelect.innerHTML = '<option value="">全部游戏</option>';

    if (catIdx !== "" && GLOBAL_CONFIG[catIdx] && GLOBAL_CONFIG[catIdx].brands) {
        GLOBAL_CONFIG[catIdx].brands.forEach((brand, bIndex) => {
            brandSelect.innerHTML += `<option value="${bIndex}">${brand.brand_name}</option>`;
        });
    }
}

// 5. 三级联动：更新游戏
function updateGames(brandSelect) {
    const tr = brandSelect.closest('tr');
    const catSelect = tr.querySelector('select[name*="[cat_idx]"]');
    const gameSelect = tr.querySelector('select[name*="[game_idx]"]');
    const catIdx = catSelect.value;
    const brandIdx = brandSelect.value;

    gameSelect.innerHTML = '<option value="">全部游戏</option>';

    if (catIdx !== "" && brandIdx !== "" && GLOBAL_CONFIG[catIdx].brands[brandIdx].games) {
        GLOBAL_CONFIG[catIdx].brands[brandIdx].games.forEach((game, gIndex) => {
            gameSelect.innerHTML += `<option value="${gIndex}">${game.name}</option>`;
        });
    }
}

// 6. UI 辅助功能
function removeRow(btn) {
    btn.closest('tr').remove();
    isChanged = true;
}

function clearDraft() {
    if (confirm('确定要清空所有已录入的数据吗？此操作不可撤销。')) {
        // 这里建议配合一个 Ajax 请求给后端清空 JSON，或者直接跳转
        window.location.href = 'step2.php?action=clear'; 
    }
}

// 输入框自动全选
document.addEventListener('focusin', (e) => {
    if (e.target.tagName === 'INPUT' && e.target.type === 'number') {
        e.target.select();
    }
});

// 离开页面提醒
window.onbeforeunload = function() {
    if (isChanged) return "检测到您有未保存的数据，确定要离开吗？";
};

// 提交表单时解除提醒
document.getElementById('perfForm').onsubmit = () => { 
    window.onbeforeunload = null; 
};