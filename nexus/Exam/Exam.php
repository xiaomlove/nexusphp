<?php

namespace Nexus\Exam;

use App\Repositories\ExamRepository;
use App\Models\Exam as ExamModel;

class Exam
{
    public function render($uid)
    {
        global $lang_functions;
        $examRep = new ExamRepository();
        $userExam = $examRep->getUserExamProgress($uid);
        if (empty($userExam)) {
            return '';
        }
        $exam = $userExam->exam;
        $row = [];
        $row[] = sprintf('%s：%s', $lang_functions['exam_name'], $exam->name);
        $row[] = sprintf('%s：%s ~ %s', $lang_functions['exam_time_range'], $exam->begin, $exam->end);
        foreach ($exam->indexes as $key => $index) {
            if (isset($index['checked']) && $index['checked']) {
                $requireValue = $index['require_value'];
                $currentValue =  $userExam->progress[$index['index']] ?? 0;
                $unit = ExamModel::$indexes[$index['index']]['unit'] ?? '';
                $row[] = sprintf(
                    '%s：%s, %s：%s %s, %s：%s %s, %s：%s',
                    $lang_functions['exam_index'] . ($key + 1), ExamModel::$indexes[$index['index']]['name'] ?? '',
                    $lang_functions['exam_require'], $requireValue, $unit,
                    $lang_functions['exam_progress_current'], $currentValue, $unit,
                    $lang_functions['exam_progress_result'],
                    $currentValue >= $requireValue ? $lang_functions['exam_progress_result_pass_yes'] : $lang_functions['exam_progress_result_pass_no']
                );
            }
        }
        return  nl2br(implode("\n", $row));
    }
}
