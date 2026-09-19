/**
 * 侧边栏分组折叠：默认收起，点击后才展开。
 * state[label] === false 表示已展开；未设置或 true 均为收起。
 */
export function isGroupCollapsed(state, label) {
    return state[label] !== false;
}

export function toggleGroupCollapsed(state, label) {
    state[label] = isGroupCollapsed(state, label) ? false : true;
    return state[label];
}

/**
 * 根据当前路径找出所属分组（优先最长 path 匹配，避免 /license 误伤 /licenses）。
 * @param {Array<{label:string, items:Array<{path:string}>}>} groups
 * @param {string} path
 * @returns {string|null}
 */
export function findGroupLabelForPath(groups, path) {
    const current = String(path || '');
    let bestLabel = null;
    let bestLen = -1;

    for (const group of groups || []) {
        for (const item of group.items || []) {
            const itemPath = String(item?.path || '');
            if (!itemPath) continue;
            const matched = current === itemPath || current.startsWith(itemPath + '/');
            if (matched && itemPath.length > bestLen) {
                bestLen = itemPath.length;
                bestLabel = group.label;
            }
        }
    }

    return bestLabel;
}

/** 展开包含当前路由的分组；其它分组保持原状（默认仍收起） */
export function expandGroupForPath(state, groups, path) {
    const label = findGroupLabelForPath(groups, path);
    if (label) {
        state[label] = false;
    }
    return label;
}
