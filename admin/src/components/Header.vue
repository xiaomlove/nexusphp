<template>
    <div class="header">
        <div class="left">
            <i v-if="hasBack" class="el-icon-back" @click="back"></i>
            <span style="font-size: 20px">{{ name }}</span>
        </div>
        <div class="right">
            <el-popover
                placement="bottom"
                :width="320"
                trigger="click"
                popper-class="popper-user-box"
            >
                <template #reference>
                    <div class="author">
                        <i class="icon el-icon-s-custom" />
                        {{ userInfo && userInfo.username || '' }}
                        <i class="el-icon-caret-bottom" />
                    </div>
                </template>
                <div class="nickname">
                    <p>Email：{{ userInfo && userInfo.email || '' }}</p>
                    <p>Class：{{ userInfo && userInfo.class_text || '' }}</p>
                    <el-tag size="small" effect="dark" class="logout" @click="logout">Logout</el-tag>
                </div>
            </el-popover>
        </div>
    </div>
</template>

<script>
import {computed, onMounted, reactive, toRefs, watch} from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {localGet, localSet, localRemove, pathMap} from '../utils'
import api from "../utils/api";


export default {
    name: 'Header',
    props: {
    },
    setup(props, context) {
        const router = useRouter()
        const route = useRoute()
        const userInfoKey = 'userInfo'
        const state = reactive({
            name: 'dashboard',
            userInfo: null,
            hasBack: false
        })
        onMounted(async () => {
            console.log("Head onMounted!")
            console.log(props)
            let userInfo = localGet(userInfoKey);
            if (userInfo) {
                state.userInfo = userInfo;
            }
        })
        const logout = () => {
            api.logout().then(() => {
                localRemove('token')
                localRemove(userInfoKey)
                router.push({ name: 'login' })
            })
        }
        const back = () => {
            router.back()
        }
        router.afterEach((to) => {
            console.log("Head afterEach to", to)
            const { id } = to.query
            state.name = pathMap[to.name]
        })
        return {
            ...toRefs(state),
            logout,
            back
        }
    }
}
</script>

<style scoped>
.header {
    height: 50px;
    border-bottom: 1px solid #e9e9e9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
}
.el-icon-back {
    border: 1px solid #e9e9e9;
    padding: 4px;
    border-radius: 50px;
    margin-right: 10px;
}
.right > div > .icon{
    font-size: 18px;
    margin-right: 6px;
}
.author {
    margin-left: 10px;
    cursor: pointer;
}
</style>
<style>
.popper-user-box {
    background: url('../assets/account-banner-bg.png') 50% 50% no-repeat!important;
    background-size: cover!important;
    border-radius: 0!important;
}
.popper-user-box .nickname {
    position: relative;
    color: #ffffff;
}
.popper-user-box .nickname .logout {
    position: absolute;
    right: 0;
    top: 0;
    cursor: pointer;
}
</style>
