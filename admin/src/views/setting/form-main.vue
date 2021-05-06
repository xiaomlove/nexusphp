<template>
    <el-form :model="formData" :rules="rules" ref="formRef" label-width="250px" class="formData">
        <el-form-item label="Site online" prop="main.site_online">
            <el-radio-group v-model="formData.main.site_online">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'yes'. Want to turn off your site while performing updates or other types of maintenance? Please Note: Administrators will still be able to see the site.
            </div>
        </el-form-item>

        <el-form-item label="Enable invite system" prop="main.invitesystem">
            <el-radio-group v-model="formData.main.invitesystem">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'yes'. Allow Registrations via Invite System.
            </div>
        </el-form-item>

        <el-form-item label="Initial uploading amount" prop="main.iniupload">
            <el-input v-model="formData.main.iniupload" placeholder="" type="number"></el-input>
            <div class="nexus-help-text">
                How many uploading credit (in Byte, i.e. 1073741824 = 1GB) should each user be given upon registration? Default '0'.
            </div>
        </el-form-item>

        <el-form-item label="Initial number of invites" prop="main.invite_count">
            <el-input v-model="formData.main.invite_count" placeholder="" type="number"></el-input>
            <div class="nexus-help-text">
                How many invites should each user be given upon registration? Default '0'.
            </div>
        </el-form-item>

        <el-form-item label="Invite timeout" prop="main.invite_timeout">
            <el-input v-model="formData.main.invite_timeout" placeholder="" type="number"></el-input>
            <div class="nexus-help-text">
                In days. Delete invite code after X days who didn't respond the invite request. Default '7'.
            </div>
        </el-form-item>

        <el-form-item label="Enable registration system" prop="main.registration">
            <el-radio-group v-model="formData.main.registration">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Allow open registrations. Default 'yes'.
            </div>
        </el-form-item>

        <el-form-item label="Verification type" prop="main.verification">
            <el-radio-group v-model="formData.main.verification">
                <el-radio label="email">Email</el-radio>
                <el-radio label="admin">Admin</el-radio>
                <el-radio label="automatic">Automatically</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                EMAIL: Sent confirmation email. ADMIN: Manual activate. AUTOMATIC: Activate user after registration.
            </div>
        </el-form-item>

        <el-form-item label="Enable wait system" prop="main.waitsystem">
            <el-radio-group v-model="formData.main.waitsystem">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Enable or disable wait system (see FAQ).
            </div>
        </el-form-item>

        <el-form-item label="Enable max slots system" prop="main.maxdlsystemyes">
            <el-radio-group v-model="formData.main.maxdlsystemyes">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'no'. Enable or disable maximum concurrent downloads (AKA Max Slots) (see FAQ).
            </div>
        </el-form-item>

        <el-form-item label="Show polls" prop="main.showpolls">
            <el-radio-group v-model="formData.main.showpolls">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'yes'. Show POLL system on main page.
            </div>
        </el-form-item>

        <el-form-item label="Show stats" prop="main.showstats">
            <el-radio-group v-model="formData.main.showstats">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'yes'. Show STATS system on main page.
            </div>
        </el-form-item>

        <el-form-item label="Show last x forum posts" prop="main.showlastxforumposts">
            <el-radio-group v-model="formData.main.showlastxforumposts">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'no'. Show Last x Forum Posts on main page.
            </div>
        </el-form-item>

        <el-form-item label="Show Last x Torrents" prop="main.showlastxtorrents">
            <el-radio-group v-model="formData.main.showlastxtorrents">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'no'. Show Last x Torrents on main page.
            </div>
        </el-form-item>

        <el-form-item label="Show server load" prop="main.showtrackerload">
            <el-radio-group v-model="formData.main.showtrackerload">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'yes'. Show Server Load on main page.
            </div>
        </el-form-item>

        <el-form-item label="Show forum stats" prop="main.showforumstats">
            <el-radio-group v-model="formData.main.showforumstats">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'yes'. Show forum stats on forums page.
            </div>
        </el-form-item>

        <el-form-item label="Show hot" prop="main.showhotmovies">
            <el-radio-group v-model="formData.main.showhotmovies">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'yes'. Show hot resources on main page. Hot resources are automatically picked by system or manually by staff members.
            </div>
        </el-form-item>

        <el-form-item label="Show classic" prop="main.showclassicmovies">
            <el-radio-group v-model="formData.main.showclassicmovies">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'no'. Show classic resources on main page. Only designated moderators or above can pick classic resources.
            </div>
        </el-form-item>

        <el-form-item label="Enable IMDb system" prop="main.showimdbinfo">
            <el-radio-group v-model="formData.main.showimdbinfo">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'yes'. System-wide IMDb info setting.
            </div>
        </el-form-item>

        <el-form-item label="Enable PT-Gen system" prop="main.enable_pt_gen_system">
            <el-radio-group v-model="formData.main.enable_pt_gen_system">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'no'. System-wide PT-Gen info setting.
            </div>
        </el-form-item>

        <el-form-item label="PT-Gen api point" prop="main.pt_gen_api_point">
            <el-input v-model="formData.main.pt_gen_api_point" placeholder=""></el-input>
            <div class="nexus-help-text">
                Default '', when required, reference to Documatation to build yourself
            </div>
        </el-form-item>

        <el-form-item label="Enable NFO" prop="main.enablenfo">
            <el-radio-group v-model="formData.main.enablenfo">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'yes'. System-wide NFO setting.
            </div>
        </el-form-item>

        <el-form-item label="Enable technical information" prop="main.enable_technical_info">
            <el-radio-group v-model="formData.main.enable_technical_info">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'No'. Technical Information comes from software MediaInfo Text view
            </div>
        </el-form-item>

        <el-form-item label="Enable school system" prop="main.enableschoolyes">
            <el-radio-group v-model="formData.main.enableschoolyes">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'no'. <span style="color: red; font-weight: bold">DO NOT</span> enable this unless you know what you are doing!
            </div>
        </el-form-item>

        <el-form-item label="Restrict email domain" prop="main.restrictemail">
            <el-radio-group v-model="formData.main.restrictemail">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'no'. Set it to 'yes' to only allow certain email domains to register. See here.
            </div>
        </el-form-item>

        <el-form-item label="Show Shoutbox" prop="main.showshoutbox">
            <el-radio-group v-model="formData.main.showshoutbox">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'yes'. Show shoutbox on main page.
            </div>
        </el-form-item>

        <el-form-item label="Show funbox" prop="main.showfunbox">
            <el-radio-group v-model="formData.main.showfunbox">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'no'. Show funbox on main page.
            </div>
        </el-form-item>

        <el-form-item label="Enable offer section" prop="main.showoffer">
            <el-radio-group v-model="formData.main.showoffer">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Default 'yes'. Enable or disable offer section.
            </div>
        </el-form-item>

        <el-form-item label="Enable Donation" prop="main.donation">
            <el-radio-group v-model="formData.main.donation">
                <el-radio label="yes">Yes</el-radio>
                <el-radio label="no">No</el-radio>
            </el-radio-group>
            <div class="nexus-help-text">
                Show donation and donation top ten.
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
                main: {
                    site_online: '',
                    invitesystem: '',
                    iniupload: '',
                    invite_count: '',
                    invite_timeout: '',
                    verification: '',
                    waitsystem: '',
                    maxdlsystemyes: '',
                    showpolls: '',
                    showstats: '',
                    showtrackerload: '',
                    showforumstats: '',
                    showlastxforumposts: '',
                    showlastxtorrents: '',
                    showhotmovies: '',
                    showclassicmovies: '',
                    showimdbinfo: '',
                    enable_pt_gen_system: '',
                    pt_gen_api_point: '',
                    enablenfo: '',
                    enable_technical_info: '',
                    enableschoolyes: '',
                    restrictemail: '',
                    showshoutbox: '',
                    showfunbox: '',
                    showoffer: '',
                    donation: '',
                }
            },
            rules: {
                'main.site_online': [
                    { required: 'true',  }
                ],
            },
        })
        onMounted( () => {
            listAllClass()
            listAllIndex()
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
