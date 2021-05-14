<template>
    <el-dialog
        title="Reset password"
        v-model="visible"
        center
        :close-on-click-modal="false"
    >
        <el-form
            :model="formData"
            label-width="200px"
            v-loading="loading"
            ref="formRef"
            :rules="rules">
            <el-form-item label="Password" prop="password">
                <el-input v-model="formData.password"></el-input>
            </el-form-item>

            <el-form-item label="Password confirmation" prop="password_confirmation">
                <el-input v-model="formData.password_confirmation"></el-input>
            </el-form-item>
        </el-form>
        <template #footer>
                <span class="dialog-footer">
                  <el-button @click="visible = false">Cancel</el-button>
                  <el-button type="primary" @click="handleSubmit">Save</el-button>
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
    name: "DialogResetPassword",
    props: {
        reload: Function
    },
    setup(props, context) {
        const formRef = ref(null)
        const state = reactive({
            loading: false,
            visible: false,
            formData: {
                uid: 0,
                password: '',
                password_confirmation: ''
            },
            rules: {
                password: [{ required: 'true'}],
                password_confirmation: [{ required: 'true'}],
            }
        })
        const open = (uid) => {
            state.formData.uid = uid
            state.visible = true

        }
        const handleSubmit = () => {
            formRef.value.validate(async (valid) => {
                if (valid) {
                    let res = await api.resetPassword(state.formData)
                    state.visible = false
                    ElMessage.success(res.msg)
                    if (props.reload) {
                        props.reload()
                    }
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
