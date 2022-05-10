<template>
    <el-dialog
        :title="title"
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
            <el-form-item label="Action" prop="action">
                <el-radio-group v-model="formData.action">
                    <el-radio label="Increment" />
                    <el-radio label="Decrement" />
                </el-radio-group>
            </el-form-item>
            <el-form-item label="Value" prop="value">
                <el-input v-model="formData.value" type="number" :placeholder="valuePlaceholder" />
            </el-form-item>
            <el-form-item label="Reason" prop="reason">
                <el-input type="textarea" v-model="formData.reason"></el-input>
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
    name: "DialogIncrementDecrement",
    props: {
        reload: Function,
        title: String,
        valuePlaceholder: String
    },
    setup(props, context) {
        const formRef = ref(null)
        const state = reactive({
            loading: false,
            visible: false,
            formData: {
                uid: 0,
                field: '',
                reason: '',
                value: '',
                action: '',
            },
            rules: {
                value: [{ required: 'true'}],
                action: [{ required: 'true'}],
            }
        })
        const open = (uid, field) => {
            state.formData.uid = uid
            state.formData.field = field
            state.visible = true

        }
        const handleSubmit = () => {
            formRef.value.validate(async (valid) => {
                if (valid) {
                    let res = await api.incrementDecrementUserField(state.formData)
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
