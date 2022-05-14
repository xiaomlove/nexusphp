<template>
    <el-form :model="formData" :rules="rules" ref="formRef" label-width="250px" class="formData" size="mini" :v-loading="loading">
        <el-form-item label="Enabled" prop="backup.enabled">
            <el-radio v-model="formData.backup.enabled" label="yes">Yes</el-radio>
            <el-radio v-model="formData.backup.enabled" label="no">No</el-radio>
            <div class="nexus-help-text">
                Enable backup or not.
            </div>
        </el-form-item>

        <el-form-item label="Frequency" prop="backup.frequency">
            <el-radio v-model="formData.backup.frequency" label="daily">Daily</el-radio>
            <el-radio v-model="formData.backup.frequency" label="hourly">Hourly</el-radio>
            <div class="nexus-help-text">
                Backup Frequency.
            </div>
        </el-form-item>

        <el-form-item label="Hour" prop="backup.hour">
            <el-select v-model="formData.backup.hour" filterable >
                <el-option
                    v-for="item in 24"
                    :key="item"
                    :label="item-1"
                    :value="item-1">
                </el-option>
            </el-select>
            <div class="nexus-help-text">
                Do backup at this hour.
            </div>
        </el-form-item>

        <el-form-item label="Minute" prop="backup.minute">
            <el-select v-model="formData.backup.minute" filterable >
                <el-option
                    v-for="item in 60"
                    :key="item"
                    :label="item-1"
                    :value="item-1">
                </el-option>
            </el-select>
            <div class="nexus-help-text">
                Do backup at this minute, If frequency  = 'hourly', this value will be ignore.
            </div>
        </el-form-item>

        <el-form-item label="Google drive client ID" prop="backup.google_drive_client_id">
            <el-input v-model="formData.backup.google_drive_client_id" label="Google drive client ID"></el-input>
            <div class="nexus-help-text">
                Google drive client ID.
            </div>
        </el-form-item>

        <el-form-item label="Google drive client secret" prop="backup.google_drive_client_secret">
            <el-input v-model="formData.backup.google_drive_client_secret" label="Google drive client secret"></el-input>
            <div class="nexus-help-text">
                Google drive client secret.
            </div>
        </el-form-item>

        <el-form-item label="Google drive refresh token" prop="backup.google_drive_refresh_token">
            <el-input v-model="formData.backup.google_drive_refresh_token" label="Google drive refresh token"></el-input>
            <div class="nexus-help-text">
                Google drive refresh token.
            </div>
        </el-form-item>

        <el-form-item label="Google drive folder ID" prop="backup.google_drive_folder_id">
            <el-input v-model="formData.backup.google_drive_folder_id" label="Google drive folder ID"></el-input>
            <div class="nexus-help-text">
                Google drive folder ID. If not set, will store in root.
            </div>
        </el-form-item>

        <el-form-item label="Via ftp" prop="backup.via_ftp">
            <el-radio v-model="formData.backup.via_ftp" label="yes">Yes</el-radio>
            <el-radio v-model="formData.backup.via_ftp" label="no">No</el-radio>
            <div class="nexus-help-text">
                Via ftp or not. If yes, add configuration to .env, refer to <a href="https://laravel.com/docs/master/filesystem#ftp-driver-configuration">Laravel doc.</a>
            </div>
        </el-form-item>

        <el-form-item label="Via sftp" prop="backup.via_sftp">
            <el-radio v-model="formData.backup.via_sftp" label="yes">Yes</el-radio>
            <el-radio v-model="formData.backup.via_sftp" label="no">No</el-radio>
            <div class="nexus-help-text">
                Via sftp or not. If yes, add configuration to .env, refer to <a href="https://laravel.com/docs/master/filesystem#sftp-driver-configuration">Laravel doc.</a>
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
            loading: false,
            token: localGet('token') || '',
            id: id,
            allClasses: [],
            formData: {
                backup: {
                    enabled: '',
                    frequency: '',
                    hour: '',
                    minute: '',
                    google_drive_client_id: '',
                    google_drive_client_secret: '',
                    google_drive_refresh_token: '',
                    google_drive_folder_id: '',
                }
            },
            rules: {
                'backup.enabled': [{ required: 'true',  }],
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
        const handleBeforeUpload = (file) => {
            const sufix = file.name.split('.')[1] || ''
            if (!['jpg', 'jpeg', 'png'].includes(sufix)) {
                ElMessage.error('请上传 jpg、jpeg、png 格式的图片')
                return false
            }
        }
        const listSetting = async () => {
            //not work....
            state.loading = true
            let res = await api.listSetting({prefix: "backup"})
            console.log("listSetting", res)
            state.formData = res.data
            state.loading = false
        }
        return {
            ...toRefs(state),
            formRef,
            submitAdd,
            handleBeforeUpload,
            listSetting,
        }
    }
}
</script>

<style scoped>

</style>
