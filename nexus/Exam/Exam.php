<?php

namespace Nexus\Exam;

use App\Models\ExamUser;
use App\Repositories\ExamRepository;

class Exam
{
    public function getCurrent($uid): array
    {
        $examRep = new ExamRepository();
        $userExam = $examRep->getUserExamProgress($uid, ExamUser::STATUS_NORMAL);
        if (empty($userExam)) {
            return ['exam' => null, 'html' => ''];
        }
        /** @var \App\Models\Exam $exam */
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
                    $index['passed'] ? nexus_trans($exam->getPassResultTransKey("pass")) : nexus_trans($exam->getPassResultTransKey("not_pass"))
                );
            }
        }
        if ($exam->description) {
            $row[] = "\n" . $exam->description;
        }
        $html =  nl2br(implode("\n", $row));
        return compact('exam', 'html');
    }
}
