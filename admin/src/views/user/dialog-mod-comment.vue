<template>
    <el-dialog
        title="Mod comment"
        v-model="visible"
        center
        width="40%"
        :close-on-click-modal="false"
    >
        <el-card v-loading="loading">
            <div v-html="modComment" class="pre-line"></div>
        </el-card>
    </el-dialog>
</template>

<script>
import { onMounted, reactive, ref, toRefs } from 'vue'
import { ElMessage } from 'element-plus'
import {useRoute, useRouter} from 'vue-router'
import api from '../../utils/api'

export default {
    name: "DialogModComment",
    props: {
        reload: Function
    },
    setup(props, context) {
        const formRef = ref(null)
        const state = reactive({
            loading: false,
            visible: false,
            uid: 0,
            modComment: ''
        })
        const getUserModComment = async () => {
            let res = await api.getUserModComment({uid: state.uid})
            state.modComment = res.data
        }
        const open = (uid) => {
            state.uid = uid
            state.loading = true
            getUserModComment()
            state.loading = false
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
