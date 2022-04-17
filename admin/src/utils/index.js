export function localGet (key) {
    const value = window.localStorage.getItem(key)
    try {
        return JSON.parse(window.localStorage.getItem(key))
    } catch (error) {
        return value
    }
}

export function localSet (key, value) {
    window.localStorage.setItem(key, JSON.stringify(value))
}

export function localRemove (key) {
    window.localStorage.removeItem(key)
}

// 判断内容是否含有表情字符，现有数据库不支持。
export function hasEmoji (str = '') {
    const reg = /[^\u0020-\u007E\u00A0-\u00BE\u2E80-\uA4CF\uF900-\uFAFF\uFE30-\uFE4F\uFF00-\uFFEF\u0080-\u009F\u2000-\u201f\u2026\u2022\u20ac\r\n]/g;
    return str.match(reg) && str.match(reg).length
}

export const pathMap = {
    login: 'Login',
    introduce: '系统介绍',
    dashboard: 'Dashboard',
    add: '添加商品',
    swiper: '轮播图配置',
    hot: '热销商品配置',
    new: '新品上线配置',
    recommend: '为你推荐配置',
    category: '分类管理',
    level2: '分类二级管理',
    level3: '分类三级管理',
    good: '商品管理',
    guest: '会员管理',
    order: '订单管理',
    order_detail: '订单详情',
    account: '修改账户',
    'agent-allow': 'Agent allow',
    'agent-allow-form': 'Agent allow form',
    'agent-deny': 'Agent deny',
    'agent-deny-form': 'Agent deny form',
    'user': 'User',
    'user-form': 'User form',
    'user-detail': 'User detail',
    'exam': 'Exam',
    'exam-form': 'Exam form',
    'exam-user': 'Exam user',
    'setting': "Setting",
    'medal': 'Medal',
    'medal-form': 'Medal form',
    'tag': 'Tag',
    'tag-form': 'Tag form',
    'hr': 'H&R',
    'hr-detail': 'H&R Detail'
}
