/**
 * step1_logic.js - 最终兼容版
 * 严格对齐 Step 2 逻辑：品牌用 brand_name, 游戏用 name
 */

let activeCatRow = null;
let activeBrandRow = null;
let catCount = 0;
let bModal = null;
let gModal = null;

document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap !== 'undefined') {
        bModal = new bootstrap.Modal(document.getElementById('brandModal'));
        gModal = new bootstrap.Modal(document.getElementById('gameModal'));
    }

    document.getElementById('mainHeader').innerHTML = generateTheadHTML('游戏分类 (必填)', true, 'main');
    
    // 数据回显：兼容 category_name 和 name 两种存法
    if (typeof SAVED_CONFIG !== 'undefined' && Array.isArray(SAVED_CONFIG) && SAVED_CONFIG.length > 0) {
        document.getElementById('categoryBody').innerHTML = '';
        SAVED_CONFIG.forEach(item => {
            const cName = item.category_name || item.name || "";
            addMainCategory(cName, item);
        });
    } else {
        ['体育', '棋牌', '电子'].forEach(name => addMainCategory(name));
    }
});

function generateTheadHTML(titleName, showSub = true, type = 'main') {
    let subCol = showSub ? `<th rowspan="2" width="70">特例</th>` : `<th rowspan="2" width="70">操作</th>`;
    let deleteAction = type === 'main' ? `onclick="deleteSelectedRows('categoryBody')"` : '';
    return `
        <tr>
            <th rowspan="2" width="40"><i class="bi bi-trash3 text-danger menu-btn" ${deleteAction}></i></th>
            <th rowspan="2" width="60">币种</th>
            <th rowspan="2" width="180">${titleName}</th>
            ${subCol}
            <th colspan="2">总代理</th><th colspan="2">1级代理</th><th colspan="2">2级代理</th>
            <th colspan="2">3级代理</th><th colspan="2">4级代理</th><th colspan="2">5级代理</th>
        </tr>
        <tr style="background:#fcfcfc;">
            ${Array(6).fill('<th>返水%</th><th>占成%</th>').join('')}
        </tr>`;
}

// 1. 添加主分类
function addMainCategory(name = '', fullData = null) {
    const tbody = document.getElementById('categoryBody');
    const tr = document.createElement('tr');
    tr.className = 'cat-row';
    
    const rates = (fullData && (fullData.rates || fullData.category_rates)) ? (fullData.rates || fullData.category_rates) : Array(12).fill(0);
    const brandsJson = (fullData && fullData.brands) ? JSON.stringify(fullData.brands) : '[]';

    tr.innerHTML = `
        <td class="text-center"><input type="checkbox" class="row-cb"></td>
        <td class="text-center text-muted">PHP</td>
        <td><input type="text" name="cfg[${catCount}][name]" class="name-input-box" value="${name}" placeholder="分类名称"></td>
        <td class="text-center">
            <i class="bi bi-list-ul menu-btn" onclick="openBrandModal(this)"></i>
            <textarea class="brands-json d-none" name="cfg[${catCount}][brands]">${brandsJson}</textarea>
        </td>
        ${rates.map(val => `<td><div class="rate-group"><input type="number" step="0.01" name="cfg[${catCount}][rates][]" class="rate-input" value="${val}"><span class="unit">%</span></div></td>`).join('')}
    `;
    tbody.appendChild(tr);
    catCount++;
}

// 2. 品牌弹窗
function openBrandModal(element) {
    activeCatRow = element.closest('tr');
    const catName = activeCatRow.querySelector('.name-input-box').value || "未命名分类";
    document.getElementById('curCatName').innerText = catName;
    document.getElementById('brandHeader').innerHTML = generateTheadHTML('品牌名称 (必填)', true, 'brand');
    document.getElementById('brandBody').innerHTML = '';
    
    const rawVal = activeCatRow.querySelector('.brands-json').value;
    const data = rawVal ? JSON.parse(rawVal) : [];
    
    if(data.length === 0) addRow('brand'); 
    else data.forEach(d => addRow('brand', d));
    bModal.show();
}

// 3. 游戏弹窗
function openGameModal(element) {
    activeBrandRow = element.closest('tr');
    const brandName = activeBrandRow.querySelector('.name-input-box').value || "未命名品牌";
    document.getElementById('curBrandName').innerText = brandName;
    document.getElementById('gameHeader').innerHTML = generateTheadHTML('游戏名称 (必填)', false, 'game');
    document.getElementById('gameBody').innerHTML = '';
    
    const rawVal = activeBrandRow.querySelector('.games-json').value;
    const data = rawVal ? JSON.parse(rawVal) : [];
    
    if(data.length === 0) addRow('game'); 
    else data.forEach(d => addRow('game', d));
    gModal.show();
}

// 4. 通用行添加 (品牌 & 游戏)
function addRow(type, data = null) {
    const tbody = document.getElementById(type + 'Body');
    const tr = document.createElement('tr');
    tr.className = type + '-item-row';
    
    // 关键：品牌读 brand_name, 游戏读 name
    let displayName = (type === 'brand') ? (data?.brand_name || data?.name || "") : (data?.name || "");
    let displayRates = data?.rates || data?.brand_rates || Array(12).fill(0);
    let subJson = (type === 'brand' && data?.games) ? JSON.stringify(data.games) : '[]';

    let subIcon = type === 'brand' ? 
        `<i class="bi bi-list-ul menu-btn" onclick="openGameModal(this)"></i>
         <textarea class="games-json d-none">${subJson}</textarea>` : 
        `<i class="bi bi-x-lg text-danger menu-btn" onclick="this.closest('tr').remove()"></i>`;

    let html = `
        <td class="text-center"><input type="checkbox" class="row-cb"></td>
        <td class="text-center text-muted">PHP</td>
        <td><input type="text" class="name-input-box" value="${displayName}" placeholder="输入名称"></td>
        <td class="text-center">${subIcon}</td>`;
    
    for(let i=0; i<12; i++) {
        let val = (displayRates[i] !== undefined) ? displayRates[i] : 0;
        html += `<td><div class="rate-group"><input type="number" step="0.01" class="rate-input" value="${val}"><span class="unit">%</span></div></td>`;
    }
    tr.innerHTML = html;
    tbody.appendChild(tr);
}

// 5. 保存品牌 (对齐 Step 2: brand_name)
function saveBrandData() {
    const rows = document.querySelectorAll('.brand-item-row');
    const brands = Array.from(rows).map((row, index) => { 
        const nameVal = row.querySelector('.name-input-box').value.trim();
        const rateInputs = row.querySelectorAll('.rate-input');
        const gamesJson = row.querySelector('.games-json').value;
        if(!nameVal) return null;
        return {
            "brand_idx": index,
            "brand_name": nameVal, // 对齐 Step 2 第 101 行
            "rates": Array.from(rateInputs).map(i => i.value || "0"),
            "games": JSON.parse(gamesJson || '[]')
        };
    }).filter(b => b !== null);
    activeCatRow.querySelector('.brands-json').value = JSON.stringify(brands);
    bModal.hide();
}

// 6. 保存游戏 (对齐 Step 2: name)
function saveGameData() {
    const rows = document.querySelectorAll('.game-item-row');
    const games = Array.from(rows).map(row => {
        const nameVal = row.querySelector('.name-input-box').value.trim();
        const rateInputs = row.querySelectorAll('.rate-input');
        if(!nameVal) return null;
        return {
            "name": nameVal, // 对齐 Step 2 第 122 行
            "rates": Array.from(rateInputs).map(i => i.value || "0")
        };
    }).filter(g => g !== null);
    activeBrandRow.querySelector('.games-json').value = JSON.stringify(games);
    gModal.hide();
}

function deleteSelectedRows(targetBodyId) {
    const tbody = document.getElementById(targetBodyId);
    const checked = tbody.querySelectorAll('.row-cb:checked');
    if (checked.length === 0) return alert("请先勾选要删除的行");
    if (confirm(`确定删除选中的 ${checked.length} 行吗？`)) {
        checked.forEach(cb => cb.closest('tr').remove());
    }
}

function handleFormSubmit(e) {
    return true;
}