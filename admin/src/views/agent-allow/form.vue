<template>
    <div class="add">
        <el-card class="add-container">
            <el-form :model="goodForm" :rules="rules" ref="goodRef" class="goodForm" label-width="150px" style="width: 50%">
                <el-form-item label="系列" prop="family">
                    <el-input type="text" v-model="goodForm.family" placeholder=""></el-input>
                </el-form-item>
                <el-form-item label="起始名称" prop="start_name">
                    <el-input type="text" v-model="goodForm.start_name" placeholder=""></el-input>
                </el-form-item>

                <el-form-item label="Agent 起始" prop="agent_start">
                    <el-input type="text" v-model="goodForm.agent_start" placeholder=""></el-input>
                </el-form-item>
                <el-form-item label="Agent 模式串" prop="agent_pattern">
                    <el-input type="text" v-model="goodForm.agent_pattern" placeholder=""></el-input>
                </el-form-item>
                <el-form-item label="Agent 匹配次数" prop="agent_match_num">
                    <el-input type="number" min="0" v-model="goodForm.agent_match_num" placeholder=""></el-input>
                </el-form-item>
                <el-form-item label="Agent 匹配类型" prop="agent_matchtype">
                    <el-radio v-model="goodForm.agent_matchtype" label="dec">十进制</el-radio>
                    <el-radio v-model="goodForm.agent_matchtype" label="hex">十六进制</el-radio>
                </el-form-item>

                <el-form-item label="Peer ID 起始" prop="peer_id_start">
                    <el-input type="text" v-model="goodForm.peer_id_start" placeholder=""></el-input>
                </el-form-item>
                <el-form-item label="Peer ID 模式串" prop="peer_id_pattern">
                    <el-input type="text" v-model="goodForm.peer_id_pattern" placeholder=""></el-input>
                </el-form-item>
                <el-form-item label="Peer ID 匹配次数" prop="peer_id_match_num">
                    <el-input type="number" min="0" v-model="goodForm.peer_id_match_num" placeholder=""></el-input>
                </el-form-item>
                <el-form-item label="Peer ID 匹配类型" prop="peer_id_matchtype">
                    <el-radio v-model="goodForm.peer_id_matchtype" label="dec">十进制</el-radio>
                    <el-radio v-model="goodForm.peer_id_matchtype" label="hex">十六进制</el-radio>
                </el-form-item>

                <el-form-item label="排除部分" prop="exception">
                    <el-radio v-model="goodForm.exception" label="yes">是</el-radio>
                    <el-radio v-model="goodForm.exception" label="no">否</el-radio>
                </el-form-item>
                <el-form-item label="允许 https" prop="allowhttps">
                    <el-radio v-model="goodForm.allowhttps" label="yes">是</el-radio>
                    <el-radio v-model="goodForm.allowhttps" label="no">否</el-radio>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input type="textarea" v-model="goodForm.comment" placeholder=""></el-input>
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
import WangEditor from 'wangeditor'
import axios from '@/utils/axios'
import api from '@/utils/api'
import { ElMessage } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { localGet, uploadImgServer, uploadImgsServer, hasEmoji } from '@/utils'

export default {
    setup() {
        const { proxy } = getCurrentInstance()
        console.log('proxy', proxy)
        const editor = ref(null)
        const goodRef = ref(null)
        const route = useRoute()
        const router = useRouter()
        const { id } = route.query
        const state = reactive({
            uploadImgServer,
            token: localGet('token') || '',
            id: id,
            defaultCate: '',
            goodForm: {
                family: '',
                start_name: '',
                peer_id_pattern: '',
                peer_id_match_num: '',
                peer_id_matchtype: '',
                peer_id_start: '',
                agent_pattern: '',
                agent_match_num: '',
                agent_matchtype: '',
                agent_start: '',
                exception: '',
                allowhttps: '',
                comment: ''
            },
            rules: {
                family: [
                    { required: 'true' }
                ],
                start_name: [
                    { required: 'true'}
                ],
                peer_id_pattern: [
                    { required: 'true' }
                ],
                peer_id_match_num: [
                    { required: 'true' }
                ],
                peer_id_matchtype: [
                    { required: 'true' }
                ],
                peer_id_start: [
                    { required: 'true' }
                ],
                agent_pattern: [
                    { required: 'true' }
                ],
                agent_match_num: [
                    { required: 'true' }
                ],
                agent_matchtype: [
                    { required: 'true' }
                ],
                agent_start: [
                    { required: 'true' }
                ],
                exception: [
                    { required: 'true' }
                ],
                allowhttps: [
                    { required: 'true' }
                ],
                comment: [
                    { required: 'true' }
                ]
            },
        })
        onMounted(() => {
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
        const submitAdd = () => {
            goodRef.value.validate((vaild) => {
                console.log("valid", vaild)
                if (vaild) {
                    // 默认新增用 post 方法
                    let params = state.goodForm
                    let res
                    console.log('params', params)
                    if (id) {
                        res = api.updateAllowAgent(id, params)
                    } else {
                        res = api.storeAllowAgent(params)
                    }
                    ElMessage.success(id ? '修改成功' : '添加成功')
                    router.push({ path: '/agent-allow' })
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
            editor,
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
