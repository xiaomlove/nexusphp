<template>
    <el-dialog
        title="Check client is allowed or not"
        v-model="visible"
        center
        :close-on-click-modal="false"
    >
        <el-form
            :model="formData"
            label-width="100px"
            v-loading="loading"
            ref="formRef"
            :rules="rules">
            <el-form-item label="Peer id" prop="peer_id">
                <el-input type="text" v-model="formData.peer_id"></el-input>
            </el-form-item>
            <el-form-item label="Agent" prop="agent">
                <el-input type="text" v-model="formData.agent"></el-input>
            </el-form-item>
        </el-form>
        <template #footer>
                <span class="dialog-footer">
                  <el-button @click="visible = false">Cancel</el-button>
                  <el-button type="primary" @click="handleSubmit">Check</el-button>
                </span>
        </template>
    </el-dialog>
</template>

<script>
import { onMounted, reactive, ref, toRefs } from 'vue'
import { ElMessage } from 'element-plus'
import {useRoute, useRouter} from 'vue-router'
import api from '../../utils/api'

export default {
    name: "DialogCheck",
    props: {
        reload: Function
    },
    setup(props, context) {
        const formRef = ref(null)
        const state = reactive({
            loading: false,
            visible: false,
            result: '',
            formData: {
                peer_id: '',
                agent: '',
            },
            rules: {
                peer_id: [{ required: 'true'}],
                agent: [{ required: 'true'}]
            }
        })
        const open = (uid) => {
            state.formData.uid = uid
            state.visible = true

        }
        const handleSubmit = () => {
            formRef.value.validate(async (valid) => {
                if (valid) {
                    let res = await api.checkAgent(state.formData)
                    ElMessage.success(res.msg)
                }
            })
        }
        return {
            ...toRefs(state),
            handleSubmit,
            formRef,
            open,

        }
    }
}
</script>
