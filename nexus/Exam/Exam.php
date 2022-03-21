<?php

namespace Nexus\Exam;

use App\Models\ExamUser;
use App\Repositories\ExamRepository;

class Exam
{
    public function render($uid)
    {
        $examRep = new ExamRepository();
        $userExam = $examRep->getUserExamProgress($uid, ExamUser::STATUS_NORMAL);
        if (empty($userExam)) {
            return '';
        }
        $exam = $userExam->exam;
        $row = [];
        $row[] = sprintf('%s：%s', nexus_trans('exam.name'), $exam->name);
        $row[] = sprintf('%s：%s ~ %s', nexus_trans('exam.time_range'), $userExam->begin, $userExam->end);
        foreach ($userExam->progress_formatted as $key => $index) {
            if (isset($index['checked']) && $index['checked']) {
                $row[] = sprintf(
                    '%s：%s, %s：%s, %s：%s, %s：%s',
                    nexus_trans('exam.index') . ($key + 1), nexus_trans('exam.index_text_' . $index['index']),
                    nexus_trans('exam.require_value'), $index['require_value_formatted'],
                    nexus_trans('exam.current_value'), $index['current_value_formatted'],
                    nexus_trans('exam.result'),
                    $index['passed'] ? nexus_trans('exam.result_pass') : nexus_trans('exam.result_not_pass')
                );
            }
        }
        if ($exam->description) {
            $row[] = "\n" . $exam->description;
        }
        return  nl2br(implode("\n", $row));
    }
}
