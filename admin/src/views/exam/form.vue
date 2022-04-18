<template>
    <div>
        <el-row>
            <el-col :span="12">
                <el-form :model="formData" :rules="rules" ref="formRef" label-width="200px" class="formData">
                    <el-form-item label="Name" prop="name">
                        <el-input v-model="formData.name" placeholder=""></el-input>
                    </el-form-item>
                    <el-form-item label="Index" prop="indexes">
                        <template v-for="(item, index) in formData.indexes" :key="index">
                            <el-row style="width: 100%">
                                <el-col :span="6">
                                    <el-checkbox v-model="item.checked" :label="item.checked">{{item.name}}</el-checkbox>
                                </el-col>
                                <el-col :span="12">
                                    <el-input type="number" v-model="item.require_value"></el-input>
                                </el-col>
                                <el-col :span="6" style="padding: 0 20px; color: #aaa">
                                    <template v-if="item.unit">
                                        Unit: {{item.unit}}
                                    </template>
                                </el-col>
                            </el-row>
                        </template>
                    </el-form-item>

                    <el-form-item label="Status" prop="status">
                        <el-radio-group v-model="formData.status">
                            <el-radio :label="0">Enabled</el-radio>
                            <el-radio :label="1">Disabled</el-radio>
                        </el-radio-group>
                    </el-form-item>

                    <el-form-item label="Discovered" prop="is_discovered">
                        <el-radio-group v-model="formData.is_discovered">
                            <el-radio :label="0">No</el-radio>
                            <el-radio :label="1">Yes</el-radio>
                        </el-radio-group>
                    </el-form-item>

                    <el-form-item label="Priority" prop="priority">
                        <el-input v-model="formData.priority" type="number" placeholder=""></el-input>
                        <div style="color: #aaa">The higher the value, the higher the priority, and when multiple exam match the same user, the one with the highest priority is assigned.</div>
                    </el-form-item>

                    <el-form-item label="Begin" prop="begin">
                        <el-date-picker
                            v-model="formData.begin"
                            type="datetime"
                            format="YYYY-MM-DD HH:mm:ss"
                            placeholder="Select Begin Time">
                        </el-date-picker>
                    </el-form-item>
                    <el-form-item label="End" prop="end">
                        <el-date-picker
                            v-model="formData.end"
                            type="datetime"
                            format="YYYY-MM-DD HH:mm:ss"
                            placeholder="Select End Time">
                        </el-date-picker>
                    </el-form-item>

                    <el-form-item label="Duration" prop="duration">
                        <el-input v-model="formData.duration" type="number" placeholder=""></el-input>
                        <div style="color: #aaa">Unit: days. When assign to user, begin and end are used if they are specified. Otherwise begin time is the time at assignment, and the end time is the time at assignment plus the duration.</div>
                    </el-form-item>

                    <el-form-item label="Target user class" prop="filters.classes">
                        <el-checkbox-group v-model="formData.filters.classes">
                            <el-checkbox v-for="(item, index) in allClasses" :label="index" :key="index">{{item}}</el-checkbox>
                        </el-checkbox-group>
                    </el-form-item>

                    <el-form-item label="Target user donated" prop="filters.donate_status">
                        <el-checkbox-group v-model="formData.filters.donate_status">
                            <el-checkbox label="no">No</el-checkbox>
                            <el-checkbox label="yes">Yes</el-checkbox>
                        </el-checkbox-group>
                    </el-form-item>

                    <el-form-item label="Target user register time">
                        <el-date-picker
                            v-model="formData.filters.register_time_range"
                            type="datetimerange"
                            format="YYYY-MM-DD HH:mm:ss"
                            range-separator="to"
                            start-placeholder="Begin"
                            end-placeholder="End">
                        </el-date-picker>
                    </el-form-item>

                    <el-form-item label="Description" prop="description">
                        <el-input type="textarea" v-model="formData.description" placeholder=""></el-input>
                    </el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="submitAdd()">Submit</el-button>
                    </el-form-item>
                </el-form>
            </el-col>
        </el-row>
    </div>
</template>

<script>
import { reactive, ref, toRefs, onMounted, onBeforeUnmount, getCurrentInstance } from 'vue'
import { ElMessage } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { localGet } from '../../utils'
import api from "../../utils/api";
import dayjs from 'dayjs'

export default {
    name: 'ExamForm',
    setup() {
        const { proxy } = getCurrentInstance()
        console.log('proxy', proxy)
        const formRef = ref(null)
        const route = useRoute()
        const router = useRouter()
        const { id } = route.query
        const state = reactive({
            token: localGet('token') || '',
            id: id,
            allClasses: [],
            formData: {
                name: '',
                description: '',
                begin: '',
                end: '',
                duration: '',
                indexes: [],
                filters: {
                    classes: [],
                    register_time_range: [],
                    donate_status: []
                },
                status: '',
                is_discovered: '',
                priority: ''
            },
            rules: {
                name: [
                    { required: 'true',  }
                ],
                indexes: [
                    { required: 'true', }
                ],
                status: [
                    { required: 'true',}
                ],
                is_discovered: [
                    { required: 'true',}
                ],
            },
        })
        onMounted( async () => {
            await listAllClass()
            await listAllIndex()
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
                    state.formData.priority = res.data.priority
                })
            }
        })
        onBeforeUnmount(() => {

        })
        const submitAdd = () => {
            formRef.value.validate(async (vaild) => {
                if (vaild) {
                    let params = state.formData;
                    console.log(params)
                    if (params.begin) {
                        params.begin = dayjs(params.begin).format('YYYY-MM-DD HH:mm:ss')
                    }
                    if (params.end) {
                        params.end = dayjs(params.end).format('YYYY-MM-DD HH:mm:ss')
                    }
                    if (params.filters.register_time_range && params.filters.register_time_range[0]) {
                        params.filters.register_time_range[0] = dayjs(params.filters.register_time_range[0]).format('YYYY-MM-DD HH:mm:ss')
                    }
                    if (params.filters.register_time_range && params.filters.register_time_range[1]) {
                        params.filters.register_time_range[1] = dayjs(params.filters.register_time_range[1]).format('YYYY-MM-DD HH:mm:ss')
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
