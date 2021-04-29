<template>
    <el-dialog
        title="Assign exam to user"
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
            <el-form-item label="Exam" prop="exam_id">
                <el-select v-model="formData.exam_id" placeholder="Select an exam...">
                    <el-option
                        v-for="item in matchExams"
                        :key="item.id"
                        :label="item.name"
                        :value="item.id">
                    </el-option>
                </el-select>
            </el-form-item>
            <el-form-item label="Time range" prop="time_range">
                <el-date-picker
                    v-model="formData.time_range"
                    type="datetimerange"
                    format="YYYY-MM-DD HH:mm:ss"
                    range-separator="to"
                    start-placeholder="Begin"
                    end-placeholder="End">
                </el-date-picker>
                <div class="time-range-help-text">If the time range is not specified, the exam's own configured time range will be used.</div>
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
    name: "DialogAssignExam",
    props: {
        reload: Function
    },
    setup(props, context) {
        const formRef = ref(null)
        const state = reactive({
            loading: false,
            matchExams: [],
            visible: false,
            formData: {
                uid: 0,
                exam_id: '',
                time_range: []
            },
            rules: {
                exam_id: [{ required: 'true'}]
            }
        })
        const listMatchExams = async () => {
            let res = await api.listUserMatchExams({uid: state.formData.uid})
            state.matchExams = res.data
        }
        const open = (uid) => {
            state.formData.uid = uid
            if (state.matchExams.length == 0) {
                state.loading = true
                listMatchExams()
                state.loading = false
            }
            state.visible = true

        }
        const handleSubmit = () => {
            formRef.value.validate(async (valid) => {
                if (valid) {
                    let res = await api.storeExamUser(state.formData)
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
