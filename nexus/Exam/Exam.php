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
        $userExams = $examRep->listUserExamProgress($uid);
        if ($userExams->isEmpty()) {
            return '';
        }
        $htmlArr = [];
        foreach ($userExams as $userExam) {
            $exam = $userExam->exam;
            $row = [];
            $row[] = sprintf('%s：%s', $lang_functions['exam_name'], $exam->name);
            $row[] = sprintf('%s：%s ~ %s', $lang_functions['exam_time_range'], $exam->begin, $exam->end);
            foreach ($exam->indexes as $key => $index) {
                if (isset($index['checked']) && $index['checked']) {
                    $requireValue = $index['require_value'];
                    $currentValue =  $userExam->progress_value[$index['index']] ?? 0;
                    $unit = ExamModel::$indexes[$index['index']]['unit'] ?? '';
                    $row[] = sprintf(
                        '%s：%s, Require：%s %s, Current：%s %s, Result：%s',
                        $lang_functions['exam_index'] . ($key + 1),
                        ExamModel::$indexes[$index['index']]['name'] ?? '',
                        $requireValue, $unit,
                        $currentValue, $unit,
                        $currentValue >= $requireValue ? 'Done!' : 'Not done!'
                    );
                }
            }
            $htmlArr[] = implode("\n", $row);
        }
        return  nl2br(implode("\n\n", $htmlArr));
    }
}
