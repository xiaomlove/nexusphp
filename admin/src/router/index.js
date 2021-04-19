import { createRouter, createWebHashHistory } from 'vue-router'

const router = createRouter({
  history: createWebHashHistory(), // hash模式：createWebHashHistory，history模式：createWebHistory
  routes: [
    {
      path: '/',
      redirect: '/introduce'
    },
    {
      path: '/introduce',
      name: 'introduce',
      component: () => import(/* webpackChunkName: "introduce" */ '../views/Introduce.vue')
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import(/* webpackChunkName: "dashboard" */ '../views/Index.vue')
    },
    {
      path: '/login',
      name: 'login',
      component: () => import(/* webpackChunkName: "login" */ '../views/Login.vue')
    },
    {
      path: '/add',
      name: 'add',
      component: () => import(/* webpackChunkName: "add" */ '../views/AddGood.vue')
    },
    {
      path: '/swiper',
      name: 'swiper',
      component: () => import(/* webpackChunkName: "swiper" */ '../views/Swiper.vue')
    },
    {
      path: '/hot',
      name: 'hot',
      component: () => import(/* webpackChunkName: "hot" */ '../views/IndexConfig.vue')
    },
    {
      path: '/new',
      name: 'new',
      component: () => import(/* webpackChunkName: "new" */ '../views/IndexConfig.vue')
    },
    {
      path: '/recommend',
      name: 'recommend',
      component: () => import(/* webpackChunkName: "recommend" */ '../views/IndexConfig.vue')
    },
    {
      path: '/category',
      name: 'category',
      component: () => import(/* webpackChunkName: "category" */ '../views/Category.vue'),
      children: [
        {
          path: '/category/level2',
          name: 'level2',
          component: () => import(/* webpackChunkName: "level2" */ '../views/Category.vue'),
        },
        {
          path: '/category/level3',
          name: 'level3',
          component: () => import(/* webpackChunkName: "level3" */ '../views/Category.vue'),
        }
      ]
    },
    {
      path: '/good',
      name: 'good',
      component: () => import(/* webpackChunkName: "new" */ '../views/Good.vue')
    },
    {
      path: '/guest',
      name: 'guest',
      component: () => import(/* webpackChunkName: "guest" */ '../views/Guest.vue')
    },
    {
      path: '/order',
      name: 'order',
      component: () => import(/* webpackChunkName: "order" */ '../views/Order.vue')
    },
    {
      path: '/order_detail',
      name: 'order_detail',
      component: () => import(/* webpackChunkName: "order_detail" */ '../views/OrderDetail.vue')
    },
    {
      path: '/account',
      name: 'account',
      component: () => import(/* webpackChunkName: "account" */ '../views/Account.vue')
    },
      {
          path: '/agent-allow',
          name: 'agent-allow',
          component: () => import(/* webpackChunkName: "account" */ '../views/agent-allow/index.vue'),
      },
      {
          path: '/agent-allow-form',
          name: 'agent-allow-form',
          component: () => import(/* webpackChunkName: "account" */ '../views/agent-allow/form.vue'),
      },
      {
          path: '/user',
          name: 'user',
          component: () => import(/* webpackChunkName: "account" */ '../views/user/index.vue'),
      },
      {
          path: '/user-form',
          name: 'user-form',
          component: () => import(/* webpackChunkName: "account" */ '../views/user/form.vue'),
      },
      {
          path: '/user-detail',
          name: 'user-detail',
          component: () => import(/* webpackChunkName: "account" */ '../views/user/detail.vue'),
      },
      {
          path: '/exam',
          name: 'exam',
          component: () => import(/* webpackChunkName: "account" */ '../views/exam/index.vue'),
      },
      {
          path: '/exam-form',
          name: 'exam-form',
          component: () => import(/* webpackChunkName: "account" */ '../views/exam/form.vue'),
      },

  ]
})

export default router
