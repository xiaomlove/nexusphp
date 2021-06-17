<template>
    <el-form :model="formData" :rules="rules" ref="formRef" label-width="250px" class="formData" size="mini">
        <el-form-item label="Enabled" prop="hr.enabled">
            <el-radio v-model="formData.hr.enabled" label="yes">Yes</el-radio>
            <el-radio v-model="formData.hr.enabled" label="no">No</el-radio>
            <div class="nexus-help-text">
                Enable H&R or not.
            </div>
        </el-form-item>

        <el-form-item label="Seed time minimum" prop="hr.seed_time_minimum">
            <el-input v-model="formData.hr.seed_time_minimum" type="number"></el-input>
            <div class="nexus-help-text">
                Seed time minimum (Unit: Hour).
            </div>
        </el-form-item>

        <el-form-item label="Ignore" prop="hr.ignore_when_ratio_reach">
            <el-input v-model="formData.hr.ignore_when_ratio_reach" type="number"></el-input>
            <div class="nexus-help-text">
                When uploaded / torrent_size reach this value, this H&R will be ignored.
            </div>
        </el-form-item>

        <el-form-item label="Disable user" prop="hr.disable_user_when_counts_reach">
            <el-input v-model="formData.hr.ban_user_when_counts_reach"></el-input>
            <div class="nexus-help-text">
                When total H&R counts reach this value, user account will be disabled.
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
    name: 'SettingFormHR',
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
                hr: {
                    enabled: '',
                    seed_time_minimum: '',
                    ignore_when_ratio_reach: '',
                    ban_user_when_counts_reach: '',
                }
            },
            rules: {
                'hr.enabled': [{ required: 'true',  }],
            },
        })
        onMounted( () => {

        })
        onBeforeUnmount(() => {

        })
        const submitAdd = () => {
            formRef.value.validate(async (vaild) => {
                if (vaild) {
                    let params = state.formData;
                    console.log(params)
                    let res = await api.storeSetting(params)
                    ElMessage.success(res.msg)
                    await listSetting();
                }
            })
        }
        const listSetting = async () => {
            let res = await api.listSetting({prefix: "hr"})
            console.log("listSetting", res)
            state.formData = res.data
        }
        return {
            ...toRefs(state),
            formRef,
            submitAdd,
            listSetting,
        }
    }
}
</script>

<style scoped>

</style>
