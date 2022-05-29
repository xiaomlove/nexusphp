<template>
    <div class="page-user-detail" v-loading="loading">
        <el-card>
            <template #header>
                <div class="card-header">
                    <span>Base info</span>
                </div>
            </template>
            <table class="table-base-info" >
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
                    <td colspan="11">
                        <div class="other-actions">
                            <el-button type="primary" size="default" @click="handleGetModComment">Mod comment</el-button>
                            <el-button type="primary" size="default" @click="handleResetPassword">Reset password</el-button>
                            <el-button type="primary" size="default" @click="handleAssignExam">Assign exam</el-button>
                            <el-button type="primary" size="default" @click="handleGrantMedal">Grant medal</el-button>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td>{{baseInfo.email}}</td>
                    <td></td>
                </tr>
                <tr>
                    <td>Enabled</td>
                    <td>{{baseInfo.enabled}}</td>
                    <td>
                        <template v-if="baseInfo.enabled && baseInfo.enabled == 'yes'">
                            <el-button size="small" @click="handleDisableUser">Disable</el-button>
                        </template>
                        <template v-if="baseInfo.enabled && baseInfo.enabled == 'no'">
                            <el-popconfirm
                                title="Confirm Enable ?"
                                @confirm="handleEnableUser"
                            >
                                <template #reference>
                                    <el-button size="small">Enable</el-button>
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
                    <td>Last access</td>
                    <td>{{baseInfo.last_access}}</td>
                </tr>
                <tr>
                    <td>Class</td>
                    <td>{{baseInfo.class_text}}</td>
                </tr>
                <tr>
                    <td>Invite by</td>
                    <td>{{baseInfo.inviter && baseInfo.inviter.username}}</td>
                    <td><el-button size="small" @click="handleViewInviteInfo">View</el-button></td>
                </tr>
                <tr>
                    <td>Two-step authentication</td>
                    <td>{{baseInfo.two_step_secret ? 'Enabled' : 'Disabled'}}</td>
                    <td>
                        <el-popconfirm
                            v-if="baseInfo.two_step_secret"
                            title="Confirm Disable Two-step authentication ?"
                            @confirm="handleRemoveTwoStepAuthentication"
                        >
                            <template #reference>
                                <el-button type="default" size="small">Disable</el-button>
                            </template>
                        </el-popconfirm>
                    </td>
                </tr>
                <tr>
                    <td>Seed points</td>
                    <td>{{baseInfo.seed_points}}</td>
                </tr>
                <tr>
                    <td>Attendance card</td>
                    <td>{{baseInfo.attendance_card}}</td>
                    <td><el-button size="small" @click="handleIncrementDecrement('attendance_card')">Change</el-button></td>
                </tr>
                <tr>
                    <td>Invites</td>
                    <td>{{baseInfo.invites}}</td>
                    <td><el-button size="small" @click="handleIncrementDecrement('invites')">Change</el-button></td>
                </tr>
                <tr>
                    <td>Uploaded</td>
                    <td>{{baseInfo.uploaded_text}}</td>
                    <td><el-button size="small" @click="handleIncrementDecrement('uploaded')">Change</el-button></td>
                </tr>
                <tr>
                    <td>Downloaded</td>
                    <td>{{baseInfo.downloaded_text}}</td>
                    <td><el-button size="small" @click="handleIncrementDecrement('downloaded')">Change</el-button></td>
                </tr>
                <tr>
                    <td>Bonus</td>
                    <td>{{baseInfo.bonus}}</td>
                    <td><el-button size="small" @click="handleIncrementDecrement('bonus')">Change</el-button></td>
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
                                <el-popconfirm
                                    v-if="examInfo.status === 0"
                                    title="Confirm Avoid ?"
                                    @confirm="handleAvoidExam(examInfo.id)"
                                >
                                    <template #reference>
                                        <el-button type="info" size="small">Avoid</el-button>
                                    </template>
                                </el-popconfirm>
                                <el-popconfirm
                                    v-if="examInfo.status === -1"
                                    title="Confirm Recover ?"
                                    @confirm="handleRecoverExam(examInfo.id)"
                                >
                                    <template #reference>
                                        <el-button type="primary" size="small">Recover</el-button>
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

        <el-row v-if="baseInfo.valid_medals && baseInfo.valid_medals.length">
            <el-col :span="12">
                <el-card >
                    <template #header>
                        <div class="card-header">
                            <span>Medal</span>
                        </div>
                    </template>

                    <el-table
                        v-loading="loading"
                        ref="multipleTable"
                        :data="baseInfo.valid_medals"
                        tooltip-effect="dark"
                    >
                        <el-table-column
                            prop="name"
                            label="Name"
                        ></el-table-column>

                        <el-table-column
                            prop="image_large"
                            label="Image"
                        >
                            <template #default="scope">
                                <el-image :src="scope.row.image_large" style="max-height: 200px" />
                            </template>
                        </el-table-column>

                        <el-table-column
                            prop="expire_at"
                            label="Expire at"
                        ></el-table-column>

                        <el-table-column
                            label="Action"
                            width="100"
                        >
                            <template #default="scope">
                                <el-popconfirm
                                    title="Confirm Remove ?"
                                    @confirm="handleRemoveUserMedal(scope.row.user_medal_id)"
                                >
                                    <template #reference>
                                        <a style="cursor: pointer">Remove</a>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
        </el-row>
    </div>
    <DialogAssignExam ref="assignExam" :reload="fetchPageData"/>
    <DialogGrantMedal ref="grantMedal" :reload="fetchPageData"/>
    <DialogViewInviteInfo ref="viewInviteInfo" />
    <DialogDisableUser ref="disableUser" :reload="fetchPageData" />
    <DialogModComment ref="modComment" />
    <DialogResetPassword ref="resetPassword" />
    <DialogIncrementDecrement ref="incrementDecrement" :reload="fetchPageData" :title="dialogTitle" :valuePlaceholder="valuePlaceholder" />
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
import DialogGrantMedal from './dialog-grant-medal.vue'
import DialogIncrementDecrement from './dialog-increment-decrement.vue'

export default {
    name: "UserDetail",
    components: {
        DialogAssignExam, DialogViewInviteInfo, DialogDisableUser, DialogModComment, DialogResetPassword, DialogGrantMedal,
        DialogIncrementDecrement
    },
    setup() {
        const route = useRoute()
        const router = useRouter()
        const { id } = route.query
        const assignExam = ref(null)
        const grantMedal = ref(null)
        const viewInviteInfo = ref(null)
        const disableUser = ref(null)
        const modComment = ref(null)
        const resetPassword = ref(null)
        const incrementDecrement = ref(null)
        const state = reactive({
            loading: false,
            baseInfo: {},
            examInfo: null,
            dialogTitle: '',
            valuePlaceholder: '',
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

        const handleAvoidExam = async (id) => {
            let res = await api.avoidExamUser(id)
            ElMessage.success(res.msg)
            await fetchPageData()
        }

        const handleRecoverExam = async (id) => {
            let res = await api.recoverExamUser(id)
            ElMessage.success(res.msg)
            await fetchPageData()
        }

        const handleAssignExam = async () => {
            assignExam.value.open(id)
        }
        const handleGrantMedal = async () => {
            grantMedal.value.open(id)
        }
        const handleViewInviteInfo = async () => {
            viewInviteInfo.value.open(id)
        }
        const handleDisableUser = async () => {
            disableUser.value.open(id)
        }
        const handleIncrementDecrement = async (field) => {
            state.dialogTitle = 'Change ' + field
            if (['uploaded', 'downloaded'].includes(field)) {
                state.valuePlaceholder = 'Unit: GB'
            } else {
                state.valuePlaceholder = ''
            }
            incrementDecrement.value.open(id, field)
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

        const handleRemoveUserMedal = async (id) => {
            let res = await api.removeUserMedal(id)
            ElMessage.success(res.msg)
            await fetchPageData()
        }
        const handleRemoveTwoStepAuthentication = async () => {
            let res = await api.removeTwoStepAuthentication({uid: id})
            ElMessage.success(res.msg)
            await fetchPageData()
        }

        return {
            ...toRefs(state),
            handleRemoveExam,
            handleAvoidExam,
            handleAssignExam,
            handleGrantMedal,
            handleRecoverExam,
            handleEnableUser,
            handleViewInviteInfo,
            handleDisableUser,
            handleGetModComment,
            handleResetPassword,
            fetchPageData,
            handleRemoveUserMedal,
            handleIncrementDecrement,
            handleRemoveTwoStepAuthentication,
            assignExam,
            grantMedal,
            viewInviteInfo,
            disableUser,
            modComment,
            resetPassword,
            incrementDecrement,
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
            padding-bottom: 4px;
        }
        td {

        }
    }
}
</style>
