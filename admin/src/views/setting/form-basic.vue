<template>
    <el-form :model="formData" :rules="rules" ref="formRef" label-width="200px" class="formData">
        <el-form-item label="Site Name" prop="basic.SITENAME">
            <el-input v-model="formData.basic.SITENAME" placeholder=""></el-input>
            <div class="nexus-help-text">
                Website name
            </div>
        </el-form-item>

        <el-form-item>
            <el-button type="primary" @click="submitAdd()">Submit</el-button>
        </el-form-item>
    </el-form>
</template>

<script>
import { reactive, ref, toRefs, onMounted, onBeforeUnmount, getCurrentInstance } from 'vue'
import { ElMessage } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { localGet } from '../../utils'
import api from "../../utils/api";

export default {
    name: 'SettingFormBasic',
    setup() {
        const { proxy } = getCurrentInstance()
        const formRef = ref(null)
        const route = useRoute()
        const router = useRouter()
        const { id } = route.query
        const state = reactive({
            token: localGet('token') || '',
            id: id,
            allClasses: [],
            formData: {
                basic: {
                    SITENAME: ''
                }
            },
            rules: {
                'basic.name': [
                    { required: 'true',  }
                ],
            },
        })
        onMounted( () => {
            if (id) {
                api.getExam(id).then(res => {
                    state.formData.name = res.data.name
                    state.formData.description = res.data.description
                    state.formData.begin = res.data.begin
                    state.formData.end = res.data.end
                    state.formData.duration = res.data.duration
                    state.formData.indexes = res.data.indexes
                    state.formData.filters = res.data.filters
                    state.formData.status = res.data.status
                    state.formData.is_discovered = res.data.is_discovered
                })
            } else {
                let res = api.listExamIndex()
                state.formData.indexes = res.data
            }
        })
        onBeforeUnmount(() => {

        })
        const submitAdd = () => {
            formRef.value.validate(async (vaild) => {
                if (vaild) {
                    let params = state.formData;
                    if (params.begin) {
                        params.begin = dayjs(params.begin).format('YYYY-MM-DD HH:mm:ss')
                    }
                    if (params.end) {
                        params.end = dayjs(params.end).format('YYYY-MM-DD HH:mm:ss')
                    }
                    console.log(params)
                    if (id) {
                        await api.updateExam(id, params)
                    } else {
                        await api.storeExam(params)
                    }
                    await router.push({name: 'exam'})
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
            state.formData.goodsCoverImg = val.data || ''
        }
        const handleChangeCate = (val) => {
            state.categoryId = val[2] || 0
        }

        const listAllClass = async () => {
            let res = await api.listClass()
            state.allClasses = res.data
        }
        const listAllIndex = async () => {
            let res = await api.listExamIndex()
            state.formData.indexes = res.data
        }
        const getExam = async (id) => {
            let res = await api.getExam(id)
            console.log(res)
        }
        return {
            ...toRefs(state),
            formRef,
            submitAdd,
            handleBeforeUpload,
            handleUrlSuccess,
            handleChangeCate,
        }
    }
}
</script>

<style scoped>

</style>
