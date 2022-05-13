<template>
    <el-dialog
        title="Invite info"
        v-model="visible"
        center
        width="65%"
        :close-on-click-modal="false"
    >
        <el-table :data="inviteInfo" v-loading="loading">
            <el-table-column prop="id" label="ID" width="80"></el-table-column>
            <el-table-column prop="inviter_user.username" label="Inviter" width="150"></el-table-column>
            <el-table-column prop="invitee" label="Receive email"></el-table-column>
            <el-table-column prop="hash" label="Hash" width="300"></el-table-column>
            <el-table-column prop="valid_text" label="Hash valid" width="100"></el-table-column>
            <el-table-column prop="invitee_register_email" label="Register email"></el-table-column>
            <el-table-column prop="time_invited" label="Time invited" width="180"></el-table-column>
        </el-table>

    </el-dialog>
</template>

<script>
import { onMounted, reactive, ref, toRefs } from 'vue'
import { ElMessage } from 'element-plus'
import {useRoute, useRouter} from 'vue-router'
import api from '../../utils/api'

export default {
    name: "DialogInviteInfo",
    props: {
        reload: Function
    },
    setup(props, context) {
        const formRef = ref(null)
        const state = reactive({
            loading: false,
            visible: false,
            uid: 0,
            inviteInfo: [],
        })
        const getInviteInfo = async () => {
            let res = await api.getInviteInfo({uid: state.uid})
            state.inviteInfo.push(res.data)
        }
        const open = (uid) => {
            state.uid = uid
            if (state.inviteInfo.length == 0) {
                state.loading = true
                getInviteInfo()
                state.loading = false
            }
            state.visible = true

        }
        return {
            ...toRefs(state),
            formRef,
            open,

        }
    }
}
</script>
