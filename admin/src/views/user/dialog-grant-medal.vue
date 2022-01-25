<template>
    <el-dialog
        title="Grant medal to user"
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
            <el-form-item label="Medal" prop="medal_id">
                <el-select v-model="formData.medal_id" placeholder="Select an medal...">
                    <el-option
                        v-for="item in medals"
                        :key="item.id"
                        :label="item.name"
                        :value="item.id">
                    </el-option>
                </el-select>
            </el-form-item>
            <el-form-item label="Duration" prop="duration">
                <el-input v-model="formData.duration" placeholder="Unit: day, if empty, it's valid forever"></el-input>
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
    name: "DialogGrantMedal",
    props: {
        reload: Function
    },
    setup(props, context) {
        const formRef = ref(null)
        const state = reactive({
            loading: false,
            medals: [],
            visible: false,
            formData: {
                uid: 0,
                medal_id: '',
                duration: '',
            },
            rules: {
                medal_id: [{ required: 'true'}]
            }
        })
        const listMedals = async () => {
            let res = await api.listMedal()
            state.medals = res.data.data
        }
        const open = (uid) => {
            state.formData.uid = uid
            if (state.medals.length == 0) {
                state.loading = true
                listMedals()
                state.loading = false
            }
            state.visible = true

        }
        const handleSubmit = () => {
            formRef.value.validate(async (valid) => {
                if (valid) {
                    let res = await api.storeUserMedal(state.formData)
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
