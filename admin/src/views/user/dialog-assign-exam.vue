<template>
    <el-dialog
        title="Assign exam to user"
        v-model="dialogAssignExamFormVisible"
        center
        class="dialog-assign-exam"
    >
        <el-form :model="assignExamFormData" label-width="150px">
            <el-form-item label="Exam">
                <el-select v-model="assignExamFormData.exam_id" placeholder="Select an exam...">
                    <el-option
                        v-for="item in matchExams"
                        :key="item.id"
                        :label="item.name"
                        :value="item.id">
                    </el-option>
                </el-select>
            </el-form-item>
            <el-form-item label="Exam time range">
                <el-date-picker
                    v-model="assignExamFormData.timeRange"
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
                  <el-button @click="dialogAssignExamFormVisible = false">取 消</el-button>
                  <el-button type="primary" @click="dialogAssignExamFormVisible = false">确 定</el-button>
                </span>
        </template>
    </el-dialog>
</template>
