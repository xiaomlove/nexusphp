<template>
    <div class="add">
        <el-card class="add-container">
            <el-form :model="goodForm" :rules="rules" ref="goodRef" class="goodForm" label-width="180px" style="width: 50%">
                <el-form-item label="名称" prop="name">
                    <el-input type="text" v-model="goodForm.name" placeholder=""></el-input>
                </el-form-item>
                <el-form-item label="考核用户组" prop="classes">
                    <el-checkbox-group v-model="goodForm.classes">
                        <el-checkbox v-for="(value, key) in classes" :label="key" :key="key">{{value}}</el-checkbox>
                    </el-checkbox-group>
                </el-form-item>

                <el-form-item>
                    <el-button type="primary" @click="submitAdd()">{{ id ? '立即修改' : '立即创建' }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>
    </div>
</template>

<script>
import { reactive, ref, toRefs, onMounted, onBeforeUnmount, getCurrentInstance } from 'vue'
import api from '@/utils/api'
import { ElMessage } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { localGet, hasEmoji } from '@/utils'

export default {
    setup() {
        const { proxy } = getCurrentInstance()
        console.log('proxy', proxy)
        const goodRef = ref(null)
        const route = useRoute()
        const router = useRouter()
        const { id } = route.query
        const state = reactive({
            token: localGet('token') || '',
            id: id,
            defaultCate: '',
            classes: [],
            goodForm: {
                name: '',
                description: '',
                begin: '',
                end: '',
                classes: [],
                filters: {
                    classes: [],
                    register_time_begin: '',
                    register_time_end: '',
                },
                requires: {}
            },
            rules: {
                username: [
                    { required: 'true' }
                ],
                email: [
                    { required: 'true'}
                ],
                password: [
                    { required: 'true' }
                ],
                password_confirmation: [
                    { required: 'true' }
                ],
            },
        })
        onMounted(() => {
            listClass()
            if (id) {
                getAgentAllow(id)
            }
        })
        onBeforeUnmount(() => {

        })
        const getAgentAllow = (id) => {
            api.getAllowAgent(id).then(res => {
                state.goodForm = res
                console.log(res, state.goodForm)
            })
        }
        const listClass = () => {
            api.listClass().then(res => {
                state.classes = res
            })
        }
        const submitAdd = () => {
            goodRef.value.validate((vaild) => {
                console.log("valid", vaild)
                if (vaild) {
                    // 默认新增用 post 方法
                    let params = state.goodForm
                    let res
                    console.log('params', params)
                    api.storeUser(params).then(res => {
                        ElMessage.success(id ? '修改成功' : '添加成功')
                        router.push({ path: '/user' })
                    })
                }
            })
        }
        const handleBeforeUpload = (file) => {
            const sufix = file.name.split('.')[1] || ''
            if (!['jpg', 'jpeg', 'png'].includes(sufix)) {
                ElMessage.error('请上传 jpg、jpeg、png 格式的图片')
                return false
            }
        }
        const handleUrlSuccess = (val) => {
            state.goodForm.goodsCoverImg = val.data || ''
        }
        const handleChangeCate = (val) => {
            state.categoryId = val[2] || 0
        }
        return {
            ...toRefs(state),
            goodRef,
            submitAdd,
            handleBeforeUpload,
            handleUrlSuccess,
            handleChangeCate
        }
    }
}
</script>

<style scoped>
.add {
    display: flex;
}
.add-container {
    flex: 1;
    height: 100%;
}
.avatar-uploader {
    width: 100px;
    height: 100px;
    color: #ddd;
    font-size: 30px;
}
.avatar-uploader-icon {
    display: block;
    width: 100%;
    height: 100%;
    border: 1px solid #e9e9e9;
    padding: 32px 17px;
}
</style>
