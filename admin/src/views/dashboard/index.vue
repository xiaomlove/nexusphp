<template>
    <el-row>
        <el-col :span="12" class="stat-box">
            <el-card>
                <template #header>{{latestUser.data.page_title}}</template>
                <el-table
                    :data="latestUser.data.data"
                    v-loading="latestUser.loading"
                    size="mini"
                >
                    <el-table-column
                        prop="username"
                        label="Username"
                    ></el-table-column>
                    <el-table-column
                        prop="email"
                        label="Email"
                    ></el-table-column>
                    <el-table-column
                        prop="status"
                        label="Status"
                    ></el-table-column>
                    <el-table-column
                        prop="added"
                        label="Added"
                        width="180"
                    ></el-table-column>
                </el-table>
            </el-card>
        </el-col>
        <el-col :span="12" class="stat-box">
            <el-card>
                <template #header>{{latestTorrent.data.page_title}}</template>
                <el-table
                    :data="latestTorrent.data.data"
                    v-loading="latestTorrent.loading"
                    size="mini"
                >
                    <el-table-column
                        prop="name"
                        label="Name"
                    ></el-table-column>
                    <el-table-column
                        prop="user.username"
                        label="User"
                        width="150"
                    ></el-table-column>
                    <el-table-column
                        prop="size_human"
                        label="Size"
                        width="100"
                    ></el-table-column>
                    <el-table-column
                        prop="added"
                        label="Added"
                        width="180"
                    ></el-table-column>
                </el-table>
            </el-card>
        </el-col>
    </el-row>
    <div v-loading="statData.loading">
        <el-row class="row">
            <el-col :span="12" class="stat-box">
                <el-descriptions :title="statData.user.text" :column="2" size="mini" border>
                    <el-descriptions-item :label="item.text" v-for="item in statData.user.data">{{item.value}}</el-descriptions-item>
                </el-descriptions>
            </el-col>
            <el-col :span="12" class="stat-box">
                <el-descriptions :title="statData.user_class.text" :column="2"  size="mini" border>
                    <el-descriptions-item :label="item.class_text" v-for="item in statData.user_class.data">{{item.counts}}</el-descriptions-item>
                </el-descriptions>
            </el-col>
        </el-row>
        <el-row class="row">
            <el-col :span="12" class="stat-box">
                <el-descriptions :title="statData.torrent.text" :column="2" size="mini" border>
                    <el-descriptions-item :label="item.text" v-for="item in statData.torrent.data">{{item.value}}</el-descriptions-item>
                </el-descriptions>
            </el-col>
            <el-col :span="12" class="stat-box">
                <el-descriptions :title="statData.system_info.text" :column="2" size="mini" border>
                    <el-descriptions-item :label="item.text" v-for="item in statData.system_info.data">{{item.value}}</el-descriptions-item>
                </el-descriptions>
            </el-col>
        </el-row>
    </div>
</template>

<script>
import { onMounted, reactive, ref, toRefs } from 'vue'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'
import api from '../../utils/api'

export default {
    name: "Dashboard",
    emits: ['updateVersion'],
    setup(props, context) {
        const router = useRouter()
        const state = reactive({
            statData: {
                loading: true,
                user: {},
                torrent: {},
                user_class: {},
                system_info: {}
            },
            latestUser: {
                loading: true,
                data: []
            },
            latestTorrent: {
                loading: true,
                data: []
            }
        })
        onMounted(() => {
            api.listStatData().then(res => {
                state.statData = res.data
                state.statData.loading = false
                context.emit('updateVersion', res.data.system_info.data)
            })
            api.listLatestUser().then(res => {
                state.latestUser.data = res.data
                state.latestUser.loading = false
            })
            api.listLatestTorrent().then(res => {
                state.latestTorrent.data = res.data
                state.latestTorrent.loading = false
            })
        })

        return {
            ...toRefs(state)
        }
    }

}
</script>

<style  lang="scss" scoped>
.stat-box {
    padding: 15px;
}
</style>
