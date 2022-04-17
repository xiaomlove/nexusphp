import { createRouter, createWebHashHistory } from 'vue-router'

const router = createRouter({
    history: createWebHashHistory(), // hash模式：createWebHashHistory，history模式：createWebHistory
    base: '/admin/',
    routes: [
        {
            path: '/',
            name: 'dashboard',
            component: () => import('../views/dashboard/index.vue')
        },
        {
            path: '/login',
            name: 'login',
            component: () => import('../views/login.vue')
        },
        {
            path: '/user',
            name: 'user',
            component: () => import('../views/user/index.vue')
        },
        {
            path: '/user-form',
            name: 'user-form',
            component: () => import('../views/user/form.vue')
        },
        {
            path: '/user-detail',
            name: 'user-detail',
            component: () => import('../views/user/detail.vue')
        },
        {
            path: '/exam',
            name: 'exam',
            component: () => import('../views/exam/index.vue')
        },
        {
            path: '/exam-form',
            name: 'exam-form',
            component: () => import('../views/exam/form.vue')
        },
        {
            path: '/exam-user',
            name: 'exam-user',
            component: () => import('../views/exam/user.vue')
        },
        {
            path: '/agent-allow',
            name: 'agent-allow',
            component: () => import('../views/agent-allow/index.vue')
        },
        {
            path: '/agent-allow-form',
            name: 'agent-allow-form',
            component: () => import('../views/agent-allow/form.vue')
        },
        {
            path: '/medal',
            name: 'medal',
            component: () => import('../views/medal/index.vue')
        },
        {
            path: '/medal-form',
            name: 'medal-form',
            component: () => import('../views/medal/form.vue')
        },
        {
            path: '/agent-allow',
            name: 'agent-allow',
            component: () => import('../views/agent-allow/index.vue')
        },
        {
            path: '/agent-allow-form',
            name: 'agent-allow-form',
            component: () => import('../views/agent-allow/form.vue')
        },
        {
            path: '/agent-deny',
            name: 'agent-deny',
            component: () => import('../views/agent-deny/index.vue')
        },
        {
            path: '/agent-deny-form',
            name: 'agent-deny-form',
            component: () => import('../views/agent-deny/form.vue')
        },
        {
            path: '/setting',
            name: 'setting',
            component: () => import('../views/setting/index.vue')
        },
        {
            path: '/tag',
            name: 'tag',
            component: () => import('../views/tag/index.vue')
        },
        {
            path: '/tag-form',
            name: 'tag-form',
            component: () => import('../views/tag/form.vue')
        },
        {
            path: '/hr',
            name: 'hr',
            component: () => import('../views/hr/index.vue')
        },
        {
            path: '/hr-detail',
            name: 'hr-detail',
            component: () => import('../views/hr/detail.vue')
        },
    ]
})

export default router
