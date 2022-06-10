<template>
    <div class="layout">
        <el-container v-if="state.showMenu" class="container">
            <el-aside class="aside">
                <div class="head">
                    <div>
<!--                        <img src="http://demo.nexusphp.org/favicon.ico" alt="logo">-->
                        <span>NexusPHP</span>
                    </div>
                </div>
                <div class="line" />
                <el-menu
                    :default-openeds="state.defaultOpen"
                    background-color="#222832"
                    text-color="#fff"
                    :router="true"
                    :default-active='state.currentPath'
                    :collapse="false"
                >
                    <el-menu-item index="/"><i class="el-icon-odometer" />Dashboard</el-menu-item>
                    <el-sub-menu index="2">
                        <template #title>
                            <span>User</span>
                        </template>
                        <el-menu-item-group>
                            <el-menu-item index="/user"><i class="el-icon-user" />User list</el-menu-item>
                            <el-menu-item index="/hr"><i class="el-icon-user" />H&R</el-menu-item>
                        </el-menu-item-group>
                    </el-sub-menu>
                    <el-sub-menu index="3">
                        <template #title>
                            <span>Agent</span>
                        </template>
                        <el-menu-item-group>
                            <el-menu-item index="/agent-allow"><i class="el-icon-user" />Allow</el-menu-item>
                        </el-menu-item-group>
                        <el-menu-item-group>
                            <el-menu-item index="/agent-deny"><i class="el-icon-user" />Deny</el-menu-item>
                        </el-menu-item-group>
                    </el-sub-menu>
                    <el-sub-menu index="4">
                        <template #title>
                            <span>System</span>
                        </template>
                        <el-menu-item-group>
                            <el-menu-item index="/exam"><i class="el-icon-menu" />Exam</el-menu-item>
                        </el-menu-item-group>
                        <el-menu-item-group>
                            <el-menu-item index="/exam-user"><i class="el-icon-menu" />Exam user</el-menu-item>
                        </el-menu-item-group>
                        <el-menu-item-group>
                            <el-menu-item index="/medal"><i class="el-icon-menu" />Medal</el-menu-item>
                        </el-menu-item-group>
                        <el-menu-item-group>
                            <el-menu-item index="/tag"><i class="el-icon-menu" />Tag</el-menu-item>
                        </el-menu-item-group>
                        <el-menu-item-group>
                            <el-menu-item index="/setting"><i class="el-icon-menu" />Setting</el-menu-item>
                        </el-menu-item-group>
                    </el-sub-menu>
                </el-menu>
            </el-aside>
            <el-container class="content">
                <Header :router-name="state.routerName"/>
                <div class="main">
                    <router-view @update-version="updateVersion" />
                </div>
                <Footer :version="state.version"/>
            </el-container>
        </el-container>
        <el-container v-else class="container">
            <router-view />
        </el-container>
    </div>
</template>

<script>
import { reactive, onMounted, onUnmounted } from 'vue'
import Header from './components/Header.vue'
import Footer from './components/Footer.vue'
import { useRouter } from 'vue-router'
import { pathMap, localGet } from './utils'
export default {
    name: 'App',
    components: {
        Header,
        Footer
    },
    setup() {
        const noMenu = ['/login']
        const router = useRouter()
        const state = reactive({
            defaultOpen: ['1', '2', '3', '4'],
            showMenu: true,
            currentPath: '/dashboard',
            count: {
                number: 1
            },
            routerName: router.name,
            version: '',
        })
        onMounted(() => {

        })
        onUnmounted(() => {
            unwatch()
        })
        const unwatch = router.beforeEach((to, from, next) => {
            if (to.path == '/login') {
                // 如果路径是 /login 则正常执行
                next()
            } else {
                // 如果不是 /login，判断是否有 token
                if (!localGet('token')) {
                    // 如果没有，则跳至登录页面
                    next({ path: '/login' })
                } else {
                    // 否则继续执行
                    next()
                }
            }
            state.showMenu = !noMenu.includes(to.path)
            state.currentPath = to.path
            document.title = pathMap[to.name]
        })
        const updateVersion = (val) => {
            // console.log('updateVersion', val)
            state.version = val.nexus_version.value
        }
        return {
            state,
            updateVersion
        }
    }
}
</script>

<style scoped>
.layout {
    min-height: 100vh;
    background-color: #ffffff;
}
.container {
    height: 100vh;
}
.aside {
    width: 200px!important;
    background-color: #222832;
    overflow: hidden;
    overflow-y: auto;
    -ms-overflow-style: none;
    overflow: -moz-scrollbars-none;
}
.aside::-webkit-scrollbar {
    display: none;
}
.head {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 50px;
}
.head > div {
    display: flex;
    align-items: center;
}

.head img {
    width: 50px;
    height: 50px;
    margin-right: 10px;
}
.head span {
    font-size: 20px;
    color: #ffffff;
}
.line {
    border-top: 1px solid hsla(0,0%,100%,.05);
    border-bottom: 1px solid rgba(0,0,0,.2);
}
.content {
    display: flex;
    flex-direction: column;
    max-height: 100vh;
    overflow: hidden;
}
.main {
    height: calc(100vh - 100px);
    overflow: auto;
    padding: 10px;
}
</style>
<style>
body {
    padding: 0;
    margin: 0;
    box-sizing: border-box;
}
.el-menu {
    border-right: none!important;
}
.el-sub-menu {
    border-top: 1px solid hsla(0, 0%, 100%, .05);
    border-bottom: 1px solid rgba(0, 0, 0, .2);
}
.el-sub-menu:first-child {
    border-top: none;
}
.el-sub-menu [class^="el-icon-"] {
    vertical-align: -1px!important;
}
a {
    color: #409eff;
    text-decoration: none;
}
.el-pagination {
    justify-content: center;
    margin-top: 20px;
}
.el-popper__arrow {
    display: none;
}
</style>
