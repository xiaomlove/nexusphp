<template>
    <el-card class="">
        <template #header>
            <div class="nexus-table-header">
                <div class="left">
                    <el-form :inline="true" :model="query">
                        <el-form-item>
                            <el-popconfirm
                                title="Confirm Remove ?"
                                @confirm="handleDeleteBulk"
                            >
                                <template #reference>
                                    <el-button type="default">Remove</el-button>
                                </template>
                            </el-popconfirm>
                            <el-popconfirm
                                title="Confirm Pardon ?"
                                @confirm="handlePardonBulk"
                            >
                                <template #reference>
                                    <el-button type="default">Pardon</el-button>
                                </template>
                            </el-popconfirm>
                        </el-form-item>

                        <el-form-item label="">
                            <el-select v-model="query.status" filterable placeholder="Status">
                                <el-option
                                    v-for="(item) in extraData.status"
                                    :key="item.status"
                                    :label="item.text"
                                    :value="item.status"
                                >
                                </el-option>
                            </el-select>
                        </el-form-item>
                        <el-form-item label="">
                            <el-input placeholder="UID" v-model="query.uid"></el-input>
                        </el-form-item>
                        <el-form-item label="">
                            <el-input placeholder="Username" v-model="query.username"></el-input>
                        </el-form-item>
                        <el-form-item label="">
                            <el-input placeholder="Torrent ID" v-model="query.torrent_id"></el-input>
                        </el-form-item>

                        <el-form-item>
                            <el-button type="primary" @click="fetchTableData">Query</el-button>
                            <el-button type="primary" @click="handleReset">Reset</el-button>
                        </el-form-item>
                    </el-form>
                </div>
<!--                <div class="right">-->
<!--                    <el-button type="primary" icon="Plus" @click="handleAdd">Add</el-button>-->
<!--                </div>-->
            </div>
        </template>
        <el-table
            v-loading="loading"
            ref="multipleTable"
            :data="tableData"
            tooltip-effect="dark"
            @selection-change="handleSelectionChange">
            <el-table-column
                type="selection"
                width="55">
            </el-table-column>
            <el-table-column
                prop="id"
                label="Id"
                width="100"
            >
            </el-table-column>

            <el-table-column
                prop=""
                label="Username"
                width="120"
                :formatter="formatColumnUsername"
            >
            </el-table-column>

            <el-table-column
                prop=""
                label="Torrent"
            >
                <template #default="scope">
                    <a class="text-one-line" :title="scope.row.torrent.name" :href="scope.row.torrent.details_url" target="_blank">{{scope.row.torrent.name}}</a>
                </template>
            </el-table-column>

            <el-table-column
                prop=""
                label="Uploaded"
                width="200"
                :formatter="formatColumnUploaded"
            >
            </el-table-column>

            <el-table-column
                prop=""
                label="Downloaded"
                width="200"
                :formatter="formatColumnDownloaded"
            >
            </el-table-column>

            <el-table-column
                prop=""
                label="Share ratio"
                width="120"
                :formatter="formatColumnShareRatio"
            ></el-table-column>

            <el-table-column
                prop="seed_time_required"
                label="Seed time required"
                width="160"
            ></el-table-column>

            <el-table-column
                prop="inspect_time_left"
                label="Inspect time left"
                width="160"
            ></el-table-column>

            <el-table-column
                prop="status_text"
                label="Status"
                width="70"
            ></el-table-column>

            <el-table-column
                label="Action"
                width="120"
            >
                <template #default="scope">
                    <a style="cursor: pointer; margin-right: 10px" @click="handleDetail(scope.row.id)">Detail</a>
                </template>
            </el-table-column>
        </el-table>
        <!--总数超过一页，再展示分页器-->
        <el-pagination
            background
            layout="prev, pager, next"
            :total="total"
            :page-size="perPage"
            :current-page="currentPage"
            @current-change="changePage"
        />
    </el-card>
</template>

<script>
import { onMounted, reactive, ref, toRefs } from 'vue'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'
import api from '../../utils/api'
import { useTable, renderTableData } from '../../utils/table'

export default {
    name: 'HrTable',
    setup() {
        const multipleTable = ref(null)
        const router = useRouter()

        const state = useTable()
        let extraData = reactive({
            status: []
        });

        onMounted(() => {
            console.log('MedalTable onMounted')
            listHrStatus()
            fetchTableData()
        })
        const fetchTableData = async () => {
            state.loading = true
            let res = await api.listHr(state.query)
            renderTableData(res, state)
            state.loading = false
        }
        const handlePardon = () => {
            router.push({ name: 'agent-deny-form' })
        }
        const handlePardonBulk = async () => {
            let ids = state.multipleSelection.map(item => item.id)
            if (ids.length == 0) {
                ElMessage.error("No data selected !")
                return
            }
            console.log(ids)
            let res = await api.pardonHrBulk({id: ids})
            ElMessage.success(res.msg)
            state.query.page = 1;
            await fetchTableData()
        }
        const handleDetail = (id) => {
            router.push({ path: '/hr-detail', query: { id } })
        }
        const handleDelete = async (id) => {
            let res = await api.deleteHr(id)
            ElMessage.success(res.msg)
            state.query.page = 1;
            await fetchTableData()
        }
        const handleDeleteBulk = async () => {
            let ids = state.multipleSelection.map(item => item.id)
            if (ids.length == 0) {
                ElMessage.error("No data selected !")
                return
            }
            console.log(ids)
            let res = await api.deleteHrBulk({id: ids})
            ElMessage.success(res.msg)
            state.query.page = 1;
            await fetchTableData()
        }
        const handleSelectionChange = (val) => {
            state.multipleSelection = val
        }
        const changePage = (val) => {
            state.query.page = val
            fetchTableData()
        }

        const listHrStatus = async () => {
            let res = await api.listHrStatus()
            extraData.status = res.data
        }

        const handleReset = () => {
            state.query.status = '';
            state.query.uid = '';
            state.query.username = '';
            state.query.torrent_id = '';
        }

        const formatColumnUsername = (row, column) => {
            return row.user.username
        }

        const formatColumnTorrent = (row, column) => {
            return '<a href="" target="_blank">' + row.torrent.name + '</a>'
        }

        const formatColumnUploaded = (row, column) => {
            return row.snatch.upload_text
        }

        const formatColumnDownloaded = (row, column) => {
            return row.snatch.download_text
        }

        const formatColumnShareRatio = (row, column) => {
            return row.snatch.share_ratio
        }

        return {
            ...toRefs(state),
            extraData,
            multipleTable,
            handleSelectionChange,
            handlePardon,
            handleDetail,
            handleDelete,
            handlePardonBulk,
            handleDeleteBulk,
            fetchTableData,
            changePage,
            handleReset,
            formatColumnUsername,
            formatColumnTorrent,
            formatColumnUploaded,
            formatColumnDownloaded,
            formatColumnShareRatio,
        }
    }
}
</script>

<style scoped>
.swiper-container {
    min-height: 100%;
}
.el-card.is-always-shadow {
    min-height: 100%!important;
}
</style>
