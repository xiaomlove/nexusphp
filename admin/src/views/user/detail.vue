<template>
    <div class="page-user-detail" v-loading="loading">
        <el-card>
            <template #header>
                <div class="card-header">
                    <span>Base info</span>
                </div>
            </template>
            <table class="table-base-info">
                <tr>
                    <th>Field</th>
                    <th>Value</th>
                    <th>Actions</th>
                    <th>Other</th>
                </tr>
                <tr>
                    <td>Username</td>
                    <td>{{baseInfo.username}}</td>
                    <td></td>
                    <td colspan="7">
                        <div class="other-actions">
                            <el-button type="primary" size="mini" @click="handleGetModComment">Mod comment</el-button>
                            <el-button type="primary" size="mini" @click="handleResetPassword">Reset password</el-button>
                            <el-button type="primary" size="mini" @click="handleAssignExam">Assign exam</el-button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td>{{baseInfo.email}}</td>
                    <td><el-button size="mini">Change</el-button></td>
                </tr>
                <tr>
                    <td>Enabled</td>
                    <td>{{baseInfo.enabled}}</td>
                    <td>
                        <template v-if="baseInfo.enabled && baseInfo.enabled == 'yes'">
                            <el-button size="mini" @click="handleDisableUser">Disable</el-button>
                        </template>
                        <template v-if="baseInfo.enabled && baseInfo.enabled == 'no'">
                            <el-popconfirm
                                title="Confirm Enable ?"
                                @confirm="handleEnableUser"
                            >
                                <template #reference>
                                    <el-button size="mini">Enable</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </td>
                </tr>
                <tr>
                    <td>Added</td>
                    <td>{{baseInfo.added}}</td>
                </tr>
                <tr>
                    <td>Class</td>
                    <td>{{baseInfo.class_text}}</td>
                </tr>
                <tr>
                    <td>Invite by</td>
                    <td>{{baseInfo.inviter && baseInfo.inviter.username}}</td>
                    <td><el-button size="mini" @click="handleViewInviteInfo">View</el-button></td>
                </tr>
                <tr>
                    <td>Uploaded</td>
                    <td>{{baseInfo.uploaded_text}}</td>
                    <td><el-button size="mini">Add</el-button></td>
                </tr>
                <tr>
                    <td>Downloaded</td>
                    <td>{{baseInfo.downloaded_text}}</td>
                    <td><el-button size="mini">Add</el-button></td>
                </tr>
                <tr>
                    <td>Bonus</td>
                    <td>{{baseInfo.bonus}}</td>
                    <td><el-button size="mini">Add</el-button></td>
                </tr>
            </table>
        </el-card>

        <el-card v-if="examInfo">
            <template #header>
                <div class="card-header">
                    <span>Exam on the way</span>
                </div>
            </template>
            <el-row>
                <el-col :span="12">
                    <table class="table-base-info">
                        <tr>
                            <td>Name</td>
                            <td>{{examInfo.exam && examInfo.exam.name}}</td>
                        </tr>
                        <tr>
                            <td>Created at</td>
                            <td>{{examInfo.created_at}}</td>
                        </tr>
                        <tr>
                            <td>Exam time</td>
                            <td>{{examInfo.begin}} ~ {{examInfo.end}}</td>
                        </tr>
                        <tr>
                            <td>Status</td>
                            <td>{{examInfo.status_text}}</td>
                        </tr>
                        <tr>
                            <td>Action</td>
                            <td>
                                <el-popconfirm
                                    title="Confirm Remove ?"
                                    @confirm="handleRemoveExam(examInfo.id)"
                                >
                                    <template #reference>
                                        <el-button type="danger" size="small">Remove</el-button>
                                    </template>
                                </el-popconfirm>
                            </td>
                        </tr>
                    </table>
                </el-col>
                <el-col :span="12">
                    <el-table :data="examInfo.progress_formatted">
                        <el-table-column prop="name" label="Index"></el-table-column>
                        <el-table-column prop="require_value_formatted" label="Require"></el-table-column>
                        <el-table-column prop="current_value_formatted" label="Current"></el-table-column>
                        <el-table-column prop="result" label="Result">
                            <template #default="scope">
                                <el-tag v-if="scope.row.passed" type="success">Pass !</el-tag>
                                <el-tag v-if="!scope.row.passed" type="danger">Not Pass !</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-col>
            </el-row>
        </el-card>
    </div>
    <DialogAssignExam ref="assignExam" :reload="fetchPageData"/>
    <DialogViewInviteInfo ref="viewInviteInfo" />
    <DialogDisableUser ref="disableUser" :reload="fetchPageData" />
    <DialogModComment ref="modComment" />
    <DialogModComment ref="modComment" />
    <DialogResetPassword ref="resetPassword" />
</template>

<script>
import { onMounted, reactive, ref, toRefs } from 'vue'
import { ElMessage } from 'element-plus'
import {useRoute, useRouter} from 'vue-router'
import api from '../../utils/api'
import DialogAssignExam from './dialog-assign-exam.vue'
import DialogViewInviteInfo from './dialog-invite-info.vue'
import DialogDisableUser from './dialog-disable-user.vue'
import DialogModComment from './dialog-mod-comment.vue'
import DialogResetPassword from './dialog-reset-password.vue'

export default {
    name: "UserDetail",
    components: {
        DialogAssignExam, DialogViewInviteInfo, DialogDisableUser, DialogModComment, DialogResetPassword
    },
    setup() {
        const route = useRoute()
        const router = useRouter()
        const { id } = route.query
        const assignExam = ref(null)
        const viewInviteInfo = ref(null)
        const disableUser = ref(null)
        const modComment = ref(null)
        const resetPassword = ref(null)
        const state = reactive({
            loading: false,
            baseInfo: {},
            examInfo: null,
        })
        onMounted(() => {
            fetchPageData()
        })
        const fetchPageData = async () => {
            state.loading = true;
            let res = await api.getUser(id)
            state.loading = false
            state.baseInfo = res.data.base_info
            state.examInfo = res.data.exam_info
        }
        const handleRemoveExam = async (id) => {
            let res = await api.deleteExamUser(id)
            ElMessage.success(res.msg)
            await fetchPageData()
        }

        const handleAssignExam = async () => {
            assignExam.value.open(id)
        }
        const handleViewInviteInfo = async () => {
            viewInviteInfo.value.open(id)
        }
        const handleDisableUser = async () => {
            disableUser.value.open(id)
        }
        const handleEnableUser = async () => {
            let res = await api.enableUser({uid: id})
            ElMessage.success(res.msg)
            await fetchPageData()
        }
        const handleGetModComment = async () => {
            modComment.value.open(id)
        }
        const handleResetPassword = async () => {
            resetPassword.value.open(id)
        }
        return {
            ...toRefs(state),
            handleRemoveExam,
            handleAssignExam,
            handleEnableUser,
            handleViewInviteInfo,
            handleDisableUser,
            handleGetModComment,
            handleResetPassword,
            fetchPageData,
            assignExam,
            viewInviteInfo,
            disableUser,
            modComment,
            resetPassword,

        }
    }
}
</script>
<style lang="scss" scoped>
.el-card {
    margin-bottom: 20px;
}
.table-base-info {
    width: 100%;
    text-align: left;
    tr {
        th {
            padding-bottom: 10px;
        }
        td {
            padding: 10px 0;
        }
    }
}
</style>
